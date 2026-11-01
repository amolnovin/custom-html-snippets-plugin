<?php
/**
 * تست‌های نسخه ۳.۰ — معماری، امنیت، کارایی و قابلیت‌ها.
 */

require __DIR__ . '/wp-stubs.php';

$pass = 0;
$fail = 0;
$failed_names = array();

function check( string $name, bool $cond, string $extra = '' ): void {
    global $pass, $fail, $failed_names;
    if ( $cond ) {
        $pass++;
        echo "  ✅ $name\n";
    } else {
        $fail++;
        $failed_names[] = $name;
        echo "  ❌ $name" . ( $extra ? " -> $extra" : '' ) . "\n";
    }
}

function section( string $title ): void {
    echo "\n=== $title ===\n";
}

use AmolNovin\CHS as CHS;

section( '۱) بارگذاری و معماری' );

require __DIR__ . '/../custom-html-snippets/custom-html-snippets.php';

check( 'افزونه بدون خطای مرگبار بارگذاری شد', true );
check( 'نسخه ۳.۰.۳ است', CHS\VERSION === '3.0.3', CHS\VERSION );
check( 'فضای نام تعریف شده است', function_exists( 'AmolNovin\CHS\plugin' ) );
check( 'PHP پیش‌فرض غیرفعال است', CHS_DISABLE_PHP === true );

$classes = array(
    'Plugin', 'Post_Type', 'Sanitizer', 'Security', 'Safe_Mode', 'Cache',
    'Conditions', 'Renderer', 'Assets', 'Woo_Compat', 'Ajax', 'Audit_Log',
    'Revisions', 'Porter', 'Metabox', 'Admin', 'Block', 'Installer', 'Repair',
);

$missing = array();
foreach ( $classes as $c ) {
    if ( ! class_exists( 'AmolNovin\CHS\\' . $c ) ) { $missing[] = $c; }
}
check( 'هر ۱۹ کلاس با بارگذار خودکار یافت شد', empty( $missing ), implode( ',', $missing ) );

$plugin = CHS\plugin();
check( 'سرویس renderer ثبت شد', $plugin->get( 'renderer' ) instanceof CHS\Renderer );
check( 'سرویس porter ثبت شد', $plugin->get( 'porter' ) instanceof CHS\Porter );

section( '۲) نوع محتوا، تاکسونومی و متاها' );

do_action( 'init' );

$cpt = $GLOBALS['__cpt']['custom_snippet'] ?? array();
check( 'CPT ثبت شد', ! empty( $cpt ) );
check( 'show_in_rest غیرفعال است', false === ( $cpt['show_in_rest'] ?? null ) );
check( 'بازنگری پشتیبانی می‌شود', in_array( 'revisions', $cpt['supports'] ?? array(), true ) );
check( 'دسترسی اختصاصی دارد', 'manage_options' === ( $cpt['capabilities']['edit_posts'] ?? '' ) );
check( 'تاکسونومی دسته‌بندی ثبت شد', isset( $GLOBALS['__tax']['chs_snippet_cat'] ) );
check( 'متاها با register_post_meta ثبت شدند', count( $GLOBALS['__registered_meta'] ) >= 15, (string) count( $GLOBALS['__registered_meta'] ) );
// عمداً sanitize_callback ثبت نمی‌شود؛ پاک‌سازی در مرز فرم انجام می‌گیرد.
check( 'متاها sanitize_callback ندارند (رفع باگ نابودی کد)', ! isset( $GLOBALS['__registered_meta']['_chs_html']['sanitize_callback'] ) );
check( 'متاها auth_callback دارند', is_callable( $GLOBALS['__registered_meta']['_chs_html']['auth_callback'] ?? null ) );

section( '۳) پاک‌سازی متمرکز (Sanitizer)' );

