<?php
/**
 * متاباکس‌های صفحه ویرایش قطعه کد.
 *
 * @package AmolNovin\CHS
 */

declare( strict_types=1 );

namespace AmolNovin\CHS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * رابط ویرایش قطعه کد.
 */
final class Metabox {

    /**
     * اتصال هوک‌ها.
     */
    public function register(): void {
        add_action( 'add_meta_boxes', array( $this, 'add' ) );
        add_action( 'save_post_' . POST_TYPE, array( $this, 'save' ), 10, 2 );
    }

    /**
     * ثبت متاباکس‌ها.
     */
    public function add(): void {
        add_meta_box( 'chs_code_box', __( 'محتوای ساختار (PHP, HTML, CSS, JS و Header/Footer)', TEXTDOMAIN ), array( $this, 'render_code' ), POST_TYPE, 'normal', 'high' );
        add_meta_box( 'chs_preview_box', __( 'پیش‌نمایش زنده', TEXTDOMAIN ), array( $this, 'render_preview' ), POST_TYPE, 'normal', 'default' );
        add_meta_box( 'chs_status_box', __( 'تنظیمات و وضعیت', TEXTDOMAIN ), array( $this, 'render_status' ), POST_TYPE, 'side', 'high' );
        add_meta_box( 'chs_shortcode_box', __( 'شورت‌کد اختصاصی', TEXTDOMAIN ), array( $this, 'render_shortcode' ), POST_TYPE, 'side', 'default' );
        add_meta_box( 'chs_rules_box', __( 'قوانین صفحات', TEXTDOMAIN ), array( $this, 'render_rules' ), POST_TYPE, 'side', 'default' );
        add_meta_box( 'chs_conditions_box', __( 'شرط‌های نمایش پیشرفته', TEXTDOMAIN ), array( $this, 'render_conditions' ), POST_TYPE, 'normal', 'default' );
        add_meta_box( 'chs_usage_box', __( 'محل‌های استفاده', TEXTDOMAIN ), array( $this, 'render_usage' ), POST_TYPE, 'side', 'low' );
        add_meta_box( 'chs_audit_box', __( 'تاریخچه تغییرات', TEXTDOMAIN ), array( $this, 'render_audit' ), POST_TYPE, 'normal', 'low' );
    }

    /**
     * ادیتورهای کد.
     *
     * @param \WP_Post $post پست.
     */
    public function render_code( $post ): void {
        wp_nonce_field( 'chs_save_code_meta', 'chs_nonce' );

        $can_php = Security::can_edit_php();

        $fields = array(
            'php'    => array( 'label' => __( 'کد PHP (بدون تگ باز و بسته)', TEXTDOMAIN ), 'class' => 'chs-field-php' ),
            'header' => array( 'label' => __( 'کدهای هدر (wp_head)', TEXTDOMAIN ), 'class' => '' ),
            'html'   => array( 'label' => __( 'کد HTML', TEXTDOMAIN ), 'class' => '' ),
            'css'    => array( 'label' => __( 'کد CSS (بدون تگ style)', TEXTDOMAIN ), 'class' => '' ),
            'js'     => array( 'label' => __( 'کد JavaScript (بدون تگ script)', TEXTDOMAIN ), 'class' => '' ),
            'footer' => array( 'label' => __( 'کدهای فوتر (wp_footer)', TEXTDOMAIN ), 'class' => '' ),
        );

        if ( CHS_DISABLE_PHP ) {
            printf(
                '<div class="chs-php-disabled-notice">%s <code>define( \'CHS_DISABLE_PHP\', false );</code></div>',
                esc_html__( 'اجرای PHP غیرفعال است. برای فعال‌سازی این خط را به wp-config.php اضافه کنید:', TEXTDOMAIN )
            );
        }

        foreach ( $fields as $key => $config ) {
            $value    = (string) Post_Type::meta( (int) $post->ID, '_chs_' . $key );
            $disabled = ( 'php' === $key && ! $can_php );

            printf( '<div class="chs-field %s">', esc_attr( $config['class'] ) );
            printf( '<label for="chs_%1$s">%2$s</label>', esc_attr( $key ), esc_html( $config['label'] ) );

            printf(
                '<textarea id="chs_%1$s" name="chs_%1$s" %2$s>%3$s</textarea>',
                esc_attr( $key ),
                $disabled ? 'readonly disabled' : '',
                esc_textarea( $value )
            );

            if ( 'php' === $key ) {
                echo '<div class="chs-php-tools">';
                printf(
                    '<button type="button" class="button button-small" id="chs-validate-php" %s>%s</button>',
                    $disabled ? 'disabled' : '',
                    esc_html__( 'بررسی نگارش کد', TEXTDOMAIN )
                );
                echo '<span class="chs-validate-result"></span>';
                echo '</div>';

                if ( $disabled ) {
                    printf( '<p class="chs-danger-text">%s</p>', esc_html__( 'شما دسترسی ویرایش کد PHP را ندارید.', TEXTDOMAIN ) );
                } else {
                    printf( '<p class="chs-danger-text">%s</p>', esc_html__( 'احتیاط: خطا در این کد می‌تواند سایت را از کار بیندازد. حالت ایمن فعال است و در صورت خطای مرگبار، این قطعه کد خودکار غیرفعال می‌شود.', TEXTDOMAIN ) );
                }
            }

            echo '</div>';
        }
    }

