<?php
/**
 * ثبت نوع محتوا و متاهای آن.
 *
 * @package AmolNovin\CHS
 */

declare( strict_types=1 );

namespace AmolNovin\CHS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * نوع محتوای «قطعه کد» و تاکسونومی دسته‌بندی.
 */
final class Post_Type {

    public const TAXONOMY = 'chs_snippet_cat';

    /**
     * تعریف متاهای افزونه: نوع، پاک‌سازی و دسترسی.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function meta_schema(): array {
        return array(
            '_chs_php'             => array( 'type' => 'string', 'sanitize' => 'raw_php', 'default' => '' ),
            '_chs_header'          => array( 'type' => 'string', 'sanitize' => 'raw_html', 'default' => '' ),
            '_chs_html'            => array( 'type' => 'string', 'sanitize' => 'raw_html', 'default' => '' ),
            '_chs_css'             => array( 'type' => 'string', 'sanitize' => 'css', 'default' => '' ),
            '_chs_js'              => array( 'type' => 'string', 'sanitize' => 'code', 'default' => '' ),
            '_chs_footer'          => array( 'type' => 'string', 'sanitize' => 'raw_html', 'default' => '' ),
            '_chs_active'          => array( 'type' => 'string', 'sanitize' => 'bool_flag', 'default' => '1' ),
            '_chs_isolate'         => array( 'type' => 'string', 'sanitize' => 'bool_flag', 'default' => '0' ),
            '_chs_delay'           => array( 'type' => 'string', 'sanitize' => 'bool_flag', 'default' => '0' ),
            '_chs_lazy_viewport'   => array( 'type' => 'string', 'sanitize' => 'bool_flag', 'default' => '0' ),
            '_chs_global_css_js'   => array( 'type' => 'string', 'sanitize' => 'bool_flag', 'default' => '0' ),
            '_chs_replace_content' => array( 'type' => 'string', 'sanitize' => 'bool_flag', 'default' => '0' ),
            '_chs_scope'           => array( 'type' => 'string', 'sanitize' => 'scope', 'default' => 'global' ),
            '_chs_exclude_pages'   => array( 'type' => 'array', 'sanitize' => 'id_list', 'default' => array() ),
            '_chs_include_pages'   => array( 'type' => 'array', 'sanitize' => 'id_list', 'default' => array() ),
            '_chs_conditions'      => array( 'type' => 'array', 'sanitize' => 'conditions', 'default' => array() ),
            '_chs_php_hash'        => array( 'type' => 'string', 'sanitize' => 'text', 'default' => '' ),
            '_chs_php_author'      => array( 'type' => 'integer', 'sanitize' => 'absint', 'default' => 0 ),
            '_chs_priority'        => array( 'type' => 'integer', 'sanitize' => 'absint', 'default' => 10 ),
        );
    }

    /**
     * اتصال هوک‌ها.
     */
    public function register(): void {
        add_action( 'init', array( $this, 'register_post_type' ) );
        add_action( 'init', array( $this, 'register_taxonomy' ) );
        add_action( 'init', array( $this, 'register_meta' ) );
    }

