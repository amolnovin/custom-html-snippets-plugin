<?php
/**
 * فایل حذف کامل افزونه «قطعه کدهای سفارشی».
 *
 * هنگام حذف (Delete) افزونه از پیشخوان وردپرس اجرا می‌شود و تمام
 * داده‌های باقی‌مانده (پست‌ها، متاها، ترنزینت‌ها) را پاک می‌کند.
 *
 * برای جلوگیری از حذف داده‌ها، این خط را به wp-config.php اضافه کنید:
 *     define( 'CHS_KEEP_DATA_ON_UNINSTALL', true );
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

if ( defined( 'CHS_KEEP_DATA_ON_UNINSTALL' ) && CHS_KEEP_DATA_ON_UNINSTALL ) {
    return;
}

global $wpdb;

/**
 * حذف داده‌های افزونه برای یک سایت.
 */
function chs_uninstall_cleanup_site() {
    global $wpdb;

    // ۱) حذف تمام قطعه‌کدها به‌همراه متاهایشان.
    $snippet_ids = $wpdb->get_col(
        $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", 'custom_snippet' )
    );

    foreach ( $snippet_ids as $snippet_id ) {
        wp_delete_post( (int) $snippet_id, true );
    }

    // ۲) حذف متاهای یتیم (اگر پستی دستی حذف شده باشد).
    $wpdb->query(
        "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '\_chs\_%'"
    );

    // ۳) حذف تمام ترنزینت‌ها و گزینه‌های افزونه.
    $wpdb->query(
        "DELETE FROM {$wpdb->options}
         WHERE option_name LIKE '\_transient\_chs\_%'
            OR option_name LIKE '\_transient\_timeout\_chs\_%'
            OR option_name LIKE 'chs\_%'"
    );

    // ۴) حذف فایل‌های CSS/JS تولیدشده در پوشه uploads.
    $uploads = wp_upload_dir();

    if ( empty( $uploads['error'] ) ) {
        $dir = trailingslashit( $uploads['basedir'] ) . 'chs-snippets';

        if ( is_dir( $dir ) ) {
            foreach ( (array) glob( $dir . '/*' ) as $file ) {
                if ( is_file( $file ) ) {
                    wp_delete_file( $file );
                }
            }

            @rmdir( $dir );
        }
    }

    // ۵) حذف ترم‌های تاکسونومی دسته‌بندی قطعه کدها.
    $terms = get_terms(
        array(
            'taxonomy'   => 'chs_snippet_cat',
            'hide_empty' => false,
            'fields'     => 'ids',
        )
    );

    if ( ! is_wp_error( $terms ) ) {
        foreach ( $terms as $term_id ) {
            wp_delete_term( (int) $term_id, 'chs_snippet_cat' );
        }
    }
}

// پشتیبانی از نصب چندسایتی (Multisite).
if ( is_multisite() ) {
    $site_ids = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );

    foreach ( $site_ids as $site_id ) {
        switch_to_blog( (int) $site_id );
        chs_uninstall_cleanup_site();
        restore_current_blog();
    }
} else {
    chs_uninstall_cleanup_site();
}

// پاک‌سازی کش شیء.
wp_cache_flush();
