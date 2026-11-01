<?php
/**
 * لایه کش — سازگار با کش آبجکت پایدار (Redis/Memcached).
 *
 * @package AmolNovin\CHS
 */

declare( strict_types=1 );

namespace AmolNovin\CHS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * مدیریت کش قطعه کدها.
 */
final class Cache {

    public const KEY_ACTIVE     = 'chs_active_snippets_ids';
    public const KEY_CANDIDATES = 'chs_shortcode_candidates';

    /**
     * مدت اعتبار کش.
     */
    public static function ttl(): int {
        return (int) apply_filters( 'chs_cache_ttl', HOUR_IN_SECONDS );
    }

    /**
     * اتصال هوک‌ها.
     */
    public function register(): void {
        add_action( 'init', array( __CLASS__, 'add_global_group' ), 1 );

        add_action( 'save_post_' . POST_TYPE, array( __CLASS__, 'flush' ) );
        add_action( 'deleted_post', array( __CLASS__, 'maybe_flush' ) );
        add_action( 'trashed_post', array( __CLASS__, 'maybe_flush' ) );
        add_action( 'untrashed_post', array( __CLASS__, 'maybe_flush' ) );
        add_action( 'transition_post_status', array( __CLASS__, 'on_transition' ), 10, 3 );
    }

    /**
     * ثبت گروه سراسری برای کش آبجکت پایدار (پیشنهاد ۱۰).
     */
    public static function add_global_group(): void {
        if ( function_exists( 'wp_cache_add_global_groups' ) ) {
            wp_cache_add_global_groups( array( CACHE_GROUP ) );
        }
    }

    /**
     * فهرست شناسه قطعه کدهای فعال — یک کوئری، بدون مشکل N+1.
     *
     * @return int[]
     */
    public static function active_ids(): array {
        $cached = get_transient( self::KEY_ACTIVE );

        if ( false !== $cached && is_array( $cached ) ) {
            return $cached;
        }

        $query = new \WP_Query(
            array(
                'post_type'              => POST_TYPE,
                'post_status'            => 'publish',
                'posts_per_page'         => 500,
                'fields'                 => 'ids',
                'no_found_rows'          => true,
                'ignore_sticky_posts'    => true,
                'update_post_term_cache' => false,
                'orderby'                => array( 'menu_order' => 'ASC', 'ID' => 'ASC' ),
                'meta_query'             => array(
                    'relation' => 'OR',
                    array( 'key' => '_chs_active', 'value' => '1' ),
                    array( 'key' => '_chs_active', 'compare' => 'NOT EXISTS' ),
                ),
            )
        );

        $ids = array_map( 'intval', (array) $query->posts );

        set_transient( self::KEY_ACTIVE, $ids, self::ttl() );

        return $ids;
    }

    /**
     * پاک‌سازی کامل کش افزونه.
     */
    public static function flush(): void {
        global $wpdb;

        delete_transient( self::KEY_ACTIVE );
        delete_transient( self::KEY_CANDIDATES );

        $like = $wpdb->esc_like( '_transient_' . self::KEY_CANDIDATES . '_' ) . '%';

        $keys = $wpdb->get_col(
            $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like )
        );

        foreach ( (array) $keys as $key ) {
            delete_transient( str_replace( '_transient_', '', (string) $key ) );
        }

        wp_cache_delete( 'chs_used_in', CACHE_GROUP );

        do_action( 'chs_cache_flushed' );
    }

    /**
     * پاک‌سازی مشروط بر اساس نوع پست.
     *
     * @param int $post_id شناسه.
     */
    public static function maybe_flush( $post_id ): void {
        if ( POST_TYPE === get_post_type( (int) $post_id ) ) {
            self::flush();
        }
    }

    /**
     * پاک‌سازی هنگام تغییر وضعیت انتشار.
     *
     * @param string   $new_status وضعیت جدید.
     * @param string   $old_status وضعیت قبلی.
     * @param \WP_Post $post       پست.
     */
    public static function on_transition( $new_status, $old_status, $post ): void {
        if ( $post instanceof \WP_Post && POST_TYPE === $post->post_type && $new_status !== $old_status ) {
            self::flush();
        }
    }
}
