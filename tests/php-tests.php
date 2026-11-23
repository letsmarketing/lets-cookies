<?php
// Simula o mínimo do WordPress para testar as classes isoladas.
define('ABSPATH', '/tmp/'); define('DAY_IN_SECONDS', 86400); define('HOUR_IN_SECONDS', 3600);
$GLOBALS['opts'] = array(); $GLOBALS['transients'] = array();
function get_option($k,$d=false){return $GLOBALS['opts'][$k] ?? $d;}
function update_option($k,$v){$GLOBALS['opts'][$k]=$v;return true;}
function apply_filters($h,$v){return $v;}
$GLOBALS['lc_hooks']=array(); function add_filter($h=''){ $GLOBALS['lc_hooks'][]=$h; } function add_action(){} function add_shortcode(){}
function is_admin(){return false;} function is_feed(){return false;}
function wp_parse_url($u,$c=-1){return parse_url($u,$c);}
function esc_attr($s){return htmlspecialchars((string)$s,ENT_QUOTES);} function esc_html($s){return htmlspecialchars((string)$s,ENT_QUOTES);}
function esc_html__($s){return esc_html($s);} function esc_url($s){return htmlspecialchars((string)$s,ENT_QUOTES);} function esc_url_raw($s){return $s;}
function sanitize_key($s){return preg_replace('/[^a-z0-9_\-]/','',strtolower($s));}
function sanitize_text_field($s){return trim(strip_tags((string)$s));} function sanitize_textarea_field($s){return trim(strip_tags((string)$s));}
function sanitize_hex_color($c){return preg_match('/^#[0-9a-f]{6}$/i',$c)?$c:null;}
function absint($v){return abs((int)$v);} function wp_salt($s){return 'salt-test';}
function get_transient($k){return $GLOBALS['transients'][$k] ?? false;} function set_transient($k,$v){$GLOBALS['transients'][$k]=$v;}
function is_wp_error($e){return $e instanceof WP_Error;}
class WP_Error{public $code;public function __construct($c,$m='',$d=array()){$this->code=$c;}}
function wp_parse_args($a,$d){return array_merge($d,$a);}
require dirname(__DIR__) . '/includes/class-settings.php';
require dirname(__DIR__) . '/includes/class-catalog.php';
require dirname(__DIR__) . '/includes/class-consent-log.php';
require dirname(__DIR__) . '/includes/class-blocker.php';
require dirname(__DIR__) . '/includes/class-frontend.php';
require dirname(__DIR__) . '/includes/functions.php';

$fail=0; function ok($c,$m){global $fail; echo ($c?'  ok   ':'  FALHA ').$m."\n"; if(!$c)$fail++;}

echo "Bloqueio de iframes\n";
$html = '<p>x</p><iframe width="560" height="315" src="https://www.youtube.com/embed/abc123XYZ?feature=oembed" title="Video" allowfullscreen></iframe>'
      . '<iframe src="https://open.spotify.com/embed/episode/1p" width="100%" height="352"></iframe>'
      . '<iframe src="https://www.google.com/maps/embed?pb=1" width="600" height="450"></iframe>'
      . '<iframe src="https://www.google.com/recaptcha/api2/anchor"></iframe>'
      . '<iframe src="https://example.com/x"></iframe>';
$out = Lets_Cookies_Blocker::filter_content($html);
ok(substr_count($out,'class="lets-ck-embed"')===3, '3 iframes conhecidos viram aviso (YouTube, Spotify, Maps)');
ok(strpos($out,'youtube-nocookie.com/embed/abc123XYZ')!==false, 'YouTube trocado por youtube-nocookie');
ok(!preg_match('#<iframe[^>]+\ssrc="https://www\.youtube#',$out), 'nenhum iframe do YouTube com src ativo');
ok(strpos($out,'src="https://example.com/x"')!==false, 'iframe desconhecido intacto');
ok(strpos($out,'recaptcha')!==false && substr_count($out,'lets-ck-embed"')===3, 'google.com que não é mapa não é bloqueado como Maps');
ok(strpos($out,'height:352px')!==false, 'Spotify com largura % usa altura fixa');
ok(strpos($out,'padding-top:56.25%')!==false, 'YouTube mantém proporção 16:9');
ok(strpos($out,'https://www.youtube.com/watch?v=abc123XYZ')!==false, 'link "Abrir no YouTube" correto');
ok(Lets_Cookies_Blocker::filter_content($out)===$out, 'filtro é idempotente (não bloqueia duas vezes)');
$xss = Lets_Cookies_Blocker::filter_content('<iframe src="https://www.youtube.com/embed/a&quot; onload=&quot;alert(1)" title="x"></iframe>');
ok(strpos($xss,'onload="alert')===false, 'atributo injetado na URL não vira atributo');

echo "Scripts bloqueados\n";
$s = Lets_Cookies_Blocker::script('analytics','gtag(1)','https://www.googletagmanager.com/gtag/js?id=G-ABC','ga4');
ok(strpos($s,'type="text/plain"')!==false && strpos($s,'data-lets-category="analytics"')!==false, 'script sai como text/plain com categoria');
ok(lets_cookies_script('outra','x')==='', 'categoria inválida não gera script');

