<?php
// PHSBOT – chat/chat-core.php
// Núcleo: constantes, helpers, i18n, OpenAI (Chat/Responses) y AJAX handler.
if (!defined('ABSPATH')) exit;

/* ===== Constantes + helpers ===== */
if (!defined('PHSBOT_CHAT_OPT'))   define('PHSBOT_CHAT_OPT',   'phsbot_chat_settings');
if (!defined('PHSBOT_CHAT_GROUP')) define('PHSBOT_CHAT_GROUP', 'phsbot_chat_group');
if (!defined('PHSBOT_KB_DOC_OPT')) define('PHSBOT_KB_DOC_OPT', 'phsbot_kb_document');

/* ===== LENGUAJE: detector externo ===== */
require_once PHSBOT_DIR . 'lang/lang.php';
/* ===== FIN LENGUAJE ===== */

/* -- Devuelve un setting del core con fallback -- */
if (!function_exists('phsbot_setting')) {
  function phsbot_setting($key, $default=null){
    $opt = get_option('phsbot_settings', array());
    return (is_array($opt) && array_key_exists($key,$opt)) ? $opt[$key] : $default;
  }
}

/* -- Defaults del chat -- */
function phsbot_chat_defaults(){
  return array(
    'model'            => 'gpt-4.1-mini',
    'temperature'      => 0.5,
    'tone'             => 'profesional',
    'welcome'          => 'Hola, soy PHSBot. ¿En qué puedo ayudarte?',
    'allow_html'       => 1,
    'allow_elementor'  => 1,
    'allow_live_fetch' => 1,
    'max_history'      => 10,
    'max_tokens'       => 1400,
    'max_height_vh'    => 70,
    'anchor_paragraph' => 1,
  );
}

/* -- Genera y cachea traducciones del saludo (welcome_i18n) -- */
if (!function_exists('phsbot_chat_build_welcome_i18n')) {
function phsbot_chat_build_welcome_i18n($text){
  $text = trim(wp_strip_all_tags((string)$text));
  if ($text === '') return array();
  $langs = apply_filters('phsbot_chat_welcome_langs', array('es','en','fr','de','it','pt'));
  $base  = substr(get_locale(),0,2); if (!$base) $base = 'es';
  $out   = array($base => $text);

  // Obtener licencia y API URL (ya NO usamos openai_api_key del cliente)
  $bot_license = (string) phsbot_setting('bot_license_key', '');
  $bot_api_url = (string) phsbot_setting('bot_api_url', 'https://bocetosmarketing.com/api_claude_5/index.php');

  if (!$bot_license) return $out;

  $domain = parse_url(home_url(), PHP_URL_HOST);

  // Llamar a API5 para traducir (la API usa su propia API key de OpenAI internamente)
  $api_endpoint = trailingslashit($bot_api_url) . '?route=bot/translate-welcome';

  $payload = array(
    'license_key' => $bot_license,
    'domain' => $domain,
    'text' => $text,
    'languages' => $langs
  );

  $res = wp_remote_post($api_endpoint, array(
    'timeout' => 30,
    'headers' => array('Content-Type' => 'application/json'),
    'body' => wp_json_encode($payload),
  ));

  if (is_wp_error($res)) return $out;
  $code = wp_remote_retrieve_response_code($res);
  if ($code !== 200) return $out;

  $body = json_decode(wp_remote_retrieve_body($res), true);
  if (!$body || !isset($body['success']) || !$body['success']) return $out;

  $translations = $body['data']['translations'] ?? array();
  if (!is_array($translations)) return $out;

  // Merge translations
  foreach ($translations as $k=>$v){
    $k2  = strtolower(substr((string)$k,0,2));
    $val = trim(wp_strip_all_tags((string)$v));
    if ($k2 && $val!=='') $out[$k2] = $val;
  }

  return $out;
}
}

