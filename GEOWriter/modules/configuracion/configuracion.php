<?php
if (!defined('ABSPATH')) exit;

// Registrar menú
add_action('admin_menu', 'ap_config_menu', 20);
function ap_config_menu() {
    add_submenu_page(
        'autopost-ia',
        'Configuración',
        'Configuración',
        'manage_options',
        'autopost-config',
        'ap_config_page'
    );
}

// Función principal que carga la UI
function ap_config_page() {
    require_once __DIR__ . '/config-ui.php';
}

// Cargar lógicas separadas
require_once __DIR__ . '/config-save.php';
require_once __DIR__ . '/config-license.php';
require_once __DIR__ . '/config-usage.php';

// Enqueue JS
add_action('admin_enqueue_scripts', 'ap_config_scripts', 10);
function ap_config_scripts($hook) {
    // LOG EN TODAS LAS PÁGINAS para detectar el hook correcto
    if (strpos($hook, 'autopost') !== false || strpos($hook, 'campaigns') !== false || strpos($hook, 'config') !== false) {
    }
    
    // Verificar hook con strpos (más flexible)
    if (strpos($hook, 'autopost-config') === false) {
        return;
    }
    
    
    $config_data = [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ap_nonce'),
        'hook' => $hook
    ];
    
    // Manejador centralizado de errores de API
    wp_enqueue_script(
        'ap-error-handler',
        AP_PLUGIN_URL . 'core/assets/ap-error-handler.js',
        ['jquery'],
        time(),
        true
    );

    // Config script
    wp_enqueue_script(
        'ap-config-js',
        AP_PLUGIN_URL . 'modules/configuracion/config.js',
        ['jquery', 'ap-error-handler'],
        AP_VERSION . '-' . time(),
        true
    );
    
    wp_localize_script('ap-config-js', 'apConfig', $config_data);
    
}