echo "Validação do registro\n";
$GLOBALS['opts']['lets_cookies_settings'] = array('enabled_categories'=>array('analytics','embedded'),'policy_version'=>2);
$good = array('consent_id'=>'3f2a9c1e-5b7d-4e8a-9c21-7d4e5f6a8b90','action'=>'custom','version'=>2,'choices'=>array('analytics'=>true,'embedded'=>false,'marketing'=>true),'page'=>'/contato/?email=a@b.com');
$v = Lets_Cookies_Consent_Log::validate($good);
ok(is_array($v), 'corpo válido aceito');
ok(is_array($v) && $v['choices']['marketing']===false, 'categoria desligada no site nunca registrada como aceita');
ok(is_array($v) && $v['page']==='/contato/', 'query string removida da página (sem dados pessoais)');
ok(is_wp_error(Lets_Cookies_Consent_Log::validate(array_merge($good,array('consent_id'=>'abc')))), 'ID fora do formato UUID v4 recusado');
ok(is_wp_error(Lets_Cookies_Consent_Log::validate(array_merge($good,array('action'=>'hack')))), 'ação desconhecida recusada');
ok(is_wp_error(Lets_Cookies_Consent_Log::validate(array_merge($good,array('version'=>3)))), 'versão maior que a atual recusada');
ok(is_wp_error(Lets_Cookies_Consent_Log::validate(array_merge($good,array('choices'=>array('analytics'=>'yes'))))), 'escolha não booleana recusada');
ok(is_wp_error(Lets_Cookies_Consent_Log::validate(array_merge($good,array('action'=>'reject_all')))), 'rejeitar com categoria aceita recusado (incoerente)');
ok(is_wp_error(Lets_Cookies_Consent_Log::validate(array_merge($good,array('choices'=>array('a'=>false,'b'=>false,'c'=>false,'d'=>false))))), 'categorias a mais recusadas');

$inj = Lets_Cookies_Consent_Log::validate(array_merge($good,array('page'=>'=HYPERLINK("http://mal.example","x")')));
ok(is_array($inj) && $inj['page']==='/', 'texto de fórmula no campo página vira "/"');
$inj2 = Lets_Cookies_Consent_Log::validate(array_merge($good,array('page'=>'/a b<script>')));
ok(is_array($inj2) && $inj2['page']==='/', 'caminho com caracteres estranhos vira "/"');
ok(Lets_Cookies_Consent_Log::csv_safe('=1+1')==="'=1+1" && Lets_Cookies_Consent_Log::csv_safe('-2')==="'-2" && Lets_Cookies_Consent_Log::csv_safe('/contato/')==='/contato/', 'exportação CSV neutraliza células que viram fórmula');

echo "Hash do IP\n";
$h1 = Lets_Cookies_Consent_Log::ip_hash('200.150.10.25'); $h2 = Lets_Cookies_Consent_Log::ip_hash('200.150.10.99');
ok($h1===$h2 && strlen($h1)===64, 'IPv4 truncado em /24 antes do hash');
ok(strpos($h1,'200.150')===false, 'IP não aparece no hash');
ok(Lets_Cookies_Consent_Log::ip_hash('2804:14c:1:2:3:4:5:6')===Lets_Cookies_Consent_Log::ip_hash('2804:14c:1:ffff::1'), 'IPv6 truncado em /48');

echo "Limite anti-abuso\n";
$GLOBALS['transients']=array(); $ip=Lets_Cookies_Consent_Log::ip_hash('10.0.0.1'); $okc=0;
for($i=0;$i<12;$i++){ if(Lets_Cookies_Consent_Log::within_rate_limit($ip,'id-a')) $okc++; }
ok($okc===10, 'mesmo visitante limitado a 10 registros por janela');
$GLOBALS['transients']=array(); $okc=0;
for($i=0;$i<250;$i++){ if(Lets_Cookies_Consent_Log::within_rate_limit($ip,'id-'.$i)) $okc++; }
ok($okc===250, '250 visitantes diferentes na mesma rede são todos registrados');
for($i=250;$i<400;$i++){ Lets_Cookies_Consent_Log::within_rate_limit($ip,'id-'.$i); }
ok(!Lets_Cookies_Consent_Log::within_rate_limit($ip,'novo'), 'rede que gera IDs aleatórios em massa é barrada');

echo "Configurações\n";
$san = Lets_Cookies_Settings::sanitize(array('enabled_categories'=>array('analytics','root'),'integrations'=>array('ga4'=>'g-abc1234','clarity'=>'x"><script>','meta'=>'123456789'),'color_primary'=>'red','policy_version'=>'0'));
ok($san['enabled_categories']===array('analytics'), 'categoria inexistente descartada');
ok($san['integrations']['ga4']==='G-ABC1234', 'ID do GA normalizado');
ok($san['integrations']['clarity']==='', 'ID malicioso do Clarity descartado');
ok($san['color_primary']==='#55693c', 'cor inválida volta ao padrão');
ok($san['policy_version']===1, 'versão mínima 1');

echo "Campos do tema (ACF)\n";
$GLOBALS['lc_hooks']=array(); Lets_Cookies_Blocker::init();
ok(in_array('acf/format_value/type=oembed',$GLOBALS['lc_hooks'],true), 'campo oEmbed do ACF passa pelo bloqueio');
ok(in_array('acf/format_value/type=wysiwyg',$GLOBALS['lc_hooks'],true), 'campo de texto rico do ACF passa pelo bloqueio');
$acf=Lets_Cookies_Blocker::filter_content('<iframe title="v" width="640" height="360" src="https://www.youtube.com/embed/MotBO_RzZmg?feature=oembed&controls=1" frameborder="0"></iframe>');
ok(strpos($acf,'lets-ck-embed')!==false && strpos($acf,' src="https://www.youtube')===false, 'vídeo do campo do tema fica bloqueado, sem src ativo');

echo $fail ? "\n$fail FALHA(S)\n" : "\nTodos os testes passaram\n";
exit($fail?1:0);