$GLOBALS['__caps'] = array( 'manage_options' => true, 'unfiltered_html' => true );
check( 'scope نامعتبر به global برمی‌گردد', 'global' === CHS\Sanitizer::apply( 'scope', '<script>x</script>' ) );
check( 'scope معتبر پذیرفته می‌شود', 'page' === CHS\Sanitizer::apply( 'scope', 'page' ) );
check( 'bool_flag درست کار می‌کند', '1' === CHS\Sanitizer::apply( 'bool_flag', 'on' ) && '0' === CHS\Sanitizer::apply( 'bool_flag', 'xx' ) );

$css = CHS\Sanitizer::css( 'body{color:red}</style><script>alert(1)</script>' );
check( 'تگ style بسته از CSS حذف شد', false === stripos( $css, '</style' ), $css );
check( 'تگ script از CSS حذف شد', false === stripos( $css, '<script' ), $css );

$ids = CHS\Sanitizer::id_list( '5, 7, 5, 0, abc, 9' );
check( 'id_list یکتا و int می‌سازد', array( 5, 7, 9 ) === $ids, json_encode( $ids ) );
check( 'id_list آرایه رشته‌ای را هم می‌پذیرد', array( 3, 4 ) === CHS\Sanitizer::id_list( array( '3', '4' ) ) );

section( '۴) امنیت: امضای کد PHP' );

$code = 'echo "ok";';
check( 'بدون امضا اجرا نمی‌شود', false === CHS\Security::php_is_runnable( 500, $code ) );
check( 'امضا طول ۶۴ دارد', 64 === strlen( CHS\Security::php_hash( $code ) ) );
check( 'امضای دو کد متفاوت یکسان نیست', CHS\Security::php_hash( 'a' ) !== CHS\Security::php_hash( 'b' ) );

$GLOBALS['__meta'][500] = array(
    '_chs_php'        => $code,
    '_chs_php_hash'   => CHS\Security::php_hash( $code ),
    '_chs_php_author' => 7,
);
$GLOBALS['__usercaps'][7] = array( 'unfiltered_html' => true );
check( 'با CHS_DISABLE_PHP=true اجرا نمی‌شود', false === CHS\Security::php_is_runnable( 500, $code ) );
check( 'کد دستکاری‌شده اجرا نمی‌شود', false === CHS\Security::php_is_runnable( 500, 'system("rm -rf /");' ) );

section( '۵) اعتبارسنجی نگارشی PHP (پیشنهاد ۵)' );

check( 'کد صحیح تایید می‌شود', true === CHS\Security::validate_php_syntax( '$a = 1; echo $a;' ) );
check( 'کد خالی تایید می‌شود', true === CHS\Security::validate_php_syntax( '' ) );
$err = CHS\Security::validate_php_syntax( 'if ( true ) { echo "x";' );
check( 'کد ناقص رد می‌شود', is_string( $err ), var_export( $err, true ) );
$err2 = CHS\Security::validate_php_syntax( 'echo 1; ?><script>bad</script>' );
check( 'تگ ?> رد می‌شود', is_string( $err2 ), var_export( $err2, true ) );

section( '۶) حالت ایمن (پیشنهاد ۴)' );

$GLOBALS['__opts'] = array();
CHS\Safe_Mode::mark_start( 42 );
check( 'نشانه اجرا ثبت شد', 42 === ( get_option( CHS\Safe_Mode::OPTION_RUNNING )['id'] ?? 0 ) );
CHS\Safe_Mode::mark_success();
check( 'نشانه پس از موفقیت پاک شد', false === get_option( CHS\Safe_Mode::OPTION_RUNNING ) );

