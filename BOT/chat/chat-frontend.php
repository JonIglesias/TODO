<?php
// PHSBOT – chat/chat-frontend.php
// Frontend: móvil patch, enqueue, markup FLOAT, shortcode y reset cliente.
if (!defined('ABSPATH')) exit;

/* Parche móvil sólo frontend */
add_action('init', function () {
    if (is_admin()) return;
    if (function_exists('wp_is_mobile') && !wp_is_mobile()) return;
    $patch = PHSBOT_DIR . 'mobile-patch.php';
    if (file_exists($patch)) require_once $patch;
}, 5);

/* Enqueue front (SOLO FLOAT) */
add_action('wp_enqueue_scripts', function(){
  if (!phsbot_setting('chat_active', 1)) return;

  wp_register_style('phsbot-chat', plugins_url('chat.css', __FILE__), array(), '1.6', 'all');
  wp_register_script('phsbot-chat', plugins_url('chat.js', __FILE__), array(), '1.6', true);

  wp_enqueue_style('phsbot-chat');
  wp_enqueue_script('phsbot-chat');

  // Detecta idioma de la PÁGINA (WPML/Polylang) con fallback a locale
  $page_lang = '';
  if (defined('ICL_LANGUAGE_CODE')) { $page_lang = (string) ICL_LANGUAGE_CODE; }
  if (!$page_lang && function_exists('apply_filters')) {
    $tmp = apply_filters('wpml_current_language', null);
    if (is_string($tmp) && $tmp!=='') $page_lang = $tmp;
  }
  if (!$page_lang && function_exists('pll_current_language')) {
    $tmp = pll_current_language('slug');
    if (is_string($tmp) && $tmp!=='') $page_lang = $tmp;
  }
  if (!$page_lang) { $page_lang = substr(get_locale(),0,2); }
  $lang = strtolower(substr((string)$page_lang,0,2));
  if (function_exists('phsbot_site_language')) { $lang = phsbot_site_language(); }

  $UI = array(
    'send'   => $lang==='en' ? 'Send' : 'Enviar',
    'ph'     => $lang==='en' ? 'Type your question...' : 'Escribe tu pregunta...',
    'typing' => $lang==='en' ? 'Typing…' : 'Escribiendo…',
  );

  $chatopt       = get_option(PHSBOT_CHAT_OPT, array());
  $welcome_raw   = (string) phsbot_chat_opt(array('welcome','saludo','mensaje_inicial','greeting'), '');

  $welcome_hash_opt = isset($chatopt['welcome_hash']) ? (string)$chatopt['welcome_hash'] : '';
  $welcome_hash_now = md5(wp_strip_all_tags($welcome_raw));

  if (function_exists('phsbot_chat_translate_welcome_runtime')) {
    $welcome_pick = phsbot_chat_translate_welcome_runtime($lang);
  } else {
    $welcome_i18n  = (array) (isset($chatopt['welcome_i18n']) && is_array($chatopt['welcome_i18n']) ? $chatopt['welcome_i18n'] : array());
    if ($welcome_hash_opt !== $welcome_hash_now) {
      $welcome_i18n = array();
    }
    $welcome_pick  = isset($welcome_i18n[$lang]) && trim((string)$welcome_i18n[$lang])!=='' ? $welcome_i18n[$lang] : $welcome_raw;
  }

  $welcome_pick = wp_kses_post($welcome_pick);

  $payload = array(
    'ajaxUrl'      => admin_url('admin-ajax.php'),
    'nonce'        => wp_create_nonce('phsbot_chat'),
    'allowHTML'    => (int) !!phsbot_chat_opt(array('allow_html')),
    'allowElem'    => (int) !!phsbot_chat_opt(array('allow_elementor')),
    'maxVH'        => intval(phsbot_chat_opt(array('max_height_vh'), 70)),
    'welcome'      => $welcome_pick,
    'welcome_i18n' => (array) ($chatopt['welcome_i18n'] ?? array()),
    'maxHistory'   => intval(phsbot_chat_opt(array('max_history'), 10)),
    'anchorPara'   => (int) !!phsbot_chat_opt(array('anchor_paragraph'), 1),
  );

  // Estilos críticos + variables CSS desde settings
  $pos = (string) phsbot_setting('chat_position', 'bottom-right');
  $pos_x = (strpos($pos,'left')!==false) ? 'left' : 'right';
  $pos_y = (strpos($pos,'top')!==false) ? 'top'  : 'bottom';

  $w     = (string) phsbot_setting('chat_width',  '360px');
  $h     = (string) phsbot_setting('chat_height', '720px');

  $c1  = (string) phsbot_setting('color_primary',    '#1e1e1e');
  $c2  = (string) phsbot_setting('color_secondary',  '#dbdbdb');
  $cbg = (string) phsbot_setting('color_background', '#e8e8e8');
  $ctx = (string) phsbot_setting('color_text',       '#000000');
  $cb  = (string) phsbot_setting('color_bot_bubble', '#f3f3f3');
  $cu  = (string) phsbot_setting('color_user_bubble','#ffffff');
  $cfoot = (string) phsbot_setting('color_footer', '#1e1e1e');

  wp_localize_script('phsbot-chat','PHSBOT_CHAT',$payload);
  wp_localize_script('phsbot-chat','PHSBOT_CHAT_UI',$UI);

  ?>
  <style id="phsbot-chat-critical">
    #phsbot-widget{
      --phsbot-primary: <?php echo esc_html($c1); ?>;
      --phsbot-secondary: <?php echo esc_html($c2); ?>;
      --phsbot-bg: <?php echo esc_html($cbg); ?>;
      --phsbot-text: <?php echo esc_html($ctx); ?>;
      --phsbot-bot-bubble: <?php echo esc_html($cb); ?>;
      --phsbot-user-bubble: <?php echo esc_html($cu); ?>;
      --phsbot-width: <?php echo esc_html($w); ?>;
      --phsbot-height: <?php echo esc_html($h); ?>;
      --phsbot-btn-h:    <?php echo (int) phsbot_setting('btn_height', 44); ?>px;
      --phsbot-head-btn: <?php echo (int) phsbot_setting('head_btn_size', 26); ?>px;
      --mic-stroke-w:    <?php echo (int) phsbot_setting('mic_stroke_w', 1); ?>px;
      --phsbot-footer: <?php echo esc_html($cfoot); ?>;
    }
    .phsbot-wrap{
      position: fixed !important;
      <?php echo $pos_x; ?>:18px; <?php echo $pos_y; ?>:88px;
      z-index: 2147483646 !important;
      width: var(--phsbot-width);
      max-width: min(92vw, 920px);
      transform: none !important; isolation: isolate;
    }
    .phsbot-launcher{
      <?php echo $pos_x; ?>:18px !important; <?php echo $pos_y; ?>:18px !important;
    }
  </style>
  <?php
}, 6);

