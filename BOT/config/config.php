<?php
/**
 * PHSBOT – Configuración unificada (v1.3.2)
 */

if (!defined('ABSPATH')) exit;

if (!defined('PHSBOT_CONFIG_SLUG'))   define('PHSBOT_CONFIG_SLUG',   'phsbot_config');
if (!defined('PHSBOT_CHAT_OPT'))      define('PHSBOT_CHAT_OPT',      'phsbot_chat_settings');
if (!defined('PHSBOT_SETTINGS_OPT'))  define('PHSBOT_SETTINGS_OPT',  'phsbot_settings');

global $phsbot_config_pagehook;


/* ======== REGISTRO DEL SUBMENÚ ======== */
/* Registra la página de Configuración bajo el menú PHSBOT y guarda el pagehook */
function phsbot_config_register_menu(){
  if (!current_user_can('manage_options')) return;
  global $phsbot_config_pagehook;
  $phsbot_config_pagehook = add_submenu_page(
    'phsbot',
    'Conversa · Configuración',
    'Configuración',
    'manage_options',
    PHSBOT_CONFIG_SLUG,
    'phsbot_config_render_page'
  );
}
/* ========FIN REGISTRO DEL SUBMENÚ ===== */
add_action('admin_menu', 'phsbot_config_register_menu', 50);


/* ======== ENQUEUE DE ASSETS ======== */
/* Carga CSS/JS solo en la pantalla de Configuración (y fallback al root del plugin) */
function phsbot_config_enqueue($hook_suffix){
  global $phsbot_config_pagehook;
  $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
  $is_target = ($hook_suffix === $phsbot_config_pagehook) || ($page === 'phsbot') || ($page === PHSBOT_CONFIG_SLUG);
  if (!$is_target) return;

  wp_enqueue_style('wp-color-picker');
  wp_enqueue_script('wp-color-picker');

  $base = plugin_dir_url(__FILE__);

  // CSS unificado (cargar primero)
  wp_enqueue_style(
    'phsbot-modules-unified',
    plugin_dir_url(dirname(__FILE__)) . 'core/assets/modules-unified.css',
    array(),
    '1.4',
    'all'
  );

  wp_enqueue_style ('phsbot-config',  $base.'config.css', array('phsbot-modules-unified'), '1.3.2', 'all');
  wp_enqueue_script('phsbot-config',  $base.'config.js',  array('jquery','wp-color-picker'), '1.3.2', true);
}
/* ========FIN ENQUEUE DE ASSETS ===== */
add_action('admin_enqueue_scripts', 'phsbot_config_enqueue');


