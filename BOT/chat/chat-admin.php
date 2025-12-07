<?php
// PHSBOT – chat/chat-admin.php
// Admin: menú y pantalla de configuración "Chat & Widget (FLOAT)".
if (!defined('ABSPATH')) exit;

/* Menús */
add_action('admin_menu', function(){
  add_menu_page('PHSBot', 'PHSBot', 'manage_options', 'phsbot', function(){
    echo '<div class="wrap"><h1>PHSBot</h1></div>';
  }, 'dashicons-format-chat', 60);

  // Submenú "Chat & Widget" eliminado - configuración ahora en módulo principal de Configuración
}, 60);

/* Render ajustes - DEPRECATED: Configuración movida a /config/config.php */
function phsbot_render_chat_settings(){
  // Esta función ya no se usa - toda la configuración está en el módulo de Configuración
  return;
  if (!current_user_can('manage_options')) return;
  $saved = false;

  if ($_SERVER['REQUEST_METHOD']==='POST' && check_admin_referer('phsbot_chat_save','phsbot_chat_nonce')){
    $opt = phsbot_chat_get_settings();

    $prev_welcome = (string)($opt['welcome'] ?? '');

    $opt['model']            = sanitize_text_field($_POST['model'] ?? 'gpt-4.1-mini');
    $opt['temperature']      = max(0.0, min(2.0, floatval($_POST['temperature'] ?? 0.5)));
    $opt['tone']             = sanitize_text_field($_POST['tone'] ?? 'profesional');
    $opt['welcome']          = wp_kses_post($_POST['welcome'] ?? 'Hola, soy PHSBot. ¿En qué puedo ayudarte?');

    $opt['allow_html']       = !empty($_POST['allow_html']) ? 1 : 0;
    $opt['allow_elementor']  = !empty($_POST['allow_elementor']) ? 1 : 0;
    $opt['allow_live_fetch'] = !empty($_POST['allow_live_fetch']) ? 1 : 0;

    $opt['max_history']      = max(1, intval($_POST['max_history'] ?? 10));
    $opt['max_tokens']       = max(200, intval($_POST['max_tokens'] ?? 1400));
    $opt['max_height_vh']    = max(50, min(95, intval($_POST['max_height_vh'] ?? 70)));
    $opt['anchor_paragraph'] = !empty($_POST['anchor_paragraph']) ? 1 : 0;

    $welcome_changed     = ((string)$opt['welcome'] !== $prev_welcome);
    $opt['welcome_i18n'] = array();
    $opt['welcome_hash'] = md5(wp_strip_all_tags($opt['welcome']));

    // Generar traducciones si hay licencia válida (usa API5, no OpenAI directamente)
    $bot_license = (string) phsbot_setting('bot_license_key', '');
    if ($bot_license && $opt['welcome'] !== '') {
      $opt['welcome_i18n'] = phsbot_chat_build_welcome_i18n($opt['welcome']);
    }

    update_option(PHSBOT_CHAT_OPT, $opt);
    if ($welcome_changed) {
      update_option('phsbot_client_reset_version', time());
    }

    $saved = true;
  }

  $opt = phsbot_chat_get_settings();
  ?>
  <div class="wrap phsbot-module-wrap">
    <!-- Header gris estilo GeoWriter -->
    <div class="phsbot-module-header" style="display: flex; justify-content: space-between; align-items: center;">
      <h1 style="margin: 0;">Chat & Widget</h1>
    </div>

    <?php if ($saved): ?>
      <div class="phsbot-alert phsbot-alert-success">Configuración guardada correctamente.</div>
    <?php endif; ?>

    <div class="phsbot-module-container">
      <div class="phsbot-module-content">
        <form method="post">
          <?php wp_nonce_field('phsbot_chat_save','phsbot_chat_nonce'); ?>

          <div class="phsbot-mega-card" style="padding: 32px;">
            <div class="phsbot-section">
              <h2 class="phsbot-section-title">Configuración del Modelo</h2>

              <div class="phsbot-grid-2">
                <div class="phsbot-field">
                  <label class="phsbot-label" for="model">Modelo</label>
                  <input type="text" name="model" id="model" class="phsbot-input-field" value="<?php echo esc_attr($opt['model']); ?>">
                </div>

                <div class="phsbot-field">
                  <label class="phsbot-label" for="temperature">Temperatura (0-2)</label>
                  <input type="number" step="0.1" name="temperature" id="temperature" class="phsbot-input-field" value="<?php echo esc_attr($opt['temperature']); ?>">
                </div>
              </div>

              <div class="phsbot-grid-2">
                <div class="phsbot-field">
                  <label class="phsbot-label" for="max_tokens">Máx. Tokens</label>
                  <input type="number" name="max_tokens" id="max_tokens" class="phsbot-input-field" value="<?php echo esc_attr($opt['max_tokens']); ?>">
                </div>

                <div class="phsbot-field">
                  <label class="phsbot-label" for="max_history">Histórico (turnos)</label>
                  <input type="number" name="max_history" id="max_history" class="phsbot-input-field" value="<?php echo esc_attr($opt['max_history']); ?>">
                </div>
              </div>

              <div class="phsbot-grid-2">
                <div class="phsbot-field">
                  <label class="phsbot-label" for="tone">Tono</label>
                  <input type="text" name="tone" id="tone" class="phsbot-input-field" value="<?php echo esc_attr($opt['tone']); ?>">
                </div>

                <div class="phsbot-field">
                  <label class="phsbot-label" for="max_height_vh">Altura máx. (%VH)</label>
                  <input type="number" name="max_height_vh" id="max_height_vh" class="phsbot-input-field" value="<?php echo esc_attr($opt['max_height_vh']); ?>" min="50" max="95">
                  <p class="phsbot-description">Entre 50 y 95</p>
                </div>
              </div>
            </div>

            <div class="phsbot-section" style="margin-top: 32px;">
              <h2 class="phsbot-section-title">Mensajes</h2>

              <div class="phsbot-field">
                <label class="phsbot-label" for="welcome">Mensaje de Bienvenida</label>
                <textarea name="welcome" id="welcome" rows="2" class="phsbot-textarea-field"><?php echo esc_textarea($opt['welcome']); ?></textarea>
              </div>
            </div>

            <div class="phsbot-section" style="margin-top: 32px;">
              <h2 class="phsbot-section-title">Opciones Avanzadas</h2>

              <div class="phsbot-field">
                <label>
                  <input type="checkbox" name="allow_html" value="1" <?php checked($opt['allow_html'],1); ?>>
                  Permitir HTML en respuestas
                </label>
              </div>

              <div class="phsbot-field">
                <label>
                  <input type="checkbox" name="allow_elementor" value="1" <?php checked($opt['allow_elementor'],1); ?>>
                  Integración con Elementor
                </label>
              </div>

              <div class="phsbot-field">
                <label>
                  <input type="checkbox" name="allow_live_fetch" value="1" <?php checked($opt['allow_live_fetch'],1); ?>>
                  Live fetch (obtener URL actual)
                </label>
              </div>

              <div class="phsbot-field">
                <label>
                  <input type="checkbox" name="anchor_paragraph" value="1" <?php checked($opt['anchor_paragraph'],1); ?>>
                  Anclar al primer párrafo
                </label>
              </div>
            </div>

            <div class="phsbot-section" style="margin-top: 32px; border: none; padding: 0;">
              <button type="submit" class="phsbot-btn-save">Guardar Cambios</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
  <?php
}
