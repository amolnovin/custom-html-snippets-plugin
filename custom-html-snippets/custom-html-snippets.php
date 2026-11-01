<?php
/**
 * Plugin Name: افزونه قطعه کدهای سفارشی (HTML/CSS/JS)
 * Description: مدیریت حرفه‌ای قطعه کدهای HTML/CSS/JS/PHP با شورت‌کد، شرط‌های نمایش پیشرفته، بلوک گوتنبرگ، درون‌ریزی/برون‌بری و حالت ایمن.
 * Version: 3.0.2
 * Author: AMOLNOVIN.IR
 * Author URI: http://AMOLNOVIN.IR
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Text Domain: custom-html-snippets
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 *
 * @package AmolNovin\CHS
 */

declare( strict_types=1 );

namespace AmolNovin\CHS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/*
|--------------------------------------------------------------------------
| ثابت‌ها
|--------------------------------------------------------------------------
*/

const VERSION     = '3.0.2';
const POST_TYPE   = 'custom_snippet';
const TEXTDOMAIN  = 'custom-html-snippets';
const CACHE_GROUP = 'chs_plugin';
const DB_VERSION  = 3;

define( __NAMESPACE__ . '\FILE', __FILE__ );
define( __NAMESPACE__ . '\PATH', plugin_dir_path( __FILE__ ) );
define( __NAMESPACE__ . '\URL', plugin_dir_url( __FILE__ ) );

/**
 * اجرای کد PHP به‌صورت پیش‌فرض غیرفعال است (امن به‌صورت پیش‌فرض).
 * برای فعال‌سازی در wp-config.php:  define( 'CHS_DISABLE_PHP', false );
 */
if ( ! defined( 'CHS_DISABLE_PHP' ) ) {
    define( 'CHS_DISABLE_PHP', true );
}

/*
|--------------------------------------------------------------------------
| بررسی حداقل نسخه PHP پیش از بارگذاری هر چیز دیگر
|--------------------------------------------------------------------------
*/

if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
    add_action(
        'admin_notices',
        static function () {
            echo '<div class="notice notice-error"><p>'
                . esc_html__( 'افزونه قطعه کدهای سفارشی به PHP نسخه ۷.۴ یا بالاتر نیاز دارد.', TEXTDOMAIN )
                . '</p></div>';
        }
    );
    return;
}

/*
|--------------------------------------------------------------------------
| بارگذارِ خودکار کلاس‌ها (PSR-4 ساده، بدون نیاز به Composer)
|--------------------------------------------------------------------------
*/

spl_autoload_register(
    static function ( string $class ): void {
        $prefix = __NAMESPACE__ . '\\';

        if ( 0 !== strpos( $class, $prefix ) ) {
            return;
        }

        $relative = substr( $class, strlen( $prefix ) );
        $file     = PATH . 'includes/class-chs-' . strtolower( str_replace( '_', '-', $relative ) ) . '.php';

        if ( is_readable( $file ) ) {
            require_once $file;
        }
    }
);

/*
|--------------------------------------------------------------------------
| توابع کمکی سراسری
|--------------------------------------------------------------------------
*/

/**
 * قابلیت لازم برای مدیریت قطعه کدها.
 */
function manage_cap(): string {
    return (string) apply_filters( 'chs_manage_capability', 'manage_options' );
}

/**
 * نمونهٔ یکتای افزونه.
 */
function plugin(): Plugin {
    static $instance = null;

    if ( null === $instance ) {
        $instance = new Plugin();
    }

    return $instance;
}

/*
|--------------------------------------------------------------------------
| راه‌اندازی
|--------------------------------------------------------------------------
*/

plugin()->boot();

register_activation_hook( __FILE__, array( Installer::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Installer::class, 'deactivate' ) );
