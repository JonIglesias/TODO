<?php
if (!defined('ABSPATH')) exit;

add_action('wp_ajax_ap_verify_license', 'ap_verify_license_ajax');
function ap_verify_license_ajax() {
    ap_verify_ajax_request();
    
    
    $license_key = sanitize_text_field($_POST['license_key'] ?? '');

    if (empty($license_key)) {
        wp_send_json_error(['message' => 'Licencia vacía']);
    }

    // Actualizar licencia antes de verificar (usando encriptación)
    AP_Encryption::update_encrypted_option('ap_license_key', $license_key);
    
    try {
        $api = new AP_API_Client();
        
        $result = $api->verify_license();
        
        if ($result && isset($result['success']) && $result['success'] && isset($result['data']['valid']) && $result['data']['valid']) {
            $data = $result['data'];
            wp_send_json_success([
                'message' => 'Licencia válida',
                'plan' => $data['plan']['name'] ?? 'N/A',
                'expires' => $data['license']['expires_at'] ?? 'N/A'
            ]);
        } else {
            wp_send_json_error(['message' => $result['error'] ?? 'Licencia inválida o caducada']);
        }
    } catch (Exception $e) {
        wp_send_json_error(['message' => 'Error: ' . $e->getMessage()]);
    }
}