    /**
     * قاب پیش‌نمایش زنده.
     *
     * @param \WP_Post $post پست.
     */
    public function render_preview( $post ): void {
        ?>
        <div class="chs-preview-wrap">
            <p class="description"><?php esc_html_e( 'پیش‌نمایش در محیط ایزوله اجرا می‌شود و استایل قالب سایت را ندارد.', TEXTDOMAIN ); ?></p>
            <button type="button" class="button" id="chs-refresh-preview"><?php esc_html_e( 'به‌روزرسانی پیش‌نمایش', TEXTDOMAIN ); ?></button>
            <label class="chs-auto-preview">
                <input type="checkbox" id="chs-auto-preview" />
                <?php esc_html_e( 'به‌روزرسانی خودکار', TEXTDOMAIN ); ?>
            </label>
            <iframe id="chs-preview-frame" sandbox="allow-scripts" title="<?php esc_attr_e( 'پیش‌نمایش', TEXTDOMAIN ); ?>"></iframe>
        </div>
        <?php
    }

    /**
     * تنظیمات و وضعیت.
     *
     * @param \WP_Post $post پست.
     */
    public function render_status( $post ): void {
        $id = (int) $post->ID;

        $toggles = array(
            'isolate'         => array( __( 'جداسازی کامل HTML/CSS/JS (iframe)', TEXTDOMAIN ), __( 'مناسب ابزار و ویجت؛ برای متن اصلی مقاله توصیه نمی‌شود. CSS/JS و هدر/فوتر فقط داخل iframe اجرا می‌شوند؛ دسترسی به قالب و سبد خرید ندارد. تاخیر و PHP در این حالت اجرا نمی‌شوند.', TEXTDOMAIN ) ),
            'active'          => array( __( 'قطعه کد فعال باشد', TEXTDOMAIN ), '' ),
            'replace_content' => array( __( 'جایگزینی محتوای صفحه', TEXTDOMAIN ), __( 'محتوای اصلی صفحه با این قطعه کد جایگزین می‌شود.', TEXTDOMAIN ) ),
            'global_css_js'   => array( __( 'اجرای سراسری CSS/JS', TEXTDOMAIN ), __( 'CSS/JS به‌جای شورت‌کد در کل سایت لود می‌شوند.', TEXTDOMAIN ) ),
            'delay'           => array( __( 'اجرای با تاخیر (پس از تعامل)', TEXTDOMAIN ), __( 'لود پس از اولین تعامل کاربر.', TEXTDOMAIN ) ),
            'lazy_viewport'   => array( __( 'اجرا هنگام ورود به دید', TEXTDOMAIN ), __( 'با IntersectionObserver — بهتر برای سرعت صفحه.', TEXTDOMAIN ) ),
        );

        echo '<input type="hidden" name="chs_settings_submitted" value="1" />';
        echo '<div class="chs-settings-card">';

        foreach ( $toggles as $key => $config ) {
            $checked = '1' === (string) Post_Type::meta( $id, '_chs_' . $key );
            ?>
            <div class="chs-toggle-row">
                <label class="chs-switch" for="chs_<?php echo esc_attr( $key ); ?>">
                    <input type="checkbox" id="chs_<?php echo esc_attr( $key ); ?>" name="chs_<?php echo esc_attr( $key ); ?>" value="1" <?php checked( $checked ); ?> />
                    <span class="chs-slider"></span>
                </label>
                <label class="chs-toggle-label" for="chs_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $config[0] ); ?></label>
            </div>
            <?php if ( $config[1] ) : ?>
                <p class="description"><?php echo esc_html( $config[1] ); ?></p>
            <?php endif;
        }

        $scope = (string) Post_Type::meta( $id, '_chs_scope' );
        ?>
            <hr />
            <p><label for="chs_scope"><strong><?php esc_html_e( 'محدوده هدر و فوتر:', TEXTDOMAIN ); ?></strong></label></p>
            <select id="chs_scope" name="chs_scope" class="chs-scope-select">
                <option value="global" <?php selected( $scope, 'global' ); ?>><?php esc_html_e( 'سراسری (تمام صفحات)', TEXTDOMAIN ); ?></option>
                <option value="page" <?php selected( $scope, 'page' ); ?>><?php esc_html_e( 'هوشمند (فقط صفحه حاوی شورت‌کد)', TEXTDOMAIN ); ?></option>
            </select>
        </div>
        <?php
    }

