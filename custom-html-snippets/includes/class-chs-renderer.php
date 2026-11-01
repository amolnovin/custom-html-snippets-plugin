<?php
/**
 * موتور رندر: شورت‌کد، تزریق هدر/فوتر و جایگزینی محتوا.
 *
 * @package AmolNovin\CHS
 */

declare( strict_types=1 );

namespace AmolNovin\CHS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * رندر قطعه کدها در فرانت‌اند.
 */
final class Renderer {

    /**
     * قطعه کدهایی که هم‌اکنون در حال رندرند (جلوگیری از بازگشت بی‌نهایت).
     *
     * @var array<int, bool>
     */
    private array $rendering = array();

    /**
     * شناسه قطعه کدی که محتوای صفحه را جایگزین کرده است.
     */
    private int $replaced_by = 0;

    private string $replacement_output = '';

    private int $replacement_post = 0;

    /**
     * آیا موتور تاخیر لازم است؟
     */
    private bool $needs_delay_engine = false;

    /**
     * آیا موتور تاخیر صف شده است؟
     */
    private bool $delay_engine_enqueued = false;

    /**
     * قطعه کدهایی که CSS آن‌ها به‌صورت فایل صف شده است.
     *
     * @var array<int, bool>
     */
    private array $enqueued_css = array();

    /**
     * قطعه کدهایی که JS آن‌ها به‌صورت فایل صف شده است.
     *
     * @var array<int, bool>
     */
    private array $enqueued_js = array();

    /**
     * اتصال هوک‌ها.
     */
    public function register(): void {
        add_shortcode( 'my_snippet', array( $this, 'shortcode' ) );

        /*
         * صف‌بندی فایل‌ها باید روی wp_enqueue_scripts انجام شود.
         * wp_head/wp_footer با اولویت ۹۹ خیلی دیر است: تا آن لحظه
         * wp_print_styles (اولویت ۸) و wp_print_footer_scripts (اولویت ۲۰)
         * از قبل اجرا شده‌اند و هر صف‌بندی جدید بی‌اثر می‌ماند.
         */
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_global_assets' ), 20 );

        add_action( 'wp_head', array( $this, 'output_header' ), 99 );
        add_action( 'wp_footer', array( $this, 'output_footer' ), 99 );

        // پیش از چاپ اسکریپت‌های فوتر (اولویت ۲۰) اجرا می‌شود.
        add_action( 'wp_footer', array( $this, 'delay_engine' ), 5 );

        add_filter( 'the_content', array( $this, 'auto_inject' ), 20 );

        // گسترش محتوای متنی شورت‌کد برای آنالیز سئو در افزونه‌های Yoast و Rank Math.
        add_filter( 'wpseo_pre_analysis_post_content', array( $this, 'expand_content_for_seo_analysis' ), 10, 1 );
        add_filter( 'rank_math/paper/auto_description', array( $this, 'expand_content_for_seo_analysis' ), 10, 1 );
    }

    /**
     * صف‌بندی CSS/JS سراسری قطعه کدها در زمان درست.
     */
    public function enqueue_global_assets(): void {
        foreach ( Cache::active_ids() as $post_id ) {
            if ( ! $this->should_output_global( $post_id ) ) {
                continue;
            }

            // اگر قطعه کد تاخیری است، فایل صف نمی‌شود؛ inline داخل template می‌رود.
            $delay    = '1' === (string) Post_Type::meta( $post_id, '_chs_delay' );
            $viewport = '1' === (string) Post_Type::meta( $post_id, '_chs_lazy_viewport' );

            if ( $delay || $viewport ) {
                $this->needs_delay_engine = true;
                continue;
            }

            if ( '1' !== (string) Post_Type::meta( $post_id, '_chs_global_css_js' ) ) {
                continue;
            }

            $css = (string) Post_Type::meta( $post_id, '_chs_css' );

            if ( '' !== trim( $css ) && Assets::enqueue_snippet_css( $post_id, $css ) ) {
                $this->enqueued_css[ $post_id ] = true;
            }

            $js = (string) Post_Type::meta( $post_id, '_chs_js' );

            if ( '' !== trim( $js ) && Assets::enqueue_snippet_js( $post_id, $js ) ) {
                $this->enqueued_js[ $post_id ] = true;
            }
        }

        // اگر هر قطعه کد تاخیری وجود دارد، موتور را همین‌جا صف می‌کنیم.
        if ( $this->needs_delay_engine ) {
            wp_enqueue_script( 'chs-delay-engine' );
            $this->delay_engine_enqueued = true;
        }
    }

