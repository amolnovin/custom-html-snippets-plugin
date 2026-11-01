<?php
/**
 * مدیریت فایل‌های CSS/JS — هم برای پیشخوان و هم تحویل فایلی قطعه کدها.
 *
 * @package AmolNovin\CHS
 */

declare( strict_types=1 );

namespace AmolNovin\CHS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * صف‌بندی دارایی‌ها.
 */
final class Assets {

    /**
     * نام زیرپوشه در uploads.
     */
    public const DIR = 'chs-snippets';

    /**
     * اتصال هوک‌ها.
     */
    public function register(): void {
        add_action( 'admin_enqueue_scripts', array( $this, 'admin' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'front_register' ) );
        add_action( 'save_post_' . POST_TYPE, array( __CLASS__, 'clear_snippet_files' ) );
    }

    /**
     * ثبت اسکریپت‌های فرانت‌اند (بدون بارگذاری تا وقتی لازم شود).
     */
    public function front_register(): void {
        wp_register_script(
            'chs-delay-engine',
            URL . 'assets/js/delay-engine.js',
            array(),
            VERSION,
            true
        );
    }

    /**
     * بارگذاری دارایی‌های پیشخوان.
     *
     * @param string $hook صفحه جاری.
     */
    public function admin( $hook ): void {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

        if ( ! $screen || POST_TYPE !== $screen->post_type ) {
            return;
        }

        wp_enqueue_style( 'chs-admin', URL . 'assets/css/admin.css', array(), VERSION );

        if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
            return;
        }

        $editors = array(
            'chs_php'    => self::editor_settings( 'application/x-httpd-php' ),
            'chs_header' => self::editor_settings( 'text/html' ),
            'chs_html'   => self::editor_settings( 'text/html' ),
            'chs_css'    => self::editor_settings( 'text/css' ),
            'chs_js'     => self::editor_settings( 'application/javascript' ),
            'chs_footer' => self::editor_settings( 'text/html' ),
        );

        wp_enqueue_script(
            'chs-admin',
            URL . 'assets/js/admin.js',
            array( 'jquery', 'code-editor' ),
            VERSION,
            true
        );

