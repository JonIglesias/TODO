<?php
if (!defined('ABSPATH')) exit;

// Eliminar campaña
add_action('wp_ajax_ap_delete_campaign', 'ap_delete_campaign_ajax');
function ap_delete_campaign_ajax() {
    ap_verify_ajax_request();
    
    $campaign_id = intval($_POST['campaign_id'] ?? 0);
    
    if (!$campaign_id) {
        wp_send_json_error(['message' => 'ID inválido']);
    }
    
    if (AP_Campaign_Actions::delete_campaign($campaign_id)) {
        wp_send_json_success(['message' => 'Campaña eliminada']);
    } else {
        wp_send_json_error(['message' => 'Error al eliminar']);
    }
}

// Eliminar múltiples
add_action('wp_ajax_ap_delete_campaigns', 'ap_delete_campaigns_ajax');
function ap_delete_campaigns_ajax() {
    ap_verify_ajax_request();
    
    $campaign_ids = $_POST['campaign_ids'] ?? [];
    
    if (empty($campaign_ids)) {
        wp_send_json_error(['message' => 'No hay campañas seleccionadas']);
    }
    
    $deleted = AP_Campaign_Actions::delete_multiple_campaigns($campaign_ids);
    
    wp_send_json_success([
        'message' => "Se eliminaron {$deleted} campañas",
        'deleted' => $deleted
    ]);
}

// Clonar campaña
add_action('wp_ajax_ap_clone_campaign', 'ap_clone_campaign_ajax');
function ap_clone_campaign_ajax() {
    ap_verify_ajax_request();
    
    $campaign_id = intval($_POST['campaign_id'] ?? 0);
    
    if (!$campaign_id) {
        wp_send_json_error(['message' => 'ID inválido']);
    }
    
    $new_id = AP_Campaign_Actions::clone_campaign($campaign_id);
    
    if ($new_id) {
        wp_send_json_success([
            'message' => 'Campaña clonada',
            'new_id' => $new_id,
            'redirect' => admin_url('admin.php?page=autopost-campaign-edit&id=' . $new_id)
        ]);
    } else {
        wp_send_json_error(['message' => 'Error al clonar']);
    }
}
