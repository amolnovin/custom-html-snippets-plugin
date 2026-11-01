<?php
/**
 * بلوک گوتنبرگ برای درج قطعه کد بدون نیاز به تایپ شورت‌کد (پیشنهاد ۱۷).
 *
 * @package AmolNovin\CHS
 */

declare( strict_types=1 );

namespace AmolNovin\CHS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * بلوک ویرایشگر.
 */
final class Block {

    /**
     * اتصال هوک‌ها.
     */
    public function register(): void {
        add_action( 'init', array( $this, 'register_block' ) );
        add_action( 'enqueue_block_editor_assets', array( $this, 'editor_assets' ) );
        add_action( 'wp_ajax_chs_block_snippets', array( $this, 'ajax_snippets' ) );
    }

    /**
     * ثبت بلوک با رندر سمت سرور.
     */
    public function register_block(): void {
        if ( ! function_exists( 'register_block_type' ) ) {
            return;
        }

        register_block_type(
            'chs/snippet',
            array(
                'api_version'     => 2,
                'title'           => __( 'قطعه کد سفارشی', TEXTDOMAIN ),
                'category'        => 'widgets',
                'icon'            => 'editor-code',
                'description'     => __( 'درج یکی از قطعه کدهای ساخته‌شده.', TEXTDOMAIN ),
                'attributes'      => array(
                    'snippetId' => array(
                        'type'    => 'number',
                        'default' => 0,
                    ),
                    'replace'   => array(
                        'type'    => 'boolean',
                        'default' => false,
                    ),
                ),
                'render_callback' => array( $this, 'render' ),
                'supports'        => array( 'html' => false ),
            )
        );
    }

    /**
     * رندر بلوک در فرانت‌اند.
     *
     * @param array<string, mixed> $attributes ویژگی‌ها.
     */
    public function render( $attributes ): string {
        $snippet_id = absint( $attributes['snippetId'] ?? 0 );

        if ( ! $snippet_id ) {
            return '';
        }

        $replace = ! empty( $attributes['replace'] ) ? 'true' : 'false';

        return do_shortcode( sprintf( '[my_snippet id="%d" replace="%s"]', $snippet_id, $replace ) );
    }

    /**
     * بارگذاری اسکریپت ویرایشگر.
     */
    public function editor_assets(): void {
        if ( ! function_exists( 'wp_enqueue_script' ) ) {
            return;
        }

        wp_enqueue_script(
            'chs-block',
            URL . 'assets/js/block.js',
            array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n', 'wp-data' ),
            VERSION,
            true
        );

        wp_localize_script(
            'chs-block',
            'chsBlock',
            array(
                'snippets' => $this->snippet_options(),
                'i18n'     => array(
                    'title'       => __( 'قطعه کد سفارشی', TEXTDOMAIN ),
                    'select'      => __( 'انتخاب قطعه کد', TEXTDOMAIN ),
                    'none'        => __( '— انتخاب کنید —', TEXTDOMAIN ),
                    'replace'     => __( 'جایگزینی کل محتوای صفحه', TEXTDOMAIN ),
                    'settings'    => __( 'تنظیمات قطعه کد', TEXTDOMAIN ),
                    'placeholder' => __( 'برای نمایش، یک قطعه کد انتخاب کنید.', TEXTDOMAIN ),
                    'inactive'    => __( 'این قطعه کد غیرفعال است و نمایش داده نمی‌شود.', TEXTDOMAIN ),
                    'empty'       => __( 'هنوز قطعه کدی نساخته‌اید.', TEXTDOMAIN ),
                ),
            )
        );
    }

    /**
     * فهرست قطعه کدها برای انتخابگر.
     *
     * @return array<int, array<string, mixed>>
     */
    private function snippet_options(): array {
        $posts = get_posts(
            array(
                'post_type'      => POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => 200,
                'orderby'        => 'title',
                'order'          => 'ASC',
            )
        );

        $options = array();

        foreach ( $posts as $post ) {
            $options[] = array(
                'value'  => (int) $post->ID,
                'label'  => $post->post_title ?: sprintf( '#%d', $post->ID ),
                'active' => Post_Type::is_active( (int) $post->ID ),
            );
        }

        return $options;
    }

    /**
     * نقطه پایانی AJAX برای تازه‌سازی فهرست.
     */
    public function ajax_snippets(): void {
        check_ajax_referer( 'chs_ajax_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array(), 403 );
        }

        wp_send_json_success( $this->snippet_options() );
    }
}
