<?php
if (!defined('ABSPATH')) exit;

add_action('wp_ajax_ap_get_usage', 'ap_get_usage_ajax');
function ap_get_usage_ajax() {
    ap_verify_ajax_request();
    
    $license_key = get_option('ap_license_key', '');
    
    if (empty($license_key)) {
        wp_send_json_error(['message' => 'Configure su licencia primero']);
    }
    
    $api = new AP_API_Client();
    
    // Obtener parámetros de fecha
    $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : null;
    $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : null;
    
    // Obtener estadísticas detalladas
    $stats_result = $api->get_detailed_stats($date_from, $date_to);
    
    // Obtener información de uso básica
    $usage_result = $api->get_usage_info();
    
    // Obtener plan activo
    $active_plan = $api->get_active_plan();
    
    if ($usage_result && isset($usage_result['success']) && $usage_result['success']) {
        $data = $usage_result['data'];
        
        $response = [
            'plan' => $data['plan_name'] ?? 'N/A',
            'tokens_limit' => $data['tokens_limit'] ?? 0,
            'tokens_used' => $data['tokens_used'] ?? 0,
            'posts_limit' => $data['posts_limit'] ?? 0,
            'posts_used' => $data['posts_used'] ?? 0,
            'price' => $data['plan_price'] ?? 0,
            'is_snapshot' => $data['is_snapshot'] ?? false
        ];
        
        // Agregar estadísticas detalladas si están disponibles
        if ($stats_result && isset($stats_result['success']) && $stats_result['success']) {
            $response['detailed_stats'] = $stats_result['data'];
        }
        
        // Si hay plan activo, añadir información adicional
        if ($active_plan) {
            $response['plan_details'] = [
                'name' => $active_plan['name'] ?? 'N/A',
                'price' => $active_plan['price'] ?? 0,
                'limits' => $active_plan['limits'] ?? [],
                'timing' => $active_plan['timing'] ?? [],
                'is_snapshot' => isset($active_plan['_is_snapshot'])
            ];
        }
        
        wp_send_json_success($response);
    } else {
        wp_send_json_error(['message' => $usage_result['error'] ?? 'No se pudo obtener información de uso']);
    }
}