// شبیه‌سازی کرش: نشانه باقی می‌ماند و در بوت بعدی باید غیرفعال شود.
$GLOBALS['__posts'][42] = (object) array( 'ID' => 42, 'post_type' => 'custom_snippet', 'post_status' => 'publish', 'post_title' => 'کد خراب' );
$GLOBALS['__meta'][42] = array( '_chs_active' => '1' );
CHS\Safe_Mode::mark_start( 42 );
( new CHS\Safe_Mode() )->detect_previous_crash();
check( 'قطعه کد کرش‌کرده خودکار غیرفعال شد', '0' === get_post_meta( 42, '_chs_active', true ) );
check( 'در فهرست غیرفعال‌شده‌ها ثبت شد', isset( get_option( CHS\Safe_Mode::OPTION_DISABLED )[42] ) );
check( 'نشانه پاک شد', false === get_option( CHS\Safe_Mode::OPTION_RUNNING ) );

section( '۷) AJAX: nonce و سطح دسترسی' );

$ajax = new CHS\Ajax();

$GLOBALS['__caps'] = array( 'manage_options' => true );
$_POST = array( 'q' => 'test' );
try { $ajax->search_posts(); check( 'جستجو بدون nonce رد شد', false, 'اجرا شد' ); }
catch ( Exception $e ) { check( 'جستجو بدون nonce رد شد', 'BAD_NONCE' === $e->getMessage() ); }

$_POST = array( 'php' => 'echo 1;' );
try { $ajax->validate_php(); check( 'اعتبارسنجی بدون nonce رد شد', false, 'اجرا شد' ); }
catch ( Exception $e ) { check( 'اعتبارسنجی بدون nonce رد شد', 'BAD_NONCE' === $e->getMessage() ); }

$_POST = array( 'html' => 'x' );
try { $ajax->preview(); check( 'پیش‌نمایش بدون nonce رد شد', false, 'اجرا شد' ); }
catch ( Exception $e ) { check( 'پیش‌نمایش بدون nonce رد شد', 'BAD_NONCE' === $e->getMessage() ); }

$GLOBALS['__caps'] = array();
$_POST = array( 'q' => 'test', 'nonce' => wp_create_nonce( 'chs_ajax_nonce' ) );
try { $ajax->search_posts(); check( 'کاربر کم‌دسترسی رد شد', false, 'اجرا شد' ); }
catch ( Exception $e ) { check( 'کاربر کم‌دسترسی رد شد (۴۰۳)', 0 === strpos( $e->getMessage(), 'JSON_ERROR:403' ), $e->getMessage() ); }

section( '۸) AJAX: عدم نشت پیش‌نویس' );

$GLOBALS['__caps'] = array( 'manage_options' => true );
$GLOBALS['__posts'][60] = (object) array( 'ID' => 60, 'post_title' => 'برگه عمومی', 'post_type' => 'page', 'post_status' => 'publish' );
$GLOBALS['__posts'][61] = (object) array( 'ID' => 61, 'post_title' => 'راز پیش‌نویس', 'post_type' => 'page', 'post_status' => 'draft' );
$_POST = array( 'ids' => '60,61', 'nonce' => wp_create_nonce( 'chs_ajax_nonce' ) );
try { $ajax->page_titles(); }
catch ( Exception $e ) {
    $out = $e->getMessage();
    check( 'پست منتشرشده برگردانده شد', false !== strpos( $out, 'عمومی' ) );
    check( 'پیش‌نویس نشت نکرد', false === strpos( $out, 'راز' ), $out );
}

section( '۹) شرط‌های نمایش پیشرفته (پیشنهاد ۱۳)' );

check( 'ده نوع شرط تعریف شده', count( CHS\Conditions::available() ) >= 10 );

$rules = CHS\Conditions::sanitize( json_encode( array(
    array( 'type' => 'device', 'operator' => 'is', 'value' => 'mobile' ),
    array( 'type' => 'hack_attempt', 'operator' => 'is', 'value' => 'x' ),
) ) );
check( 'شرط ناشناخته حذف شد', 1 === count( $rules ), json_encode( $rules ) );
check( 'شرط معتبر حفظ شد', 'device' === $rules[0]['type'] );

