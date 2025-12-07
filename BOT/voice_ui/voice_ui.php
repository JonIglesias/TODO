<?php
if (!defined('ABSPATH')) exit;

/**
 * PHSBOT – Voice UI (desde cero)
 * - No modifica el DOM del chat salvo inyectar el botón de mic junto a #phsbot-send
 * - Encola CSS/JS solo en el front
 * - Añade visualizador de onda (voice_viz.js + voice_viz.css) separado del core de Voice UI
 */


/* ======== ENCOLAR ASSETS DE VOICE UI (+ VISUALIZADOR) ======== */
/**
 * Encola los assets de Voice UI y, si existen, los del visualizador de audio.
 * Carga solo en front. El visualizador depende de voice_ui para respetar el orden.
 */
function phsbot_voice_ui_enqueue_minimal() {
    if (is_admin()) return;

    $base_dir = trailingslashit(PHSBOT_DIR) . 'voice_ui/';
    $base_url = trailingslashit(PHSBOT_URL) . 'voice_ui/';

    // Core Voice UI
    $ui_css_rel = 'voice_ui.css';
    $ui_js_rel  = 'voice_ui.js';

    $ui_css = $base_url . $ui_css_rel;
    $ui_js  = $base_url . $ui_js_rel;

    $ui_css_v = @filemtime($base_dir . $ui_css_rel) ?: (defined('PHSBOT_VERSION') ? PHSBOT_VERSION : time());
    $ui_js_v  = @filemtime($base_dir . $ui_js_rel)  ?: (defined('PHSBOT_VERSION') ? PHSBOT_VERSION : time());

    wp_enqueue_style('phsbot-voice-ui', $ui_css, array(), $ui_css_v);
    wp_enqueue_script('phsbot-voice-ui', $ui_js, array(), $ui_js_v, true);
    if (function_exists('wp_script_add_data')) {
        wp_script_add_data('phsbot-voice-ui', 'defer', true);
    }

    // Visualizador de audio (opcional, solo si los archivos existen)
    $viz_js_rel  = 'voice_viz.js';
    $viz_css_rel = 'voice_viz.css';

    $viz_js_path  = $base_dir . $viz_js_rel;
    $viz_css_path = $base_dir . $viz_css_rel;

    if (file_exists($viz_js_path)) {
        $viz_js  = $base_url . $viz_js_rel;
        $viz_js_v = @filemtime($viz_js_path) ?: $ui_js_v;

        // Depende del core para que la clase .is-recording ya esté gestionada
        wp_enqueue_script('phsbot-voice-viz', $viz_js, array('phsbot-voice-ui'), $viz_js_v, true);
        if (function_exists('wp_script_add_data')) {
            wp_script_add_data('phsbot-voice-viz', 'defer', true);
        }
    }

    if (file_exists($viz_css_path)) {
        $viz_css  = $base_url . $viz_css_rel;
        $viz_css_v = @filemtime($viz_css_path) ?: $ui_css_v;

        wp_enqueue_style('phsbot-voice-viz', $viz_css, array('phsbot-voice-ui'), $viz_css_v);
    }
} // ======== FIN ENCOLAR ASSETS DE VOICE UI (+ VISUALIZADOR) ========


add_action('wp_enqueue_scripts', 'phsbot_voice_ui_enqueue_minimal', 60);