        wp_localize_script(
            'chs-admin',
            'chsAdmin',
            array(
                'nonce'      => wp_create_nonce( 'chs_ajax_nonce' ),
                'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
                'editors'    => $editors,
                'conditions' => Conditions::available(),
                'i18n'       => array(
                    'remove'      => __( 'حذف', TEXTDOMAIN ),
                    'noResults'   => __( 'موردی یافت نشد.', TEXTDOMAIN ),
                    'addRule'     => __( 'افزودن شرط', TEXTDOMAIN ),
                    'is'          => __( 'هست', TEXTDOMAIN ),
                    'isNot'       => __( 'نیست', TEXTDOMAIN ),
                    'previewing'  => __( 'در حال به‌روزرسانی پیش‌نمایش…', TEXTDOMAIN ),
                    'syntaxOk'    => __( 'کد معتبر است.', TEXTDOMAIN ),
                ),
            )
        );
    }

    /**
     * تنظیمات CodeMirror.
     *
     * @param string $type نوع محتوا.
     * @return array<string, mixed>|false
     */
    private static function editor_settings( string $type ) {
        if ( ! function_exists( 'wp_enqueue_code_editor' ) ) {
            return false;
        }

        $settings = wp_enqueue_code_editor( array( 'type' => $type ) );

        if ( false === $settings ) {
            return false;
        }

        $settings['codemirror'] = array_merge(
            $settings['codemirror'],
            array(
                'lineNumbers'       => true,
                'lineWrapping'      => true,
                'matchBrackets'     => true,
                'autoCloseBrackets' => true,
                'styleActiveLine'   => true,
                'direction'         => 'ltr',
                'indentUnit'        => 4,
                'tabSize'           => 4,
            )
        );

        return $settings;
    }

    /**
     * مسیر پوشه فایل‌های تولیدی.
     *
     * @return array{dir:string,url:string}|null
     */
    private static function upload_dir(): ?array {
        $uploads = wp_upload_dir();

        if ( ! empty( $uploads['error'] ) ) {
            return null;
        }

        $dir = trailingslashit( $uploads['basedir'] ) . self::DIR;
        $url = trailingslashit( $uploads['baseurl'] ) . self::DIR;

        if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
            return null;
        }

        return array(
            'dir' => trailingslashit( $dir ),
            'url' => trailingslashit( $url ),
        );
    }

    /**
     * نوشتن یک فایل دارایی و صف‌بندی آن.
     *
     * @param int    $post_id  شناسه.
     * @param string $content  محتوا.
     * @param string $ext      پسوند (css|js).
     */
    private static function enqueue_file( int $post_id, string $content, string $ext ): bool {
        if ( ! apply_filters( 'chs_use_file_assets', true ) ) {
            return false;
        }

        $paths = self::upload_dir();

        if ( null === $paths ) {
            return false;
        }

        $hash     = substr( md5( $content ), 0, 12 );
        $filename = sprintf( 'snippet-%d.%s.%s', $post_id, $hash, $ext );
        $file     = $paths['dir'] . $filename;

        if ( ! file_exists( $file ) ) {
            // نسخه‌های قدیمی همین قطعه کد را پاک می‌کنیم.
            foreach ( (array) glob( $paths['dir'] . sprintf( 'snippet-%d.*.%s', $post_id, $ext ) ) as $old ) {
                wp_delete_file( (string) $old );
            }

            if ( ! self::write( $file, $content ) ) {
                return false;
            }
        }

        $handle = sprintf( 'chs-snippet-%d-%s', $post_id, $ext );

        if ( 'css' === $ext ) {
            wp_enqueue_style( $handle, $paths['url'] . $filename, array(), $hash );
        } else {
            wp_enqueue_script( $handle, $paths['url'] . $filename, array(), $hash, true );
        }

        return true;
    }

    /**
     * نوشتن فایل با WP_Filesystem و بازگشت به file_put_contents.
     *
     * @param string $file    مسیر.
     * @param string $content محتوا.
     */
    private static function write( string $file, string $content ): bool {
        global $wp_filesystem;

        if ( ! function_exists( 'WP_Filesystem' ) ) {
            $include = ABSPATH . 'wp-admin/includes/file.php';

            // در بافت فرانت‌اند این فایل ممکن است در دسترس نباشد.
            if ( is_readable( $include ) ) {
                require_once $include;
            }
        }

        if ( function_exists( 'WP_Filesystem' ) && WP_Filesystem() && $wp_filesystem ) {
            return (bool) $wp_filesystem->put_contents( $file, $content, FS_CHMOD_FILE );
        }

        // نوشتن اتمیک تا فایل نیمه‌کاره به مرورگر سرو نشود.
        $temp = $file . '.tmp' . wp_rand( 1000, 9999 );

        if ( false === file_put_contents( $temp, $content ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
            return false;
        }

        if ( ! rename( $temp, $file ) ) {
            wp_delete_file( $temp );
            return false;
        }

        return true;
    }

    /**
     * صف‌بندی CSS سراسری یک قطعه کد به‌صورت فایل.
     *
     * @param int    $post_id شناسه.
     * @param string $css     محتوا.
     */
    public static function enqueue_snippet_css( int $post_id, string $css ): bool {
        /*
         * اگر استایل‌ها از قبل چاپ شده‌اند، صف‌بندی بی‌اثر است و باید
         * false برگردانیم تا فراخواننده به چاپ inline برگردد.
         */
        if ( did_action( 'wp_print_styles' ) || did_action( 'wp_head' ) ) {
            return false;
        }

        return self::enqueue_file( $post_id, $css, 'css' );
    }

    /**
     * صف‌بندی JS سراسری یک قطعه کد به‌صورت فایل.
     *
     * @param int    $post_id شناسه.
     * @param string $js      محتوا.
     */
    public static function enqueue_snippet_js( int $post_id, string $js ): bool {
        // پس از چاپ اسکریپت‌های فوتر، صف‌بندی گم می‌شود.
        if ( did_action( 'wp_print_footer_scripts' ) ) {
            return false;
        }

        return self::enqueue_file( $post_id, $js, 'js' );
    }

    /**
     * حذف فایل‌های تولیدی یک قطعه کد پس از ویرایش.
     *
     * @param int $post_id شناسه.
     */
    public static function clear_snippet_files( $post_id ): void {
        $paths = self::upload_dir();

        if ( null === $paths ) {
            return;
        }

        foreach ( (array) glob( $paths['dir'] . sprintf( 'snippet-%d.*', (int) $post_id ) ) as $file ) {
            wp_delete_file( (string) $file );
        }
    }
}
