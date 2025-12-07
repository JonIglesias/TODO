<?php
// mobile_patch/mobile_patch.php
// Carga CSS/JS SOLO en móviles (iPhone y Android). Nunca en escritorio ni tablets.
// Maneja iPhone con “Solicitar sitio de escritorio” usando detección híbrida (UA + media queries).

if (!defined('ABSPATH')) exit;


/* ======== ENQUEUE DEL PATCH SOLO EN MÓVIL (FRONT) ======== */
/**
 * Carga mobile_patch.css/js únicamente en teléfonos.
 * Estrategia: no encolamos assets en PHP; inyectamos un loader inline que,
 * si detecta phone, añade <link>/<script> a los assets en runtime.
 */
function phsbot_mobile_patch_enqueue_mobile_only() {
  // No en admin, AJAX o CRON
  if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX) || (defined('DOING_CRON') && DOING_CRON)) {
    return;
  }

  // Rutas/URLs y versión por filemtime
  $css_file = 'mobile_patch.css';
  $js_file  = 'mobile_patch.js';

  $css_path = __DIR__ . '/' . $css_file;
  $js_path  = __DIR__ . '/' . $js_file;

  $css_url  = plugins_url($css_file, __FILE__);
  $js_url   = plugins_url($js_file, __FILE__);

  $css_ver  = file_exists($css_path) ? @filemtime($css_path) : null;
  $js_ver   = file_exists($js_path)  ? @filemtime($js_path)  : null;

  $cfg = array(
    'css'   => $css_url . ($css_ver ? ('?ver=' . $css_ver) : ''),
    'js'    => $js_url  . ($js_ver  ? ('?ver=' . $js_ver)  : ''),
    'debug' => (isset($_GET['phsbot_debug']) && $_GET['phsbot_debug'] === '1'),
  );

  // Loader inline: detecta teléfono (iPhone/Android) y añade assets; excluye tablets y escritorio.
  $loader = '(function(d,w){try{var C=' . wp_json_encode($cfg) . ';
    var ua=(w.navigator.userAgent||"");
    var isIphone=/iPhone/i.test(ua);
    var isAndroidPhone=/Android/i.test(ua)&&/Mobile/i.test(ua);
    var isTabletUA=/iPad|Tablet|Nexus 7|Nexus 9|Nexus 10|SM-T|Kindle|Silk|PlayBook|Tab/i.test(ua);
    var hasCoarse=w.matchMedia&&w.matchMedia("(pointer:coarse)").matches;
    var smallDev=w.matchMedia&&w.matchMedia("(max-device-width: 767px)").matches;
    var isPhone = !isTabletUA && (isIphone || isAndroidPhone || (hasCoarse && smallDev));
    if(C.debug){w.console&&console.log("[PHSBOT] mobile_patch detect",{ua:ua,isIphone:isIphone,isAndroidPhone:isAndroidPhone,isTabletUA:isTabletUA,hasCoarse:hasCoarse,smallDev:smallDev,isPhone:isPhone});}
    if(!isPhone){return;}
    var head=d.head||d.getElementsByTagName("head")[0]||d.documentElement;
    if(C.css){var l=d.createElement("link");l.rel="stylesheet";l.href=C.css;head.appendChild(l);}
    if(C.js){var s=d.createElement("script");s.src=C.js;s.defer=true; (d.body?d.body:head).appendChild(s);}
  }catch(e){}})(document,window);';

  // Imprime el loader al final para no bloquear; inyecta CSS/JS sólo si es teléfono.
  add_action('wp_print_footer_scripts', function() use ($loader) {
    echo "<script>{$loader}</script>";
  }, 99);
} /* ========FIN ENQUEUE DEL PATCH SOLO EN MÓVIL ===== */


// Hook tardío para ir detrás del resto de módulos (la CSS inyectada gana cascada)
add_action('wp_enqueue_scripts', 'phsbot_mobile_patch_enqueue_mobile_only', 99);
