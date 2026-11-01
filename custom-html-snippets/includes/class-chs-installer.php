<?php
/**
 * نصب، فعال‌سازی و مهاجرت داده‌ها (پیشنهاد ۲۲).
 *
 * @package AmolNovin\CHS
 */

declare( strict_types=1 );

namespace AmolNovin\CHS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * مدیریت چرخه عمر افزونه.
 */
final class Installer {

    public const OPTION_DB_VERSION = 'chs_db_version';
    public const OPTION_VERSION    = 'chs_version';
    public const OPTION_MIGRATION  = 'chs_migration_report';

    /**
     * اتصال هوک‌ها.
     */
    public function register(): void {
        add_action( 'plugins_loaded', array( __CLASS__, 'maybe_migrate' ), 5 );
        add_action( 'admin_notices', array( __CLASS__, 'migration_notice' ) );
    }

    /**
     * فعال‌سازی افزونه.
     */
    public static function activate(): void {
        if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
            deactivate_plugins( plugin_basename( FILE ) );
            wp_die(
                esc_html__( 'این افزونه به PHP نسخه ۷.۴ یا بالاتر نیاز دارد.', TEXTDOMAIN ),
                '',
                array( 'back_link' => true )
            );
        }

        ( new Post_Type() )->register_post_type();

        self::maybe_migrate();

        Cache::flush();
        flush_rewrite_rules();

        update_option( self::OPTION_VERSION, VERSION );
    }

    /**
     * غیرفعال‌سازی.
     */
    public static function deactivate(): void {
        Cache::flush();
        flush_rewrite_rules();
    }

    /**
     * اجرای مهاجرت در صورت نیاز.
     */
    public static function maybe_migrate(): void {
        $current = (int) get_option( self::OPTION_DB_VERSION, 0 );

        if ( $current >= DB_VERSION ) {
            return;
        }

        // نصب تازه: چیزی برای مهاجرت نیست.
        if ( 0 === $current && ! self::has_legacy_data() ) {
            update_option( self::OPTION_DB_VERSION, DB_VERSION );
            return;
        }

        $report = self::migrate_to_v3();

        update_option( self::OPTION_DB_VERSION, DB_VERSION );
        update_option( self::OPTION_MIGRATION, $report, false );

        Cache::flush();
    }

    /**
     * آیا داده‌ای از نسخه‌های قبلی وجود دارد؟
     */
    private static function has_legacy_data(): bool {
        $existing = get_posts(
            array(
                'post_type'      => POST_TYPE,
                'post_status'    => 'any',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
            )
        );

        return ! empty( $existing );
    }

    /**
     * مهاجرت از نسخه ۲.x به ۳.۰.
     *
     * - نرمال‌سازی شناسه‌های صفحات به int
     * - ساخت امضای HMAC برای کدهای PHP موجود (با نویسنده پست)
     * - مقداردهی پیش‌فرض کلیدهای جدید
     *
     * @return array<string, int>
     */
    private static function migrate_to_v3(): array {
        $report = array(
            'total'     => 0,
            'signed'    => 0,
            'unsigned'  => 0,
            'normalized'=> 0,
        );

        $ids = get_posts(
            array(
                'post_type'      => POST_TYPE,
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
            )
        );

        foreach ( (array) $ids as $id ) {
            $id = (int) $id;
            $report['total']++;

            // ۱) نرمال‌سازی فهرست شناسه‌ها.
            foreach ( array( '_chs_exclude_pages', '_chs_include_pages' ) as $key ) {
                $value = get_post_meta( $id, $key, true );

                if ( is_array( $value ) && ! empty( $value ) ) {
                    $clean = Sanitizer::id_list( $value );

                    if ( $clean !== $value ) {
                        update_post_meta( $id, $key, $clean );
                        $report['normalized']++;
                    }
                }
            }

            // ۲) مقداردهی کلیدهای جدید نسخه ۳.
            if ( '' === (string) get_post_meta( $id, '_chs_lazy_viewport', true ) ) {
                update_post_meta( $id, '_chs_lazy_viewport', '0' );
            }

            if ( '' === (string) get_post_meta( $id, '_chs_priority', true ) ) {
                update_post_meta( $id, '_chs_priority', 10 );
            }

            // ۳) امضای کدهای PHP موجود.
            $php = (string) get_post_meta( $id, '_chs_php', true );

            if ( '' === trim( $php ) ) {
                continue;
            }

            $existing_hash = (string) get_post_meta( $id, '_chs_php_hash', true );

            if ( '' !== $existing_hash && hash_equals( $existing_hash, Security::php_hash( $php ) ) ) {
                $report['signed']++;
                continue;
            }

            /*
             * برای کدهای قدیمی، نویسنده پست را مرجع می‌گیریم.
             * فقط اگر او واقعاً unfiltered_html داشته باشد امضا صادر می‌شود؛
             * در غیر این صورت کد ذخیره می‌ماند ولی اجرا نمی‌شود.
             */
            $author_id = (int) get_post_field( 'post_author', $id );

            if ( $author_id > 0 && user_can( $author_id, 'unfiltered_html' ) ) {
                update_post_meta( $id, '_chs_php_hash', Security::php_hash( $php ) );
                update_post_meta( $id, '_chs_php_author', $author_id );
                $report['signed']++;
            } else {
                delete_post_meta( $id, '_chs_php_hash' );
                delete_post_meta( $id, '_chs_php_author' );
                $report['unsigned']++;
            }
        }

        return $report;
    }

    /**
     * گزارش مهاجرت در پیشخوان.
     */
    public static function migration_notice(): void {
        if ( ! current_user_can( manage_cap() ) ) {
            return;
        }

        $report = get_option( self::OPTION_MIGRATION );

        if ( empty( $report ) || ! is_array( $report ) ) {
            return;
        }

        $lines = array();

        $lines[] = sprintf(
            /* translators: %s: تعداد */
            esc_html__( '%s قطعه کد بررسی شد.', TEXTDOMAIN ),
            esc_html( number_format_i18n( (int) $report['total'] ) )
        );

        if ( ! empty( $report['signed'] ) ) {
            $lines[] = sprintf(
                /* translators: %s: تعداد */
                esc_html__( '%s کد PHP امضا و فعال شد.', TEXTDOMAIN ),
                esc_html( number_format_i18n( (int) $report['signed'] ) )
            );
        }

        if ( ! empty( $report['unsigned'] ) ) {
            $lines[] = '<strong>' . sprintf(
                /* translators: %s: تعداد */
                esc_html__( '%s کد PHP امضا نشد و اجرا نخواهد شد — نویسنده آن‌ها دسترسی unfiltered_html ندارد. برای فعال‌سازی، هر کدام را باز و ذخیره کنید.', TEXTDOMAIN ),
                esc_html( number_format_i18n( (int) $report['unsigned'] ) )
            ) . '</strong>';
        }

        printf(
            '<div class="notice notice-info is-dismissible"><p><strong>%s</strong> %s</p></div>',
            esc_html__( 'ارتقای قطعه کدهای سفارشی به نسخه ۳:', TEXTDOMAIN ),
            wp_kses_post( implode( ' ', $lines ) )
        );

        delete_option( self::OPTION_MIGRATION );
    }
}
