<?php
/**
 * شبیه‌ساز حداقلی وردپرس برای تست نسخه ۳.
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MB_IN_BYTES', 1048576 );
define( 'WP_DEBUG', true );
define( 'FS_CHMOD_FILE', 0644 );

$GLOBALS['__actions']    = array();
$GLOBALS['__filters']    = array();
$GLOBALS['__meta']       = array();
$GLOBALS['__posts']      = array();
$GLOBALS['__transients'] = array();
$GLOBALS['__opts']       = array();
$GLOBALS['__caps']       = array();
$GLOBALS['__usercaps']   = array();
$GLOBALS['__did']        = array();
$GLOBALS['__shortcodes'] = array();
$GLOBALS['__cpt']        = array();
$GLOBALS['__tax']        = array();
$GLOBALS['__registered_meta'] = array();
$GLOBALS['__enqueued']   = array();
$GLOBALS['__blocks']     = array();
$GLOBALS['__localized']  = array();

function add_action( $h, $c, $p = 10, $a = 1 ) { $GLOBALS['__actions'][ $h ][] = array( $c, $p ); return true; }
function add_filter( $h, $c, $p = 10, $a = 1 ) { $GLOBALS['__filters'][ $h ][] = array( $c, $p ); return true; }
function remove_action( $h, $c, $p = 10 ) { return true; }
function remove_filter( $h, $c, $p = 10 ) { return true; }
function has_action( $h, $c = false ) { return ! empty( $GLOBALS['__actions'][ $h ] ); }

function do_action( $h, ...$args ) {
    $GLOBALS['__did'][ $h ] = true;
    $list = $GLOBALS['__actions'][ $h ] ?? array();
    usort( $list, fn( $a, $b ) => $a[1] <=> $b[1] );
    foreach ( $list as $entry ) { call_user_func_array( $entry[0], $args ); }
}

function apply_filters( $h, $v, ...$args ) {
    $list = $GLOBALS['__filters'][ $h ] ?? array();
    usort( $list, fn( $a, $b ) => $a[1] <=> $b[1] );
    foreach ( $list as $entry ) { $v = call_user_func_array( $entry[0], array_merge( array( $v ), $args ) ); }
    return $v;
}

function did_action( $h ) { return ! empty( $GLOBALS['__did'][ $h ] ); }
function add_shortcode( $t, $c ) { $GLOBALS['__shortcodes'][ $t ] = $c; }
function do_shortcode( $content ) {
    return preg_replace_callback(
        '/\[my_snippet([^\]]*)\]/',
        function ( $m ) {
            $atts = array();
            if ( preg_match_all( '/(\w+)\s*=\s*"([^"]*)"/', $m[1], $pairs, PREG_SET_ORDER ) ) {
                foreach ( $pairs as $p ) { $atts[ $p[1] ] = $p[2]; }
            }
            return call_user_func( $GLOBALS['__shortcodes']['my_snippet'], $atts );
        },
        $content
    );
}
function shortcode_atts( $pairs, $atts, $sc = '' ) {
    $atts = (array) $atts; $out = array();
    foreach ( $pairs as $n => $d ) { $out[ $n ] = array_key_exists( $n, $atts ) ? $atts[ $n ] : $d; }
    return $out;
}

function register_post_type( $t, $a = array() ) { $GLOBALS['__cpt'][ $t ] = $a; return (object) $a; }
function register_taxonomy( $t, $obj, $a = array() ) { $GLOBALS['__tax'][ $t ] = $a; }
function register_post_meta( $pt, $key, $args = array() ) { $GLOBALS['__registered_meta'][ $key ] = $args; return true; }
function register_block_type( $name, $args = array() ) { $GLOBALS['__blocks'][ $name ] = $args; return (object) $args; }
function register_activation_hook( $f, $c ) {}
function register_deactivation_hook( $f, $c ) {}
function plugin_basename( $f ) { return basename( dirname( $f ) ) . '/' . basename( $f ); }
function plugin_dir_path( $f ) { return dirname( $f ) . '/'; }
function plugin_dir_url( $f ) { return 'http://example.test/wp-content/plugins/' . basename( dirname( $f ) ) . '/'; }
function load_plugin_textdomain( ...$a ) { return true; }
function deactivate_plugins( ...$a ) {}
function spl_autoload_register_stub() {}

function get_post_meta( $id, $key = '', $single = false ) {
    if ( '' === $key ) { return $GLOBALS['__meta'][ $id ] ?? array(); }
    return $GLOBALS['__meta'][ $id ][ $key ] ?? ( $single ? '' : array() );
}
function update_post_meta( $id, $key, $val ) { $GLOBALS['__meta'][ $id ][ $key ] = $val; return true; }
function add_metadata( $type, $id, $key, $val, $unique = false ) { $GLOBALS['__meta'][ $id ][ $key ] = $val; return true; }
function get_metadata( $type, $id, $key = '', $single = false ) { return get_post_meta( $id, $key, $single ); }
function delete_post_meta( $id, $key ) { unset( $GLOBALS['__meta'][ $id ][ $key ] ); return true; }
function get_post( $id = null ) {
    if ( null === $id ) { return $GLOBALS['__current_post'] ?? null; }
    if ( $id instanceof WP_Post ) { return $id; }
    return $GLOBALS['__posts'][ $id ] ?? null;
}
function get_post_type( $id = null ) { $p = get_post( $id ); return $p ? $p->post_type : false; }
function get_post_status( $id ) { $p = get_post( $id ); return $p ? $p->post_status : false; }
function get_the_title( $id ) { $p = get_post( $id ); return $p ? $p->post_title : ''; }
function get_post_field( $field, $id ) { $p = get_post( $id ); return $p->$field ?? ''; }
function get_post_type_object( $t ) { return (object) array( 'labels' => (object) array( 'singular_name' => $t ) ); }
function get_edit_post_link( $id ) { return 'http://x/edit?p=' . $id; }
function get_page_template_slug( $id ) { return $GLOBALS['__template'] ?? ''; }
function wp_delete_post( $id, $f = false ) { unset( $GLOBALS['__posts'][ $id ] ); return true; }
function wp_insert_post( $args, $wp_error = false ) {
    static $next = 9000;
    $id = ++$next;
    $GLOBALS['__posts'][ $id ] = (object) array_merge(
        array( 'ID' => $id, 'post_title' => '', 'post_type' => 'post', 'post_status' => 'publish', 'post_content' => '', 'post_author' => 1 ),
        $args
    );
    $GLOBALS['__posts'][ $id ]->ID = $id;
    return $id;
}
function wp_is_post_revision( $id ) { return false; }
function wp_is_post_autosave( $id ) { return false; }
function get_posts( $args = array() ) {
    $out = array();
    foreach ( $GLOBALS['__posts'] as $p ) {
        if ( isset( $args['post_type'] ) && $p->post_type !== $args['post_type'] ) { continue; }
        $out[] = ( ( $args['fields'] ?? '' ) === 'ids' ) ? $p->ID : $p;
    }
    return $out;
}
function wp_get_object_terms( $id, $tax, $args = array() ) { return array(); }
function wp_set_object_terms( $id, $terms, $tax ) { return true; }
function get_terms( $args = array() ) { return array(); }
function wp_delete_term( $id, $tax ) { return true; }
function has_term( $term, $tax, $id ) { return in_array( $term, $GLOBALS['__terms'][ $id ] ?? array(), true ); }

function get_transient( $k ) { return $GLOBALS['__transients'][ $k ] ?? false; }
function set_transient( $k, $v, $t = 0 ) { $GLOBALS['__transients'][ $k ] = $v; return true; }
function delete_transient( $k ) { unset( $GLOBALS['__transients'][ $k ] ); return true; }
function wp_cache_get( $k, $g = '' ) { return $GLOBALS['__cache'][ $g ][ $k ] ?? false; }
function wp_cache_set( $k, $v, $g = '', $t = 0 ) { $GLOBALS['__cache'][ $g ][ $k ] = $v; return true; }
function wp_cache_delete( $k, $g = '' ) { unset( $GLOBALS['__cache'][ $g ][ $k ] ); return true; }
function wp_cache_flush() { $GLOBALS['__cache'] = array(); }
function wp_cache_add_global_groups( $g ) { $GLOBALS['__global_groups'] = (array) $g; }
function get_option( $k, $d = false ) { return $GLOBALS['__opts'][ $k ] ?? $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['__opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['__opts'][ $k ] ); return true; }

function current_user_can( $cap ) { return ! empty( $GLOBALS['__caps'][ $cap ] ); }
function user_can( $uid, $cap ) { return ! empty( $GLOBALS['__usercaps'][ $uid ][ $cap ] ); }
function get_current_user_id() { return $GLOBALS['__uid'] ?? 1; }
function wp_get_current_user() {
    return (object) array(
        'ID' => $GLOBALS['__uid'] ?? 1,
        'user_login' => 'tester',
        'roles' => $GLOBALS['__roles'] ?? array( 'administrator' ),
    );
}
function is_user_logged_in() { return ! empty( $GLOBALS['__logged_in'] ); }
function wp_salt( $s = '' ) { return 'test-salt-' . $s; }
function wp_create_nonce( $a = '' ) { return 'nonce-' . md5( (string) $a ); }
function wp_verify_nonce( $n, $a = '' ) { return $n === 'nonce-' . md5( (string) $a ); }
function check_admin_referer( $a = '', $q = '_wpnonce' ) { return true; }
function check_ajax_referer( $a = '', $q = false, $die = true ) {
    if ( ! wp_verify_nonce( $_POST[ $q ] ?? '', $a ) ) { throw new Exception( 'BAD_NONCE' ); }
    return true;
}
function wp_send_json_error( $d = null, $code = 0 ) { throw new Exception( 'JSON_ERROR:' . $code . ':' . json_encode( $d, JSON_UNESCAPED_UNICODE ) ); }
function wp_send_json_success( $d = null ) { throw new Exception( 'JSON_SUCCESS:' . json_encode( $d, JSON_UNESCAPED_UNICODE ) ); }
function wp_die( $m = '', $t = '', $a = array() ) { throw new Exception( 'WP_DIE:' . $m ); }
function wp_nonce_field( ...$a ) { echo '<input type="hidden" name="chs_nonce" />'; }
function wp_nonce_url( $u, $a = '' ) { return $u . '&_wpnonce=' . wp_create_nonce( $a ); }
function wp_safe_redirect( $u ) { throw new Exception( 'REDIRECT:' . $u ); }
function wp_get_referer() { return false; }
function add_query_arg( ...$a ) {
    if ( is_array( $a[0] ) ) { return ( $a[1] ?? '' ) . '?' . http_build_query( $a[0] ); }
    return ( $a[2] ?? '' ) . '?' . $a[0] . '=' . $a[1];
}
function remove_query_arg( $k, $u = '' ) { return $u; }
function admin_url( $p = '' ) { return 'http://x/wp-admin/' . $p; }
function home_url( $p = '' ) { return 'http://x' . $p; }
function get_current_screen() { return $GLOBALS['__screen'] ?? (object) array( 'post_type' => 'custom_snippet' ); }
function add_meta_box( $id, $title, $cb, $screen = null, $ctx = 'advanced', $pri = 'default' ) {
    $GLOBALS['__metaboxes'][ $id ] = array( 'title' => $title, 'callback' => $cb );
}
function add_submenu_page( ...$a ) { $GLOBALS['__submenus'][] = $a; return 'hook'; }
function wp_enqueue_code_editor( $a ) { return array( 'codemirror' => array( 'mode' => $a['type'] ?? '' ) ); }
function wp_register_style( $h, ...$a ) { $GLOBALS['__enqueued']['style'][] = $h; }
function wp_enqueue_style( $h, ...$a ) { $GLOBALS['__enqueued']['style'][] = $h; }
function wp_register_script( $h, ...$a ) { $GLOBALS['__enqueued']['registered'][] = $h; }
function wp_enqueue_script( $h, ...$a ) { $GLOBALS['__enqueued']['script'][] = $h; }
function wp_localize_script( $h, $obj, $data ) { $GLOBALS['__localized'][ $obj ] = $data; }
function wp_add_inline_style( ...$a ) {}
function wp_add_inline_script( ...$a ) {}
function flush_rewrite_rules() {}
function nocache_headers() {}
function number_format_i18n( $n ) { return (string) $n; }
function checked( $a, $b = true, $e = true ) { if ( $a == $b ) { echo ' checked'; } }
function selected( $a, $b = true, $e = true ) { if ( $a == $b ) { echo ' selected'; } }
function submit_button( $t = '', ...$a ) { echo '<button>' . esc_html( $t ) . '</button>'; }
function wp_date( $f, $ts = null ) { return gmdate( $f, $ts ?? time() ); }
function current_time( $type ) { return 'timestamp' === $type ? time() : gmdate( 'Y-m-d H:i:s' ); }
function wp_upload_dir() {
    $base = sys_get_temp_dir() . '/chs-uploads';
    if ( ! is_dir( $base ) ) { mkdir( $base, 0777, true ); }
    return array( 'basedir' => $base, 'baseurl' => 'http://x/uploads', 'error' => false );
}
function wp_mkdir_p( $d ) { return is_dir( $d ) || mkdir( $d, 0777, true ); }
function wp_delete_file( $f ) { if ( is_file( $f ) ) { unlink( $f ); } }
function trailingslashit( $s ) { return rtrim( (string) $s, '/\\' ) . '/'; }
function wp_check_filetype( $name, $mimes = null ) {
    $ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
    return array( 'ext' => $ext, 'type' => 'application/json' );
}
function wp_privacy_anonymize_ip( $ip ) { return preg_replace( '/\.\d+$/', '.0', $ip ); }
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function wp_slash( $v ) { return $v; }
function is_rtl() { return true; }
function wp_is_mobile() { return ! empty( $GLOBALS['__mobile'] ); }
function absint( $v ) { return abs( (int) $v ); }
function wp_rand( $min = 0, $max = 0 ) { return random_int( $min, $max ?: PHP_INT_MAX ); }
function WP_Filesystem( ...$a ) { return false; }

function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $t ) { return esc_html( $t ); }
function esc_url( $t ) { return (string) $t; }
function esc_textarea( $t ) { return esc_html( $t ); }
function esc_js( $t ) { return $t; }
function esc_html__( $t, $d = '' ) { return esc_html( $t ); }
function esc_attr__( $t, $d = '' ) { return esc_html( $t ); }
function esc_attr_e( $t, $d = '' ) { echo esc_html( $t ); }
function __( $t, $d = '' ) { return $t; }
function esc_html_e( $t, $d = '' ) { echo esc_html( $t ); }
function _e( $t, $d = '' ) { echo $t; }
function sanitize_text_field( $t ) { return trim( strip_tags( (string) $t ) ); }
function sanitize_key( $t ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $t ) ); }
function wp_unslash( $v ) { return is_string( $v ) ? stripslashes( $v ) : $v; }
function wp_kses_post( $t ) {
    $t = strip_tags( (string) $t, '<p><a><strong><em><ul><li><br><div><span><h1><h2><h3><img>' );
    $t = preg_replace( '/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]*)/i', '', $t );
    return preg_replace( '/\s(href|src)\s*=\s*(["\']?)\s*javascript:[^"\'>\s]*\2/i', '', $t );
}
function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f | JSON_UNESCAPED_UNICODE ); }

function is_singular( $t = '' ) { return $GLOBALS['__is_singular'] ?? true; }
function is_main_query() { return true; }
function in_the_loop() { return true; }
function is_feed() { return false; }
function is_admin() { return ! empty( $GLOBALS['__is_admin'] ); }
function is_front_page() { return ! empty( $GLOBALS['__front_page'] ); }
function is_multisite() { return false; }
function get_the_ID() { return $GLOBALS['__loop_id'] ?? ($GLOBALS['__queried_id'] ?? 0); }
function get_queried_object_id() { return $GLOBALS['__queried_id'] ?? 0; }

class WP_Error {
    private $msg;
    public function __construct( $c = '', $m = '' ) { $this->msg = $m; }
    public function get_error_message() { return $this->msg; }
}

class WP_Post {
    public $ID = 0;
    public $post_type = 'post';
    public $post_status = 'publish';
    public $post_title = '';
    public $post_content = '';
    public $post_author = 1;
    public $post_parent = 0;
}

class WP_Query {
    public $posts = array();
    public $query_vars = array();
    public function __construct( $args = array() ) {
        $this->query_vars = $args;
        $this->posts = $GLOBALS['__wp_query_result'] ?? array();
    }
    public function get( $k, $d = null ) { return $this->query_vars[ $k ] ?? $d; }
    public function set( $k, $v ) { $this->query_vars[ $k ] = $v; }
    public function is_main_query() { return true; }
    public function is_search() { return ! empty( $this->query_vars['s'] ); }
}

class wpdb_stub {
    public $posts = 'wp_posts';
    public $postmeta = 'wp_postmeta';
    public $options = 'wp_options';
    public $last_query = '';
    public $prepare_args = array();
    public function prepare( $q, ...$args ) {
        $this->last_query = $q;
        if ( 1 === count( $args ) && is_array( $args[0] ) ) { $args = $args[0]; }
        $this->prepare_args = $args;
        return $q . ' /*ARGS:' . json_encode( $args, JSON_UNESCAPED_UNICODE ) . '*/';
    }
    public function get_results( $q ) { return $GLOBALS['__db_results'] ?? array(); }
    public function get_col( $q ) { return $GLOBALS['__db_col'] ?? array(); }
    public function query( $q ) { return 0; }
    public function esc_like( $t ) { return addcslashes( (string) $t, '_%\\' ); }
}
$GLOBALS['wpdb'] = new wpdb_stub();