/* Render FLOAT */
if (!function_exists('phsbot_render_chat_markup')) {
function phsbot_render_chat_markup(){
  if (!phsbot_setting('chat_active', 1)) return;
  if (!empty($GLOBALS['__phsbot_chat_rendered'])) return;
  $GLOBALS['__phsbot_chat_rendered'] = true;
  include __DIR__ . '/chat-view.php';
}
}

/* Pintar en footer (FLOAT) */
add_action('wp_footer', function(){
  if (is_admin()) return;
  if (!(int) phsbot_setting('chat_active', 1)) return;
  if (!empty($GLOBALS['__phsbot_chat_rendered'])) return;
  phsbot_render_chat_markup();
}, 98);

/* Shortcode: [phsbot] -> FLOAT */
add_shortcode('phsbot', function($atts){
  ob_start();
  phsbot_render_chat_markup();
  return ob_get_clean();
});

/* Reset cliente por versión */
add_action('wp_footer', function(){
  if (!phsbot_setting('chat_active', 1)) return; ?>
  <script>
  (function(){
    var KEY = 'phsbot_client_reset_version';
    try{
      var server = parseInt(<?php echo json_encode((int) get_option('phsbot_client_reset_version', 0)); ?>, 10) || 0;
      var local  = parseInt(localStorage.getItem(KEY)||'0',10)||0;
      if (server && server !== local){
        try{ localStorage.removeItem('phsbot:cid'); }catch(e){}
        try{ localStorage.removeItem('phsbot:ui'); }catch(e){}
        try{ localStorage.removeItem('phsbot:conv'); }catch(e){}
        sessionStorage.removeItem('phsbot:cid');
        sessionStorage.removeItem('phsbot:ui');
        sessionStorage.removeItem('phsbot:conv');
        if (window.indexedDB){ try{ indexedDB.deleteDatabase('phsbot-db'); }catch(e){} }
        document.cookie.split(';').forEach(function(c){
          var name = c.split('=')[0].trim();
          if (/phs|phsbot|chat/i.test(name)){
            document.cookie = name+'=; Max-Age=0; path=/';
          }
        });
        localStorage.setItem(KEY, String(server));
      }
    }catch(e){}
  })();
  </script>
<?php
}, 1);
