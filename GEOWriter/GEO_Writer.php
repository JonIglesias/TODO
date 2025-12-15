<?php
/**
 * Plugin Name: GEO Writer - V7.0
 * Description: Sistema profesional de generación automática con IA - Versión mejorada con arquitectura optimizada
 * Version: 7.0.90
 * Author: Bocetos Marketing
 * Text Domain: GEO Writer - V7.0
 * Requires PHP: 7.4
 * Requires at least: 5.8
 */

if (!defined('ABSPATH')) {
    exit;
}

// Constantes globales
define('AP_VERSION', '7.0.90');
define('AP_MIN_PHP', '7.4');
define('AP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AP_CORE_DIR', AP_PLUGIN_DIR . 'core/');
define('AP_MODULES_DIR', AP_PLUGIN_DIR . 'modules/');
define('AP_API_URL_DEFAULT', 'https://www.bocetosmarketing.com/api_claude_5');

// Constantes de configuración
define('AP_MAX_TIMEOUT', 120); // Timeout máximo de 2 minutos
define('AP_RATE_LIMIT_WINDOW', 300); // 5 minutos
define('AP_CACHE_DURATION', HOUR_IN_SECONDS);
define('AP_LOG_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('AP_BATCH_SIZE', 5);
define('AP_MAX_RETRIES', 3);
define('AP_RETRY_DELAY', 2);

// Suprimir deprecated/notice solo en producción (PHP 8+ compatibility)
// Esto evita warnings de WordPress core y otros plugins con código legacy
if (!defined('WP_DEBUG') || !WP_DEBUG) {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_STRICT);
    @ini_set('display_errors', '0');
}

// CARGAR SANITIZADOR POST INMEDIATAMENTE (PHP 8+ fix)
require_once AP_PLUGIN_DIR . 'core/bootstrap/ap-sanitize-post.php';

/**
 * Sistema de actualizaciones automáticas desde GitHub
 */
// Cargar Parsedown (requerido por Plugin Update Checker)
if (!class_exists('Parsedown')) {
    require_once AP_PLUGIN_DIR . 'lib/Parsedown.php';
}

require AP_PLUGIN_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$geowriterUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/bocetosmarketing/geowriter',
    __FILE__,
    'geowriter'
);

// Opcional: establecer rama específica para updates (por defecto usa releases)
// $geowriterUpdateChecker->setBranch('main');

/**
 * Verificar versión PHP
 */
if (version_compare(PHP_VERSION, AP_MIN_PHP, '<')) {
    add_action('admin_notices', function(): void {
        echo '<div class="error"><p>'
            . sprintf(
                esc_html__('GEO Writer requiere PHP %s o superior. Versión actual: %s', 'autopost-v2'),
                AP_MIN_PHP,
                PHP_VERSION
            )
            . '</p></div>';
    });
    return;
}

/**
 * Verificar dependencias del sistema
 */
function ap_check_dependencies(): bool {
    $errors = [];

    if (!function_exists('curl_init')) {
        $errors[] = __('cURL no disponible', 'autopost-v2');
    }

    if (!function_exists('json_encode')) {
        $errors[] = __('JSON no disponible', 'autopost-v2');
    }

    if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
        $errors[] = __('WP Cron desactivado', 'autopost-v2');
    }

    if (!empty($errors)) {
        add_action('admin_notices', function() use ($errors): void {
            echo '<div class="error"><p>'
                . esc_html__('AutoPost IA - Dependencias faltantes:', 'autopost-v2')
                . ' ' . esc_html(implode(', ', $errors))
                . '</p></div>';
        });
        return false;
    }

    return true;
}

/**
 * Activación del plugin
 */
register_activation_hook(__FILE__, 'ap_activate');
function ap_activate(): void {
    require_once AP_PLUGIN_DIR . 'core/bootstrap/ap-install.php';
    ap_create_tables();
    ap_set_default_options();

    // Limpiar caché
    ap_clear_all_caches();
}

/**
 * Desactivación del plugin
 */