$GLOBALS['__meta'][700] = array( '_chs_conditions' => array( array( 'type' => 'device', 'operator' => 'is', 'value' => 'mobile' ) ) );
$GLOBALS['__mobile'] = true;
check( 'شرط دستگاه موبایل برقرار است', true === CHS\Conditions::passes( 700 ) );
$GLOBALS['__mobile'] = false;
check( 'شرط دستگاه روی دسکتاپ رد می‌شود', false === CHS\Conditions::passes( 700 ) );

$GLOBALS['__meta'][701] = array( '_chs_conditions' => array( array( 'type' => 'device', 'operator' => 'is_not', 'value' => 'mobile' ) ) );
check( 'عملگر is_not درست کار می‌کند', true === CHS\Conditions::passes( 701 ) );

$GLOBALS['__meta'][702] = array( '_chs_conditions' => array( array( 'type' => 'url_param', 'operator' => 'is', 'value' => 'utm_source=news' ) ) );
$_GET = array();
check( 'پارامتر URL موجود نیست → رد', false === CHS\Conditions::passes( 702 ) );
$_GET = array( 'utm_source' => 'news' );
check( 'پارامتر URL مطابق → قبول', true === CHS\Conditions::passes( 702 ) );
$_GET = array();

$GLOBALS['__meta'][703] = array( '_chs_conditions' => array( array( 'type' => 'date_range', 'operator' => 'is', 'value' => '2020-01-01 00:00|2099-12-31 23:59' ) ) );
check( 'بازه زمانی جاری برقرار است', true === CHS\Conditions::passes( 703 ) );
$GLOBALS['__meta'][704] = array( '_chs_conditions' => array( array( 'type' => 'date_range', 'operator' => 'is', 'value' => '2000-01-01 00:00|2001-01-01 00:00' ) ) );
check( 'بازه زمانی گذشته رد می‌شود', false === CHS\Conditions::passes( 704 ) );

$GLOBALS['__meta'][705] = array( '_chs_conditions' => array( array( 'type' => 'logged_in', 'operator' => 'is', 'value' => 'yes' ) ) );
$GLOBALS['__logged_in'] = false;
check( 'شرط ورود برای مهمان رد می‌شود', false === CHS\Conditions::passes( 705 ) );
$GLOBALS['__logged_in'] = true;
check( 'شرط ورود برای کاربر واردشده قبول', true === CHS\Conditions::passes( 705 ) );

check( 'بدون شرط، همیشه قبول', true === CHS\Conditions::passes( 999 ) );

section( '۱۰) کش و رفع N+1' );

delete_transient( CHS\Cache::KEY_ACTIVE );
$GLOBALS['__wp_query_result'] = array( 1, 2, 3 );
$active = CHS\Cache::active_ids();
check( 'شناسه‌ها از یک کوئری آمد', array( 1, 2, 3 ) === $active );
check( 'در ترنزینت ذخیره شد', array( 1, 2, 3 ) === get_transient( CHS\Cache::KEY_ACTIVE ) );
CHS\Cache::add_global_group();
check( 'گروه کش سراسری ثبت شد', in_array( CHS\CACHE_GROUP, $GLOBALS['__global_groups'] ?? array(), true ) );

section( '۱۱) کوئری «محل استفاده» بهینه' );

$GLOBALS['__db_results'] = array(
    (object) array( 'ID' => 80, 'post_title' => 'صفحه الف', 'post_type' => 'page', 'post_content' => 'x [my_snippet id="7"] y' ),
    (object) array( 'ID' => 81, 'post_title' => 'صفحه ب', 'post_type' => 'page', 'post_content' => 'بدون شورت‌کد' ),
);
delete_transient( CHS\Cache::KEY_CANDIDATES . '_7' );
$usage = CHS\Admin::find_usage( 7 );
check( 'فقط تطبیق واقعی برگشت', 1 === count( $usage ) && 80 === (int) $usage[0]->ID, (string) count( $usage ) );
check( 'محتوای حجیم کش نشد', ! isset( $usage[0]->post_content ) );
check( 'نوع پست‌ها پارامتری شد', substr_count( $GLOBALS['wpdb']->last_query, '%s' ) >= 9 );

