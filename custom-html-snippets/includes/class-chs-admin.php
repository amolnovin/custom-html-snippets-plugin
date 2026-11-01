<?php
/**
 * رابط پیشخوان: ستون‌ها، جستجو، اعلان‌ها و تغییر وضعیت سریع.
 *
 * @package AmolNovin\CHS
 */

declare( strict_types=1 );

namespace AmolNovin\CHS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * صفحات مدیریت.
 */
final class Admin {

    /**
     * اتصال هوک‌ها.
     */
    public function register(): void {
        add_filter( 'manage_' . POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
        add_action( 'manage_' . POST_TYPE . '_posts_custom_column', array( $this, 'column_content' ), 10, 2 );

        add_action( 'admin_post_chs_toggle_active', array( $this, 'toggle_active' ) );
        add_action( 'admin_notices', array( $this, 'notices' ) );

        add_filter( 'plugin_action_links_' . plugin_basename( FILE ), array( $this, 'action_links' ) );

        // جستجو در محتوای کد (پیشنهاد ۱۶).
        add_action( 'pre_get_posts', array( $this, 'search_in_code' ) );
        add_filter( 'posts_search', array( $this, 'search_join' ), 10, 2 );
    }

    /**
     * ستون‌های لیست.
     *
     * @param array<string, string> $columns ستون‌ها.
     * @return array<string, string>
     */
    public function columns( $columns ): array {
        $new = array();

        foreach ( (array) $columns as $key => $label ) {
            $new[ $key ] = $label;

            if ( 'title' === $key ) {
                $new['shortcode']  = __( 'شورت‌کد', TEXTDOMAIN );
                $new['chs_active'] = __( 'وضعیت', TEXTDOMAIN );
                $new['chs_used']   = __( 'محل استفاده', TEXTDOMAIN );
            }
        }

        return $new;
    }

    /**
     * محتوای ستون‌ها.
     *
     * @param string $column  نام ستون.
     * @param int    $post_id شناسه.
     */
    public function column_content( $column, $post_id ): void {
        $post_id = (int) $post_id;

        switch ( $column ) {
            case 'shortcode':
                printf(
                    '<code class="chs-shortcode-inline">%s</code>',
                    esc_html( sprintf( '[my_snippet id="%d"]', $post_id ) )
                );
                break;

            case 'chs_active':
                $active = Post_Type::is_active( $post_id );

                $url = wp_nonce_url(
                    admin_url( 'admin-post.php?action=chs_toggle_active&post_id=' . $post_id ),
                    'chs_toggle_active_' . $post_id
                );

                printf(
                    '<span class="chs-status-badge %1$s">%2$s</span><br><a class="button button-small" href="%3$s">%4$s</a>',
                    esc_attr( $active ? 'chs-status-active' : 'chs-status-inactive' ),
                    esc_html( $active ? __( 'فعال', TEXTDOMAIN ) : __( 'غیرفعال', TEXTDOMAIN ) ),
                    esc_url( $url ),
                    esc_html( $active ? __( 'غیرفعال کردن', TEXTDOMAIN ) : __( 'فعال کردن', TEXTDOMAIN ) )
                );
                break;

            case 'chs_used':
                self::render_usage_list( $post_id, 3 );
                break;
        }
    }

    /**
     * فهرست محل‌های استفاده از شورت‌کد.
     *
     * @param int $snippet_id شناسه.
     * @param int $limit      سقف نمایش.
     */
    public static function render_usage_list( int $snippet_id, int $limit = 0 ): void {
        $items = self::find_usage( $snippet_id );

        if ( empty( $items ) ) {
            printf( '<span class="chs-muted">%s</span>', esc_html__( 'یافت نشد.', TEXTDOMAIN ) );
            return;
        }

        $total = count( $items );

        if ( $limit > 0 ) {
            $items = array_slice( $items, 0, $limit );
        }

        echo '<ul class="chs-used-in-list">';

        foreach ( $items as $item ) {
            $edit  = get_edit_post_link( (int) $item->ID );
            $title = $item->post_title ?: __( '(بدون عنوان)', TEXTDOMAIN );

            echo '<li>';

            if ( $edit ) {
                printf( '<a href="%s"><strong>%s</strong></a>', esc_url( $edit ), esc_html( $title ) );
            } else {
                printf( '<strong>%s</strong>', esc_html( $title ) );
            }

            printf( '<br><small>%s | ID: %d</small>', esc_html( $item->post_type ), (int) $item->ID );
            echo '</li>';
        }

        echo '</ul>';

        if ( $limit > 0 && $total > $limit ) {
            printf(
                '<small class="chs-muted">%s</small>',
                esc_html( sprintf( /* translators: %s: تعداد */ __( 'و %s مورد دیگر…', TEXTDOMAIN ), number_format_i18n( $total - $limit ) ) )
            );
        }
    }

    /**
     * یافتن نوشته‌هایی که شورت‌کد را دارند.
     *
     * @param int $snippet_id شناسه.
     * @return array<int, object>
     */
    public static function find_usage( int $snippet_id ): array {
        global $wpdb;

        $snippet_id = absint( $snippet_id );

        if ( ! $snippet_id ) {
            return array();
        }

        $cache_key = Cache::KEY_CANDIDATES . '_' . $snippet_id;
        $found     = get_transient( $cache_key );

        if ( false !== $found && is_array( $found ) ) {
            return $found;
        }

        $excluded     = array( POST_TYPE, 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation' );
        $placeholders = implode( ',', array_fill( 0, count( $excluded ), '%s' ) );

        $like = '%' . $wpdb->esc_like( '[my_snippet' ) . '%' . $wpdb->esc_like( (string) $snippet_id ) . '%';
        $args = array_merge( array( $like ), $excluded );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, post_title, post_type, post_content FROM {$wpdb->posts}
                 WHERE post_content LIKE %s
                   AND post_status = 'publish'
                   AND post_type NOT IN ($placeholders)
                 ORDER BY post_modified DESC
                 LIMIT 100",
                $args
            )
        );

        $pattern  = '/\[my_snippet\b[^\]]*\bid\s*=\s*(["\']?)' . preg_quote( (string) $snippet_id, '/' ) . '\1[^\]]*\]/i';
        $filtered = array();

        foreach ( (array) $rows as $row ) {
            if ( ! empty( $row->post_content ) && preg_match( $pattern, $row->post_content ) ) {
                unset( $row->post_content );
                $filtered[] = $row;
            }
        }

        set_transient( $cache_key, $filtered, Cache::ttl() );

        return $filtered;
    }