    /**
     * ثبت نوع محتوا.
     */
    public function register_post_type(): void {
        $cap = manage_cap();

        $labels = array(
            'name'          => __( 'قطعه کدها', TEXTDOMAIN ),
            'singular_name' => __( 'قطعه کد', TEXTDOMAIN ),
            'menu_name'     => __( 'قطعه کدهای من', TEXTDOMAIN ),
            'add_new'       => __( 'افزودن قطعه کد', TEXTDOMAIN ),
            'add_new_item'  => __( 'افزودن قطعه کد جدید', TEXTDOMAIN ),
            'edit_item'     => __( 'ویرایش قطعه کد', TEXTDOMAIN ),
            'all_items'     => __( 'همه قطعه کدها', TEXTDOMAIN ),
            'search_items'  => __( 'جستجوی کد', TEXTDOMAIN ),
            'not_found'     => __( 'کدی یافت نشد.', TEXTDOMAIN ),
        );

        register_post_type(
            POST_TYPE,
            array(
                'labels'              => $labels,
                'public'              => false,
                'publicly_queryable'  => false,
                'exclude_from_search' => true,
                'show_ui'             => true,
                'show_in_menu'        => true,
                'show_in_rest'        => false,
                'menu_icon'           => 'dashicons-editor-code',
                'supports'            => array( 'title', 'revisions' ),
                'capabilities'        => array(
                    'edit_post'          => $cap,
                    'read_post'          => $cap,
                    'delete_post'        => $cap,
                    'delete_posts'       => $cap,
                    'edit_posts'         => $cap,
                    'edit_others_posts'  => $cap,
                    'publish_posts'      => $cap,
                    'read_private_posts' => $cap,
                    'create_posts'       => $cap,
                ),
                'map_meta_cap'        => false,
            )
        );
    }

    /**
     * ثبت تاکسونومی دسته‌بندی قطعه کدها.
     */
    public function register_taxonomy(): void {
        $cap = manage_cap();

        register_taxonomy(
            self::TAXONOMY,
            POST_TYPE,
            array(
                'labels'            => array(
                    'name'          => __( 'دسته‌بندی قطعه کدها', TEXTDOMAIN ),
                    'singular_name' => __( 'دسته‌بندی', TEXTDOMAIN ),
                    'add_new_item'  => __( 'افزودن دسته‌بندی', TEXTDOMAIN ),
                ),
                'public'            => false,
                'show_ui'           => true,
                'show_admin_column' => true,
                'show_in_rest'      => false,
                'hierarchical'      => true,
                'capabilities'      => array(
                    'manage_terms' => $cap,
                    'edit_terms'   => $cap,
                    'delete_terms' => $cap,
                    'assign_terms' => $cap,
                ),
            )
        );
    }

    /**
     * ثبت متاها (پیشنهاد ۳).
     *
     * نکته مهم امنیتی/داده‌ای:
     * عمداً هیچ sanitize_callback ثبت نمی‌شود. آن کال‌بک روی *هر*
     * فراخوانی update_post_meta اجرا می‌شود — از جمله مهاجرت داده،
     * بازگردانی بازنگری و درون‌ریزی — و چون به current_user_can وابسته
     * است، در بافتی که کاربر جاری وجود ندارد (WP-CLI، کرون، مهاجرت)
     * کد کاربر را بی‌صدا نابود می‌کرد.
     *
     * پاک‌سازی در مرز ورودی فرم انجام می‌شود: Metabox::save() و Porter.
     */
    public function register_meta(): void {
        foreach ( self::meta_schema() as $key => $args ) {
            register_post_meta(
                POST_TYPE,
                $key,
                array(
                    'type'          => $args['type'],
                    'single'        => true,
                    'default'       => $args['default'],
                    'show_in_rest'  => false,
                    'auth_callback' => static function () {
                        return current_user_can( manage_cap() );
                    },
                )
            );
        }
    }

    /**
     * خواندن متا با اعمال پیش‌فرض.
     *
     * @param int    $post_id شناسه.
     * @param string $key     کلید متا.
     * @return mixed
     */
    public static function meta( int $post_id, string $key ) {
        $schema = self::meta_schema();
        $value  = get_post_meta( $post_id, $key, true );

        if ( '' === $value || null === $value ) {
            if ( '_chs_active' === $key ) {
                // سازگاری: نبود مقدار یعنی فعال.
                return '1';
            }
            return $schema[ $key ]['default'] ?? '';
        }

        return $value;
    }

    /**
     * آیا قطعه کد فعال است؟
     *
     * @param int $post_id شناسه.
     */
    public static function is_active( int $post_id ): bool {
        return '1' === (string) self::meta( $post_id, '_chs_active' );
    }
}
