<?php
/**
 * نسخه‌بندی متاها در بازنگری‌ها (پیشنهاد ۱۴).
 *
 * وردپرس به‌صورت پیش‌فرض متاها را در بازنگری ذخیره نمی‌کند.
 * این کلاس کدها را هم نسخه‌بندی و قابل بازگردانی می‌کند.
 *
 * @package AmolNovin\CHS
 */

declare( strict_types=1 );

namespace AmolNovin\CHS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * مدیریت بازنگری قطعه کدها.
 */
final class Revisions {

    /**
     * فیلدهایی که نسخه‌بندی می‌شوند.
     *
     * @return string[]
     */
    public static function fields(): array {
        return array( '_chs_php', '_chs_header', '_chs_html', '_chs_css', '_chs_js', '_chs_footer' );
    }

    /**
     * اتصال هوک‌ها.
     */
    public function register(): void {
        add_action( '_wp_put_post_revision', array( $this, 'save_revision_meta' ) );
        add_action( 'wp_restore_post_revision', array( $this, 'restore_revision_meta' ), 10, 2 );
        add_filter( '_wp_post_revision_fields', array( $this, 'revision_fields' ), 10, 2 );
        add_filter( 'wp_save_post_revision_post_has_changed', array( $this, 'has_changed' ), 10, 3 );
    }

    /**
     * ذخیره متاها روی بازنگری.
     *
     * @param int $revision_id شناسه بازنگری.
     */
    public function save_revision_meta( $revision_id ): void {
        $revision = get_post( (int) $revision_id );

        if ( ! $revision || POST_TYPE !== get_post_type( $revision->post_parent ) ) {
            return;
        }

        foreach ( self::fields() as $field ) {
            $value = get_post_meta( (int) $revision->post_parent, $field, true );

            if ( '' !== $value && null !== $value ) {
                add_metadata( 'post', (int) $revision_id, $field, wp_slash( $value ) );
            }
        }
    }

    /**
     * بازگردانی متاها از بازنگری.
     *
     * @param int $post_id     شناسه اصلی.
     * @param int $revision_id شناسه بازنگری.
     */
    public function restore_revision_meta( $post_id, $revision_id ): void {
        if ( POST_TYPE !== get_post_type( (int) $post_id ) ) {
            return;
        }

        foreach ( self::fields() as $field ) {
            $value = get_metadata( 'post', (int) $revision_id, $field, true );

            if ( '' !== $value && null !== $value ) {
                update_post_meta( (int) $post_id, $field, wp_slash( $value ) );
            } else {
                delete_post_meta( (int) $post_id, $field );
            }
        }

        // پس از بازگردانی، کد PHP باید دوباره امضا شود.
        $php = (string) get_post_meta( (int) $post_id, '_chs_php', true );

        if ( '' !== trim( $php ) && Security::can_edit_php() ) {
            Security::sign_php( (int) $post_id, $php );
        } else {
            delete_post_meta( (int) $post_id, '_chs_php_hash' );
            delete_post_meta( (int) $post_id, '_chs_php_author' );
        }

        Cache::flush();
        Audit_Log::record( (int) $post_id, 'restored', __( 'از بازنگری بازگردانی شد.', TEXTDOMAIN ) );
    }

    /**
     * افزودن فیلدها به صفحه مقایسه بازنگری‌ها.
     *
     * @param array<string, string> $fields فیلدها.
     * @param array<string, mixed>  $post   پست.
     * @return array<string, string>
     */
    public function revision_fields( $fields, $post = array() ) {
        $post_type = is_array( $post ) ? ( $post['post_type'] ?? '' ) : '';

        if ( POST_TYPE !== $post_type ) {
            return $fields;
        }

        $labels = array(
            '_chs_php'    => __( 'کد PHP', TEXTDOMAIN ),
            '_chs_header' => __( 'کد هدر', TEXTDOMAIN ),
            '_chs_html'   => __( 'کد HTML', TEXTDOMAIN ),
            '_chs_css'    => __( 'کد CSS', TEXTDOMAIN ),
            '_chs_js'     => __( 'کد JavaScript', TEXTDOMAIN ),
            '_chs_footer' => __( 'کد فوتر', TEXTDOMAIN ),
        );

        foreach ( $labels as $key => $label ) {
            $fields[ $key ] = $label;

            add_filter(
                "_wp_post_revision_field_{$key}",
                static function ( $value, $field, $compare_from ) {
                    if ( is_object( $compare_from ) ) {
                        return (string) get_metadata( 'post', $compare_from->ID, $field, true );
                    }
                    return $value;
                },
                10,
                3
            );
        }

        return $fields;
    }

    /**
     * اعلام تغییر به وردپرس حتی وقتی فقط متاها عوض شده‌اند.
     *
     * @param bool     $has_changed آیا تغییر کرده.
     * @param \WP_Post $last        آخرین بازنگری.
     * @param \WP_Post $post        پست جاری.
     */
    public function has_changed( $has_changed, $last, $post ) {
        if ( $has_changed || ! $post instanceof \WP_Post || POST_TYPE !== $post->post_type ) {
            return $has_changed;
        }

        foreach ( self::fields() as $field ) {
            $current  = (string) get_post_meta( $post->ID, $field, true );
            $previous = (string) get_metadata( 'post', $last->ID, $field, true );

            if ( $current !== $previous ) {
                return true;
            }
        }

        return $has_changed;
    }
}
