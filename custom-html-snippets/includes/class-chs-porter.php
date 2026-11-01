<?php
/**
 * درون‌ریزی و برون‌بری JSON (پیشنهاد ۱۲) و کپی قطعه کد (پیشنهاد ۱۵).
 *
 * @package AmolNovin\CHS
 */

declare( strict_types=1 );

namespace AmolNovin\CHS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * انتقال قطعه کدها بین سایت‌ها.
 */
final class Porter {

    /**
     * فیلدهای قابل انتقال.
     *
     * @return string[]
     */
    private static function portable_fields(): array {
        return array(
            '_chs_php',
            '_chs_header',
            '_chs_html',
            '_chs_css',
            '_chs_js',
            '_chs_footer',
            '_chs_active',
            '_chs_delay',
            '_chs_lazy_viewport',
            '_chs_global_css_js',
            '_chs_replace_content',
            '_chs_scope',
            '_chs_conditions',
            '_chs_priority',
        );
    }

    /**
     * اتصال هوک‌ها.
     */
    public function register(): void {
        add_action( 'admin_menu', array( $this, 'add_page' ) );
        add_action( 'admin_post_chs_export', array( $this, 'handle_export' ) );
        add_action( 'admin_post_chs_import', array( $this, 'handle_import' ) );
        add_action( 'admin_post_chs_duplicate', array( $this, 'handle_duplicate' ) );

        add_filter( 'post_row_actions', array( $this, 'row_action' ), 10, 2 );
    }

    /**
     * افزودن صفحه ابزارها.
     */
    public function add_page(): void {
        add_submenu_page(
            'edit.php?post_type=' . POST_TYPE,
            __( 'درون‌ریزی / برون‌بری', TEXTDOMAIN ),
            __( 'درون‌ریزی / برون‌بری', TEXTDOMAIN ),
            manage_cap(),
            'chs-porter',
            array( $this, 'render_page' )
        );
    }

    /**
     * نمایش صفحه.
     */
    public function render_page(): void {
        if ( ! current_user_can( manage_cap() ) ) {
            wp_die( esc_html__( 'دسترسی غیرمجاز.', TEXTDOMAIN ) );
        }

        $snippets = get_posts(
            array(
                'post_type'      => POST_TYPE,
                'post_status'    => array( 'publish', 'draft' ),
                'posts_per_page' => 500,
                'orderby'        => 'title',
                'order'          => 'ASC',
            )
        );
        ?>
        <div class="wrap chs-porter">
            <h1><?php esc_html_e( 'درون‌ریزی و برون‌بری قطعه کدها', TEXTDOMAIN ); ?></h1>

            <div class="chs-porter-grid">
                <div class="chs-porter-card">
                    <h2><?php esc_html_e( 'برون‌بری', TEXTDOMAIN ); ?></h2>
                    <p class="description"><?php esc_html_e( 'قطعه کدهای موردنظر را انتخاب کنید تا در قالب یک فایل JSON دریافت شوند.', TEXTDOMAIN ); ?></p>

                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="chs_export" />
                        <?php wp_nonce_field( 'chs_export' ); ?>

                        <p>
                            <label>
                                <input type="checkbox" id="chs-export-all" checked />
                                <strong><?php esc_html_e( 'انتخاب همه', TEXTDOMAIN ); ?></strong>
                            </label>
                        </p>

                        <div class="chs-porter-list">
                            <?php foreach ( $snippets as $snippet ) : ?>
                                <label>
                                    <input type="checkbox" name="snippets[]" value="<?php echo esc_attr( (string) $snippet->ID ); ?>" checked />
                                    <?php echo esc_html( $snippet->post_title ?: sprintf( '#%d', $snippet->ID ) ); ?>
                                </label>
                            <?php endforeach; ?>

                            <?php if ( empty( $snippets ) ) : ?>
                                <p class="chs-muted"><?php esc_html_e( 'هنوز قطعه کدی ندارید.', TEXTDOMAIN ); ?></p>
                            <?php endif; ?>
                        </div>

                        <?php submit_button( __( 'دریافت فایل JSON', TEXTDOMAIN ) ); ?>
                    </form>
                </div>

                <div class="chs-porter-card">
                    <h2><?php esc_html_e( 'درون‌ریزی', TEXTDOMAIN ); ?></h2>
                    <p class="description"><?php esc_html_e( 'فایل JSON خروجی‌گرفته‌شده را بارگذاری کنید.', TEXTDOMAIN ); ?></p>

                    <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="chs_import" />
                        <?php wp_nonce_field( 'chs_import' ); ?>

                        <p><input type="file" name="chs_file" accept="application/json,.json" required /></p>

                        <p>
                            <label>
                                <input type="checkbox" name="as_draft" value="1" checked />
                                <?php esc_html_e( 'درون‌ریزی به‌صورت پیش‌نویس و غیرفعال (توصیه‌شده)', TEXTDOMAIN ); ?>
                            </label>
                        </p>

                        <div class="chs-notice-inline">
                            <?php esc_html_e( 'توجه: کدهای PHP پس از درون‌ریزی امضا ندارند و اجرا نمی‌شوند. برای فعال‌سازی باید هر قطعه کد را باز و ذخیره کنید.', TEXTDOMAIN ); ?>
                        </div>

                        <?php submit_button( __( 'درون‌ریزی', TEXTDOMAIN ), 'secondary' ); ?>
                    </form>
                </div>
            </div>
        </div>
        <script>
        document.getElementById('chs-export-all')?.addEventListener('change', function () {
            document.querySelectorAll('.chs-porter-list input[type=checkbox]').forEach(cb => { cb.checked = this.checked; });
        });
        </script>
        <?php
    }