    /**
     * تغییر وضعیت فعال/غیرفعال.
     */
    public function toggle_active(): void {
        $post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;

        if ( ! $post_id || POST_TYPE !== get_post_type( $post_id ) ) {
            wp_die( esc_html__( 'قطعه کد نامعتبر است.', TEXTDOMAIN ), '', array( 'response' => 400 ) );
        }

        check_admin_referer( 'chs_toggle_active_' . $post_id );

        if ( ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( manage_cap() ) ) {
            wp_die( esc_html__( 'دسترسی غیرمجاز.', TEXTDOMAIN ), '', array( 'response' => 403 ) );
        }

        $now_active = ! Post_Type::is_active( $post_id );

        update_post_meta( $post_id, '_chs_active', $now_active ? '1' : '0' );

        Cache::flush();
        Audit_Log::record( $post_id, $now_active ? 'activated' : 'deactivated' );

        $redirect = wp_get_referer() ?: admin_url( 'edit.php?post_type=' . POST_TYPE );
        $redirect = remove_query_arg( array( 'action', 'post_id', '_wpnonce' ), $redirect );

        wp_safe_redirect( add_query_arg( 'chs_msg', 'toggled', $redirect ) );
        exit;
    }

    /**
     * اعلان‌های پیشخوان.
     */
    public function notices(): void {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

        if ( ! $screen || POST_TYPE !== $screen->post_type ) {
            return;
        }

        // خطای نگارشی PHP.
        $post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        if ( $post_id ) {
            $error = get_transient( 'chs_syntax_error_' . $post_id );

            if ( $error ) {
                delete_transient( 'chs_syntax_error_' . $post_id );

                printf(
                    '<div class="notice notice-error"><p><strong>%s</strong> %s<br>%s</p></div>',
                    esc_html__( 'کد PHP ذخیره شد اما اجرا نخواهد شد:', TEXTDOMAIN ),
                    esc_html( (string) $error ),
                    esc_html__( 'پس از رفع خطا، دوباره ذخیره کنید.', TEXTDOMAIN )
                );
            }
        }

        $msg = isset( $_GET['chs_msg'] ) ? sanitize_key( wp_unslash( (string) $_GET['chs_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        if ( '' === $msg ) {
            return;
        }

        $count = isset( $_GET['count'] ) ? absint( $_GET['count'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        $messages = array(
            'toggled'       => array( 'success', __( 'وضعیت با موفقیت تغییر کرد.', TEXTDOMAIN ) ),
            'imported'      => array( 'success', sprintf( /* translators: %s: تعداد */ __( '%s قطعه کد با موفقیت درون‌ریزی شد.', TEXTDOMAIN ), number_format_i18n( $count ) ) ),
            'import_nofile' => array( 'error', __( 'فایلی انتخاب نشده است.', TEXTDOMAIN ) ),
            'import_badtype'=> array( 'error', __( 'فقط فایل JSON پذیرفته می‌شود.', TEXTDOMAIN ) ),
            'import_toobig' => array( 'error', __( 'حجم فایل بیش از حد مجاز است.', TEXTDOMAIN ) ),
            'import_invalid'=> array( 'error', __( 'ساختار فایل نامعتبر است.', TEXTDOMAIN ) ),
            'export_empty'  => array( 'error', __( 'هیچ قطعه کدی انتخاب نشد.', TEXTDOMAIN ) ),
            'repaired'      => array( 'success', sprintf( /* translators: %s: تعداد */ __( '%s فیلد با موفقیت بازیابی شد.', TEXTDOMAIN ), number_format_i18n( $count ) ) ),
        );

        if ( ! isset( $messages[ $msg ] ) ) {
            return;
        }

        printf(
            '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
            esc_attr( $messages[ $msg ][0] ),
            esc_html( $messages[ $msg ][1] )
        );
    }

    /**
     * لینک‌های میان‌بر افزونه.
     *
     * @param array<int, string> $links لینک‌ها.
     * @return array<int, string>
     */
    public function action_links( $links ): array {
        array_unshift(
            $links,
            sprintf(
                '<a href="%s">%s</a>',
                esc_url( admin_url( 'edit.php?post_type=' . POST_TYPE ) ),
                esc_html__( 'قطعه کدها', TEXTDOMAIN )
            )
        );

        return (array) $links;
    }

    /**
     * فعال‌سازی جستجو در محتوای کدها.
     *
     * @param \WP_Query $query کوئری.
     */
    public function search_in_code( $query ): void {
        if ( ! is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
            return;
        }

        if ( POST_TYPE !== $query->get( 'post_type' ) ) {
            return;
        }

        $query->set( 'chs_search_code', true );
    }

    /**
     * افزودن جستجوی متا به عبارت WHERE.
     *
     * @param string    $search عبارت جستجو.
     * @param \WP_Query $query  کوئری.
     */
    public function search_join( $search, $query ) {
        global $wpdb;

        if ( ! $query->get( 'chs_search_code' ) || '' === $search ) {
            return $search;
        }

        $term = $query->get( 's' );

        if ( ! $term ) {
            return $search;
        }

        $like = '%' . $wpdb->esc_like( (string) $term ) . '%';

        $meta_sql = $wpdb->prepare(
            " OR ({$wpdb->posts}.ID IN (
                SELECT post_id FROM {$wpdb->postmeta}
                WHERE meta_key IN ('_chs_html','_chs_css','_chs_js','_chs_php','_chs_header','_chs_footer')
                AND meta_value LIKE %s
            )) ",
            $like
        );

        // تزریق پیش از پرانتز پایانی عبارت جستجوی پیش‌فرض.
        return preg_replace( '/\)\s*$/', $meta_sql . ')', $search, 1 ) ?? $search;
    }
}