    /**
     * نمایش شورت‌کد.
     *
     * @param \WP_Post $post پست.
     */
    public function render_shortcode( $post ): void {
        if ( 'auto-draft' === $post->post_status ) {
            printf( '<p class="chs-muted">%s</p>', esc_html__( 'پس از انتشار، شورت‌کد نمایش داده می‌شود.', TEXTDOMAIN ) );
            return;
        }

        printf(
            '<input type="text" class="chs-shortcode-box" onfocus="this.select();" readonly value="%s" />',
            esc_attr( sprintf( '[my_snippet id="%d"]', (int) $post->ID ) )
        );
    }

    /**
     * قوانین صفحات (استثنا / اجرای خودکار).
     *
     * @param \WP_Post $post پست.
     */
    public function render_rules( $post ): void {
        $id = (int) $post->ID;

        $sections = array(
            'exclude' => array( __( '🚫 صفحات استثنا', TEXTDOMAIN ), __( 'در این صفحات اجرا نمی‌شود.', TEXTDOMAIN ), Conditions::exclude_ids( $id ) ),
            'include' => array( __( '✅ اجرای خودکار', TEXTDOMAIN ), __( 'بدون شورت‌کد در این صفحات نمایش داده می‌شود.', TEXTDOMAIN ), Conditions::include_ids( $id ) ),
        );

        echo '<div id="chs_page_rules_box">';

        foreach ( $sections as $type => $config ) {
            printf( '<div class="chs-rules-section" data-type="%s">', esc_attr( $type ) );
            printf( '<h4>%s</h4>', esc_html( $config[0] ) );
            printf( '<p class="description">%s</p>', esc_html( $config[1] ) );

            echo '<div class="chs-page-search-wrap">';
            printf( '<input type="text" class="chs-page-search" placeholder="%s" />', esc_attr__( 'جستجوی نوشته، برگه، محصول…', TEXTDOMAIN ) );
            echo '<div class="chs-page-results"></div>';
            echo '</div>';

            printf(
                '<input type="hidden" class="chs-page-ids" name="chs_%s_pages" value="%s" />',
                esc_attr( $type ),
                esc_attr( implode( ',', $config[2] ) )
            );

            echo '<ul class="chs-page-list"></ul>';
            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * سازنده شرط‌های پیشرفته.
     *
     * @param \WP_Post $post پست.
     */
    public function render_conditions( $post ): void {
        $rules = Post_Type::meta( (int) $post->ID, '_chs_conditions' );
        $rules = is_array( $rules ) ? $rules : array();
        ?>
        <div id="chs-conditions-builder">
            <p class="description">
                <?php esc_html_e( 'اگر هیچ شرطی تعریف نشود، قطعه کد همه‌جا (با رعایت قوانین صفحات) نمایش داده می‌شود. همه شرط‌ها باید هم‌زمان برقرار باشند.', TEXTDOMAIN ); ?>
            </p>

            <input type="hidden" name="chs_conditions" id="chs-conditions-data"
                value="<?php echo esc_attr( (string) wp_json_encode( $rules ) ); ?>" />

            <div class="chs-conditions-rows"></div>

            <button type="button" class="button" id="chs-add-condition">
                <?php esc_html_e( '+ افزودن شرط', TEXTDOMAIN ); ?>
            </button>

            <details class="chs-conditions-help">
                <summary><?php esc_html_e( 'راهنمای مقادیر', TEXTDOMAIN ); ?></summary>
                <ul>
                    <li><strong><?php esc_html_e( 'نوع محتوا:', TEXTDOMAIN ); ?></strong> <code>post</code>, <code>page</code>, <code>product</code></li>
                    <li><strong><?php esc_html_e( 'دسته / برچسب:', TEXTDOMAIN ); ?></strong> <code>category:news</code> یا <code>product_cat:shoes</code></li>
                    <li><strong><?php esc_html_e( 'قالب صفحه:', TEXTDOMAIN ); ?></strong> <code>templates/full-width.php</code></li>
                    <li><strong><?php esc_html_e( 'نقش کاربر:', TEXTDOMAIN ); ?></strong> <code>administrator</code>, <code>customer</code></li>
                    <li><strong><?php esc_html_e( 'وضعیت ورود:', TEXTDOMAIN ); ?></strong> <code>yes</code> یا <code>no</code></li>
                    <li><strong><?php esc_html_e( 'دستگاه:', TEXTDOMAIN ); ?></strong> <code>mobile</code> یا <code>desktop</code></li>
                    <li><strong><?php esc_html_e( 'بازه زمانی:', TEXTDOMAIN ); ?></strong> <code>2026-01-01 00:00|2026-12-29 23:59</code></li>
                    <li><strong><?php esc_html_e( 'پارامتر URL:', TEXTDOMAIN ); ?></strong> <code>utm_source=newsletter</code></li>
                    <li><strong><?php esc_html_e( 'کوکی:', TEXTDOMAIN ); ?></strong> <code>ab_variant=b</code></li>
                    <li><strong><?php esc_html_e( 'صفحه اصلی:', TEXTDOMAIN ); ?></strong> <code>yes</code> یا <code>no</code></li>
                </ul>
            </details>
        </div>
        <?php
    }

    /**
     * محل‌های استفاده از شورت‌کد.
     *
     * @param \WP_Post $post پست.
     */
    public function render_usage( $post ): void {
        if ( 'auto-draft' === $post->post_status ) {
            printf( '<p class="chs-muted">%s</p>', esc_html__( 'پس از ذخیره بررسی می‌شود.', TEXTDOMAIN ) );
            return;
        }

        Admin::render_usage_list( (int) $post->ID );
    }

    /**
     * تاریخچه تغییرات.
     *
     * @param \WP_Post $post پست.
     */
    public function render_audit( $post ): void {
        $log = Audit_Log::get( (int) $post->ID );

        if ( empty( $log ) ) {
            printf( '<p class="chs-muted">%s</p>', esc_html__( 'هنوز رویدادی ثبت نشده است.', TEXTDOMAIN ) );
            return;
        }

        echo '<table class="widefat striped chs-audit-table"><thead><tr>';
        printf( '<th>%s</th>', esc_html__( 'زمان', TEXTDOMAIN ) );
        printf( '<th>%s</th>', esc_html__( 'کاربر', TEXTDOMAIN ) );
        printf( '<th>%s</th>', esc_html__( 'رویداد', TEXTDOMAIN ) );
        printf( '<th>%s</th>', esc_html__( 'توضیح', TEXTDOMAIN ) );
        echo '</tr></thead><tbody>';

        foreach ( $log as $entry ) {
            echo '<tr>';
            printf(
                '<td>%s</td>',
                esc_html( wp_date( 'Y/m/d H:i', (int) ( $entry['time'] ?? 0 ) ) ?: '—' )
            );
            printf( '<td>%s</td>', esc_html( (string) ( $entry['user'] ?? '—' ) ) );
            printf( '<td>%s</td>', esc_html( Audit_Log::label( (string) ( $entry['action'] ?? '' ) ) ) );
            printf( '<td>%s</td>', esc_html( (string) ( $entry['note'] ?? '' ) ) );
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    /**
     * ذخیره متاها.
     *
     * @param int      $post_id شناسه.
     * @param \WP_Post $post    پست.
     */
    public function save( $post_id, $post = null ): void {
        $post_id = (int) $post_id;

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }

        if ( ! isset( $_POST['chs_nonce'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['chs_nonce'] ) ), 'chs_save_code_meta' ) ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $schema  = Post_Type::meta_schema();
        $changed = array();

        /*
         * فیلدهای کد.
         *
         * نکته حیاتی: اگر کاربر اجازه ذخیره یک فیلد را نداشته باشد،
         * آن فیلد را *دست‌نخورده* رها می‌کنیم. در نسخه قبل مقدار
         * پاک‌سازی‌شده (یا خالی) ذخیره می‌شد و کد موجود از بین می‌رفت.
         */
        $code_fields = array( 'php', 'header', 'html', 'css', 'js', 'footer' );
        $can_raw     = Security::can_save_raw_html();
        $can_php     = Security::can_edit_php();

        foreach ( $code_fields as $field ) {
            $input = 'chs_' . $field;

            if ( ! isset( $_POST[ $input ] ) ) {
                continue;
            }

            // فیلد PHP فقط با دسترسی کامل قابل تغییر است.
            if ( 'php' === $field && ! $can_php ) {
                continue;
            }

            // JS و کدهای هدر/فوتر نیازمند unfiltered_html هستند.
            if ( ! $can_raw && in_array( $field, array( 'js', 'header', 'footer' ), true ) ) {
                continue;
            }

            $meta_key = '_chs_' . $field;
            $rule     = $schema[ $meta_key ]['sanitize'] ?? 'text';
            $raw      = wp_unslash( $_POST[ $input ] ); // phpcs:ignore WordPress.Security.ValidationSanitization
            $value    = Sanitizer::apply( (string) $rule, $raw );
            $previous = (string) get_post_meta( $post_id, $meta_key, true );

            if ( (string) $value !== $previous ) {
                $changed[ $field ] = true;
            }

            update_post_meta( $post_id, $meta_key, $value );
        }

        // امضای کد PHP.
        if ( isset( $_POST['chs_php'] ) && $can_php ) {
            $php = (string) wp_unslash( $_POST['chs_php'] ); // phpcs:ignore WordPress.Security.ValidationSanitization

            $syntax = Security::validate_php_syntax( $php );

            if ( true === $syntax ) {
                Security::sign_php( $post_id, $php );
            } else {
                // کد نگارش نامعتبر ذخیره می‌شود ولی امضا نمی‌گیرد تا اجرا نشود.
                delete_post_meta( $post_id, '_chs_php_hash' );
                delete_post_meta( $post_id, '_chs_php_author' );

                set_transient( 'chs_syntax_error_' . $post_id, $syntax, 60 );
            }
        }

        // کلیدهای دو-حالته.
        if ( isset( $_POST['chs_settings_submitted'] ) ) {
            $flags = array( 'active', 'replace_content', 'global_css_js', 'delay', 'lazy_viewport', 'isolate' );

            foreach ( $flags as $flag ) {
                update_post_meta( $post_id, '_chs_' . $flag, isset( $_POST[ 'chs_' . $flag ] ) ? '1' : '0' );
            }
        }

        // محدوده.
        if ( isset( $_POST['chs_scope'] ) ) {
            update_post_meta(
                $post_id,
                '_chs_scope',
                Sanitizer::apply( 'scope', wp_unslash( (string) $_POST['chs_scope'] ) )
            );
        }

        // قوانین صفحات.
        foreach ( array( 'exclude', 'include' ) as $type ) {
            $input = 'chs_' . $type . '_pages';

            if ( isset( $_POST[ $input ] ) ) {
                update_post_meta(
                    $post_id,
                    '_chs_' . $type . '_pages',
                    Sanitizer::id_list( sanitize_text_field( wp_unslash( (string) $_POST[ $input ] ) ) )
                );
            }
        }

        // شرط‌های پیشرفته.
        if ( isset( $_POST['chs_conditions'] ) ) {
            update_post_meta(
                $post_id,
                '_chs_conditions',
                Conditions::sanitize( wp_unslash( (string) $_POST['chs_conditions'] ) )
            );
        }

        Cache::flush();

        do_action( 'chs_snippet_saved', $post_id, $changed );
    }
}