section( '۱۲) رندر و جلوگیری از بازگشت' );

$renderer = $plugin->get( 'renderer' );
$GLOBALS['__posts'][100] = (object) array( 'ID' => 100, 'post_type' => 'custom_snippet', 'post_status' => 'publish', 'post_title' => 's' );
$GLOBALS['__meta'][100] = array( '_chs_html' => '<p>سلام دنیا</p>', '_chs_active' => '1' );
$GLOBALS['__queried_id'] = 555;

$out = $renderer->shortcode( array( 'id' => 100 ) );
check( 'خروجی HTML رندر شد', false !== strpos( $out, 'سلام دنیا' ) );
check( 'کانتینر با شناسه درست', false !== strpos( $out, 'id="snippet-100"' ) );

$GLOBALS['__meta'][101] = array( '_chs_html' => 'A[my_snippet id="101"]B', '_chs_active' => '1' );
$GLOBALS['__posts'][101] = (object) array( 'ID' => 101, 'post_type' => 'custom_snippet', 'post_status' => 'publish', 'post_title' => 'r' );
$out2 = $renderer->render( 101 );
check( 'بازگشت بی‌نهایت رخ نداد', is_string( $out2 ) );

$GLOBALS['__meta'][102] = array( '_chs_html' => 'مخفی', '_chs_active' => '0' );
$GLOBALS['__posts'][102] = (object) array( 'ID' => 102, 'post_type' => 'custom_snippet', 'post_status' => 'publish', 'post_title' => 'i' );
check( 'قطعه کد غیرفعال رندر نمی‌شود', '' === $renderer->shortcode( array( 'id' => 102 ) ) );

$GLOBALS['__meta'][103] = array( '_chs_html' => 'محتوا', '_chs_active' => '1', '_chs_exclude_pages' => array( 555 ) );
$GLOBALS['__posts'][103] = (object) array( 'ID' => 103, 'post_type' => 'custom_snippet', 'post_status' => 'publish', 'post_title' => 'e' );
check( 'صفحه استثنا رندر نمی‌شود', '' === $renderer->shortcode( array( 'id' => 103 ) ) );

section( '۱۳) اجرای با تاخیر و IntersectionObserver' );

$GLOBALS['__meta'][110] = array( '_chs_html' => 'X', '_chs_js' => 'console.log(1)', '_chs_active' => '1', '_chs_lazy_viewport' => '1' );
$GLOBALS['__posts'][110] = (object) array( 'ID' => 110, 'post_type' => 'custom_snippet', 'post_status' => 'publish', 'post_title' => 'v' );
$lazy = $renderer->render( 110 );
check( 'خروجی در template قرار گرفت', false !== strpos( $lazy, 'chs-delay-run' ) );
check( 'حالت viewport علامت خورد', false !== strpos( $lazy, 'data-mode="viewport"' ), $lazy );

$GLOBALS['__meta'][111] = array( '_chs_html' => 'X', '_chs_js' => 'a()', '_chs_active' => '1', '_chs_delay' => '1' );
$GLOBALS['__posts'][111] = (object) array( 'ID' => 111, 'post_type' => 'custom_snippet', 'post_status' => 'publish', 'post_title' => 'd' );
check( 'حالت interaction علامت خورد', false !== strpos( $renderer->render( 111 ), 'data-mode="interaction"' ) );

section( '۱۴) لاگ حسابرسی (پیشنهاد ۶)' );

CHS\Audit_Log::record( 200, 'updated', 'تست' );
$log = CHS\Audit_Log::get( 200 );
check( 'رویداد ثبت شد', 1 === count( $log ) );
check( 'نام کاربر ثبت شد', 'tester' === $log[0]['user'] );
check( 'IP ناشناس‌سازی شد یا خالی است', ! isset( $log[0]['ip'] ) || false === strpos( (string) $log[0]['ip'], '.999' ) );
check( 'برچسب فارسی دارد', 'به‌روزرسانی' === CHS\Audit_Log::label( 'updated' ) );

