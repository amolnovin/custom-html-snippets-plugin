<?php
require __DIR__ . '/wp-stubs.php';
use AmolNovin\CHS as CHS;
require __DIR__ . '/../custom-html-snippets/custom-html-snippets.php';
do_action('init');

$r = CHS\plugin()->get('renderer');
$GLOBALS['__posts'][5]=(object)array('ID'=>5,'post_type'=>'custom_snippet','post_status'=>'publish','post_title'=>'x');
$GLOBALS['__meta'][5]=array('_chs_html'=>'X','_chs_js'=>'run()','_chs_active'=>'1','_chs_delay'=>'1');

echo "\n=== سناریو: شورت‌کد وسط محتوا، اسکریپت فوتر قبلاً چاپ شده ===\n";
$out=$r->render(5);
echo "template تولید شد: ".(false!==strpos($out,'chs-delay-run')?'بله':'خیر')."\n";
$GLOBALS['__did']['wp_print_footer_scripts']=true;
$GLOBALS['__enqueued']=array();
ob_start(); $r->delay_engine(); $printed=ob_get_clean();
echo "طول اسکریپت inline چاپ‌شده: ".strlen($printed)." بایت\n";
echo (strlen($printed)>500)?"✅ موتور تاخیر به‌صورت inline چاپ شد → کد اجرا می‌شود\n":"❌ موتور چاپ نشد\n";

echo "\n=== سناریو: پنجره صف‌بندی هنوز باز است ===\n";
$r2 = new CHS\Renderer();
$GLOBALS['__did']=array();
$GLOBALS['__enqueued']=array();
$r2->render(5);
ob_start(); $r2->delay_engine(); $p2=ob_get_clean();
$sc=$GLOBALS['__enqueued']['script']??array();
echo "صف‌شده: ".json_encode($sc)." | inline: ".strlen($p2)." بایت\n";
echo (in_array('chs-delay-engine',$sc,true) && strlen($p2)===0)?"✅ از راه استاندارد صف شد (بدون تکرار)\n":"❌ اشتباه\n";