/* -- Traducción runtime del saludo con hash anti-stale -- */
if (!function_exists('phsbot_chat_translate_welcome_runtime')) {
function phsbot_chat_translate_welcome_runtime($lang){
  $lang = strtolower(substr((string)$lang, 0, 2));
  $opt  = phsbot_chat_get_settings();
  $base = trim((string)($opt['welcome'] ?? ''));
  $map  = (array)($opt['welcome_i18n'] ?? array());
  if ($lang === '') return $base;
  if ($base === '') return '';

  $hash_current = md5(wp_strip_all_tags($base));
  $hash_stored  = isset($opt['welcome_hash']) ? (string)$opt['welcome_hash'] : '';
  if ($hash_stored !== $hash_current) {
    $map = array();
  }

  if (!empty($map[$lang])) return (string)$map[$lang];

  // Obtener configuración de la API5
  $bot_license = (string) phsbot_setting('bot_license_key', '');
  $bot_api_url = (string) phsbot_setting('bot_api_url', 'https://bocetosmarketing.com/api_claude_5/index.php');

  if (!$bot_license) return $base;

  $domain = parse_url(home_url(), PHP_URL_HOST);

  // Llamar al endpoint de traducción de API5
  $api_endpoint = trailingslashit($bot_api_url) . '?route=bot/translate-welcome';

  $api_payload = array(
    'license_key' => $bot_license,
    'domain' => $domain,
    'text' => $base,
    'languages' => array('es', 'en', 'fr', 'de', 'it', 'pt', 'ca', 'eu', 'gl')
  );

  $res = wp_remote_post($api_endpoint, array(
    'timeout' => 30,
    'headers' => array('Content-Type' => 'application/json'),
    'body' => wp_json_encode($api_payload),
  ));

  if (is_wp_error($res)) return $base;

  $code = wp_remote_retrieve_response_code($res);
  if ($code !== 200) return $base;

  $body = json_decode(wp_remote_retrieve_body($res), true);

  if (!is_array($body) || !isset($body['success']) || !$body['success']) {
    return $base;
  }

  $translations = $body['data']['translations'] ?? array();

  if (!is_array($translations) || empty($translations)) {
    return $base;
  }

  // Guardar TODAS las traducciones recibidas (más eficiente que traducir una por una)
  foreach ($translations as $lang_code => $translation) {
    if (trim($translation) !== '') {
      $map[$lang_code] = trim(wp_strip_all_tags((string)$translation));
    }
  }

  $opt['welcome_i18n'] = $map;
  $opt['welcome_hash'] = $hash_current;
  update_option(PHSBOT_CHAT_OPT, $opt);

  // Devolver la traducción del idioma solicitado (o base si no existe)
  return isset($map[$lang]) ? (string)$map[$lang] : $base;
}
}

/* -- ¿Usa API /responses? (GPT-5*) -- */
function phsbot_model_uses_responses_api($model){
  return (bool) preg_match('/^gpt-?5/i', (string)$model);
}

/* -- Convierte messages[] a input[] (Responses) -- */
function phsbot_messages_to_responses_input($messages){
  $out = array();
  foreach ((array)$messages as $m){
    $role = isset($m['role']) && in_array($m['role'], array('system','user','assistant'), true) ? $m['role'] : 'user';
    $text = (string)($m['content'] ?? '');
    $out[] = array(
      'role'    => $role,
      'content' => array(array('type'=>'text', 'text'=>$text)),
    );
  }
  return $out;
}

/* -- Lee opciones fusionadas con defaults -- */
function phsbot_chat_get_settings(){
  $opt = get_option(PHSBOT_CHAT_OPT, array());
  if (!is_array($opt)) $opt = array();
  return array_merge(phsbot_chat_defaults(), $opt);
}

/* -- Acceso a una clave anidada -- */
function phsbot_chat_opt($keys, $def=null){
  $opt = phsbot_chat_get_settings();
  if (!is_array($keys)) $keys = array($keys);
  $cur = $opt;
  foreach ($keys as $k){
    if (!is_array($cur) || !array_key_exists($k, $cur)) return $def;
    $cur = $cur[$k];
  }
  return $cur;
}

/* ===== AJAX hooks ===== */
add_action('wp_ajax_phsbot_chat','phsbot_ajax_chat');
add_action('wp_ajax_nopriv_phsbot_chat','phsbot_ajax_chat');