for ( $i = 0; $i < 60; $i++ ) { CHS\Audit_Log::record( 200, 'updated', "n$i" ); }
check( 'سقف ۵۰ رویداد رعایت شد', 50 === count( CHS\Audit_Log::get( 200 ) ), (string) count( CHS\Audit_Log::get( 200 ) ) );

section( '۱۵) بازنگری متاها (پیشنهاد ۱۴)' );

$rev = new CHS\Revisions();
check( 'شش فیلد نسخه‌بندی می‌شوند', 6 === count( CHS\Revisions::fields() ) );

$GLOBALS['__posts'][300] = (object) array( 'ID' => 300, 'post_type' => 'custom_snippet', 'post_status' => 'publish', 'post_title' => 'p', 'post_parent' => 0 );
$GLOBALS['__meta'][300] = array( '_chs_html' => 'نسخه اول' );
$GLOBALS['__posts'][301] = (object) array( 'ID' => 301, 'post_type' => 'revision', 'post_status' => 'inherit', 'post_title' => 'r', 'post_parent' => 300 );

$rev->save_revision_meta( 301 );
check( 'متا روی بازنگری ذخیره شد', 'نسخه اول' === get_post_meta( 301, '_chs_html', true ) );

$GLOBALS['__meta'][300]['_chs_html'] = 'نسخه دوم';
$rev->restore_revision_meta( 300, 301 );
check( 'متا از بازنگری بازگردانی شد', 'نسخه اول' === get_post_meta( 300, '_chs_html', true ) );

section( '۱۶) درون‌ریزی و برون‌بری (پیشنهاد ۱۲)' );

$porter = new CHS\Porter();
$GLOBALS['__caps'] = array( 'manage_options' => true, 'unfiltered_html' => true );

$file = tempnam( sys_get_temp_dir(), 'chs' ) . '.json';
file_put_contents( $file, json_encode( array(
    'format'   => 'chs-snippets',
    'snippets' => array(
        array(
            'title' => 'وارداتی',
            'meta'  => array(
                '_chs_html'   => '<p>سلام</p>',
                '_chs_php'    => 'system("evil");',
                '_chs_active' => '1',
                '_chs_scope'  => 'bogus',
            ),
        ),
    ),
), JSON_UNESCAPED_UNICODE ) );

$_FILES = array( 'chs_file' => array( 'tmp_name' => $file, 'name' => 'x.json', 'size' => filesize( $file ) ) );
$_POST  = array( 'as_draft' => '1' );

$before = count( $GLOBALS['__posts'] );
try { $porter->handle_import(); } catch ( Exception $e ) { /* redirect */ }
check( 'قطعه کد درون‌ریزی شد', count( $GLOBALS['__posts'] ) > $before );

$new_id = 0;
foreach ( $GLOBALS['__posts'] as $id => $p ) {
    if ( 'وارداتی' === $p->post_title ) { $new_id = $id; }
}
check( 'عنوان درست ذخیره شد', $new_id > 0 );
check( 'به‌صورت پیش‌نویس وارد شد', 'draft' === ( $GLOBALS['__posts'][ $new_id ]->post_status ?? '' ) );
check( 'غیرفعال وارد شد', '0' === get_post_meta( $new_id, '_chs_active', true ) );
check( 'کد PHP وارداتی امضا نشد (امنیت)', '' === get_post_meta( $new_id, '_chs_php_hash', true ) );
check( 'scope نامعتبر اصلاح شد', 'global' === get_post_meta( $new_id, '_chs_scope', true ) );
@unlink( $file );

section( '۱۷) کپی قطعه کد (پیشنهاد ۱۵)' );

