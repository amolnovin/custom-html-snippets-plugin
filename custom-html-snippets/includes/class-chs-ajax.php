<?php
/**
 * نقاط پایانی AJAX — همگی با بررسی nonce و سطح دسترسی.
 *
 * @package AmolNovin\CHS
 */

declare( strict_types=1 );

namespace AmolNovin\CHS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * درخواست‌های AJAX پیشخوان.
 */
final class Ajax {

    /**
     * اتصال هوک‌ها.
     */
    public function register(): void {
        add_action( 'wp_ajax_chs_search_posts', array( $this, 'search_posts' ) );
        add_action( 'wp_ajax_chs_get_page_titles', array( $this, 'page_titles' ) );
        add_action( 'wp_ajax_chs_validate_php', array( $this, 'validate_php' ) );
        add_action( 'wp_ajax_chs_preview', array( $this, 'preview' ) );
    }

    /**
     * بررسی مشترک امنیتی برای همه نقاط پایانی.
     */
    private function guard(): void {
        check_ajax_referer( 'chs_ajax_nonce', 'nonce' );

        if ( ! current_user_can( manage_cap() ) ) {
            wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', TEXTDOMAIN ) ), 403 );
        }
    }

    /**
     * جستجوی نوشته‌ها برای قوانین صفحات.
     */
    public function search_posts(): void {
        $this->guard();

        $q = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['q'] ) ) : '';

        if ( mb_strlen( $q ) < 2 ) {
            wp_send_json_error();
        }

        $excluded = array(
            POST_TYPE,
            'revision',
            'nav_menu_item',
            'custom_css',
            'customize_changeset',
            'wp_template',
            'wp_template_part',
            'wp_global_styles',
            'wp_navigation',
            'attachment',
        );

        $query = new \WP_Query(
            array(
                'post_type'      => 'any',
                'post_status'    => 'publish',
                'posts_per_page' => 15,
                's'              => $q,
                'no_found_rows'  => true,
            )
        );

        $results = array();

        foreach ( $query->posts as $post ) {
            if ( in_array( $post->post_type, $excluded, true ) ) {
                continue;
            }

            $type_obj = get_post_type_object( $post->post_type );

            $results[] = array(
                'id'              => $post->ID,
                'title'           => $post->post_title ?: __( '(بدون عنوان)', TEXTDOMAIN ),
                'post_type'       => $post->post_type,
                'post_type_label' => $type_obj ? $type_obj->labels->singular_name : $post->post_type,
            );
        }

        wp_send_json_success( $results );
    }

    /**
     * دریافت عنوان صفحات انتخاب‌شده.
     */
    public function page_titles(): void {
        $this->guard();

        $raw = isset( $_POST['ids'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['ids'] ) ) : '';

        if ( '' === $raw ) {
            wp_send_json_error();
        }

        $ids = array_slice( Sanitizer::id_list( $raw ), 0, 200 );

        if ( empty( $ids ) ) {
            wp_send_json_success( array( 'html' => '' ) );
        }

        $html = '';

        foreach ( $ids as $pid ) {
            $post = get_post( $pid );

            // فقط پست‌های منتشرشده — جلوگیری از نشت پیش‌نویس و خصوصی.
            if ( ! $post || 'publish' !== $post->post_status ) {
                continue;
            }

            $type_obj = get_post_type_object( $post->post_type );
            $label    = $type_obj ? $type_obj->labels->singular_name : $post->post_type;

            $html .= sprintf(
                '<li data-id="%1$d"><span class="chs-page-title">%2$s</span><span class="chs-page-type">%3$s</span><a class="chs-page-remove" href="#">&times;</a></li>',
                (int) $pid,
                esc_html( get_the_title( $pid ) ?: __( '(بدون عنوان)', TEXTDOMAIN ) ),
                esc_html( $label )
            );
        }

        wp_send_json_success( array( 'html' => $html ) );
    }

    /**
     * اعتبارسنجی نگارشی کد PHP (پیشنهاد ۵).
     */
    public function validate_php(): void {
        $this->guard();

        if ( ! Security::can_edit_php() ) {
            wp_send_json_error( array( 'message' => __( 'اجرای PHP غیرفعال است.', TEXTDOMAIN ) ), 403 );
        }

        $php    = isset( $_POST['php'] ) ? wp_unslash( (string) $_POST['php'] ) : '';
        $result = Security::validate_php_syntax( $php );

        if ( true === $result ) {
            wp_send_json_success( array( 'message' => __( 'کد معتبر است.', TEXTDOMAIN ) ) );
        }

        wp_send_json_error( array( 'message' => $result ) );
    }

    /**
     * پیش‌نمایش زنده قطعه کد (پیشنهاد ۱۸).
     *
     * خروجی داخل iframe با sandbox نمایش داده می‌شود.
     */
    public function preview(): void {
        $this->guard();

        $html = isset( $_POST['html'] ) ? wp_unslash( (string) $_POST['html'] ) : '';
        $css  = isset( $_POST['css'] ) ? wp_unslash( (string) $_POST['css'] ) : '';
        $js   = isset( $_POST['js'] ) ? wp_unslash( (string) $_POST['js'] ) : '';

        $document = sprintf(
            '<!DOCTYPE html><html dir="%1$s"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<style>body{font-family:Tahoma,sans-serif;padding:12px;margin:0}%2$s</style></head>'
            . '<body>%3$s<script>%4$s</script></body></html>',
            is_rtl() ? 'rtl' : 'ltr',
            Sanitizer::css( $css ),
            $html,
            $js
        );

        wp_send_json_success( array( 'document' => $document ) );
    }
}
