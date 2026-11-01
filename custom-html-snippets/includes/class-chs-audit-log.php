<?php
/**
 * لاگ حسابرسی (پیشنهاد ۶): چه کسی، چه زمانی، چه چیزی را تغییر داد.
 *
 * @package AmolNovin\CHS
 */

declare( strict_types=1 );

namespace AmolNovin\CHS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ثبت و نمایش تاریخچه تغییرات.
 */
final class Audit_Log {

    public const META_KEY = '_chs_audit_log';
    public const MAX      = 50;

    /**
     * اتصال هوک‌ها.
     */
    public function register(): void {
        add_action( 'chs_snippet_saved', array( __CLASS__, 'on_save' ), 10, 2 );
    }

    /**
     * ثبت یک رویداد.
     *
     * @param int    $snippet_id شناسه.
     * @param string $action     نوع رویداد.
     * @param string $note       توضیح.
     */
    public static function record( int $snippet_id, string $action, string $note = '' ): void {
        if ( ! apply_filters( 'chs_enable_audit_log', true ) ) {
            return;
        }

        $log = get_post_meta( $snippet_id, self::META_KEY, true );
        $log = is_array( $log ) ? $log : array();

        $user = wp_get_current_user();

        array_unshift(
            $log,
            array
            (
                'time'   => time(),
                'user'   => $user && $user->ID ? $user->user_login : __( 'سیستم', TEXTDOMAIN ),
                'user_id'=> $user ? (int) $user->ID : 0,
                'action' => sanitize_key( $action ),
                'note'   => sanitize_text_field( $note ),
                'ip'     => self::client_ip(),
            )
        );

        update_post_meta( $snippet_id, self::META_KEY, array_slice( $log, 0, self::MAX ) );
    }

    /**
     * ثبت خودکار هنگام ذخیره.
     *
     * @param int                  $snippet_id شناسه.
     * @param array<string, mixed> $changed    فیلدهای تغییرکرده.
     */
    public static function on_save( $snippet_id, $changed = array() ): void {
        $fields = is_array( $changed ) ? implode( '، ', array_keys( $changed ) ) : '';

        self::record(
            (int) $snippet_id,
            'updated',
            $fields ? sprintf(
                /* translators: %s: فهرست فیلدها */
                __( 'فیلدهای تغییریافته: %s', TEXTDOMAIN ),
                $fields
            ) : __( 'ذخیره شد.', TEXTDOMAIN )
        );
    }

    /**
     * دریافت لاگ یک قطعه کد.
     *
     * @param int $snippet_id شناسه.
     * @return array<int, array<string, mixed>>
     */
    public static function get( int $snippet_id ): array {
        $log = get_post_meta( $snippet_id, self::META_KEY, true );

        return is_array( $log ) ? $log : array();
    }

    /**
     * آدرس IP کاربر (با در نظر گرفتن حریم خصوصی).
     */
    private static function client_ip(): string {
        $ip = isset( $_SERVER['REMOTE_ADDR'] )
            ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) )
            : '';

        if ( '' === $ip ) {
            return '';
        }

        // ناشناس‌سازی جزئی برای رعایت GDPR.
        if ( function_exists( 'wp_privacy_anonymize_ip' ) ) {
            return (string) wp_privacy_anonymize_ip( $ip );
        }

        return $ip;
    }

    /**
     * برچسب خوانا برای نوع رویداد.
     *
     * @param string $action نوع.
     */
    public static function label( string $action ): string {
        $labels = array(
            'updated'       => __( 'به‌روزرسانی', TEXTDOMAIN ),
            'created'       => __( 'ایجاد', TEXTDOMAIN ),
            'activated'     => __( 'فعال‌سازی', TEXTDOMAIN ),
            'deactivated'   => __( 'غیرفعال‌سازی', TEXTDOMAIN ),
            'auto_disabled' => __( 'غیرفعال‌سازی خودکار', TEXTDOMAIN ),
            'imported'      => __( 'درون‌ریزی', TEXTDOMAIN ),
            'duplicated'    => __( 'کپی', TEXTDOMAIN ),
            'restored'      => __( 'بازگردانی', TEXTDOMAIN ),
        );

        return $labels[ $action ] ?? $action;
    }
}