$GLOBALS['__posts'][400] = (object) array( 'ID' => 400, 'post_type' => 'custom_snippet', 'post_status' => 'publish', 'post_title' => 'اصلی' );
$GLOBALS['__meta'][400] = array( '_chs_html' => 'محتوای اصلی', '_chs_active' => '1', '_chs_php_hash' => 'abc' );
$_GET = array( 'post_id' => 400 );

$count_before = count( $GLOBALS['__posts'] );
try { $porter->handle_duplicate(); } catch ( Exception $e ) { /* redirect */ }
check( 'کپی ساخته شد', count( $GLOBALS['__posts'] ) > $count_before );

$copy_id = 0;
foreach ( $GLOBALS['__posts'] as $id => $p ) {
    if ( false !== strpos( (string) $p->post_title, 'کپی' ) ) { $copy_id = $id; }
}
check( 'عنوان کپی درست است', $copy_id > 0 );
check( 'محتوا کپی شد', 'محتوای اصلی' === get_post_meta( $copy_id, '_chs_html', true ) );
check( 'کپی غیرفعال است', '0' === get_post_meta( $copy_id, '_chs_active', true ) );
check( 'امضای PHP کپی نشد', '' === get_post_meta( $copy_id, '_chs_php_hash', true ) );

section( '۱۸) بلوک گوتنبرگ (پیشنهاد ۱۷)' );

check( 'بلوک ثبت شد', isset( $GLOBALS['__blocks']['chs/snippet'] ) );
$block_args = $GLOBALS['__blocks']['chs/snippet'] ?? array();
check( 'رندر سمت سرور دارد', is_callable( $block_args['render_callback'] ?? null ) );
check( 'ویژگی snippetId دارد', isset( $block_args['attributes']['snippetId'] ) );

$block = new CHS\Block();
check( 'بلوک بدون شناسه خروجی خالی می‌دهد', '' === $block->render( array( 'snippetId' => 0 ) ) );
$rendered = $block->render( array( 'snippetId' => 100 ) );
check( 'بلوک شورت‌کد را اجرا می‌کند', false !== strpos( $rendered, 'سلام دنیا' ), $rendered );

section( '۱۹) مهاجرت خودکار از نسخه ۲ (پیشنهاد ۲۲)' );

$GLOBALS['__opts'] = array();
$GLOBALS['__posts'] = array(
    600 => (object) array( 'ID' => 600, 'post_type' => 'custom_snippet', 'post_status' => 'publish', 'post_title' => 'قدیمی', 'post_author' => 3 ),
);
$GLOBALS['__meta'][600] = array(
    '_chs_php'           => 'echo "legacy";',
    '_chs_exclude_pages' => array( '11', '12' ),
);
$GLOBALS['__usercaps'][3] = array( 'unfiltered_html' => true );

CHS\Installer::maybe_migrate();

check( 'نسخه دیتابیس ثبت شد', CHS\DB_VERSION === (int) get_option( CHS\Installer::OPTION_DB_VERSION ) );
check( 'کد PHP قدیمی امضا شد', CHS\Security::php_hash( 'echo "legacy";' ) === get_post_meta( 600, '_chs_php_hash', true ) );
check( 'نویسنده ثبت شد', 3 === (int) get_post_meta( 600, '_chs_php_author', true ) );
check( 'شناسه‌های رشته‌ای به int تبدیل شدند', array( 11, 12 ) === get_post_meta( 600, '_chs_exclude_pages', true ), json_encode( get_post_meta( 600, '_chs_exclude_pages', true ) ) );
check( 'کلید جدید lazy_viewport مقداردهی شد', '0' === get_post_meta( 600, '_chs_lazy_viewport', true ) );

$report = get_option( CHS\Installer::OPTION_MIGRATION );
check( 'گزارش مهاجرت تولید شد', is_array( $report ) && 1 === (int) $report['signed'], json_encode( $report ) );

