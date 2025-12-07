<?php
if (!defined('ABSPATH')) exit;

// Registrar submenú
add_action('admin_menu', 'ap_campaign_edit_menu', 15);
function ap_campaign_edit_menu() {
    add_submenu_page(
        'autopost-ia',
        'Crear/Editar Campaña',
        'Crear Campaña',
        'manage_options',
        'autopost-campaign-edit',
        'ap_campaign_edit_page'
    );
}

// Función principal que carga la UI
function ap_campaign_edit_page() {
    global $wpdb;

    // IMPORTANTE: Limpiar caché del plan activo para reflejar cambios inmediatos desde la API
    $api_client = new AP_API_Client();
    $api_client->clear_plan_cache();

    $campaign_id = intval($_GET['id'] ?? 0);
    $campaign = null;

    if ($campaign_id) {
        // Cargar campaña existente
        $campaign = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}ap_campaigns WHERE id = %d", $campaign_id)
        );
    }
    // Si no hay ID, $campaign será null y el formulario estará vacío
    // La campaña se creará en el primer guardado (manual o autoguardado)

    require_once __DIR__ . '/edit-ui-clean.php';
}

// Cargar lógicas separadas
require_once __DIR__ . '/edit-save.php';
require_once __DIR__ . '/edit-ia-helpers.php';
require_once __DIR__ . '/edit-ajax.php';

// Enqueue scripts - SISTEMA UNIFICADO V2
add_action('admin_enqueue_scripts', 'ap_campaign_edit_scripts');
function ap_campaign_edit_scripts($hook) {
    if (strpos($hook, 'autopost-campaign-edit') === false) return;

    // Manejador centralizado de errores de API
    wp_enqueue_script(
        'ap-error-handler',
        AP_PLUGIN_URL . 'core/assets/ap-error-handler.js',
        ['jquery'],
        time(),
        true
    );

    // ⭐ NUEVO SISTEMA UNIFICADO DE AUTOGUARDADO
    wp_enqueue_script(
        'ap-campaign-autosave-unified',
        AP_PLUGIN_URL . 'modules/ver_editar_campanas/campaign-autosave-unified.js',
        ['jquery', 'ap-error-handler'],
        '2.0.0', // Versión del nuevo sistema
        true
    );

    wp_localize_script('ap-campaign-autosave-unified', 'apCampaignEdit', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ap_nonce')
    ]);
}