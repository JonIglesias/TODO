<?php
if (!defined('ABSPATH')) exit;

// Registrar menú de estadísticas
add_action('admin_menu', 'ap_stats_menu', 25);
function ap_stats_menu() {
    add_submenu_page(
        'autopost-ia',
        'Estadísticas',
        'Estadísticas',
        'manage_options',
        'autopost-estadisticas',
        'ap_stats_page'
    );
}

// Función principal que carga la UI
function ap_stats_page() {
    require_once __DIR__ . '/stats-ui.php';
}

// Cargar AJAX handlers
require_once __DIR__ . '/stats-ajax.php';

// Enqueue scripts
add_action('admin_enqueue_scripts', 'ap_stats_scripts');
function ap_stats_scripts($hook) {
    if (strpos($hook, 'autopost-estadisticas') === false) {
        return;
    }
    
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
        'ap-stats-js',
        AP_PLUGIN_URL . 'modules/estadisticas/stats.js',
        ['jquery', 'chartjs'],
        AP_VERSION . '-' . time(),
        true
    );
    
    wp_localize_script('ap-stats-js', 'apStats', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ap_nonce')
    ]);
}
