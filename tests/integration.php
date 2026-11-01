<?php
/**
 * تست یکپارچه: شبیه‌سازی یک بارگذاری کامل صفحه.
 */
require __DIR__ . '/wp-stubs.php';
use AmolNovin\CHS as CHS;

$pass=0;$fail=0;
function ok($n,$c,$e=''){global $pass,$fail;if($c){$pass++;echo "  ✅ $n\n";}else{$fail++;echo "  ❌ $n".($e?" -> $e":'')."\n";}}

require __DIR__ . '/../custom-html-snippets/custom-html-snippets.php';
do_action('init');

$renderer = CHS\plugin()->get('renderer');

echo "\n=== تزریق خودکار در محتوا (include list) ===\n";
$GLOBALS['__posts'][10] = (object)array('ID'=>10,'post_type'=>'custom_snippet','post_status'=>'publish','post_title'=>'بنر');
$GLOBALS['__meta'][10] = array('_chs_html'=>'<div class="banner">تخفیف</div>','_chs_active'=>'1','_chs_include_pages'=>array(55));
$GLOBALS['__queried_id']=55;
$GLOBALS['__wp_query_result']=array(10);
delete_transient(CHS\Cache::KEY_ACTIVE);

$out = $renderer->auto_inject('محتوای اصلی صفحه');
ok('محتوای اصلی حفظ شد', false!==strpos($out,'محتوای اصلی صفحه'));
ok('قطعه کد به انتها اضافه شد', false!==strpos($out,'تخفیف'));

echo "\n=== جایگزینی کامل محتوا ===\n";
$GLOBALS['__meta'][10]['_chs_replace_content']='1';
$out2 = $renderer->auto_inject('محتوای اصلی صفحه');
ok('محتوای اصلی حذف شد', false===strpos($out2,'محتوای اصلی صفحه'), $out2);
ok('فقط قطعه کد ماند', false!==strpos($out2,'تخفیف'));

echo "\n=== صفحه‌ای که در include نیست ===\n";
$GLOBALS['__queried_id']=999;
$out3 = $renderer->auto_inject('صفحه دیگر');
ok('چیزی تزریق نشد', 'صفحه دیگر'===$out3, $out3);

echo "\n=== شرط نمایش مانع تزریق می‌شود ===\n";
$GLOBALS['__queried_id']=55;
$GLOBALS['__meta'][10]['_chs_replace_content']='0';
$GLOBALS['__meta'][10]['_chs_conditions']=array(array('type'=>'device','operator'=>'is','value'=>'mobile'));
$GLOBALS['__mobile']=false;
$out4 = $renderer->auto_inject('متن');
ok('شرط رد شده → تزریق نشد', false===strpos($out4,'تخفیف'), $out4);
$GLOBALS['__mobile']=true;
$out5 = $renderer->auto_inject('متن');
ok('شرط برقرار → تزریق شد', false!==strpos($out5,'تخفیف'));

echo "\n=== هدر و فوتر سراسری ===\n";
$GLOBALS['__meta'][20]=array('_chs_header'=>'<meta name="x" content="1">','_chs_footer'=>'<!--بای-->','_chs_active'=>'1','_chs_scope'=>'global');
$GLOBALS['__posts'][20]=(object)array('ID'=>20,'post_type'=>'custom_snippet','post_status'=>'publish','post_title'=>'h');
$GLOBALS['__wp_query_result']=array(20);
delete_transient(CHS\Cache::KEY_ACTIVE);
$GLOBALS['__mobile']=false;

ob_start(); $renderer->output_header(); $head=ob_get_clean();
ok('کد هدر چاپ شد', false!==strpos($head,'meta name="x"'), $head);

ob_start(); $renderer->output_footer(); $foot=ob_get_clean();
ok('کد فوتر چاپ شد', false!==strpos($foot,'بای'), $foot);

echo "\n=== محدوده هوشمند (page scope) ===\n";
$GLOBALS['__meta'][20]['_chs_scope']='page';
$GLOBALS['__current_post']=(object)array('ID'=>77,'post_content'=>'بدون شورت‌کد','post_type'=>'page','post_status'=>'publish','post_title'=>'p');
ob_start(); $renderer->output_header(); $h2=ob_get_clean();
ok('بدون شورت‌کد → هدر چاپ نشد', false===strpos($h2,'meta name="x"'), $h2);

$GLOBALS['__current_post']->post_content='قبل [my_snippet id="20"] بعد';
ob_start(); $renderer->output_header(); $h3=ob_get_clean();
ok('با شورت‌کد → هدر چاپ شد', false!==strpos($h3,'meta name="x"'), $h3);

echo "\n=== صفحه استثنا هدر را هم متوقف می‌کند ===\n";
$GLOBALS['__meta'][20]['_chs_scope']='global';
$GLOBALS['__meta'][20]['_chs_exclude_pages']=array(55);
$GLOBALS['__queried_id']=55;
ob_start(); $renderer->output_header(); $h4=ob_get_clean();
ok('صفحه استثنا → هدر چاپ نشد', false===strpos($h4,'meta name="x"'), $h4);

echo "\n".str_repeat('=',46)."\nنتیجه یکپارچه: $pass موفق، $fail ناموفق\n".str_repeat('=',46)."\n";
exit($fail>0?1:0);