    /**
     * تولید فایل JSON.
     */
    public function handle_export(): void {
        check_admin_referer( 'chs_export' );

        if ( ! current_user_can( manage_cap() ) ) {
            wp_die( esc_html__( 'دسترسی غیرمجاز.', TEXTDOMAIN ), '', array( 'response' => 403 ) );
        }

        $ids = isset( $_POST['snippets'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['snippets'] ) ) : array();
        $ids = array_filter( $ids );

        if ( empty( $ids ) ) {
            wp_safe_redirect( add_query_arg( 'chs_msg', 'export_empty', wp_get_referer() ?: admin_url() ) );
            exit;
        }

        $payload = array(
            'format'    => 'chs-snippets',
            'version'   => VERSION,
            'exported'  => gmdate( 'c' ),
            'site'      => home_url(),
            'snippets'  => array(),
        );

        foreach ( $ids as $id ) {
            if ( POST_TYPE !== get_post_type( $id ) ) {
                continue;
            }

            $meta = array();

            foreach ( self::portable_fields() as $field ) {
                $meta[ $field ] = get_post_meta( $id, $field, true );
            }

            $terms = wp_get_object_terms( $id, Post_Type::TAXONOMY, array( 'fields' => 'names' ) );

            $payload['snippets'][] = array(
                'title'      => get_the_title( $id ),
                'meta'       => $meta,
                'categories' => is_wp_error( $terms ) ? array() : $terms,
            );
        }

        $json     = (string) wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        $filename = sprintf( 'chs-snippets-%s.json', gmdate( 'Y-m-d-His' ) );

        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . strlen( $json ) );

        echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    /**
     * پردازش فایل درون‌ریزی.
     */
    public function handle_import(): void {
        check_admin_referer( 'chs_import' );

        if ( ! current_user_can( manage_cap() ) ) {
            wp_die( esc_html__( 'دسترسی غیرمجاز.', TEXTDOMAIN ), '', array( 'response' => 403 ) );
        }

        $redirect = admin_url( 'edit.php?post_type=' . POST_TYPE . '&page=chs-porter' );

        if ( empty( $_FILES['chs_file']['tmp_name'] ) ) {
            wp_safe_redirect( add_query_arg( 'chs_msg', 'import_nofile', $redirect ) );
            exit;
        }

        // بررسی نوع فایل.
        $check = wp_check_filetype( (string) ( $_FILES['chs_file']['name'] ?? '' ), array( 'json' => 'application/json' ) );

        if ( 'json' !== $check['ext'] ) {
            wp_safe_redirect( add_query_arg( 'chs_msg', 'import_badtype', $redirect ) );
            exit;
        }

        $size = (int) ( $_FILES['chs_file']['size'] ?? 0 );

        if ( $size <= 0 || $size > 5 * MB_IN_BYTES ) {
            wp_safe_redirect( add_query_arg( 'chs_msg', 'import_toobig', $redirect ) );
            exit;
        }

        $raw  = (string) file_get_contents( sanitize_text_field( (string) $_FILES['chs_file']['tmp_name'] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
        $data = json_decode( $raw, true );

        if ( ! is_array( $data ) || empty( $data['snippets'] ) || ! is_array( $data['snippets'] ) ) {
            wp_safe_redirect( add_query_arg( 'chs_msg', 'import_invalid', $redirect ) );
            exit;
        }

        $as_draft = ! empty( $_POST['as_draft'] );
        $imported = 0;
        $schema   = Post_Type::meta_schema();

        foreach ( array_slice( $data['snippets'], 0, 200 ) as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $title = sanitize_text_field( (string) ( $item['title'] ?? __( 'قطعه کد درون‌ریزی‌شده', TEXTDOMAIN ) ) );

            $post_id = wp_insert_post(
                array(
                    'post_type'   => POST_TYPE,
                    'post_title'  => $title,
                    'post_status' => $as_draft ? 'draft' : 'publish',
                ),
                true
            );

            if ( is_wp_error( $post_id ) ) {
                continue;
            }

            $meta = is_array( $item['meta'] ?? null ) ? $item['meta'] : array();

            foreach ( self::portable_fields() as $field ) {
                if ( ! array_key_exists( $field, $meta ) ) {
                    continue;
                }

                $rule  = $schema[ $field ]['sanitize'] ?? 'text';
                $value = Sanitizer::apply( (string) $rule, $meta[ $field ] );

                update_post_meta( (int) $post_id, $field, $value );
            }

            // قطعه کد درون‌ریزی‌شده به‌صورت پیش‌فرض غیرفعال است.
            if ( $as_draft ) {
                update_post_meta( (int) $post_id, '_chs_active', '0' );
            }

            /*
             * کد PHP عمداً امضا نمی‌شود: امضا به نمک همین سایت گره خورده
             * و اجرای کد وارداتی بدون بازبینی انسانی خطرناک است.
             */
            delete_post_meta( (int) $post_id, '_chs_php_hash' );
            delete_post_meta( (int) $post_id, '_chs_php_author' );

            if ( ! empty( $item['categories'] ) && is_array( $item['categories'] ) ) {
                wp_set_object_terms(
                    (int) $post_id,
                    array_map( 'sanitize_text_field', $item['categories'] ),
                    Post_Type::TAXONOMY
                );
            }

            Audit_Log::record( (int) $post_id, 'imported', __( 'از فایل JSON درون‌ریزی شد.', TEXTDOMAIN ) );
            $imported++;
        }

        Cache::flush();

        wp_safe_redirect( add_query_arg( array( 'chs_msg' => 'imported', 'count' => $imported ), $redirect ) );
        exit;
    }

    /**
     * افزودن گزینه «کپی» به سطر لیست.
     *
     * @param array<string, string> $actions اقدامات.
     * @param \WP_Post              $post    پست.
     * @return array<string, string>
     */
    public function row_action( $actions, $post ) {
        if ( ! $post instanceof \WP_Post || POST_TYPE !== $post->post_type ) {
            return $actions;
        }

        if ( ! current_user_can( manage_cap() ) ) {
            return $actions;
        }

        $url = wp_nonce_url(
            admin_url( 'admin-post.php?action=chs_duplicate&post_id=' . $post->ID ),
            'chs_duplicate_' . $post->ID
        );

        $actions['chs_duplicate'] = sprintf(
            '<a href="%s">%s</a>',
            esc_url( $url ),
            esc_html__( 'کپی', TEXTDOMAIN )
        );

        return $actions;
    }

    /**
     * کپی‌کردن یک قطعه کد.
     */
    public function handle_duplicate(): void {
        $post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;

        if ( ! $post_id || POST_TYPE !== get_post_type( $post_id ) ) {
            wp_die( esc_html__( 'قطعه کد نامعتبر است.', TEXTDOMAIN ), '', array( 'response' => 400 ) );
        }

        check_admin_referer( 'chs_duplicate_' . $post_id );

        if ( ! current_user_can( manage_cap() ) ) {
            wp_die( esc_html__( 'دسترسی غیرمجاز.', TEXTDOMAIN ), '', array( 'response' => 403 ) );
        }

        $new_id = wp_insert_post(
            array(
                'post_type'   => POST_TYPE,
                'post_status' => 'draft',
                /* translators: %s: عنوان اصلی */
                'post_title'  => sprintf( __( '%s (کپی)', TEXTDOMAIN ), get_the_title( $post_id ) ),
            ),
            true
        );

        if ( is_wp_error( $new_id ) ) {
            wp_die( esc_html( $new_id->get_error_message() ) );
        }

        foreach ( self::portable_fields() as $field ) {
            $value = get_post_meta( $post_id, $field, true );

            if ( '' !== $value && null !== $value ) {
                update_post_meta( (int) $new_id, $field, $value );
            }
        }

        // کپی غیرفعال و بدون امضای PHP آغاز می‌شود.
        update_post_meta( (int) $new_id, '_chs_active', '0' );
        delete_post_meta( (int) $new_id, '_chs_php_hash' );
        delete_post_meta( (int) $new_id, '_chs_php_author' );

        Audit_Log::record( (int) $new_id, 'duplicated', sprintf( 'از #%d', $post_id ) );
        Cache::flush();

        wp_safe_redirect( admin_url( 'post.php?action=edit&post=' . (int) $new_id ) );
        exit;
    }
}