/* ======== GUARDADO DE OPCIONES ======== */
/* Procesa el POST y persiste tanto ajustes generales como de chat */
function phsbot_config_handle_save(){
  if (!current_user_can('manage_options')) wp_die('No autorizado');
  check_admin_referer('phsbot_config_save', '_phsbot_config_nonce');

  // -------- Ajustes Generales --------
  $g = get_option(PHSBOT_SETTINGS_OPT, array()); if (!is_array($g)) $g = array();

  $g['chat_position']  = isset($_POST['chat_position']) ? sanitize_text_field($_POST['chat_position']) : ($g['chat_position'] ?? 'bottom-right');
  $g['chat_width']     = isset($_POST['chat_width'])    ? sanitize_text_field($_POST['chat_width'])    : ($g['chat_width'] ?? '360px');
  $g['chat_height']    = isset($_POST['chat_height'])   ? sanitize_text_field($_POST['chat_height'])   : ($g['chat_height'] ?? '720px');

  $g['color_primary']       = isset($_POST['color_primary'])       ? sanitize_text_field($_POST['color_primary'])       : ($g['color_primary']       ?? '#1e1e1e');
  $g['color_secondary']     = isset($_POST['color_secondary'])     ? sanitize_hex_color($_POST['color_secondary'])     : ($g['color_secondary']     ?? '#dbdbdb');
  $g['color_background']    = isset($_POST['color_background'])    ? sanitize_hex_color($_POST['color_background'])    : ($g['color_background']    ?? '#e8e8e8');
  $g['color_text']          = isset($_POST['color_text'])          ? sanitize_hex_color($_POST['color_text'])          : ($g['color_text']          ?? '#000000');
  $g['color_bot_bubble']    = isset($_POST['color_bot_bubble'])    ? sanitize_hex_color($_POST['color_bot_bubble'])    : ($g['color_bot_bubble']    ?? '#f3f3f3');
  $g['color_user_bubble']   = isset($_POST['color_user_bubble'])   ? sanitize_hex_color($_POST['color_user_bubble'])   : ($g['color_user_bubble']   ?? '#ffffff');
  $g['color_footer']        = isset($_POST['color_footer'])        ? sanitize_hex_color($_POST['color_footer'])        : ($g['color_footer']        ?? '#1e1e1e');

  $g['color_launcher_bg']   = isset($_POST['color_launcher_bg'])   ? sanitize_hex_color($_POST['color_launcher_bg'])   : ($g['color_launcher_bg']   ?? '#1e1e1e');
  $g['color_launcher_icon'] = isset($_POST['color_launcher_icon']) ? sanitize_hex_color($_POST['color_launcher_icon']) : ($g['color_launcher_icon'] ?? '#ffffff');
  $g['color_launcher_text'] = isset($_POST['color_launcher_text']) ? sanitize_hex_color($_POST['color_launcher_text']) : ($g['color_launcher_text'] ?? '#ffffff');

  $g['btn_height']     = isset($_POST['btn_height'])     ? max(36, min(56, intval($_POST['btn_height'])))           : ($g['btn_height']     ?? 44);
  $g['head_btn_size']  = isset($_POST['head_btn_size'])  ? max(20, min(34, intval($_POST['head_btn_size'])))        : ($g['head_btn_size']  ?? 26);
  $g['mic_stroke_w']   = isset($_POST['mic_stroke_w'])   ? max(1,  min(3,  intval($_POST['mic_stroke_w'])))         : ($g['mic_stroke_w']   ?? 1);

  $g['bot_license_key']    = isset($_POST['bot_license_key'])    ? (string) wp_unslash($_POST['bot_license_key'])    : ($g['bot_license_key']    ?? '');
  $g['bot_api_url']        = isset($_POST['bot_api_url'])        ? esc_url_raw($_POST['bot_api_url'])                : ($g['bot_api_url']        ?? 'https://bocetosmarketing.com/api_claude_5/index.php');
  $g['telegram_bot_token'] = isset($_POST['telegram_bot_token']) ? (string) wp_unslash($_POST['telegram_bot_token']) : ($g['telegram_bot_token'] ?? '');
  $g['telegram_chat_id']   = isset($_POST['telegram_chat_id'])   ? sanitize_text_field($_POST['telegram_chat_id'])   : ($g['telegram_chat_id']   ?? '');
  $g['whatsapp_phone']     = isset($_POST['whatsapp_phone'])     ? sanitize_text_field($_POST['whatsapp_phone'])     : ($g['whatsapp_phone']     ?? '');

  // Nuevo: tamaño de fuente de las burbujas (12–22 px)
  $g['bubble_font_size'] = isset($_POST['bubble_font_size'])
    ? max(12, min(22, intval($_POST['bubble_font_size'])))
    : ($g['bubble_font_size'] ?? 15);

  // Título de cabecera (guardado seguro)
  if ( array_key_exists('chat_title', $_POST) ) {
    $raw = (string) wp_unslash($_POST['chat_title']);
    $val = trim( wp_strip_all_tags( $raw ) );
    $g['chat_title'] = ($val === '') ? 'PHSBot' : $val;
  }

  update_option(PHSBOT_SETTINGS_OPT, $g);

  // -------- Ajustes del Chat (IA) --------
  $c = get_option(PHSBOT_CHAT_OPT, array()); if (!is_array($c)) $c = array();

  // Solo guardar mensajes y opciones avanzadas (modelo configurado desde API)
  $c['welcome']          = isset($_POST['chat_welcome'])       ? wp_kses_post($_POST['chat_welcome'])              : ($c['welcome']          ?? 'Hola, soy Conversa. ¿En qué puedo ayudarte?');
  $c['system_prompt']    = isset($_POST['chat_system_prompt']) ? wp_kses_post($_POST['chat_system_prompt'])         : ($c['system_prompt']    ?? '');
  // Checkboxes: si está en POST = 1, si no está en POST = 0
  $c['allow_html']       = isset($_POST['chat_allow_html'])       ? 1 : 0;
  $c['allow_elementor']  = isset($_POST['chat_allow_elementor'])  ? 1 : 0;
  $c['allow_live_fetch'] = isset($_POST['chat_allow_live_fetch']) ? 1 : 0;

  update_option(PHSBOT_CHAT_OPT, $c, false);

  // Redirección OK
  $url = add_query_arg(array('page'=>PHSBOT_CONFIG_SLUG,'updated'=>'1'), admin_url('admin.php'));
  wp_safe_redirect($url); exit;
}
/* ========FIN GUARDADO DE OPCIONES ===== */
add_action('admin_post_phsbot_config_save', 'phsbot_config_handle_save');

/* ======== BOT: OBTENER LICENCIA ======== */
/* Devuelve la información de licencia BOT desde los ajustes principales */
if (!function_exists('phsbot_get_license_info')) {
    function phsbot_get_license_info() {
        $main = get_option(defined('PHSBOT_MAIN_SETTINGS_OPT') ? PHSBOT_MAIN_SETTINGS_OPT : 'phsbot_settings', array());
        return [
            'license_key' => isset($main['bot_license_key']) ? trim($main['bot_license_key']) : '',
            'api_url'     => isset($main['bot_api_url']) ? trim($main['bot_api_url']) : 'https://bocetosmarketing.com/api_claude_5/index.php',
            'domain'      => parse_url(home_url(), PHP_URL_HOST)
        ];
    }
} /* ========FIN BOT: OBTENER LICENCIA ===== */



/* ======== OPENAI: NORMALIZAR ALIAS DE MODELO ======== */
/* Colapsa snapshots/aliases fechados a su alias base (p. ej. gpt-4.1-2025-05-13 → gpt-4.1) */
if (!function_exists('phsbot_openai_collapse_model_alias')) {
    function phsbot_openai_collapse_model_alias($model_id) {
        $alias = preg_replace('/-(20\d{2}-\d{2}-\d{2}|latest)$/i', '', (string)$model_id);
        return $alias ?: (string)$model_id;
    }
} /* ========FIN OPENAI: NORMALIZAR ALIAS DE MODELO ===== */