    /**
     * پردازش شورت‌کد [my_snippet].
     *
     * @param array<string, string>|string $atts پارامترها.
     */
    public function shortcode( $atts ): string {
        $atts = shortcode_atts(
            array(
                'id'      => '',
                'replace' => 'false',
            ),
            (array) $atts,
            'my_snippet'
        );

        $post_id = absint( $atts['id'] );

        if ( ! $post_id || ! $this->is_renderable( $post_id ) ) {
            return '';
        }

        if ( 'true' === $atts['replace'] && is_singular() && in_the_loop() && is_main_query() ) {
            if ( function_exists( 'is_product' ) && is_product() ) {
                Woo_Compat::hide_product_elements();
            }
            $this->replaced_by = $post_id;
            $this->replacement_post = (int) get_queried_object_id();
            $this->replacement_output = $this->render( $post_id );
            return $this->replacement_output;
        }

        return $this->render( $post_id );
    }

    /**
     * آیا این قطعه کد در شرایط فعلی قابل نمایش است؟
     *
     * @param int $post_id شناسه.
     */
    private function is_renderable( int $post_id ): bool {
        if ( POST_TYPE !== get_post_type( $post_id ) || 'publish' !== get_post_status( $post_id ) ) {
            return false;
        }

        if ( ! Post_Type::is_active( $post_id ) ) {
            return false;
        }

        if ( Conditions::is_excluded( $post_id ) ) {
            return false;
        }

        return Conditions::passes( $post_id );
    }

