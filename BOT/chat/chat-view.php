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
<button type="button" class="phsbot-launcher" id="phsbot-launcher" aria-label="Abrir chat"
        style="--phsbot-launcher-bg: <?php echo esc_attr(phsbot_setting('color_launcher_bg', '#1e1e1e')); ?>;
               --phsbot-launcher-icon: <?php echo esc_attr(phsbot_setting('color_launcher_icon', '#ffffff')); ?>;
               --phsbot-launcher-text: <?php echo esc_attr(phsbot_setting('color_launcher_text', '#ffffff')); ?>;">

  <svg class="phsbot-launcher-icon" viewBox="0 0 300 300" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" preserveAspectRatio="xMidYMid meet">
    <g transform="translate(0,300) scale(0.1,-0.1)">
      <path fill="currentColor" fill-rule="nonzero" stroke="none" d="M1375 2640 c-107 -17 -238 -59 -342 -110 -304 -149 -524 -421 -614 -757 -30 -110 -38 -354 -15 -474 44 -237 140 -420 311 -598 491 -509 1319 -455 1743 113 28 38 28 78 0 110 -13 14 -201 133 -418 266 -217 133 -403 251 -412 262 -25 28 -23 70 5 95 12 11 204 123 427 248 223 125 413 236 423 247 44 52 -4 143 -151 286 -155 150 -345 250 -565 298 -86 19 -308 27 -392 14z m325 -44 c182 -28 338 -92 490 -202 86 -61 201 -176 246 -243 46 -71 41 -77 -186 -203 -388 -216 -611 -346 -638 -372 -20 -19 -35 -46 -38 -66 -11 -69 10 -85 436 -347 217 -134 403 -252 412 -263 27 -33 5 -74 -96 -175 -291 -291 -690 -399 -1086 -294 -402 108 -718 451 -796 866 -23 122 -15 366 14 474 86 311 286 563 562 708 218 114 442 152 680 117z"/>
      <path fill="currentColor" fill-rule="nonzero" stroke="none" d="M1545 2313 c-153 -80 -155 -286 -3 -364 127 -65 287 35 288 179 0 116 -86 202 -202 202 -29 -1 -66 -8 -83 -17z m167 -39 c59 -31 94 -109 78 -178 -18 -80 -78 -128 -160 -128 -93 0 -162 69 -162 162 0 128 128 203 244 144z"/>
      <path fill="currentColor" fill-rule="nonzero" stroke="none" d="M2094 1584 c-105 -51 -39 -210 72 -174 81 27 84 151 3 179 -40 14 -37 14 -75 -5z m78 -51 c22 -20 23 -41 1 -65 -23 -26 -59 -23 -79 7 -15 23 -15 27 0 50 19 29 51 32 78 8z"/>
      <path fill="currentColor" fill-rule="nonzero" stroke="none" d="M2485 1591 c-81 -34 -79 -151 4 -182 31 -12 86 6 106 34 18 25 20 80 4 109 -6 11 -26 26 -44 34 -39 16 -42 16 -70 5z m70 -57 c25 -25 15 -67 -18 -78 -34 -12 -61 1 -65 32 -8 52 46 82 83 46z"/>
    </g>
  </svg>
  <span class="phsbot-launcher-text"><?php echo esc_html($chat_title); ?></span>
</button>