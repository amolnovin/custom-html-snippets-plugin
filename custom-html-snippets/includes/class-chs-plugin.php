<?php
/**
 * ارکستراتور افزونه — فقط سرویس‌ها را می‌سازد و به هم وصل می‌کند.
 *
 * @package AmolNovin\CHS
 */

declare( strict_types=1 );

namespace AmolNovin\CHS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * کلاس اصلی افزونه.
 */
final class Plugin {

    /**
     * سرویس‌های ثبت‌شده.
     *
     * @var array<string, object>
     */
    private array $services = array();

    /**
     * جلوگیری از بوت دوباره.
     */
    private bool $booted = false;

    /**
     * راه‌اندازی همه سرویس‌ها.
     */
    public function boot(): void {
        if ( $this->booted ) {
            return;
        }
        $this->booted = true;

        $classes = array(
            'post_type'  => Post_Type::class,
            'cache'      => Cache::class,
            'security'   => Security::class,
            'safe_mode'  => Safe_Mode::class,
            'assets'     => Assets::class,
            'conditions' => Conditions::class,
            'renderer'   => Renderer::class,
            'audit'      => Audit_Log::class,
            'metabox'    => Metabox::class,
            'ajax'       => Ajax::class,
            'admin'      => Admin::class,
            'revisions'  => Revisions::class,
            'porter'     => Porter::class,
            'repair'     => Repair::class,
            'block'      => Block::class,
            'installer'  => Installer::class,
        );

        foreach ( $classes as $key => $class ) {
            $service = new $class();

            if ( method_exists( $service, 'register' ) ) {
                $service->register();
            }

            $this->services[ $key ] = $service;
        }

        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
    }

    /**
     * دریافت یک سرویس.
     *
     * @param string $key کلید سرویس.
     * @return object|null
     */
    public function get( string $key ) {
        return $this->services[ $key ] ?? null;
    }

    /**
     * بارگذاری فایل ترجمه.
     */
    public function load_textdomain(): void {
        load_plugin_textdomain( TEXTDOMAIN, false, dirname( plugin_basename( FILE ) ) . '/languages' );
    }
}
