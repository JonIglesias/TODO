<?php
if (!defined('ABSPATH')) exit;

// Registrar submenú oculto (se accede desde campañas)
add_action('admin_menu', 'ap_queue_menu', 25);
function ap_queue_menu() {
    add_submenu_page(
        null, // Oculto del menú
        'Cola de Posts',
        'Cola',
        'manage_options',
        'autopost-queue',
        'ap_queue_page'
    );
}

// Función principal
function ap_queue_page() {
    $campaign_id = intval($_GET['campaign_id'] ?? 0);
    
    if (!$campaign_id) {
        echo '<div class="wrap"><h1>Error</h1><p>Campaña no especificada</p></div>';
        return;
    }
    
    global $wpdb;
    $campaign = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$wpdb->prefix}ap_campaigns WHERE id = %d", $campaign_id)
    );
    
    if (!$campaign) {
        echo '<div class="wrap"><h1>Error</h1><p>Campaña no encontrada</p></div>';
        return;
    }
    
    require_once __DIR__ . '/queue-ui.php';
}

// Cargar lógicas
require_once __DIR__ . '/bloqueo-system.php';
require_once __DIR__ . '/queue-generate.php';
require_once __DIR__ . '/queue-images.php';
require_once __DIR__ . '/queue-ajax.php';
require_once __DIR__ . '/queue-row-template.php'; // Template único para filas

// Scripts
add_action('admin_enqueue_scripts', 'ap_queue_scripts');
function ap_queue_scripts($hook) {
    if ($hook !== 'admin_page_autopost-queue') return;
    
    // jQuery UI Sortable para drag & drop
    wp_enqueue_script('jquery-ui-sortable');

    // Media uploader para biblioteca WP
    wp_enqueue_media();

    // Manejador centralizado de errores de API
    wp_enqueue_script(
        'ap-error-handler',
        AP_PLUGIN_URL . 'core/assets/ap-error-handler.js',
        ['jquery'],
        time(), // Timestamp para forzar recarga
        true
    );

    wp_enqueue_script(
        'ap-queue-js',
        AP_PLUGIN_URL . 'modules/cola/queue.js',
        ['jquery', 'jquery-ui-sortable', 'ap-error-handler'],
        time(), // Timestamp para forzar recarga
        true
    );
    
    // UI Blocker para bloqueo global
    wp_enqueue_script(
        'ap-ui-blocker-js',
        AP_PLUGIN_URL . 'modules/cola/ui-blocker.js',
        ['jquery'],
        time(), // Timestamp para forzar recarga
        true
    );
    
    $campaign_id = intval($_GET['campaign_id'] ?? 0);
    
    // Obtener proveedores configurados
    $providers = [];
    if (get_option('ap_unsplash_key')) $providers['unsplash'] = 'Unsplash';
    if (get_option('ap_pixabay_key')) $providers['pixabay'] = 'Pixabay';
    if (get_option('ap_pexels_key')) $providers['pexels'] = 'Pexels';
    
    wp_localize_script('ap-queue-js', 'apQueue', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ap_nonce'),
        'campaign_id' => $campaign_id,
        'image_providers' => $providers
    ]);
    
    // Localize para ui-blocker también
    wp_localize_script('ap-ui-blocker-js', 'apQueue', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ap_nonce'),
        'campaign_id' => $campaign_id
    ]);
}
