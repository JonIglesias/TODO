<?php
/**
 * PHSBOT - Módulo de Estadísticas
 *
 * Muestra estadísticas de uso del chatbot: conversaciones, tokens, uso por día, etc.
 *
 * @version 1.0
 */

if (!defined('ABSPATH')) exit;

// El menú se registra en menu.php
// Función principal que carga la UI
function phsbot_stats_page() {
    require_once __DIR__ . '/stats-ui.php';
}

// Cargar AJAX handlers
require_once __DIR__ . '/stats-ajax.php';

// Enqueue scripts
add_action('admin_enqueue_scripts', 'phsbot_stats_scripts');
function phsbot_stats_scripts($hook) {
    if (strpos($hook, 'phsbot-estadisticas') === false) {
        return;
    }

    $version = defined('PHSBOT_VERSION') ? PHSBOT_VERSION : '1.4';

    // CSS unificado (cargar primero)
    wp_enqueue_style(
        'phsbot-modules-unified',
        plugin_dir_url(dirname(__FILE__)) . 'core/assets/modules-unified.css',
        [],
        $version
    );

    // Chart.js para gráficas
    wp_enqueue_script(
        'chartjs',
        'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
        [],
        '4.4.0',
        true
    );

    // Script del módulo
    wp_enqueue_script(
        'phsbot-stats-js',
        plugin_dir_url(__FILE__) . 'stats.js',
        ['jquery', 'chartjs'],
        $version . '-' . time(),
        true
    );

    wp_localize_script('phsbot-stats-js', 'phsbotStats', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('phsbot_stats_nonce')
    ]);

    // Estilos del módulo
    wp_enqueue_style(
        'phsbot-stats-css',
        plugin_dir_url(__FILE__) . 'stats.css',
        [],
        $version
    );
}
