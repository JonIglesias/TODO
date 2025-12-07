<?php
if (!defined('ABSPATH')) exit;

// Registrar menú principal
add_action('admin_menu', 'ap_campaigns_menu', 10);
function ap_campaigns_menu() {
    // Icono SVG personalizado: cuadrado blanco con esquinas redondeadas y G negra
    $icon_svg = 'data:image/svg+xml;base64,' . base64_encode('
        <svg width="20" height="20" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
            <rect x="2" y="2" width="16" height="16" rx="3" fill="#ffffff"/>
            <path d="M 10 4.5 C 7 4.5 4.5 7 4.5 10 C 4.5 13 7 15.5 10 15.5 C 12.5 15.5 14.5 13.8 15.2 11.5 L 15.2 9 L 10 9 L 10 11.2 L 13 11.2 C 12.5 12.5 11.3 13.3 10 13.3 C 8 13.3 6.7 11.8 6.7 10 C 6.7 8.2 8 6.7 10 6.7 C 11.2 6.7 12.2 7.3 12.8 8.2 L 14.5 6.8 C 13.5 5.5 11.9 4.5 10 4.5 Z" fill="#000000"/>
        </svg>
    ');

    // Menú principal
    add_menu_page(
        'GEO Writer',           // Page title
        'GEO Writer',           // Menu title
        'manage_options',
        'autopost-ia',           // Menu slug
        'ap_campaigns_list_page',
        $icon_svg,              // Icono SVG personalizado
        30
    );
    
    // Primer submenu (reemplaza el principal)
    add_submenu_page(
        'autopost-ia',
        'Campañas',
        'Campañas',
        'manage_options',
        'autopost-ia',           // Mismo slug que el padre para que sea el primero
        'ap_campaigns_list_page'
    );
}

// Función principal que carga la UI
function ap_campaigns_list_page() {
    require_once __DIR__ . '/list-ui.php';
}

// Cargar lógicas separadas
require_once __DIR__ . '/list-actions.php';
require_once __DIR__ . '/list-ajax.php';

// Enqueue scripts
add_action('admin_enqueue_scripts', 'ap_campaigns_list_scripts');
function ap_campaigns_list_scripts($hook) {
    // Actualizar hook al nuevo slug
    if ($hook !== 'toplevel_page_autopost-ia') return;
    
    wp_enqueue_script(
        'ap-campaigns-list-js',
        AP_PLUGIN_URL . 'modules/listado_campanas/list.js',
        ['jquery'],
        AP_VERSION,
        true
    );
    
    // UI Blocker para deshabilitar botones
    wp_enqueue_script(
        'ap-ui-blocker-js',
        AP_PLUGIN_URL . 'modules/cola/ui-blocker.js',
        ['jquery'],
        AP_VERSION,
        true
    );
    
    wp_localize_script('ap-campaigns-list-js', 'apCampaigns', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'admin_url' => admin_url(),
        'nonce' => wp_create_nonce('ap_nonce'),
        'confirm_delete' => '¿Seguro que deseas eliminar esta campaña?',
        'confirm_delete_multiple' => '¿Seguro que deseas eliminar las campañas seleccionadas?'
    ]);
    
    wp_localize_script('ap-ui-blocker-js', 'apQueue', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ap_nonce')
    ]);
}