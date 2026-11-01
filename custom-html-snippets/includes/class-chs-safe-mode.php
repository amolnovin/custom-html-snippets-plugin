<?php
/**
 * حالت ایمن: تشخیص قطعه کدی که باعث خطای مرگبار شده و غیرفعال‌سازی خودکار آن.
 *
 * سازوکار: پیش از اجرای کد PHP یک «نشانه» در دیتابیس نوشته می‌شود.
 * اگر اجرا موفق باشد نشانه پاک می‌شود. اگر صفحه با خطای مرگبار
 * از بین برود، نشانه باقی می‌ماند و در درخواست بعدی همان قطعه کد
 * به‌صورت خودکار غیرفعال می‌گردد. نتیجه: «سایت سفید» رخ نمی‌دهد.
 *
 * @package AmolNovin\CHS
 */

declare( strict_types=1 );

namespace AmolNovin\CHS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * محافظ اجرای کد.
 */
final class Safe_Mode {

    public const OPTION_RUNNING  = 'chs_running_snippet';
    public const OPTION_DISABLED = 'chs_auto_disabled';

    /**
     * اتصال هوک‌ها.
     */
    public function register(): void {
        add_action( 'plugins_loaded', array( $this, 'detect_previous_crash' ), 1 );
        add_action( 'admin_notices', array( $this, 'crash_notice' ) );
    }

    /**
     * اگر نشانه‌ای از اجرای ناتمام باقی مانده باشد، آن قطعه کد را غیرفعال کن.
     */
    public function detect_previous_crash(): void {
        $running = get_option( self::OPTION_RUNNING, array() );

        if ( empty( $running ) || ! is_array( $running ) ) {
            return;
        }

        $snippet_id = (int) ( $running['id'] ?? 0 );

        // نشانه‌های خیلی قدیمی را نادیده می‌گیریم (مثلاً باقی‌مانده از دیباگ).
        $age = time() - (int) ( $running['time'] ?? 0 );

        if ( $snippet_id > 0 && $age < DAY_IN_SECONDS ) {
            update_post_meta( $snippet_id, '_chs_active', '0' );

            $disabled                = get_option( self::OPTION_DISABLED, array() );
            $disabled[ $snippet_id ] = array(
                'time'  => time(),
                'title' => get_the_title( $snippet_id ),
            );

            update_option( self::OPTION_DISABLED, $disabled, false );

            Cache::flush();

            if ( class_exists( __NAMESPACE__ . '\Audit_Log' ) ) {
                Audit_Log::record( $snippet_id, 'auto_disabled', __( 'به‌دلیل خطای مرگبار به‌صورت خودکار غیرفعال شد.', TEXTDOMAIN ) );
            }
        }

        delete_option( self::OPTION_RUNNING );
    }

    /**
     * علامت‌گذاری شروع اجرای یک قطعه کد.
     *
     * @param int $snippet_id شناسه.
     */
    public static function mark_start( int $snippet_id ): void {
        update_option(
            self::OPTION_RUNNING,
            array(
                'id'   => $snippet_id,
                'time' => time(),
            ),
            false
        );
    }

    /**
     * پاک‌کردن نشانه پس از اجرای موفق.
     */
    public static function mark_success(): void {
        delete_option( self::OPTION_RUNNING );
    }

    /**
     * نمایش اطلاع‌رسانی درباره قطعه کدهای غیرفعال‌شده خودکار.
     */
    public function crash_notice(): void {
        if ( ! current_user_can( manage_cap() ) ) {
            return;
        }

        $disabled = get_option( self::OPTION_DISABLED, array() );

        if ( empty( $disabled ) || ! is_array( $disabled ) ) {
            return;
        }

        $titles = array();

        foreach ( $disabled as $id => $info ) {
            $title    = $info['title'] ?? sprintf( '#%d', (int) $id );
            $titles[] = sprintf(
                '<a href="%s"><strong>%s</strong></a>',
                esc_url( (string) get_edit_post_link( (int) $id ) ),
                esc_html( (string) $title )
            );
        }

        printf(
            '<div class="notice notice-error is-dismissible"><p><strong>%s</strong> %s %s</p></div>',
            esc_html__( 'حالت ایمن:', TEXTDOMAIN ),
            esc_html__( 'قطعه کدهای زیر به‌دلیل خطای مرگبار به‌صورت خودکار غیرفعال شدند:', TEXTDOMAIN ),
            wp_kses_post( implode( '، ', $titles ) )
        );

        delete_option( self::OPTION_DISABLED );
    }
}
