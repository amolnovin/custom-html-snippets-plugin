<?php
require __DIR__ . '/wp-stubs.php';
// جلوگیری از فراخوانی WP_Filesystem واقعی
function WP_Filesystem(){ return false; }
use AmolNovin\CHS as CHS;
require __DIR__ . '/../custom-html-snippets/custom-html-snippets.php';
do_action('init');

echo "\n===== ترتیب واقعی هوک‌های وردپرس =====\n";
echo "wp_head:   wp_print_styles اولویت 8  |  Renderer::output_header اولویت 99\n";
echo "wp_footer: wp_print_footer_scripts اولویت 20 | output_footer 99 | delay_engine 9999\n";
echo "→ یعنی هر wp_enqueue_style/script بعد از این نقاط، هرگز چاپ نمی‌شود.\n";

echo "\n===== باگ ۳: JS سراسری در فوتر گم می‌شود =====\n";
$GLOBALS['__did']['wp_print_footer_scripts']=true;
$GLOBALS['__did']['wp_print_scripts']=true;
$GLOBALS['__enqueued']=array();
$h = CHS\Assets::enqueue_snippet_js(77,'console.log("hi")');
echo "enqueue_snippet_js → ".var_export($h,true)."\n";
echo ($h===true) ? "❌ گفت موفق، ولی چون فوتر چاپ شده اسکریپت گم می‌شود\n" : "✅ به inline برگشت\n";

echo "\n===== باگ ۴: موتور تاخیر هرگز بارگذاری نمی‌شود =====\n";
$GLOBALS['__enqueued']=array();
$r = CHS\plugin()->get('renderer');
$GLOBALS['__posts'][5]=(object)array('ID'=>5,'post_type'=>'custom_snippet','post_status'=>'publish','post_title'=>'x');
$GLOBALS['__meta'][5]=array('_chs_html'=>'X','_chs_js'=>'run()','_chs_active'=>'1','_chs_delay'=>'1');
$out=$r->render(5);
echo "خروجی شامل template: ".(false!==strpos($out,'chs-delay-run')?'بله':'خیر')."\n";
$r->delay_engine();
$scripts = $GLOBALS['__enqueued']['script'] ?? array();
echo "اسکریپت‌های صف‌شده: ".json_encode($scripts)."\n";
echo "→ delay_engine روی wp_footer 9999 است، ولی اسکریپت‌های فوتر در اولویت 20 چاپ شده‌اند.\n";
echo "❌ نتیجه: قطعه‌کد تاخیری هرگز اجرا نمی‌شود (CSS/JS داخل template می‌ماند)\n";
