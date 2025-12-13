<?php
// /chat/chat-view.php
if (!defined('ABSPATH')) exit;

/**
 * Este archivo se incluye desde phsbot_render_chat_markup() en chat.php.
 * Ahí ya existe $is_inline (bool). Aquí derivamos $open:
 *  - Inline   => data-open="1" (visible al cargar)
 *  - Floating => data-open="0" (oculto hasta que el usuario lo abra)
 */

/* ======== DERIVACIÓN DE PARÁMETROS ======== */
$open = (isset($is_inline) && $is_inline) ? '1' : '0';

// Título (fallback si no existe la clase)
if (!isset($chat_title)) {
  if (class_exists('PHSBOT_Plugin')) {
    $chat_title = PHSBOT_Plugin::get_setting('chat_title', 'PHSBot');
  } else {
    $g = get_option('phsbot_settings', array());
    $chat_title = isset($g['chat_title']) && $g['chat_title'] !== '' ? $g['chat_title'] : 'PHSBot';
  }
}

// Tamaño de fuente burbujas (con clamp duro 12–22)
if (class_exists('PHSBOT_Plugin')) {
  $fs = intval(PHSBOT_Plugin::get_setting('bubble_font_size', 15));
} else {
  $g  = get_option('phsbot_settings', array());
  $fs = isset($g['bubble_font_size']) ? intval($g['bubble_font_size']) : 15;
}
if ($fs < 12) $fs = 12;
if ($fs > 22) $fs = 22;
/* ======== FIN DERIVACIÓN DE PARÁMETROS ======== */

?>
<!-- ======== INYECCIÓN CSS LOCAL PARA FS ======== -->
<style id="phsbot-bubble-fs-inline">
  /* Forzamos que las burbujas usen la variable; el !important evita pisados ajenos */
  #phsbot-widget .phsbot-bubble,
  #phsbot-widget .phsbot-bubble p,
  #phsbot-widget .phsbot-bubble span,
  #phsbot-widget .phsbot-bubble li {
    font-size: var(--phsbot-bubble-fs, 15px) !important;
    line-height: 1.45;
  }
</style>
<!-- ======== FIN INYECCIÓN CSS LOCAL PARA FS ======== -->


<div id="phsbot-widget"
     class="phsbot-wrap"
     data-open="<?php echo esc_attr($open); ?>"
     data-bubble-fs="<?php echo esc_attr($fs); ?>"
     style="--phsbot-bubble-fs: <?php echo esc_attr($fs); ?>px;">
  <div class="phsbot-card" data-open="<?php echo esc_attr($open); ?>" role="dialog" aria-label="Chat PHSBot" aria-modal="false">
    <div class="phsbot-head" id="phsbot-header" role="button" aria-label="Cerrar/abrir chat">
      <div class="phsbot-head-title">
        <span class="phsbot-head-name"><?php echo esc_html($chat_title); ?></span>
      </div>

      <span class="phsbot-head-x" aria-hidden="true">
        <svg viewBox="0 0 24 24" role="img" focusable="false" aria-hidden="true">
          <g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
            <line x1="6"  y1="6"  x2="18" y2="18"></line>
            <line x1="18" y1="6"  x2="6"  y2="18"></line>
          </g>
        </svg>
      </span>

      <!-- SLOT del toggle de voz (lo monta voice_ui) -->
      <div id="phsbot-voice-slot" class="phsbot-voice-slot" aria-live="polite"></div>
    </div>

    <div class="phsbot-body" id="phsbot-body">
      <!-- Forzamos también en el contenedor de mensajes por si algún tema mete !important en cadena -->
      <div class="phsbot-messages" id="phsbot-messages" style="font-size: <?php echo esc_attr($fs); ?>px !important;"></div>
      <div class="phsbot-typing" id="phsbot-typing" style="display:none"></div>

      <!-- ======== INPUT: textarea + botones (Enviar y Micrófono) ======== -->
      <div class="phsbot-input" role="group" aria-label="<?php echo esc_attr_x('Escritura y envío', 'composer', 'phsbot'); ?>">
        <textarea id="phsbot-q" rows="1" placeholder=""></textarea>

        <!-- ENVIAR: píldora con texto (i18n) + círculo flecha -->
        <button class="phsbot-btn phsbot-send" id="phsbot-send" type="button"
                aria-label="<?php echo esc_attr_x('Enviar', 'Send button label', 'phsbot'); ?>">
          <span class="phsbot-send__label">
            <?php echo esc_html_x('Enviar', 'Send button text', 'phsbot'); ?>
          </span>
          <span class="phsbot-send__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" role="img" focusable="false" aria-hidden="true">
              <polygon points="12,6 18,18 6,18" fill="currentColor"/>
            </svg>
          </span>
        </button>

        <!-- MIC: circular -->
        <button class="phsbot-btn phsbot-mic" id="phsbot-mic" type="button"
                aria-label="<?php echo esc_attr_x('Micrófono', 'Microphone button', 'phsbot'); ?>">
          <svg viewBox="0 0 24 24" role="img" aria-hidden="true" focusable="false">
            <g fill="none" stroke="currentColor" stroke-width="1"
               stroke-linecap="round" stroke-linejoin="round">
              <rect x="9" y="3" width="6" height="10" rx="3"/>
              <path d="M5 11a7 7 0 0 0 14 0"/>
              <line x1="12" y1="17" x2="12" y2="20"/>
              <line x1="9"  y1="21" x2="15" y2="21"/>
            </g>
          </svg>
        </button>
      </div>
      <!-- ======== FIN INPUT ======== -->

    </div>
  </div>
</div>


<!-- Botón lanzador (solo se muestra en modo flotante por el CSS que inyecta chat.php) -->
<button type="button" class="phsbot-launcher" id="phsbot-launcher" aria-label="Abrir chat">
  <svg class="phsbot-launcher-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
    <!-- Robot cuadrado estilo Conversa -->
    <rect x="5" y="3" width="14" height="16" rx="2" ry="2" fill="currentColor"/>
    <rect x="3" y="8" width="2" height="4" rx="1" ry="1" fill="currentColor"/>
    <rect x="19" y="8" width="2" height="4" rx="1" ry="1" fill="currentColor"/>
    <circle cx="9.5" cy="9" r="1.5" fill="#fff"/>
    <circle cx="14.5" cy="9" r="1.5" fill="#fff"/>
    <rect x="9" y="13" width="6" height="2" rx="1" ry="1" fill="#fff"/>
    <rect x="7" y="19" width="3" height="3" rx="0.5" ry="0.5" fill="currentColor"/>
    <rect x="14" y="19" width="3" height="3" rx="0.5" ry="0.5" fill="currentColor"/>
  </svg>
  <span class="phsbot-launcher-text"><?php echo esc_html($chat_title); ?></span>
</button>