register_deactivation_hook(__FILE__, 'ap_deactivate');
function ap_deactivate(): void {
    wp_clear_scheduled_hook('ap_process_queue');
    ap_clear_all_caches();
}

/**
 * Limpiar todos los cachés del plugin
 */
function ap_clear_all_caches(): void {
    delete_transient('ap_active_plan_v11');
    delete_transient('ap_license_info');
    wp_cache_flush();
}

/**
 * Inicialización del plugin
 */
add_action('plugins_loaded', 'ap_init');
function ap_init(): void {
    // Verificar dependencias
    if (!ap_check_dependencies()) {
        return;
    }

    // Cargar utilidades de encriptación y seguridad
    require_once AP_PLUGIN_DIR . 'core/security/ap-encryption.php';
    require_once AP_PLUGIN_DIR . 'core/security/ap-rate-limiter.php';

    // Cargar cliente API
    require_once AP_PLUGIN_DIR . 'core/api/ap-api-client.php';

    // Ejecutar migraciones
    require_once AP_PLUGIN_DIR . 'core/bootstrap/ap-migrations.php';
    ap_run_migrations();

    // Cargar helpers
    require_once AP_PLUGIN_DIR . 'core/bootstrap/ap-nichos-helper.php';
    require_once AP_PLUGIN_DIR . 'core/bootstrap/ap-campaign-helpers.php';

    // Cargar módulos
    require_once AP_PLUGIN_DIR . 'core/bootstrap/ap-modules-loader.php';
    ap_load_modules();

    // Cargar assets
    add_action('admin_enqueue_scripts', 'ap_enqueue_global_styles');

    // Cargar textos de traducción
    load_plugin_textdomain('autopost-v2', false, dirname(plugin_basename(__FILE__)) . '/languages');

    // Hook para inyectar Schema.org JSON-LD en posts
    add_action('wp_head', 'ap_inject_schema_markup', 1);
}

/**
 * Encolar estilos globales
 */
function ap_enqueue_global_styles(): void {
    wp_enqueue_style(
        'ap-modern-ui',
        AP_PLUGIN_URL . 'core/assets/modern-ui.css',
        [],
        AP_VERSION
    );

    wp_enqueue_style(
        'ap-modules-unified',
        AP_PLUGIN_URL . 'core/assets/modules-unified.css',
        ['ap-modern-ui'],
        AP_VERSION
    );

    // Shepherd.js CSS desde CDN
    wp_enqueue_style(
        'shepherd-js',
        'https://cdn.jsdelivr.net/npm/shepherd.js@11.2.0/dist/css/shepherd.css',
        [],
        '11.2.0'
    );

    // Estilos personalizados de Shepherd para GEOWriter
    wp_enqueue_style(
        'ap-shepherd-custom',
        AP_PLUGIN_URL . 'core/assets/ap-shepherd-custom.css',
        ['shepherd-js'],
        AP_VERSION
    );

    // Shepherd.js Library desde CDN
    wp_enqueue_script(
        'shepherd-js',
        'https://cdn.jsdelivr.net/npm/shepherd.js@11.2.0/dist/js/shepherd.min.js',
        [],
        '11.2.0',
        true
    );

    // Tours personalizados de GEOWriter
    wp_enqueue_script(
        'ap-shepherd-tours',
        AP_PLUGIN_URL . 'core/assets/ap-shepherd-tours.js',
        ['jquery', 'shepherd-js'],
        AP_VERSION,
        true
    );
}

/**
 * Inyectar Schema.org JSON-LD en el <head> de posts generados por AutoPost
 */
function ap_inject_schema_markup(): void {
    if (!is_single()) {
        return;
    }

    global $post;
    if (!$post) {
        return;
    }

    $schema = get_post_meta($post->ID, '_autopost_schema_markup', true);

    if (!empty($schema) && is_string($schema)) {
        echo "\n<!-- AutoPost IA - Schema.org JSON-LD -->\n";
        echo '<script type="application/ld+json">' . "\n";
        echo wp_kses_post($schema) . "\n";
        echo '</script>' . "\n";
    }
}