    /**
     * ساخت خروجی یک قطعه کد.
     *
     * @param int $post_id شناسه.
     */
    public function render( int $post_id ): string {
        if ( isset( $this->rendering[ $post_id ] ) ) {
            return '';
        }

        if ( ! $this->is_renderable( $post_id ) ) {
            return '';
        }

        $this->rendering[ $post_id ] = true;

        if ( '1' === (string) Post_Type::meta( $post_id, '_chs_isolate' ) ) {
            $output = $this->render_isolated( $post_id );
            unset( $this->rendering[ $post_id ] );
            return $this->maybe_prepend_edit_box( $post_id, $output );
        }

        $html      = (string) Post_Type::meta( $post_id, '_chs_html' );
        $css       = (string) Post_Type::meta( $post_id, '_chs_css' );
        $js        = (string) Post_Type::meta( $post_id, '_chs_js' );
        $php       = (string) Post_Type::meta( $post_id, '_chs_php' );
        $delay     = '1' === (string) Post_Type::meta( $post_id, '_chs_delay' );
        $viewport  = '1' === (string) Post_Type::meta( $post_id, '_chs_lazy_viewport' );
        $is_global = '1' === (string) Post_Type::meta( $post_id, '_chs_global_css_js' );

        ob_start();

        printf( '<div class="custom-snippet-container" id="snippet-%d">', $post_id );

        if ( '' !== trim( $css ) && ! $is_global ) {
            echo $this->maybe_defer( '<style>' . $css . '</style>', $delay, $viewport ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        if ( '' !== trim( $php ) ) {
            $this->run_php( $post_id, $php );
        }

        if ( '' !== $html ) {
            echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        if ( '' !== trim( $js ) && ! $is_global ) {
            echo $this->maybe_defer( '<script>' . $js . '</script>', $delay, $viewport ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        echo '</div>';

        unset( $this->rendering[ $post_id ] );

        $output = (string) ob_get_clean();

        return $this->maybe_prepend_edit_box( $post_id, $output );
    }

    /** Render untrusted document structure in a separate browsing context.
     * PHP remains server-side and is deliberately NOT executed in isolated mode.
     */
    private function render_isolated( int $post_id ): string {
        wp_enqueue_script( 'chs-isolation', URL . 'assets/js/isolation.js', array(), VERSION, true );
        $html = (string) Post_Type::meta( $post_id, '_chs_html' );
        $css = (string) Post_Type::meta( $post_id, '_chs_css' );
        $js = (string) Post_Type::meta( $post_id, '_chs_js' );
        $head = (string) Post_Type::meta( $post_id, '_chs_header' );
        $foot = (string) Post_Type::meta( $post_id, '_chs_footer' );
        // Neutralize raw-text end tags in code fields; these fields contain code, not tags.
        $css = str_ireplace( '</style', '<\\/style', $css );
        $js = str_ireplace( '</script', '<\\/script', $js );
        $base = '<style>'
            . 'html{margin:0;padding:0;min-height:0;overflow-y:hidden;overflow-x:auto;-webkit-text-size-adjust:100%}'
            . 'body{margin:0;padding:0;min-height:0;overflow:hidden}'
            . 'img{max-width:100%;height:auto}'
            . '</style>';
        $head .= '<style>' . $css . '</style>';
        $foot .= '<script>' . $js . '</script>';
        $resize = file_get_contents( PATH . 'assets/js/isolation-child.js' );
        $foot .= '<script>' . $resize . '</script>';
        if ( preg_match( '/<html\b/i', $html ) ) {
            $doc = $html;
            if ( preg_match( '/<head\b[^>]*>/i', $doc ) ) {
                $doc = preg_replace_callback( '/<head\b[^>]*>/i', static function ( $m ) use ( $base, $head ) { return $m[0] . $base . $head; }, $doc, 1 );
            } else {
                $doc = preg_replace_callback( '/<html\b[^>]*>/i', static function ( $m ) use ( $base, $head ) { return $m[0] . '<head>' . $base . $head . '</head>'; }, $doc, 1 );
            }
            if ( preg_match( '#</body\s*>#i', $doc ) ) {
                $doc = preg_replace_callback( '#</body\s*>#i', static function () use ( $foot ) { return $foot . '</body>'; }, $doc, 1 );
            } else {
                $doc .= $foot;
            }
        } else {
            $doc = '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' . $base . $head . '</head><body>' . $html . $foot . '</body></html>';
        }
        return sprintf(
            '<iframe class="chs-isolated-frame" data-chs-snippet="%d" title="%s" scrolling="no" sandbox="allow-scripts allow-popups allow-popups-to-escape-sandbox allow-top-navigation-by-user-activation" referrerpolicy="no-referrer" style="display:block;width:100%%;height:auto;min-height:50px;border:0;overflow:hidden;" srcdoc="%s"></iframe>',
            $post_id,
            esc_attr( 'قطعه کد ' . $post_id ),
            esc_attr( $doc )
        );
    }

    /**
     * اجرای امن کد PHP با محافظ حالت ایمن.
     *
     * @param int    $post_id شناسه.
     * @param string $php     کد.
     */
    private function run_php( int $post_id, string $php ): void {
        if ( ! Security::php_is_runnable( $post_id, $php ) ) {
            return;
        }

        Safe_Mode::mark_start( $post_id );

        try {
            eval( $php ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
        } catch ( \Throwable $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( sprintf( '[CHS] PHP error in snippet #%d: %s', $post_id, $e->getMessage() ) ); // phpcs:ignore
            }

            if ( current_user_can( manage_cap() ) ) {
                printf(
                    '<div style="background:#f8d7da;color:#721c24;padding:10px;border:1px solid #f5c6cb;margin-bottom:15px;"><strong>%s</strong><br/>%s</div>',
                    esc_html__( 'خطای PHP در شورت‌کد:', TEXTDOMAIN ),
                    esc_html( $e->getMessage() )
                );
            }
        }

        Safe_Mode::mark_success();
    }

    /**
     * بسته‌بندی خروجی در template برای اجرای با تاخیر.
     *
     * @param string $output   خروجی.
     * @param bool   $delay    تاخیر تا تعامل کاربر.
     * @param bool   $viewport تاخیر تا ورود به دید.
     * @param string $target   محل تزریق.
     */
    private function maybe_defer( string $output, bool $delay, bool $viewport = false, string $target = 'inline' ): string {
        if ( ! $delay && ! $viewport ) {
            return $output;
        }

        $this->needs_delay_engine = true;

        return sprintf(
            '<template class="chs-delay-run" data-target="%s" data-mode="%s">%s</template>',
            esc_attr( $target ),
            esc_attr( $viewport ? 'viewport' : 'interaction' ),
            $output
        );
    }

    /**
     * آیا کدهای سراسری این قطعه کد باید در این صفحه چاپ شوند؟
     *
     * @param int $post_id شناسه.
     */
    private function should_output_global( int $post_id ): bool {
        if ( '1' === (string) Post_Type::meta( $post_id, '_chs_isolate' ) || ! $this->is_renderable( $post_id ) ) {
            return false;
        }

        $scope = (string) Post_Type::meta( $post_id, '_chs_scope' );

        if ( 'global' === $scope ) {
            return true;
        }

        if ( 'page' === $scope && is_singular() ) {
            $post = get_post();

            if ( $post && ! empty( $post->post_content ) ) {
                $pattern = '/\[my_snippet\b[^\]]*\bid\s*=\s*(["\']?)' . $post_id . '\1[^\]]*\]/i';

                return (bool) preg_match( $pattern, $post->post_content );
            }
        }

        return false;
    }

    /**
     * چاپ کدهای هدر.
     */
    public function output_header(): void {
        foreach ( Cache::active_ids() as $post_id ) {
            if ( ! $this->should_output_global( $post_id ) ) {
                continue;
            }

            $delay    = '1' === (string) Post_Type::meta( $post_id, '_chs_delay' );
            $viewport = '1' === (string) Post_Type::meta( $post_id, '_chs_lazy_viewport' );
            $header   = (string) Post_Type::meta( $post_id, '_chs_header' );

            if ( '' !== trim( $header ) ) {
                echo "\n" . $this->maybe_defer( $header, $delay, $viewport, 'head' ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }

            if ( '1' === (string) Post_Type::meta( $post_id, '_chs_global_css_js' ) ) {
                // اگر از قبل به‌صورت فایل صف شده، دوباره چاپ نمی‌کنیم.
                if ( isset( $this->enqueued_css[ $post_id ] ) ) {
                    continue;
                }

                $css = (string) Post_Type::meta( $post_id, '_chs_css' );

                if ( '' !== trim( $css ) ) {
                    echo "\n" . $this->maybe_defer( '<style>' . $css . "\n</style>", $delay, $viewport, 'head' ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }
            }
        }
    }

    /**
     * چاپ کدهای فوتر.
     */
    public function output_footer(): void {
        foreach ( Cache::active_ids() as $post_id ) {
            if ( ! $this->should_output_global( $post_id ) ) {
                continue;
            }

            $delay    = '1' === (string) Post_Type::meta( $post_id, '_chs_delay' );
            $viewport = '1' === (string) Post_Type::meta( $post_id, '_chs_lazy_viewport' );
            $footer   = (string) Post_Type::meta( $post_id, '_chs_footer' );

            if ( '' !== trim( $footer ) ) {
                echo "\n" . $this->maybe_defer( $footer, $delay, $viewport, 'footer' ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }

            if ( '1' === (string) Post_Type::meta( $post_id, '_chs_global_css_js' ) ) {
                if ( isset( $this->enqueued_js[ $post_id ] ) ) {
                    continue;
                }

                $js = (string) Post_Type::meta( $post_id, '_chs_js' );

                if ( '' !== trim( $js ) ) {
                    echo "\n" . $this->maybe_defer( '<script>' . $js . "\n</script>", $delay, $viewport, 'footer' ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }
            }
        }
    }

    /**
     * تزریق خودکار در محتوا و جایگزینی.
     *
     * @param string $content محتوای اصلی.
     */
    public function auto_inject( $content ): string {
        $content = (string) $content;

        if ( is_admin() || ! is_singular() || ! in_the_loop() || ! is_main_query() || is_feed()
            || (int) get_the_ID() !== (int) get_queried_object_id()
            || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return $content;
        }

        // Return exactly the rendered snippet, never a greedy match across theme markup.
        if ( $this->replaced_by > 0 && $this->replacement_post === (int) get_queried_object_id() ) {
            $output = $this->replacement_output;
            $this->replaced_by = 0;
            $this->replacement_output = '';
            return $output;
        }

        $current_id = (int) get_queried_object_id();

        if ( ! $current_id ) {
            return $content;
        }

        $replacement = '';
        $appended    = array();

        foreach ( Cache::active_ids() as $snippet_id ) {
            if ( ! in_array( $current_id, Conditions::include_ids( $snippet_id ), true ) ) {
                continue;
            }

            if ( ! $this->is_renderable( $snippet_id ) ) {
                continue;
            }

            if ( false !== strpos( $content, 'id="snippet-' . $snippet_id . '"' )
                || false !== strpos( $content, 'data-chs-snippet="' . $snippet_id . '"' ) ) {
                continue;
            }

            $output = $this->render( $snippet_id );

            if ( '' === $output ) {
                continue;
            }

            $replaces = '1' === (string) Post_Type::meta( $snippet_id, '_chs_replace_content' );

            if ( $replaces && '' === $replacement ) {
                $replacement = $output;

                if ( function_exists( 'is_product' ) && is_product() ) {
                    Woo_Compat::hide_product_elements();
                }
            } else {
                $appended[] = $output;
            }
        }

        if ( '' !== $replacement ) {
            return $replacement;
        }

        if ( ! empty( $appended ) ) {
            $content .= "\n" . implode( "\n", $appended );
        }

        return $content;
    }

    /**
     * چاپ موتور اجرای با تاخیر (فقط در صورت نیاز).
     */
    public function delay_engine(): void {
        if ( ! $this->needs_delay_engine || $this->delay_engine_enqueued ) {
            return;
        }

        /*
         * قطعه کد تاخیری از طریق شورت‌کد وسط محتوا ظاهر شده، یعنی پنجره
         * wp_enqueue_scripts گذشته است. اگر اسکریپت‌های فوتر هنوز چاپ
         * نشده‌اند صف می‌کنیم، در غیر این صورت مستقیماً چاپ می‌کنیم تا
         * موتور تاخیر حتماً اجرا شود.
         */
        if ( ! did_action( 'wp_print_footer_scripts' ) ) {
            wp_enqueue_script( 'chs-delay-engine' );
            $this->delay_engine_enqueued = true;
            return;
        }

        $file = PATH . 'assets/js/delay-engine.js';

        if ( is_readable( $file ) ) {
            echo "\n<script id=\"chs-delay-engine-inline\">\n";
            echo file_get_contents( $file ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.AlternativeFunctions
            echo "\n</script>\n";
            $this->delay_engine_enqueued = true;
        }
    }

    /**
     * پیش‌افزودن نوار دسترسی سریع برای ویرایش قطعه‌کد توسط مدیران و نویسندگان مجاز.
     *
     * @param int    $post_id شناسه قطعه‌کد.
     * @param string $output  خروجی رندرشده قطعه‌کد.
     */
    private function maybe_prepend_edit_box( int $post_id, string $output ): string {
        if ( is_admin() || is_feed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return $output;
        }

        if ( ! is_user_logged_in() ) {
            return $output;
        }

        if ( ! current_user_can( manage_cap() ) && ! current_user_can( 'edit_post', $post_id ) ) {
            return $output;
        }

        if ( ! apply_filters( 'chs_show_frontend_edit_box', true, $post_id ) ) {
            return $output;
        }

        $edit_url = get_edit_post_link( $post_id );
        if ( ! $edit_url ) {
            $edit_url = admin_url( 'post.php?post=' . $post_id . '&action=edit' );
        }

        $title = get_the_title( $post_id );
        if ( '' === trim( (string) $title ) ) {
            $title = sprintf( __( 'قطعه‌کد #%d', TEXTDOMAIN ), $post_id );
        }

        $is_isolate = '1' === (string) Post_Type::meta( $post_id, '_chs_isolate' );
        $mode_label = $is_isolate ? __( 'ایزوله (iframe)', TEXTDOMAIN ) : __( 'عادی', TEXTDOMAIN );

        $bar = sprintf(
            '<div class="chs-frontend-edit-bar" data-nosnippet="true" style="margin:8px 0;padding:6px 12px;background:#1d2327;color:#f0f0f1;border-radius:6px;font-size:12px;line-height:1.5;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;display:flex;align-items:center;justify-content:space-between;gap:8px;direction:rtl;text-align:right;box-shadow:0 1px 3px rgba(0,0,0,0.25);position:relative;z-index:99999;">'
            . '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">'
            . '<span style="display:inline-flex;align-items:center;color:#72aee6;">'
            . '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>'
            . '</span>'
            . '<span>%s: <strong>%s</strong></span>'
            . '<span style="background:#2c3338;padding:1px 6px;border-radius:3px;font-family:monospace;font-size:11px;color:#a7aaad;">#%d</span>'
            . '<span style="background:%s;color:#fff;padding:1px 6px;border-radius:3px;font-size:10px;">%s</span>'
            . '</div>'
            . '<div>'
            . '<a href="%s" target="_blank" rel="noopener noreferrer" style="background:#2271b1;color:#fff;padding:4px 10px;border-radius:4px;text-decoration:none;font-weight:500;font-size:11px;display:inline-flex;align-items:center;gap:4px;transition:background .15s;">'
            . '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>'
            . '%s'
            . '</a>'
            . '</div>'
            . '</div>',
            esc_html__( 'قطعه‌کد', TEXTDOMAIN ),
            esc_html( $title ),
            $post_id,
            $is_isolate ? '#d63638' : '#00a32a',
            esc_html( $mode_label ),
            esc_url( $edit_url ),
            esc_html__( 'ویرایش قطعه‌کد ↗', TEXTDOMAIN )
        );

        return $bar . $output;
    }

    /**
     * گسترش محتوای متنی قطعه‌کد برای افزونه‌های سئو (مانند Yoast SEO و Rank Math)
     * تا تعداد کلمات و تحلیل محتوا را به‌درستی تشخیص دهند.
     *
     * @param mixed $content محتوای نوشته.
     * @return mixed
     */
    public function expand_content_for_seo_analysis( $content ) {
        if ( ! is_string( $content ) || false === strpos( $content, '[my_snippet' ) ) {
            return $content;
        }

        return preg_replace_callback(
            '/\[my_snippet\b[^\]]*\bid\s*=\s*(["\']?)(\d+)\1[^\]]*\]/i',
            static function ( array $matches ): string {
                $snippet_id = absint( $matches[2] );
                if ( ! $snippet_id || ! Post_Type::is_active( $snippet_id ) ) {
                    return '';
                }

                $html = (string) Post_Type::meta( $snippet_id, '_chs_html' );
                if ( '' === trim( $html ) ) {
                    return '';
                }

                // حذف اسکریپت‌ها و استایل‌ها تا فقط متن خالص برای تحلیل سئو باقی بماند
                $clean = preg_replace( '/<script\b[^>]*>(.*?)<\/script>/is', '', $html );
                $clean = preg_replace( '/<style\b[^>]*>(.*?)<\/style>/is', '', (string) $clean );

                return "\n" . (string) $clean . "\n";
            },
            $content
        );
    }
}
