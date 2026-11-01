<?php
/**
 * شرط‌های نمایش پیشرفته (پیشنهاد ۱۳).
 *
 * پشتیبانی از: نوع محتوا، دسته/برچسب، نقش کاربر، وضعیت ورود،
 * دستگاه، بازه زمانی، پارامتر URL و کوکی.
 *
 * @package AmolNovin\CHS
 */

declare( strict_types=1 );

namespace AmolNovin\CHS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ارزیابی شرط‌های نمایش.
 */
final class Conditions {

    /**
     * اتصال هوک‌ها (فعلاً نیازی نیست، برای یکنواختی).
     */
    public function register(): void {}

    /**
     * تعریف شرط‌های در دسترس برای رابط کاربری.
     *
     * @return array<string, string>
     */
    public static function available(): array {
        return array(
            'post_type'   => __( 'نوع محتوا', TEXTDOMAIN ),
            'taxonomy'    => __( 'دسته / برچسب', TEXTDOMAIN ),
            'template'    => __( 'قالب صفحه', TEXTDOMAIN ),
            'user_role'   => __( 'نقش کاربر', TEXTDOMAIN ),
            'logged_in'   => __( 'وضعیت ورود', TEXTDOMAIN ),
            'device'      => __( 'دستگاه', TEXTDOMAIN ),
            'date_range'  => __( 'بازه زمانی', TEXTDOMAIN ),
            'url_param'   => __( 'پارامتر URL', TEXTDOMAIN ),
            'cookie'      => __( 'کوکی', TEXTDOMAIN ),
            'front_page'  => __( 'صفحه اصلی', TEXTDOMAIN ),
        );
    }

    /**
     * پاک‌سازی آرایه شرط‌ها.
     *
     * @param mixed $value ورودی خام.
     * @return array<int, array<string, string>>
     */
    public static function sanitize( $value ): array {
        if ( is_string( $value ) ) {
            $decoded = json_decode( $value, true );
            $value   = is_array( $decoded ) ? $decoded : array();
        }

        if ( ! is_array( $value ) ) {
            return array();
        }

        $allowed = array_keys( self::available() );
        $clean   = array();

        foreach ( $value as $rule ) {
            if ( ! is_array( $rule ) ) {
                continue;
            }

            $type = sanitize_key( (string) ( $rule['type'] ?? '' ) );

            if ( ! in_array( $type, $allowed, true ) ) {
                continue;
            }

            $operator = in_array( ( $rule['operator'] ?? 'is' ), array( 'is', 'is_not' ), true )
                ? (string) $rule['operator']
                : 'is';

            $clean[] = array(
                'type'     => $type,
                'operator' => $operator,
                'value'    => sanitize_text_field( (string) ( $rule['value'] ?? '' ) ),
            );
        }

        return array_slice( $clean, 0, 30 );
    }

    /**
     * آیا همه شرط‌های یک قطعه کد برقرار است؟
     *
     * @param int $snippet_id شناسه.
     */
    public static function passes( int $snippet_id ): bool {
        $rules = Post_Type::meta( $snippet_id, '_chs_conditions' );

        if ( empty( $rules ) || ! is_array( $rules ) ) {
            return true;
        }

        foreach ( $rules as $rule ) {
            $result = self::evaluate_rule( (string) $rule['type'], (string) $rule['value'] );

            if ( 'is_not' === ( $rule['operator'] ?? 'is' ) ) {
                $result = ! $result;
            }

            if ( ! $result ) {
                return false;
            }
        }

        return (bool) apply_filters( 'chs_conditions_pass', true, $snippet_id, $rules );
    }