/* -- Handler AJAX principal -- */
function phsbot_ajax_chat(){
  if (!check_ajax_referer('phsbot_chat','_ajax_nonce', false)) {
    wp_send_json(array('ok'=>false,'error'=>'Nonce inválido'));
  }

  $chat = phsbot_chat_get_settings();

  // Obtener configuración de la API5
  $bot_license = (string) phsbot_setting('bot_license_key', '');
  $bot_api_url = (string) phsbot_setting('bot_api_url', 'https://bocetosmarketing.com/api_claude_5/index.php');

  // Validar que exista la licencia
  if (!$bot_license) {
    wp_send_json(array('ok'=>false,'error'=>'Falta la clave de licencia del chatbot. Por favor, configúrala en PHSBOT → Configuración → Conexiones.'));
  }

  // Obtener dominio actual
  $domain = parse_url(home_url(), PHP_URL_HOST);

  $q     = sanitize_text_field($_POST['q'] ?? '');
  $cid   = sanitize_text_field($_POST['cid'] ?? '');
  $url   = esc_url_raw($_POST['url'] ?? '');
  $hist  = json_decode(stripslashes($_POST['history'] ?? '[]'), true);
  if (!is_array($hist)) $hist = array();
  $hist  = array_slice($hist, -max(1,intval($chat['max_history'] ?? 10))*2);

  $ctx_raw = isset($_POST['ctx']) ? wp_unslash($_POST['ctx']) : '';
  $ctx = array();
  if ($ctx_raw) { $tmp = json_decode($ctx_raw, true); if (is_array($tmp)) $ctx = $tmp; }
  $lim = function($s,$n){ $s=(string)$s; $s=wp_strip_all_tags($s); return (mb_strlen($s)>$n)?mb_substr($s,0,$n):$s; };
  $ctx_url   = $ctx && !empty($ctx['url']) ? esc_url_raw($ctx['url']) : '';
  $ctx_h1    = $lim($ctx['h1'] ?? '', 160);
  $ctx_title = $lim($ctx['title'] ?? '', 160);
  $ctx_topic = $lim($ctx['topic'] ?? '', 160);
  $ctx_mdesc = $lim($ctx['meta_description'] ?? '', 300);
  $ctx_ogt   = $lim($ctx['og_title'] ?? '', 160);
  $ctx_bc    = $lim($ctx['breadcrumbs'] ?? '', 220);
  $ctx_lang  = sanitize_text_field($ctx['lang'] ?? '');
  $ctx_sel   = $lim($ctx['selection'] ?? '', 400);
  $ctx_main  = $lim($ctx['main_excerpt'] ?? '', 1200);

  $model = (string) ($chat['model'] ?? 'gpt-4o-mini');
  $temp  = floatval($chat['temperature'] ?? 0.5);
  $tone  = (string)  ($chat['tone'] ?? 'profesional');
  $sys_p = (string)  ($chat['system_prompt'] ?? '');
  $max_t = intval($chat['max_tokens'] ?? 1400);
  if ($max_t < 200) $max_t = 200;

  $kb = (string) get_option(PHSBOT_KB_DOC_OPT, '');
  if ($kb !== '') $kb = wp_strip_all_tags($kb);

  // Live fetch (si está habilitado)
  $live = '';
  if (!empty($chat['allow_live_fetch']) && !empty($url)){
    $allowed = phsbot_setting('allowed_domains', array());
    if (is_string($allowed)) {
      $allowed = preg_split('/[\s,]+/', $allowed);
    }
    if (!is_array($allowed)) $allowed = array();
    $host = parse_url($url, PHP_URL_HOST);
    if ($host){
      $domain_check = strtolower($host);
      $ok = false;
      foreach ($allowed as $ad){
        $ad = strtolower(trim((string)$ad));
        if (!$ad) continue;
        if (substr('.'.$domain_check, -strlen('.'.$ad)) === '.'.$ad || $domain_check===$ad) { $ok=true; break; }
      }
      if ($ok){
        $res = wp_remote_get($url, array('timeout'=>8));
        if (!is_wp_error($res) && wp_remote_retrieve_response_code($res)===200){
          $live = wp_strip_all_tags(wp_remote_retrieve_body($res));
          $live = preg_replace('/\s+/',' ', $live);
          $live = wp_trim_words($live, 1200, '…');
        }
      }
    }
  }

  $__phs_reply_lang = function_exists('phsbot_reply_language') ? phsbot_reply_language($q) : 'es';
  $__phs_lang_directive = "LANGUAGE: Always reply in [{$__phs_reply_lang}] and keep that language consistently. Switch only if the user's latest message is clearly in another language.";

  $system = ($sys_p !== '') ? $sys_p : 'Responde de forma precisa y honesta. Si no sabes, dilo. Responde en el idioma del usuario.';
  $system .= "\n".$__phs_lang_directive;
  if ($tone !== '') $system .= "\nTono: ".$tone.".";
  if (!empty(phsbot_chat_opt(array('allow_html')))) {
    $system .= "\nFORMATO DE SALIDA: Devuelve SIEMPRE HTML válido, usando <p>, <ul>, <ol>, <li>, <strong>, <em>, <pre>, <code>, <br>. No uses <script>, <style>, iframes ni backticks. No envuelvas con <html> ni <body>.";
  }

  // Construir contexto de página
  $ctx_lines = array();
  if ($ctx_url)   $ctx_lines[] = '- URL: '.$ctx_url;
  if ($ctx_h1)    $ctx_lines[] = '- H1: '.$ctx_h1;
  if ($ctx_title) $ctx_lines[] = '- Título: '.$ctx_title;
  if ($ctx_topic) $ctx_lines[] = '- Tema: '.$ctx_topic;
  if ($ctx_mdesc) $ctx_lines[] = '- Meta: '.$ctx_mdesc;
  if ($ctx_ogt)   $ctx_lines[] = '- OG: '.$ctx_ogt;
  if ($ctx_bc)    $ctx_lines[] = '- Migas: '.$ctx_bc;
  if ($ctx_lang)  $ctx_lines[] = '- HTML lang: '.$ctx_lang;
  if ($ctx_sel)   $ctx_lines[] = '- Selección: '.$ctx_sel;
  if ($ctx_main)  $ctx_lines[] = "- Extracto:\n".$ctx_main;

  $page_context = !empty($ctx_lines) ? implode("\n", $ctx_lines) : '';

  // Construir historial para API5
  $history = array();
  foreach ($hist as $h){
    $content = isset($h['content']) ? (string)$h['content'] : ( isset($h['html']) ? wp_strip_all_tags((string)$h['html']) : '' );
    $history[] = array(
      'role' => ($h['role']==='assistant' ? 'assistant' : 'user'),
      'content' => $content
    );
  }

  // Construir KB content completo
  $kb_full = '';
  if ($kb) $kb_full .= $kb;
  if ($live) $kb_full .= "\n\nContenido de la URL:\n".$live;

  // Payload para la API5
  $api_payload = array(
    'license_key' => $bot_license,
    'domain' => $domain,
    'message' => $q,
    'conversation_id' => $cid,
    'context' => array(
      'kb_content' => $kb_full,
      'history' => $history,
      'page_url' => $ctx_url,
      'page_title' => $ctx_title
    ),
    'settings' => array(
      'model' => $model,
      'temperature' => $temp,
      'max_tokens' => $max_t,
      'system_prompt' => $system
    )
  );

  // Llamar a la API5
  $api_endpoint = trailingslashit($bot_api_url) . '?route=bot/chat';

  $res = wp_remote_post($api_endpoint, array(
    'timeout' => 30,
    'headers' => array('Content-Type' => 'application/json'),
    'body' => wp_json_encode($api_payload),
  ));

  if (is_wp_error($res)) {
    wp_send_json(array('ok'=>false,'error'=>'Error de conexión con la API: ' . $res->get_error_message()));
  }

  $code = wp_remote_retrieve_response_code($res);
  $body = json_decode(wp_remote_retrieve_body($res), true);

  // Log para debug
  if ($code !== 200 && defined('WP_DEBUG') && WP_DEBUG) {
    error_log('PHSBOT API5 endpoint='.$api_endpoint.' code='.$code);
    error_log('PHSBOT API5 response='.substr(wp_remote_retrieve_body($res),0,400));
  }

  // Manejar errores de la API
  if (!is_array($body) || !isset($body['success'])) {
    wp_send_json(array('ok'=>false,'error'=>'Respuesta inválida de la API (código '.$code.')'));
  }

  if (!$body['success']) {
    $error_code = $body['error']['code'] ?? 'UNKNOWN';
    $error_msg = $body['error']['message'] ?? 'Error desconocido de la API';

    // Mensajes de error personalizados según el código
    $user_msg = $error_msg;
    if ($error_code === 'TOKEN_LIMIT_EXCEEDED') {
      $user_msg = 'Has alcanzado el límite de tokens de tu plan. Por favor, actualiza tu suscripción o espera al próximo período de facturación.';
    } elseif ($error_code === 'DOMAIN_MISMATCH') {
      $user_msg = 'Esta licencia está registrada para otro dominio. Contacta con soporte para cambiar el dominio.';
    } elseif ($error_code === 'LICENSE_EXPIRED') {
      $user_msg = 'Tu licencia ha expirado. Por favor, renueva tu suscripción.';
    } elseif ($error_code === 'LICENSE_NOT_FOUND') {
      $user_msg = 'Licencia no válida. Verifica la clave de licencia en la configuración.';
    }

    wp_send_json(array('ok'=>false,'error'=>$user_msg,'code'=>$error_code));
  }

  // Extraer respuesta
  $txt = trim((string)($body['data']['response'] ?? ''));

  if (empty($txt)) {
    wp_send_json(array('ok'=>false,'error'=>'La API no devolvió respuesta'));
  }

  $allow_html = !empty($chat['allow_html']) || !empty(phsbot_chat_opt(array('allow_html')));
  $html       = $allow_html ? wp_kses_post($txt) : esc_html($txt);

  wp_send_json(array(
    'ok'   => true,
    'text' => $allow_html ? '' : $txt,
    'html' => $allow_html ? $html : '',
  ));
}