// نویسنده بدون دسترسی → نباید امضا شود.
$GLOBALS['__opts'] = array();
$GLOBALS['__posts'] = array(
    601 => (object) array( 'ID' => 601, 'post_type' => 'custom_snippet', 'post_status' => 'publish', 'post_title' => 'خطرناک', 'post_author' => 9 ),
);
$GLOBALS['__meta'][601] = array( '_chs_php' => 'system("x");' );
$GLOBALS['__usercaps'][9] = array();

CHS\Installer::maybe_migrate();
check( 'کد نویسنده بی‌دسترسی امضا نشد', '' === get_post_meta( 601, '_chs_php_hash', true ) );
$report2 = get_option( CHS\Installer::OPTION_MIGRATION );
check( 'در گزارش به‌عنوان امضانشده ثبت شد', 1 === (int) ( $report2['unsigned'] ?? 0 ), json_encode( $report2 ) );

section( '۲۰) دارایی‌ها و بین‌المللی‌سازی' );

$assets = new CHS\Assets();
$GLOBALS['__caps'] = array( 'manage_options' => true, 'unfiltered_html' => true );
$assets->admin( 'post.php' );
check( 'CSS پیشخوان صف شد', in_array( 'chs-admin', $GLOBALS['__enqueued']['style'] ?? array(), true ) );
check( 'JS پیشخوان صف شد', in_array( 'chs-admin', $GLOBALS['__enqueued']['script'] ?? array(), true ) );
check( 'nonce به JS پاس داده شد', ! empty( $GLOBALS['__localized']['chsAdmin']['nonce'] ) );
check( 'فهرست شرط‌ها به JS پاس داده شد', ! empty( $GLOBALS['__localized']['chsAdmin']['conditions'] ) );

$assets->front_register();
check( 'موتور تاخیر ثبت شد', in_array( 'chs-delay-engine', $GLOBALS['__enqueued']['registered'] ?? array(), true ) );

section( '۲۱) فایل‌های افزونه' );

$base = __DIR__ . '/../custom-html-snippets/';
check( 'uninstall.php وجود دارد', file_exists( $base . 'uninstall.php' ) );
check( 'readme.txt وجود دارد', file_exists( $base . 'readme.txt' ) );
check( 'assets/css/admin.css وجود دارد', file_exists( $base . 'assets/css/admin.css' ) );
check( 'assets/js/admin.js وجود دارد', file_exists( $base . 'assets/js/admin.js' ) );
check( 'assets/js/block.js وجود دارد', file_exists( $base . 'assets/js/block.js' ) );
check( 'assets/js/delay-engine.js وجود دارد', file_exists( $base . 'assets/js/delay-engine.js' ) );
check( 'پوشه includes دارای ۱۹ کلاس است', 19 === count( glob( $base . 'includes/*.php' ) ), (string) count( glob( $base . 'includes/*.php' ) ) );

$main = (string) file_get_contents( $base . 'custom-html-snippets.php' );
check( 'فایل اصلی کوچک و فقط بوت‌استرپ است', substr_count( $main, "\n" ) < 150, (string) substr_count( $main, "\n" ) );
check( 'هیچ CSS اینلاینی در PHP نمانده', false === strpos( $main, 'wp_add_inline_style' ) );

$uninstall = (string) file_get_contents( $base . 'uninstall.php' );
check( 'uninstall فایل‌های uploads را پاک می‌کند', false !== strpos( $uninstall, 'chs-snippets' ) );
check( 'uninstall از multisite پشتیبانی می‌کند', false !== strpos( $uninstall, 'switch_to_blog' ) );

echo "\n" . str_repeat( '=', 50 ) . "\n";
echo "نتیجه: $pass موفق، $fail ناموفق\n";
if ( $fail > 0 ) {
    echo "ناموفق‌ها:\n";
    foreach ( $failed_names as $n ) { echo "  - $n\n"; }
}
echo str_repeat( '=', 50 ) . "\n";

exit( $fail > 0 ? 1 : 0 );
