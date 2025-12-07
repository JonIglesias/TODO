<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'ap_execute_menu', 30);
function ap_execute_menu() {
    add_submenu_page(
        null,
        'Ejecutar Cola',
        'Ejecutar',
        'manage_options',
        'autopost-execute',
        'ap_execute_page'
    );
}

// Cargar scripts y estilos para el módulo de ejecución
add_action('admin_enqueue_scripts', 'ap_execute_enqueue_scripts');
function ap_execute_enqueue_scripts($hook) {
    // Solo cargar en la página de ejecución
    if ($hook !== 'admin_page_autopost-execute') {
        return;
    }

    // Manejador centralizado de errores de API
    wp_enqueue_script(
        'ap-error-handler',
        AP_PLUGIN_URL . 'core/assets/ap-error-handler.js',
        ['jquery'],
        time(),
        true
    );

    // Cargar ui-blocker.js para el botón de cancelar proceso
    wp_enqueue_script(
        'ap-ui-blocker-js',
        AP_PLUGIN_URL . 'modules/cola/ui-blocker.js',
        ['jquery', 'ap-error-handler'],
        AP_VERSION,
        true
    );

    // Pasar datos al script
    $campaign_id = intval($_GET['campaign_id'] ?? 0);
    wp_localize_script('ap-ui-blocker-js', 'apQueue', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ap_nonce'),
        'campaign_id' => $campaign_id
    ]);
}

function ap_execute_page() {
    $campaign_id = intval($_GET['campaign_id'] ?? 0);
    require_once __DIR__ . '/execute-ui.php';
}

require_once __DIR__ . '/execute-process.php';
