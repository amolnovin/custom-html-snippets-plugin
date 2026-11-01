<?php
/**
 * پاک‌سازی متمرکز مقادیر ورودی.
 *
 * @package AmolNovin\CHS
 */

declare( strict_types=1 );

namespace AmolNovin\CHS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * توابع پاک‌سازی مورد استفاده در register_post_meta و فرم‌ها.
 */
final class Sanitizer {

    /**
     * اعمال یک قانون پاک‌سازی.
     *
     * @param string $rule  نام قانون.
     * @param mixed  $value مقدار.
     * @return mixed
     */
    public static function apply( string $rule, $value ) {
        switch ( $rule ) {
            case 'raw_html':
                return self::raw_html( $value );

            case 'code':
                return self::code( $value );

            case 'raw_php':
                return self::raw_php( $value );

            case 'css':
                return self::css( (string) $value );

            case 'bool_flag':
                return in_array( (string) $value, array( '1', 'on', 'true' ), true ) ? '1' : '0';

            case 'scope':
                $scope = sanitize_key( (string) $value );
                return in_array( $scope, array( 'global', 'page' ), true ) ? $scope : 'global';

            case 'id_list':
                return self::id_list( $value );

            case 'conditions':
                return Conditions::sanitize( $value );

            case 'absint':
                return absint( $value );

            case 'text':
            default:
                return sanitize_text_field( (string) $value );
        }
    }

    /**
     * محتوای نشانه‌گذاری (HTML هدر/فوتر/بدنه).
     *
     * فقط برای کاربران بدون unfiltered_html محدود می‌شود.
     * این تابع نباید روی JavaScript اجرا شود — برای آن از code() استفاده کنید.
     *
     * @param mixed $value مقدار.
     */
    public static function raw_html( $value ): string {
        $value = (string) $value;

        if ( Security::can_save_raw_html() ) {
            return $value;
        }

        return wp_kses_post( $value );
    }

    /**
     * کد برنامه‌نویسی (JavaScript).
     *
     * اجرای wp_kses_post روی JavaScript فاجعه‌بار است: عملگرهای «<» و «>»
     * به‌عنوان تگ HTML تفسیر و کد از وسط بریده می‌شود
     * (مثلاً «for (i=0; i<10; i++)» به «for (i=0; i» تبدیل می‌شد).
     *
     * بنابراین یا کاربر اجازه ذخیره کد را دارد و کد دست‌نخورده ذخیره می‌شود،
     * یا اجازه ندارد و مقدار قبلی حفظ می‌گردد (تصمیم در لایه فرم).
     *
     * @param mixed $value مقدار.
     */
    public static function code( $value ): string {
        return (string) $value;
    }

    /**
     * کد PHP.
     *
     * برخلاف نسخه قبل، اینجا کد را پاک نمی‌کنیم. تصمیم درباره «آیا این
     * کاربر اجازه تغییر کد PHP را دارد؟» در Metabox::save گرفته می‌شود؛
     * اگر اجازه نداشته باشد اصلاً فیلد را به‌روزرسانی نمی‌کنیم تا کد
     * موجود از بین نرود. اجرا هم جداگانه به امضای HMAC مشروط است.
     *
     * @param mixed $value مقدار.
     */
    public static function raw_php( $value ): string {
        return (string) $value;
    }

    /**
     * پاک‌سازی CSS: جلوگیری از فرار از تگ style و تزریق اسکریپت.
     *
     * @param string $css محتوای CSS.
     */
    public static function css( string $css ): string {
        $css = preg_replace( '#</\s*style#i', '', $css ) ?? '';
        $css = str_ireplace(
            array( '<script', '</script', 'javascript:', 'expression(', '<iframe' ),
            '',
            $css
        );

        return $css;
    }

    /**
     * فهرست شناسه‌ها — همیشه آرایه‌ای یکتا از int.
     *
     * @param mixed $value رشتهٔ کاما-جدا یا آرایه.
     * @return int[]
     */
    public static function id_list( $value ): array {
        if ( is_string( $value ) ) {
            $value = '' === trim( $value ) ? array() : explode( ',', $value );
        }

        if ( ! is_array( $value ) ) {
            return array();
        }

        $ids = array_map( 'absint', $value );
        $ids = array_values( array_unique( array_filter( $ids ) ) );

        return array_slice( $ids, 0, 500 );
    }
}