    /**
     * ارزیابی یک شرط.
     *
     * @param string $type  نوع شرط.
     * @param string $value مقدار.
     */
    private static function evaluate_rule( string $type, string $value ): bool {
        switch ( $type ) {
            case 'post_type':
                return '' === $value || get_post_type( get_queried_object_id() ) === $value;

            case 'taxonomy':
                // قالب مقدار: taxonomy:term_slug  یا فقط term_slug برای category.
                if ( '' === $value ) {
                    return true;
                }
                $parts    = explode( ':', $value, 2 );
                $taxonomy = 2 === count( $parts ) ? $parts[0] : 'category';
                $term     = 2 === count( $parts ) ? $parts[1] : $parts[0];

                return has_term( $term, $taxonomy, get_queried_object_id() );

            case 'template':
                return '' === $value || get_page_template_slug( get_queried_object_id() ) === $value;

            case 'user_role':
                if ( '' === $value ) {
                    return true;
                }
                $user = wp_get_current_user();
                return $user && in_array( $value, (array) $user->roles, true );

            case 'logged_in':
                return 'yes' === $value ? is_user_logged_in() : ! is_user_logged_in();

            case 'device':
                $is_mobile = wp_is_mobile();
                return 'mobile' === $value ? $is_mobile : ! $is_mobile;

            case 'date_range':
                return self::in_date_range( $value );

            case 'url_param':
                return self::match_url_param( $value );

            case 'cookie':
                return self::match_cookie( $value );

            case 'front_page':
                return 'yes' === $value ? is_front_page() : ! is_front_page();

            default:
                return (bool) apply_filters( 'chs_evaluate_condition', true, $type, $value );
        }
    }

    /**
     * بررسی بازه زمانی به قالب «Y-m-d H:i|Y-m-d H:i».
     *
     * @param string $value بازه.
     */
    private static function in_date_range( string $value ): bool {
        $parts = array_map( 'trim', explode( '|', $value ) );
        $now   = current_time( 'timestamp' );

        $start = ! empty( $parts[0] ) ? strtotime( $parts[0] ) : false;
        $end   = ! empty( $parts[1] ) ? strtotime( $parts[1] ) : false;

        if ( false !== $start && $now < $start ) {
            return false;
        }

        if ( false !== $end && $now > $end ) {
            return false;
        }

        return true;
    }

    /**
     * تطبیق پارامتر URL به قالب «key=value» یا فقط «key».
     *
     * @param string $value الگو.
     */
    private static function match_url_param( string $value ): bool {
        if ( '' === $value ) {
            return true;
        }

        $parts = explode( '=', $value, 2 );
        $key   = sanitize_key( $parts[0] );

        if ( '' === $key || ! isset( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return false;
        }

        if ( 1 === count( $parts ) ) {
            return true;
        }

        $actual = sanitize_text_field( wp_unslash( (string) $_GET[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        return $actual === $parts[1];
    }

    /**
     * تطبیق کوکی به قالب «name=value» یا فقط «name».
     *
     * @param string $value الگو.
     */
    private static function match_cookie( string $value ): bool {
        if ( '' === $value ) {
            return true;
        }

        $parts = explode( '=', $value, 2 );
        $name  = sanitize_key( $parts[0] );

        if ( '' === $name || ! isset( $_COOKIE[ $name ] ) ) {
            return false;
        }

        if ( 1 === count( $parts ) ) {
            return true;
        }

        return sanitize_text_field( wp_unslash( (string) $_COOKIE[ $name ] ) ) === $parts[1];
    }

    /**
     * آیا صفحه جاری در فهرست استثنا است؟
     *
     * @param int $snippet_id شناسه.
     */
    public static function is_excluded( int $snippet_id ): bool {
        if ( ! is_singular() ) {
            return false;
        }

        $current = (int) get_queried_object_id();

        if ( ! $current ) {
            return false;
        }

        return in_array( $current, self::exclude_ids( $snippet_id ), true );
    }

    /**
     * فهرست شناسه‌های استثنا.
     *
     * @param int $snippet_id شناسه.
     * @return int[]
     */
    public static function exclude_ids( int $snippet_id ): array {
        return Sanitizer::id_list( Post_Type::meta( $snippet_id, '_chs_exclude_pages' ) );
    }

    /**
     * فهرست شناسه‌های اجرای خودکار.
     *
     * @param int $snippet_id شناسه.
     * @return int[]
     */
    public static function include_ids( int $snippet_id ): array {
        return Sanitizer::id_list( Post_Type::meta( $snippet_id, '_chs_include_pages' ) );
    }
}
