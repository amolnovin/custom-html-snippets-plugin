<?php
require __DIR__ . '/wp-stubs.php';
use AmolNovin\CHS as CHS;
require __DIR__ . '/../custom-html-snippets/custom-html-snippets.php';
do_action('init');

echo "\n===== باگ ۱: kses کد JS را مثله می‌کند =====\n";
// کاربری که unfiltered_html ندارد (مالتی‌سایت یا DISALLOW_UNFILTERED_HTML)
$GLOBALS['__caps'] = array();
$js = 'for (var i=0; i<10; i++) { if (a<b && c>d) total++; }';
$out = CHS\Sanitizer::apply('raw_html', $js);
echo "ورودی : $js\n";
echo "خروجی : $out\n";
echo ( $out === $js ) ? "✅ سالم\n" : "❌ مثله شد (طول ".strlen($js)." → ".strlen($out).")\n";

echo "\n===== باگ ۲: sanitize_callback کد PHP را پاک می‌کند =====\n";
$php = 'echo "salam";';
$res = CHS\Sanitizer::apply('raw_php', $php);
echo "CHS_DISABLE_PHP = ".var_export(CHS_DISABLE_PHP,true)."\n";
echo "ورودی : $php\n";
echo "خروجی : '".$res."'\n";
echo ( $res === $php ) ? "✅ سالم\n" : "❌ کد PHP پاک شد!\n";

echo "\n===== باگ ۳: زمان‌بندی صف‌بندی JS در فوتر =====\n";
// شبیه‌سازی ترتیب واقعی وردپرس: اسکریپت‌های فوتر در اولویت ۲۰ چاپ می‌شوند
$GLOBALS['__did']['wp_print_footer_scripts'] = true; // یعنی گذشته
$GLOBALS['__enqueued'] = array();
$handled = CHS\Assets::enqueue_snippet_js(77, 'console.log(1)');
echo "enqueue_snippet_js برگرداند: ".var_export($handled,true)."\n";
echo ( $handled === true ) ? "❌ ادعا کرد موفق بوده ولی اسکریپت هرگز چاپ نمی‌شود\n" : "✅ به inline برگشت\n";

echo "\n===== باگ ۴: موتور تاخیر خیلی دیر صف می‌شود =====\n";
echo "delay_engine روی wp_footer اولویت 9999 است\n";
echo "ولی wp_print_footer_scripts در اولویت 20 اجرا شده → اسکریپت چاپ نمی‌شود\n";