/* ======== OPENAI: LISTAR MODELOS GPT-4+ / GPT-5* (VÍA API5 + CACHE) ======== */
/* Llama a API5 bot/list-models que filtra GPT-4* y GPT-5* óptimos para chat y cachea en transient */
if (!function_exists('phsbot_openai_list_chat_models')) {
    function phsbot_openai_list_chat_models($ttl = 12 * HOUR_IN_SECONDS) {
        $cache_key = 'phsbot_openai_models_chat_v3';
        $cached = get_transient($cache_key);
        if (is_array($cached) && !empty($cached)) return $cached;

        $license = phsbot_get_license_info();
        if (!$license['license_key']) return array();

        // Llamar a API5 para obtener modelos (la API usa su propia API key de OpenAI)
        $api_endpoint = trailingslashit($license['api_url']) . '?route=bot/list-models';

        $payload = [
            'license_key' => $license['license_key'],
            'domain'      => $license['domain']
        ];

        $resp = wp_remote_post($api_endpoint, [
            'timeout' => 8,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($payload),
        ]);

        if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) return array();

        $data = json_decode(wp_remote_retrieve_body($resp), true);
        if (!isset($data['success']) || !$data['success'] || !isset($data['data']['models'])) return array();

        $ids = $data['data']['models'];

        set_transient($cache_key, $ids, $ttl);
        return $ids;
    }
} /* ========FIN OPENAI: LISTAR MODELOS GPT-4+ / GPT-5* (VÍA API5 + CACHE) ===== */



/* ======== OPENAI: LABEL AMIGABLE PARA MODELO ======== */
/* Genera un label descriptivo; no inventa IDs, solo añade descriptor genérico por patrón */
if (!function_exists('phsbot_openai_model_label')) {
    function phsbot_openai_model_label($id) {
        $label = (string)$id;
        $id_l  = strtolower($label);

        $is5     = (strpos($id_l, 'gpt-5') === 0);
        $is4o    = (strpos($id_l, 'gpt-4o') === 0);
        $is41    = (strpos($id_l, 'gpt-4.1') === 0);
        $isMini  = (strpos($id_l, 'mini') !== false);

        if     ($is5 && $isMini)  $desc = 'rápido y económico; buen razonamiento';
        elseif ($is5)             $desc = 'máxima calidad de razonamiento; más costoso';
        elseif ($is4o && $isMini) $desc = 'muy barato y veloz; multimodal';
        elseif ($is4o)            $desc = 'multimodal equilibrado; calidad alta';
        elseif ($is41 && $isMini) $desc = 'rápido y barato; buena calidad';
        elseif ($is41)            $desc = 'texto de alta calidad; razonamiento sólido';
        else                      $desc = $isMini ? 'rápido y barato' : 'equilibrado para chat';

        return sprintf('%s — %s', $label, $desc);
    }
} /* ========FIN OPENAI: LABEL AMIGABLE PARA MODELO ===== */

