<?php
require __DIR__ . '/wp-stubs.php';
use AmolNovin\CHS as CHS;
require __DIR__ . '/../custom-html-snippets/custom-html-snippets.php';
do_action('init');

$schema = CHS\Post_Type::meta_schema();
$js = 'for (var i=0; i<10; i++) { if (a<b && c>d) total++; }';

echo "\n=== قانون هر فیلد ===\n";
foreach (['_chs_js','_chs_css','_chs_html','_chs_php','_chs_header'] as $k) {
    echo str_pad($k,14)." → ".$schema[$k]['sanitize']."\n";
}

echo "\n=== JS با قانون درست (code) ===\n";
$GLOBALS['__caps']=array(); // کاربر بدون unfiltered_html
$out = CHS\Sanitizer::apply($schema['_chs_js']['sanitize'], $js);
echo "خروجی: $out\n";
echo ($out===$js)?"✅ JS دست‌نخورده\n":"❌ هنوز مثله می‌شود\n";

echo "\n=== CSS با عملگرهای مقایسه ===\n";
$css = '@media (max-width: 600px) { .a > .b { width: calc(100% - 10px); } }';
$outc = CHS\Sanitizer::apply($schema['_chs_css']['sanitize'], $css);
echo "خروجی: $outc\n";
echo ($outc===$css)?"✅ CSS دست‌نخورده\n":"❌ CSS خراب شد\n";

echo "\n=== ذخیره: کاربر کم‌دسترسی نباید کد موجود را پاک کند ===\n";
$GLOBALS['__posts'][50]=(object)array('ID'=>50,'post_type'=>'custom_snippet','post_status'=>'publish','post_title'=>'t');
$GLOBALS['__meta'][50]=array('_chs_js'=>'کد ارزشمند قبلی','_chs_header'=>'<meta x>','_chs_php'=>'echo 1;');
$GLOBALS['__caps']=array('edit_post'=>true); // بدون unfiltered_html
$_POST=array('chs_nonce'=>wp_create_nonce('chs_save_code_meta'),'chs_js'=>'','chs_header'=>'','chs_php'=>'');
(new CHS\Metabox())->save(50);
echo "_chs_js بعد از ذخیره   : '".get_post_meta(50,'_chs_js',true)."'\n";
echo "_chs_header بعد از ذخیره: '".get_post_meta(50,'_chs_header',true)."'\n";
echo "_chs_php بعد از ذخیره   : '".get_post_meta(50,'_chs_php',true)."'\n";
$ok = get_post_meta(50,'_chs_js',true)==='کد ارزشمند قبلی' && get_post_meta(50,'_chs_php',true)==='echo 1;';
echo $ok?"✅ کد قبلی حفظ شد\n":"❌ کد قبلی نابود شد\n";

echo "\n=== ذخیره: کاربر مجاز باید بتواند تغییر دهد ===\n";
$GLOBALS['__caps']=array('edit_post'=>true,'unfiltered_html'=>true,'manage_options'=>true);
$_POST=array('chs_nonce'=>wp_create_nonce('chs_save_code_meta'),'chs_js'=>$js);
(new CHS\Metabox())->save(50);
$saved=get_post_meta(50,'_chs_js',true);
echo "ذخیره‌شده: $saved\n";
echo ($saved===$js)?"✅ کد کامل ذخیره شد\n":"❌ ناقص\n";
