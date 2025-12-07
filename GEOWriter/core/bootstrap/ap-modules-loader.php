<?php
/**
 * Cargador de módulos del plugin
 *
 * @package AutoPost
 * @version 7.0.0
 */

if (!defined('ABSPATH')) exit;

/**
 * Cargar todos los módulos del plugin
 *
 * @return void
 */
function ap_load_modules(): void {
    $modules_dir = AP_PLUGIN_DIR . 'modules/';

    if (!is_dir($modules_dir)) {
        return;
    }

    $modules = scandir($modules_dir);

    foreach ($modules as $module) {
        if ($module === '.' || $module === '..') continue;

        $module_path = $modules_dir . $module;

        if (!is_dir($module_path)) continue;

        // Buscar archivo principal (mismo nombre que carpeta)
        $main_file = $module_path . '/' . $module . '.php';

        if (file_exists($main_file)) {
            require_once $main_file;
        } else {
        }
    }

    do_action('ap_modules_loaded');
}

/**
 * Variables globales compartidas por todos los módulos
 *
 * @return array Array con variables de configuración global
 */
function ap_get_global_vars(): array {
    return [
        'api_url' => get_option('ap_api_url', AP_API_URL_DEFAULT),
        'license_key' => AP_Encryption::get_encrypted_option('ap_license_key', ''),
        'unsplash_key' => AP_Encryption::get_encrypted_option('ap_unsplash_key', ''),
        'pixabay_key' => AP_Encryption::get_encrypted_option('ap_pixabay_key', ''),
        'pexels_key' => AP_Encryption::get_encrypted_option('ap_pexels_key', '')
    ];
}

/**
 * Validar nonce + capability para AJAX con rate limiting
 *
 * @param string $capability Capacidad requerida (por defecto 'manage_options')
 * @param bool $apply_rate_limit Si aplicar rate limiting (por defecto false)
 * @param int $rate_limit Número de peticiones permitidas (por defecto 30)
 * @return void Termina ejecución si falla validación
 */
function ap_verify_ajax_request(string $capability = 'manage_options', bool $apply_rate_limit = false, int $rate_limit = 30): void {
    // Verificar nonce
    check_ajax_referer('ap_nonce', 'nonce');

    // Verificar permisos
    if (!current_user_can($capability)) {
        wp_send_json_error(['message' => 'Permisos insuficientes'], 403);
    }

    // Aplicar rate limiting si se solicita
    if ($apply_rate_limit) {
        $action = sanitize_text_field($_POST['action'] ?? 'unknown');
        AP_Rate_Limiter::enforce($action, $rate_limit, AP_RATE_LIMIT_WINDOW);
    }
}
