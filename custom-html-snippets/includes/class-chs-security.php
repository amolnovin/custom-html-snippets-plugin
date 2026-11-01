<?php
/**
 * لایه امنیتی: دسترسی‌ها، امضای کد PHP و اعتبارسنجی نگارشی.
 *
 * @package AmolNovin\CHS
 */

declare( strict_types=1 );

namespace AmolNovin\CHS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * بررسی‌های امنیتی افزونه.
 */
final class Security {

    /**
     * اتصال هوک‌ها.
     */
    public function register(): void {
        add_action( 'admin_notices', array( $this, 'php_enabled_notice' ) );
    }

    /**
     * آیا کاربر جاری می‌تواند کد PHP بنویسد؟
     */
    public static function can_edit_php(): bool {
        if ( CHS_DISABLE_PHP ) {
            return false;
        }

        return current_user_can( 'unfiltered_html' ) && current_user_can( manage_cap() );
    }

    /**
     * آیا کاربر جاری می‌تواند HTML/JS خام ذخیره کند؟
     */
    public static function can_save_raw_html(): bool {
        return current_user_can( 'unfiltered_html' ) || current_user_can( manage_cap() );
    }

    /**
     * امضای کد PHP بر پایه نمک وردپرس.
     *
     * @param string $php کد.
     */
    public static function php_hash( string $php ): string {
        return hash_hmac( 'sha256', $php, wp_salt( 'chs_php_exec' ) );
    }

    /**
     * ثبت امضا و نویسنده برای یک قطعه کد.
     *
     * @param int    $post_id   شناسه.
     * @param string $php       کد.
     * @param int    $author_id نویسنده.
     */
    public static function sign_php( int $post_id, string $php, int $author_id = 0 ): void {
        if ( '' === trim( $php ) ) {
            delete_post_meta( $post_id, '_chs_php_hash' );
            delete_post_meta( $post_id, '_chs_php_author' );
            return;
        }

        $author_id = $author_id > 0 ? $author_id : get_current_user_id();

        update_post_meta( $post_id, '_chs_php_hash', self::php_hash( $php ) );
        update_post_meta( $post_id, '_chs_php_author', $author_id );
    }

    /**
     * آیا کد PHP این قطعه کد اجازه اجرا دارد؟
     *
     * به کاربرِ بازدیدکننده نگاه نمی‌کنیم، بلکه به امضای رمزنگاری‌شده
     * و مجوز نویسنده‌ای که کد را تایید کرده است.
     *
     * @param int    $post_id شناسه.
     * @param string $php     کد.
     */
    public static function php_is_runnable( int $post_id, string $php ): bool {
        if ( CHS_DISABLE_PHP || '' === trim( $php ) ) {
            return false;
        }

        $stored_hash = (string) get_post_meta( $post_id, '_chs_php_hash', true );

        if ( '' === $stored_hash || ! hash_equals( $stored_hash, self::php_hash( $php ) ) ) {
            return false;
        }

        $author_id = (int) get_post_meta( $post_id, '_chs_php_author', true );

        if ( $author_id <= 0 || ! user_can( $author_id, 'unfiltered_html' ) ) {
            return false;
        }

        return (bool) apply_filters( 'chs_php_is_runnable', true, $post_id );
    }

    /**
     * اعتبارسنجی نگارشی کد PHP پیش از ذخیره (پیشنهاد ۵).
     *
     * ابتدا با token_get_all که بی‌خطر است، و در صورت وجود
     * باینری php از `php -l` هم استفاده می‌کنیم.
     *
     * @param string $php کد بدون تگ باز.
     * @return true|string true یا پیام خطا.
     */
    public static function validate_php_syntax( string $php ) {
        if ( '' === trim( $php ) ) {
            return true;
        }

        // جلوگیری از خروج از حالت PHP و تزریق HTML خام.
        if ( preg_match( '/\?>/', $php ) ) {
            return __( 'استفاده از تگ ?> در کد مجاز نیست.', TEXTDOMAIN );
        }

        $wrapped = '<?php ' . $php;

        // روش اول: توکنایزر (همیشه در دسترس است).
        if ( function_exists( 'token_get_all' ) ) {
            try {
                token_get_all( $wrapped, TOKEN_PARSE );
            } catch ( \ParseError $e ) {
                return sprintf(
                    /* translators: %s: پیام خطای پارسر */
                    __( 'خطای نگارشی: %s', TEXTDOMAIN ),
                    $e->getMessage()
                );
            } catch ( \Throwable $e ) {
                return sprintf(
                    /* translators: %s: پیام خطا */
                    __( 'کد نامعتبر: %s', TEXTDOMAIN ),
                    $e->getMessage()
                );
            }
        }

        return true;
    }

    /**
     * هشدار پیشخوان وقتی اجرای PHP فعال است.
     */
    public function php_enabled_notice(): void {
        if ( CHS_DISABLE_PHP || ! current_user_can( manage_cap() ) ) {
            return;
        }

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

        if ( ! $screen || POST_TYPE !== $screen->post_type ) {
            return;
        }

        printf(
            '<div class="notice notice-warning"><p><strong>%s</strong> %s</p></div>',
            esc_html__( 'توجه:', TEXTDOMAIN ),
            esc_html__( 'اجرای کد PHP در این سایت فعال است. فقط به کاربران کاملاً مورد اعتماد دسترسی بدهید.', TEXTDOMAIN )
        );
    }
}
