<?php
/**
 * تست‌های رگرسیون برای باگ‌های نسخه ۳.۰.۰.
 */
require __DIR__ . '/wp-stubs.php';
use AmolNovin\CHS as CHS;
require __DIR__ . '/../custom-html-snippets/custom-html-snippets.php';
do_action('init');

$pass=0;$fail=0;$bad=array();
function ok($n,$c,$e=''){global $pass,$fail,$bad;if($c){$pass++;echo "  ✅ $n\n";}else{$fail++;$bad[]=$n;echo "  ❌ $n".($e?" -> $e":'')."\n";}}

echo "\n=== باگ ۱: kses نباید JS را مثله کند ===\n";
$schema = CHS\Post_Type::meta_schema();
ok('فیلد JS از قانون code استفاده می‌کند', 'code'===$schema['_chs_js']['sanitize'], $schema['_chs_js']['sanitize']);
$GLOBALS['__caps']=array();
$cases = array(
  'for (var i=0; i<10; i++) { x++; }',
  'if (a < b && c > d) { run(); }',
  'const f = (a,b) => a<b ? a : b;',
  'arr.filter(x => x.n < 5).map(y => y*2)',
  '$("<div>").appendTo(body);',
);
$allok=true;
foreach($cases as $c){ if(CHS\Sanitizer::apply('code',$c)!==$c){$allok=false;echo "     شکست: $c\n";} }
ok('پنج الگوی رایج JS دست‌نخورده ماند', $allok);

echo "\n=== باگ ۲: CSS نباید خراب شود ===\n";
$css='@media (max-width:600px){.a>.b{width:calc(100% - 10px)}}';
ok('CSS با > و % سالم ماند', CHS\Sanitizer::apply('css',$css)===$css);
ok('CSS همچنان </style> را مسدود می‌کند', false===stripos(CHS\Sanitizer::apply('css','a{}</style><script>x</script>'),'</style'));

echo "\n=== باگ ۳: register_post_meta نباید sanitize_callback داشته باشد ===\n";
$rm = $GLOBALS['__registered_meta'];
$has_cb=false;
foreach($rm as $k=>$args){ if(isset($args['sanitize_callback'])){$has_cb=true;echo "     $k دارای کال‌بک\n";} }
ok('هیچ متایی sanitize_callback ندارد', !$has_cb);
ok('auth_callback حفظ شده', isset($rm['_chs_js']['auth_callback']));

echo "\n=== باگ ۴: کد PHP در بافت بدون کاربر پاک نمی‌شود ===\n";
$GLOBALS['__caps']=array();
ok('raw_php کد را برمی‌گرداند', 'echo 1;'===CHS\Sanitizer::apply('raw_php','echo 1;'));

echo "\n=== باگ ۵: ذخیره نباید کد موجود را نابود کند ===\n";
$GLOBALS['__posts'][90]=(object)array('ID'=>90,'post_type'=>'custom_snippet','post_status'=>'publish','post_title'=>'t');
$GLOBALS['__meta'][90]=array('_chs_js'=>'کد مهم','_chs_php'=>'echo 2;','_chs_header'=>'<meta>');
$GLOBALS['__caps']=array('edit_post'=>true);
$_POST=array('chs_nonce'=>wp_create_nonce('chs_save_code_meta'),'chs_js'=>'','chs_php'=>'','chs_header'=>'');
(new CHS\Metabox())->save(90);
ok('JS حفظ شد', 'کد مهم'===get_post_meta(90,'_chs_js',true), get_post_meta(90,'_chs_js',true));
ok('PHP حفظ شد', 'echo 2;'===get_post_meta(90,'_chs_php',true));
ok('هدر حفظ شد', '<meta>'===get_post_meta(90,'_chs_header',true));

echo "\n=== باگ ۶: زمان‌بندی صف‌بندی دارایی‌ها ===\n";
$GLOBALS['__did']=array();
ok('CSS پیش از wp_head صف می‌شود', true===CHS\Assets::enqueue_snippet_css(1,'a{}'));
$GLOBALS['__did']['wp_head']=true;
ok('CSS پس از wp_head رد می‌شود (fallback inline)', false===CHS\Assets::enqueue_snippet_css(2,'b{}'));
$GLOBALS['__did']=array();
ok('JS پیش از چاپ فوتر صف می‌شود', true===CHS\Assets::enqueue_snippet_js(3,'x()'));
$GLOBALS['__did']['wp_print_footer_scripts']=true;
ok('JS پس از چاپ فوتر رد می‌شود', false===CHS\Assets::enqueue_snippet_js(4,'y()'));

echo "\n=== باگ ۷: موتور تاخیر همیشه بارگذاری می‌شود ===\n";
$r=new CHS\Renderer();
$GLOBALS['__posts'][91]=(object)array('ID'=>91,'post_type'=>'custom_snippet','post_status'=>'publish','post_title'=>'d');
$GLOBALS['__meta'][91]=array('_chs_html'=>'X','_chs_js'=>'go()','_chs_active'=>'1','_chs_delay'=>'1');
$GLOBALS['__did']=array(); $GLOBALS['__enqueued']=array();
$r->render(91);
ob_start();$r->delay_engine();$o1=ob_get_clean();
ok('پنجره باز → صف‌بندی استاندارد', in_array('chs-delay-engine',$GLOBALS['__enqueued']['script']??array(),true) && ''===$o1);

$r2=new CHS\Renderer();
$GLOBALS['__enqueued']=array();
$r2->render(91);
$GLOBALS['__did']['wp_print_footer_scripts']=true;
ob_start();$r2->delay_engine();$o2=ob_get_clean();
ok('پنجره بسته → چاپ inline', strlen($o2)>500, strlen($o2).' bytes');

echo "\n=== باگ ۸: write() نباید fatal بدهد ===\n";
$GLOBALS['__did']=array();
$res = CHS\Assets::enqueue_snippet_css(95,'.z{color:red}');
ok('نوشتن فایل بدون WP_Filesystem کار کرد', true===$res);
$up=wp_upload_dir();
$files=glob($up['basedir'].'/chs-snippets/snippet-95.*.css');
ok('فایل واقعاً روی دیسک ساخته شد', !empty($files), json_encode($files));
if(!empty($files)){ ok('محتوای فایل درست است', '.z{color:red}'===file_get_contents($files[0])); }
ok('فایل موقت باقی نماند', empty(glob($up['basedir'].'/chs-snippets/*.tmp*')));

echo "\n=== ابزار بازیابی ===\n";
ok('کلاس Repair وجود دارد', class_exists('AmolNovin\CHS\Repair'));
ok('سرویس repair ثبت شد', CHS\plugin()->get('repair') instanceof CHS\Repair);

echo "\n".str_repeat('=',46)."\nرگرسیون: $pass موفق، $fail ناموفق\n";
foreach($bad as $b) echo "  - $b\n";
echo str_repeat('=',46)."\n";
exit($fail>0?1:0);
