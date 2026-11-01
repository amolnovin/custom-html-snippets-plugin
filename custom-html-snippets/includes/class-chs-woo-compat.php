<?php
/**
 * سازگاری با ووکامرس: مخفی‌سازی اجزای صفحه محصول هنگام جایگزینی محتوا.
 *
 * @package AmolNovin\CHS
 */

declare( strict_types=1 );

namespace AmolNovin\CHS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ابزار مخفی‌سازی اجزای محصول.
 */
final class Woo_Compat {

    /**
     * جلوگیری از اجرای تکراری.
     */
    private static bool $done = false;

    /**
     * مخفی‌سازی کامل اجزای صفحه تکی محصول.
     */
    public static function hide_product_elements(): void {
        // Content replacement must not silently remove the product purchase UI.
        // Legacy full-product takeover requires an explicit developer opt-in.
        if ( ! apply_filters( 'chs_allow_product_takeover', false ) ) {
            return;
        }

        if ( self::$done ) {
            return;
        }

        self::$done = true;

        add_filter(
            'body_class',
            static function ( $classes ) {
                $classes[] = 'chs-woo-product-replaced';
                return $classes;
            }
        );

        /*
         * این متد از داخل the_content صدا زده می‌شود، یعنی wp_head گذشته است.
         * پس اگر دیر شده باشد استایل را بی‌درنگ چاپ می‌کنیم.
         */
        $print = static function (): void {
            echo '<style id="chs-woo-hide-product">' . self::css() . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        };

        if ( did_action( 'wp_head' ) ) {
            $print();
        } else {
            add_action( 'wp_head', $print, 1 );
        }

        self::remove_hooks();
    }

    /**
     * حذف هوک‌های رندر محصول.
     */
    private static function remove_hooks(): void {
        $hooks = array(
            'woocommerce_before_single_product_summary' => array(
                array( 'woocommerce_show_product_images', 20 ),
            ),
            'woocommerce_product_thumbnails'            => array(
                array( 'woocommerce_show_product_thumbnails', 20 ),
            ),
            'woocommerce_single_product_summary'        => array(
                array( 'woocommerce_template_single_title', 5 ),
                array( 'woocommerce_template_single_rating', 10 ),
                array( 'woocommerce_template_single_price', 10 ),
                array( 'woocommerce_template_single_excerpt', 20 ),
                array( 'woocommerce_template_single_add_to_cart', 30 ),
                array( 'woocommerce_template_single_meta', 40 ),
                array( 'woocommerce_template_single_sharing', 50 ),
            ),
            'woocommerce_after_single_product_summary'  => array(
                array( 'woocommerce_output_product_data_tabs', 10 ),
                array( 'woocommerce_upsell_display', 15 ),
                array( 'woocommerce_output_related_products', 20 ),
            ),
        );

        foreach ( $hooks as $tag => $callbacks ) {
            foreach ( $callbacks as $callback ) {
                remove_action( $tag, $callback[0], $callback[1] );
            }
        }
    }

    /**
     * استایل مخفی‌سازی.
     */
    private static function css(): string {
        $hide = array(
            '.woocommerce-product-gallery',
            '.product_title',
            '.summary',
            '.woocommerce-product-details__short-description',
            '.woocommerce-breadcrumb',
            '.product_meta',
            '.related.products',
            '.upsells.products',
            '.cross-sells',
            '#reviews',
            '.woocommerce-Reviews',
            '.woocommerce-tabs',
            '.product .images',
            '.product .price',
            '.product .cart',
            '.product .variations_form',
            '.product .single_add_to_cart_button',
            '.product .quantity',
            '.product .stock',
            '.product .sku_wrapper',
            '.onsale',
            '.star-rating',
            '.yith-wcwl-add-to-wishlist',
            '.woocommerce-notices-wrapper',
        );

        $selectors = array_map(
            static function ( string $sel ): string {
                return '.chs-woo-product-replaced ' . $sel;
            },
            $hide
        );

        $css  = implode( ',', $selectors );
        $css .= '{display:none!important;visibility:hidden!important;height:0!important;overflow:hidden!important;margin:0!important;padding:0!important}';

        $css .= '.chs-woo-product-replaced .product{padding:0!important;margin:0!important}';
        $css .= '.chs-woo-product-replaced .entry-content,.chs-woo-product-replaced .custom-snippet-container'
            . '{display:block!important;visibility:visible!important;width:100%!important;max-width:none!important;float:none!important;clear:both!important}';

        return $css;
    }
}