/* ======== RENDER DE LA PÁGINA ======== */
/* Pinta la UI de configuración con previsualización */
function phsbot_config_render_page(){
  if (!current_user_can('manage_options')) return;

  $g = get_option(PHSBOT_SETTINGS_OPT, array()); if (!is_array($g)) $g = array();
  $c = get_option(PHSBOT_CHAT_OPT, array());     if (!is_array($c)) $c = array();

  // Conexiones
  $bot_license_key    = isset($g['bot_license_key'])    ? $g['bot_license_key']    : '';
  $bot_api_url        = isset($g['bot_api_url'])        ? $g['bot_api_url']        : 'https://bocetosmarketing.com/api_claude_5/index.php';
  $telegram_bot_token = isset($g['telegram_bot_token']) ? $g['telegram_bot_token'] : '';
  $telegram_chat_id   = isset($g['telegram_chat_id'])   ? $g['telegram_chat_id']   : '';
  $whatsapp_phone     = isset($g['whatsapp_phone'])     ? $g['whatsapp_phone']     : '';

  // Apariencia
  $chat_position  = isset($g['chat_position']) ? $g['chat_position'] : 'bottom-right';
  $chat_width     = isset($g['chat_width'])    ? $g['chat_width']    : '360px';
  $chat_height    = isset($g['chat_height'])   ? $g['chat_height']   : '720px';
  $chat_title     = isset($g['chat_title'])    ? $g['chat_title']    : 'PHSBot';
  $bubble_font_size = isset($g['bubble_font_size']) ? intval($g['bubble_font_size']) : 15;

  $color_primary       = isset($g['color_primary'])    ? $g['color_primary']    : '#1e1e1e';
  $color_secondary     = isset($g['color_secondary'])  ? $g['color_secondary']  : '#dbdbdb';
  $color_background    = isset($g['color_background']) ? $g['color_background'] : '#e8e8e8';
  $color_text          = isset($g['color_text'])       ? $g['color_text']       : '#000000';
  $color_bot_bubble    = isset($g['color_bot_bubble']) ? $g['color_bot_bubble'] : '#f3f3f3';
  $color_user_bubble   = isset($g['color_user_bubble'])? $g['color_user_bubble']: '#ffffff';
  $color_launcher_bg   = isset($g['color_launcher_bg'])   ? $g['color_launcher_bg']   : '#1e1e1e';
  $color_launcher_icon = isset($g['color_launcher_icon']) ? $g['color_launcher_icon'] : '#ffffff';
  $color_launcher_text = isset($g['color_launcher_text']) ? $g['color_launcher_text'] : '#ffffff';

  // Footer (preview)
  $color_footer_saved   = isset($g['color_footer']) ? $g['color_footer'] : '#1e1e1e';
  $color_footer_preview = ($color_footer_saved !== '') ? $color_footer_saved : '#1e1e1e';

  $btn_height    = isset($g['btn_height'])    ? intval($g['btn_height'])    : 44;
  $head_btn_size = isset($g['head_btn_size']) ? intval($g['head_btn_size']) : 26;
  $mic_stroke_w  = isset($g['mic_stroke_w'])  ? intval($g['mic_stroke_w'])  : 1;

  // Chat (IA) - Solo mensajes y opciones avanzadas (modelo configurado desde API)
  $chat_welcome         = isset($c['welcome']) ? $c['welcome'] : 'Hola, soy Conversa. ¿En qué puedo ayudarte?';
  $chat_system_prompt   = isset($c['system_prompt']) ? $c['system_prompt'] : '';
  // Valores por defecto = true (marcados) si no hay valor guardado
  $chat_allow_html      = isset($c['allow_html'])       ? (bool)$c['allow_html']       : true;
  $chat_allow_elementor = isset($c['allow_elementor'])  ? (bool)$c['allow_elementor']  : true;
  $chat_allow_live_fetch= isset($c['allow_live_fetch']) ? (bool)$c['allow_live_fetch'] : true;

  // Normaliza tamaños px
  $w_px = intval(preg_replace('/[^0-9]/','', $chat_width));
  $h_px = intval(preg_replace('/[^0-9]/','', $chat_height));
  if ($w_px < 260) $w_px = 360;
  if ($h_px < 400) $h_px = 720;

  /* ======== PROMPT POR DEFECTO (usa dominio activo) ======== */
  $root_url = untrailingslashit( home_url() );
  $contact_url_default = home_url( '/contacto/' );
  $default_system_prompt = <<<PHSBOT_DEF
***Rol y objetivo***
Eres el asistente de atención al cliente del sitio $root_url. Responde siempre en el mismo idioma que use el usuario. Tu objetivo principal es orientar al usuario, resolver sus dudas y ayudarle a encontrar los productos o servicios que mejor se adapten a sus necesidades.
Eres parte de la empresa, no hables de la empresa en tercera persona.

***Estilo de respuesta***

- Breve y concisa. Máximo 200 palabras.
- Formato en HTML obligado
- No repitas la pregunta del usuario como entradilla.

***Captura de datos de forma discreta y escalonada a partir del 10º mensaje***
- Nunca pidas datos como teléfono, mail al inicio de la conversación
- Camufla la petición de teléfono o mail dentro del siguiente paso útil (1º pide correo electrónico, 2ª teléfono, 3ª Prefíjo telefónico del país).
- Si el usuario comparte datos, confirma brevemente y continúa con el siguiente paso.



Plantillas sutiles (adaptar al idioma del usuario)
- Si quieres te envío una propuesta con fechas y precios por mail
- ¿Prefieres que te llame a un teléfono y lo comentamos?


Reglas de contenido
- Usa información del sitio  $root_url (o su Base de Conocimiento).
- Cuando cites una sección existente, añade su enlace interno en HTML.
- Evita más de un enlace por mensaje salvo que sea imprescindible.
- Mantén el tono profesional y útil; nada de frases de relleno.

Si falta contexto
- Haz una única pregunta breve para avanzar (≤12 palabras).
PHSBOT_DEF;
  /* ========FIN PROMPT POR DEFECTO ===== */

  // Si no hay prompt guardado, mostrar el por defecto en el textarea
  $chat_system_prompt_display = ($chat_system_prompt !== '') ? $chat_system_prompt : $default_system_prompt;
  ?>
  <div class="wrap phsbot-module-wrap">
    <!-- Header gris estilo GeoWriter -->
    <div class="phsbot-module-header" style="display: flex; justify-content: space-between; align-items: center;">
      <h1 style="margin: 0; color: rgba(0, 0, 0, 0.8);">Configuración</h1>
    </div>

    <?php if (!empty($_GET['updated'])): ?>
      <div class="phsbot-alert phsbot-alert-success">
        Configuración guardada correctamente.
      </div>
    <?php endif; ?>

    <!-- Tabs de navegación -->
    <h2 class="nav-tab-wrapper phsbot-config-tabs" role="tablist" aria-label="Conversa Config">
      <a href="#tab-conexiones" class="nav-tab nav-tab-active" role="tab" aria-selected="true">Conexiones</a>
      <a href="#tab-chat" class="nav-tab" role="tab" aria-selected="false">Chat (IA)</a>
      <a href="#tab-aspecto" class="nav-tab" role="tab" aria-selected="false">Aspecto</a>
    </h2>

    <form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post" class="phsbot-config-form">
      <?php wp_nonce_field('phsbot_config_save','_phsbot_config_nonce'); ?>
      <input type="hidden" name="action" value="phsbot_config_save" />

      <!-- TAB 1: CONEXIONES -->
      <section id="tab-conexiones" class="phsbot-config-panel" aria-hidden="false">
        <div class="phsbot-module-container">
          <div class="phsbot-module-content">
            <div class="phsbot-mega-card" style="padding: 32px;">
              
              <!-- Sección: Licencia BOT -->
              <div class="phsbot-section">
                <h2 class="phsbot-section-title">Licencia BOT</h2>
                
                <div class="phsbot-field">
                  <label class="phsbot-label" for="bot_license_key">License Key</label>
                  <input type="text" 
                         name="bot_license_key" 
                         id="bot_license_key" 
                         class="phsbot-input-field" 
                         placeholder="BOT-XXXX-XX-XXXX-XXXXXXXX" 
                         value="<?php echo esc_attr($bot_license_key);?>">
                  <p class="phsbot-description">Introduce tu clave de licencia del chatbot.</p>
                  <button type="button" class="phsbot-btn-secondary" id="phsbot-validate-license" style="margin-top: 12px;">
                    Validar Licencia
                  </button>
                </div>

                <!-- API URL: Campo oculto (hardcodeado) -->
                <div class="phsbot-field" style="display: none;">
                  <input type="hidden"
                         name="bot_api_url"
                         id="bot_api_url"
                         value="<?php echo esc_attr($bot_api_url);?>">
                </div>

                <!-- Status de validación -->
                <div id="phsbot-license-status" style="margin-top: 20px;"></div>
              </div>

              <!-- Sección: Telegram -->
              <div class="phsbot-section" style="margin-top: 32px;">
                <h2 class="phsbot-section-title">Notificaciones Telegram</h2>
                
                <div class="phsbot-grid-2">
                  <div class="phsbot-field">
                    <label class="phsbot-label" for="telegram_bot_token">Token del Bot</label>
                    <input type="text" 
                           name="telegram_bot_token" 
                           id="telegram_bot_token" 
                           class="phsbot-input-field" 
                           value="<?php echo esc_attr($telegram_bot_token);?>">
                  </div>

                  <div class="phsbot-field">
                    <label class="phsbot-label" for="telegram_chat_id">Chat ID</label>
                    <input type="text" 
                           name="telegram_chat_id" 
                           id="telegram_chat_id" 
                           class="phsbot-input-field" 
                           value="<?php echo esc_attr($telegram_chat_id);?>">
                  </div>
                </div>
              </div>

              <!-- Sección: WhatsApp -->
              <div class="phsbot-section" style="margin-top: 32px;">
                <h2 class="phsbot-section-title">WhatsApp</h2>
                
                <div class="phsbot-field">
                  <label class="phsbot-label" for="whatsapp_phone">Teléfono (Formato E.164)</label>
                  <input type="text" 
                         name="whatsapp_phone" 
                         id="whatsapp_phone" 
                         class="phsbot-input-field" 
                         placeholder="+34123456789" 
                         value="<?php echo esc_attr($whatsapp_phone);?>">
                  <p class="phsbot-description">Número de teléfono en formato internacional.</p>
                </div>
              </div>

              <!-- Botón guardar -->
              <div class="phsbot-section" style="margin-top: 32px; border: none; padding: 0;">
                <button type="submit" class="phsbot-btn-save">Guardar Configuración</button>
              </div>

            </div>
          </div>
        </div>
      </section>

      <!-- TAB 2: CHAT (IA) -->
      <section id="tab-chat" class="phsbot-config-panel" aria-hidden="true">
        <div class="phsbot-module-container">
          <div class="phsbot-module-content">
            <div class="phsbot-mega-card" style="padding: 32px;">

              <!-- Sección: Mensajes -->
              <div class="phsbot-section">
                <h2 class="phsbot-section-title">Mensajes</h2>

                <div class="phsbot-field">
                  <label class="phsbot-label" for="chat_welcome">Mensaje de Bienvenida</label>
                  <textarea name="chat_welcome"
                            id="chat_welcome"
                            rows="2"
                            class="phsbot-textarea-field"><?php echo esc_textarea($chat_welcome);?></textarea>
                </div>

                <div class="phsbot-field">
                  <label class="phsbot-label" for="chat_system_prompt">System Prompt</label>
                  <textarea name="chat_system_prompt"
                            id="chat_system_prompt"
                            rows="8"
                            class="phsbot-textarea-field"><?php echo esc_textarea($chat_system_prompt_display);?></textarea>
                  <button type="button" class="phsbot-btn-secondary" id="phsbot-system-default-btn" style="margin-top: 12px;">
                    Restaurar valor por defecto
                  </button>
                  <script>
                    (function(){
                      var btn = document.getElementById('phsbot-system-default-btn');
                      var ta  = document.getElementById('chat_system_prompt');
                      if(!btn || !ta) return;
                      var DEFAULT_PROMPT = <?php echo json_encode($default_system_prompt); ?>;
                      btn.addEventListener('click', function(){
                        ta.value = DEFAULT_PROMPT;
                        ta.dispatchEvent(new Event('input', {bubbles:true}));
                      });
                    })();
                  </script>
                </div>
              </div>

              <!-- Sección: Opciones Avanzadas -->
              <div class="phsbot-section" style="margin-top: 32px;">
                <h2 class="phsbot-section-title">Opciones Avanzadas</h2>

                <div class="phsbot-field">
                  <label>
                    <input type="checkbox" name="chat_allow_html" value="1" <?php checked($chat_allow_html, true); ?>>
                    Permitir HTML en respuestas
                  </label>
                </div>

                <div class="phsbot-field">
                  <label>
                    <input type="checkbox" name="chat_allow_elementor" value="1" <?php checked($chat_allow_elementor, true); ?>>
                    Integración con Elementor
                  </label>
                </div>

                <div class="phsbot-field">
                  <label>
                    <input type="checkbox" name="chat_allow_live_fetch" value="1" <?php checked($chat_allow_live_fetch, true); ?>>
                    Live fetch (obtener URL actual)
                  </label>
                </div>
              </div>

              <!-- Botón guardar -->
              <div class="phsbot-section" style="margin-top: 32px; border: none; padding: 0;">
                <button type="submit" class="phsbot-btn-save">Guardar Configuración</button>
              </div>

            </div>
          </div>
        </div>
      </section>

      <!-- TAB 3: ASPECTO -->
      <section id="tab-aspecto" class="phsbot-config-panel" aria-hidden="true">
        <div class="phsbot-aspecto-wrapper" style="display: grid; grid-template-columns: 1fr 400px; gap: 24px; align-items: start;">
          <div class="phsbot-aspecto-left">
            <div class="phsbot-mega-card" style="padding: 32px;">
              
              <!-- Sección: Posición y Tamaño -->
              <div class="phsbot-section">
                <h2 class="phsbot-section-title">Posición y Tamaño</h2>

                <div class="phsbot-field">
                  <label class="phsbot-label" for="chat_position">Posición del Chat</label>
                  <select name="chat_position" id="chat_position" class="phsbot-input-field">
                    <option value="bottom-right" <?php selected($chat_position, 'bottom-right'); ?>>Abajo derecha</option>
                    <option value="bottom-left" <?php selected($chat_position, 'bottom-left'); ?>>Abajo izquierda</option>
                  </select>
                  <p class="phsbot-description">Selecciona dónde aparecerá el icono y el chat flotante.</p>
                  <script>
                  (function(){
                    var select = document.getElementById('chat_position');
                    if(select){
                      select.addEventListener('change', function(){
                        var preview = document.getElementById('phsbot-preview');
                        if(preview) preview.setAttribute('data-pos', this.value);
                      });
                    }
                  })();
                  </script>
                </div>

                <div class="phsbot-grid-2">
                  <div class="phsbot-field">
                    <label class="phsbot-label" for="chat_title">Título cabecera</label>
                    <input type="text"
                           name="chat_title"
                           id="chat_title"
                           class="phsbot-input-field"
                           value="<?php echo esc_attr($chat_title); ?>"
                           placeholder="PHSBot">
                  </div>

                  <div class="phsbot-field">
                    <label class="phsbot-label">Tamaño de fuente: <span id="bubble_font_size_val"><?php echo esc_html($bubble_font_size); ?> px</span></label>
                    <input type="range"
                           id="bubble_font_size"
                           name="bubble_font_size"
                           min="12"
                           max="22"
                           step="1"
                           value="<?php echo esc_attr($bubble_font_size); ?>"
                           style="width: 100%;">
                  </div>
                </div>

                <div class="phsbot-grid-2">
                  <div class="phsbot-field">
                    <label class="phsbot-label">Ancho: <span id="chat_width_val"><?php echo esc_html($w_px);?> px</span></label>
                    <input type="range"
                           id="chat_width_slider"
                           min="260"
                           max="920"
                           step="2"
                           value="<?php echo esc_attr($w_px);?>"
                           style="width: 100%;">
                    <input type="hidden" id="chat_width" name="chat_width" value="<?php echo esc_attr($w_px.'px');?>">
                  </div>

                  <div class="phsbot-field">
                    <label class="phsbot-label">Alto: <span id="chat_height_val"><?php echo esc_html($h_px);?> px</span></label>
                    <input type="range"
                           id="chat_height_slider"
                           min="420"
                           max="960"
                           step="2"
                           value="<?php echo esc_attr($h_px);?>"
                           style="width: 100%;">
                    <input type="hidden" id="chat_height" name="chat_height" value="<?php echo esc_attr($h_px.'px');?>">
                  </div>
                </div>
              </div>

              <!-- Sección: Colores -->
              <div class="phsbot-section" style="margin-top: 32px;">
                <h2 class="phsbot-section-title">Colores</h2>

                <!-- Mantener campos ocultos para compatibilidad backend -->
                <input type="hidden" name="btn_height" value="<?php echo esc_attr($btn_height);?>">
                <input type="hidden" name="head_btn_size" value="<?php echo esc_attr($head_btn_size);?>">
                <input type="hidden" name="mic_stroke_w" value="<?php echo esc_attr($mic_stroke_w);?>">

                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px;">
                  <div class="phsbot-field">
                    <label class="phsbot-label">Color Primario (Cabecera)</label>
                    <input type="text" name="color_primary" class="phsbot-color phsbot-input-field" value="<?php echo esc_attr($color_primary);?>">
                  </div>

                  <div class="phsbot-field">
                    <label class="phsbot-label">Color Secundario (Hovers)</label>
                    <input type="text" name="color_secondary" class="phsbot-color phsbot-input-field" value="<?php echo esc_attr($color_secondary);?>">
                  </div>

                  <div class="phsbot-field">
                    <label class="phsbot-label">Fondo del Chat</label>
                    <input type="text" name="color_background" class="phsbot-color phsbot-input-field" value="<?php echo esc_attr($color_background);?>">
                  </div>

                  <div class="phsbot-field">
                    <label class="phsbot-label">Texto General</label>
                    <input type="text" name="color_text" class="phsbot-color phsbot-input-field" value="<?php echo esc_attr($color_text);?>">
                  </div>

                  <div class="phsbot-field">
                    <label class="phsbot-label">Burbuja del Bot</label>
                    <input type="text" name="color_bot_bubble" class="phsbot-color phsbot-input-field" value="<?php echo esc_attr($color_bot_bubble);?>">
                  </div>

                  <div class="phsbot-field">
                    <label class="phsbot-label">Burbuja del Usuario</label>
                    <input type="text" name="color_user_bubble" class="phsbot-color phsbot-input-field" value="<?php echo esc_attr($color_user_bubble);?>">
                  </div>

                  <div class="phsbot-field">
                    <label class="phsbot-label">Color Footer (opcional)</label>
                    <input type="text" name="color_footer" class="phsbot-color phsbot-input-field" value="<?php echo esc_attr($color_footer_saved);?>">
                  </div>

                  <div class="phsbot-field">
                    <label class="phsbot-label">Color Fondo Botón Chat</label>
                    <input type="text" name="color_launcher_bg" class="phsbot-color phsbot-input-field" value="<?php echo esc_attr($color_launcher_bg);?>">
                  </div>

                  <div class="phsbot-field">
                    <label class="phsbot-label">Color Icono Botón Chat</label>
                    <input type="text" name="color_launcher_icon" class="phsbot-color phsbot-input-field" value="<?php echo esc_attr($color_launcher_icon);?>">
                  </div>

                  <div class="phsbot-field">
                    <label class="phsbot-label">Color Texto Botón Chat</label>
                    <input type="text" name="color_launcher_text" class="phsbot-color phsbot-input-field" value="<?php echo esc_attr($color_launcher_text);?>">
                  </div>
                </div>
              </div>

              <!-- Botón guardar -->
              <div class="phsbot-section" style="margin-top: 32px; border: none; padding: 0;">
                <button type="submit" class="phsbot-btn-save">Guardar Configuración</button>
              </div>

            </div>
          </div>

          <!-- Preview del Chatbot -->
          <div class="phsbot-aspecto-right">
            <div id="phsbot-preview"
                 data-pos="<?php echo esc_attr($chat_position); ?>"
                 style="--phsbot-width: <?php echo esc_attr(intval($w_px)); ?>px;
                        --phsbot-height: <?php echo esc_attr(intval($h_px)); ?>px;
                        --phsbot-bg: <?php echo esc_attr($color_background); ?>;
                        --phsbot-text: <?php echo esc_attr($color_text); ?>;
                        --phsbot-bot-bubble: <?php echo esc_attr($color_bot_bubble); ?>;
                        --phsbot-user-bubble: <?php echo esc_attr($color_user_bubble); ?>;
                        --phsbot-primary: <?php echo esc_attr($color_primary); ?>;
                        --phsbot-secondary: <?php echo esc_attr($color_secondary); ?>;
                        --phsbot-footer: <?php echo esc_attr($color_footer_preview); ?>;
                        --phsbot-launcher-bg: <?php echo esc_attr($color_launcher_bg); ?>;
                        --phsbot-launcher-icon: <?php echo esc_attr($color_launcher_icon); ?>;
                        --phsbot-launcher-text: <?php echo esc_attr($color_launcher_text); ?>;
                        --phsbot-btn-h: <?php echo esc_attr(intval($btn_height)); ?>px;
                        --phsbot-head-btn: <?php echo esc_attr(intval($head_btn_size)); ?>px;
                        --mic-stroke-w: <?php echo esc_attr(intval($mic_stroke_w)); ?>px;
                        --phsbot-bubble-fs: <?php echo esc_attr(intval($bubble_font_size)); ?>px;">
              <div class="phs-header">
                <div class="phs-title"><?php echo esc_html($chat_title); ?></div>
                <div class="phs-head-actions">
                  <button type="button" class="phsbot-btn phsbot-mic" style="width: 32px; height: 32px;" title="Cerrar" aria-label="Cerrar">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                  </button>
                </div>
              </div>
              <div class="phs-messages" style="font-size: var(--phsbot-bubble-fs, 15px);">
                <div class="phs-msg bot"><div class="phsbot-bubble"><p>¡Hola! ¿Me dices tu nombre y en qué puedo ayudarte?</p></div></div>
                <div class="phs-msg user"><div class="phsbot-bubble"><p>Aquí va la respuesta del usuario, normalmente un sin sentido...</p></div></div>
              </div>
              <div class="phs-input">
                <button class="phsbot-btn phsbot-mic" id="phsbot-mic" type="button" aria-label="<?php echo esc_attr_x('Micrófono', 'Microphone button', 'phsbot'); ?>">
                  <svg viewBox="0 0 24 24" role="img" aria-hidden="true" focusable="false">
                    <g fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round">
                      <rect x="9" y="3" width="6" height="10" rx="3"/>
                      <path d="M5 11a7 7 0 0 0 14 0"/>
                      <line x1="12" y1="17" x2="12" y2="20"/>
                      <line x1="9"  y1="21" x2="15" y2="21"/>
                    </g>
                  </svg>
                </button>
                <textarea style="border-radius:99px;height:50px" id="phsbot-q" disabled placeholder="Escribe un mensaje…"></textarea>
                <button class="phsbot-btn phsbot-mic" id="phsbot-send" type="button">
                  <svg viewBox="0 0 24 24" role="img" focusable="false" aria-hidden="true">
                    <polygon points="12,6 18,18 6,18" fill="currentColor"/>
                  </svg>
                </button>
              </div>
            </div>

            <!-- Preview del Botón Launcher -->
            <div style="margin-top: 30px; padding: 20px; background: #f5f5f5; border-radius: 8px;">
              <h3 style="margin: 0 0 15px 0; font-size: 14px; font-weight: 600; color: #333;">Vista Previa Botón Chat</h3>
              <div style="display: flex; justify-content: center; align-items: center; min-height: 100px;">
                <button type="button" id="phsbot-launcher-preview" class="phsbot-launcher-preview"
                        style="background: var(--phsbot-launcher-bg, #1e1e1e);
                               border: 5px solid #ffffff;
                               border-radius: 23px;
                               padding: 12px 20px;
                               cursor: pointer;
                               display: flex;
                               align-items: center;
                               gap: 10px;
                               box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                               transition: all 0.3s ease;">
                  <svg class="phsbot-launcher-icon-preview" viewBox="0 0 500 500" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" preserveAspectRatio="xMidYMid meet"
                       style="width: 24px; height: 24px; display: block; flex-shrink: 0; color: var(--phsbot-launcher-icon, #ffffff);">
                    <path d="M 230.501 456.687 C 117.074 456.687 25.12 362.978 25.12 247.39 C 25.12 131.808 117.074 38.106 230.501 38.106 C 308.291 38.106 375.112 80.862 409.967 145.836 C 408.767 145.805 408.41 147.111 407.167 147.139 L 222.56 260.072 L 380.844 389.987 C 343.348 431.028 289.866 456.687 230.501 456.687 Z M 221.895 88.336 C 203.85 88.336 189.222 102.512 189.222 120.003 C 189.222 137.492 203.85 151.669 221.895 151.669 C 239.939 151.669 254.567 137.492 254.567 120.003 C 254.567 102.512 239.939 88.336 221.895 88.336 Z" fill="currentColor" stroke="none"/>
                    <ellipse cx="358.745" cy="261.771" rx="32.672" ry="31.666" fill="currentColor" stroke="none"/>
                    <ellipse cx="451.045" cy="259.5" rx="32.672" ry="31.666" fill="currentColor" stroke="none"/>
                  </svg>
                  <span class="phsbot-launcher-text-preview"
                        style="color: var(--phsbot-launcher-text, #ffffff);
                               font-size: 16px;
                               font-weight: 600;
                               white-space: nowrap;
                               line-height: 1;"><?php echo esc_html($chat_title); ?></span>
                </button>
              </div>
            </div>

          </div>
        </div>
      </section>

    </form>

    <!-- Widget informativo del plan (fuera del form) -->
    <div id="phsbot-plan-widget" style="margin-top: 30px; display: none;">
      <div style="background: #000000; color: #fff; border-radius: 8px; padding: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <h3 style="margin: 0 0 20px 0; font-size: 18px; font-weight: 600; border-bottom: 1px solid rgba(255,255,255,0.2); padding-bottom: 10px;">
          Información del Plan
        </h3>
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
          <div style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 6px;">
            <div style="font-size: 12px; opacity: 0.8; margin-bottom: 5px;">Plan Actual</div>
            <div style="font-size: 20px; font-weight: 600;" id="widget-plan-name">-</div>
          </div>
          <div style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 6px;">
            <div style="font-size: 12px; opacity: 0.8; margin-bottom: 5px;">Estado</div>
            <div style="font-size: 20px; font-weight: 600;" id="widget-plan-status">-</div>
          </div>
          <div style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 6px;">
            <div style="font-size: 12px; opacity: 0.8; margin-bottom: 5px;">Tokens Disponibles</div>
            <div style="font-size: 20px; font-weight: 600;">
              <span id="widget-tokens-available">0</span> / <span id="widget-tokens-limit">0</span>
            </div>
            <div style="margin-top: 8px; background: rgba(0,0,0,0.2); height: 6px; border-radius: 3px; overflow: hidden;">
              <div id="widget-tokens-progress" style="height: 100%; background: #fff; width: 0%; transition: width 0.5s ease;"></div>
            </div>
          </div>
          <div style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 6px;">
            <div style="font-size: 12px; opacity: 0.8; margin-bottom: 5px;">Renovación</div>
            <div style="font-size: 16px; font-weight: 600;" id="widget-renewal-date">-</div>
            <div style="font-size: 12px; opacity: 0.8; margin-top: 5px;" id="widget-days-remaining">-</div>
          </div>
        </div>
      </div>
    </div>
  </div>
<?php
}
/* ========FIN RENDER DE LA PÁGINA ===== */
