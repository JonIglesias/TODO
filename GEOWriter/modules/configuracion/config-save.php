<?php
if (!defined('ABSPATH')) exit;

add_action('admin_init', 'ap_config_save_handler');
function ap_config_save_handler() {
    if (!isset($_POST['ap_config_nonce'])) return;
    
    if (!wp_verify_nonce($_POST['ap_config_nonce'], 'ap_save_config')) {
        wp_die('Nonce inválido');
    }
    
    if (!current_user_can('manage_options')) {
        wp_die('Permisos insuficientes');
    }
    
    $fields = [
        'ap_api_url',
        'ap_license_key',
        'ap_unsplash_key',
        'ap_pixabay_key',
        'ap_pexels_key',
        'ap_company_desc'
    ];
    
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            $post_value = $_POST[$field] ?? '';

            if ($field === 'ap_company_desc') {
                $value = sanitize_textarea_field($post_value);
            } else {
                $value = sanitize_text_field($post_value);
            }

            // Guardar licencia con encriptación
            if ($field === 'ap_license_key') {
                AP_Encryption::update_encrypted_option($field, $value);
            } else {
                update_option($field, $value);
            }
        }
    }

    add_settings_error(
        'ap_config_messages',
        'ap_config_updated',
        'Configuración guardada correctamente',
        'updated'
    );
}

add_action('admin_notices', 'ap_config_notices');
function ap_config_notices() {
    settings_errors('ap_config_messages');
}
