<?php
/**
 * GEO Writer - Configuración Centralizada
 *
 * Este archivo contiene todas las constantes de configuración del plugin.
 * Para cambiar configuraciones, edita este archivo.
 *
 * @package GEOWriter
 * @version 7.0.90
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================================================
// CONFIGURACIÓN DE API
// ============================================================================

/**
 * URL de la API de GEO Writer
 *
 * IMPORTANTE: Esta es la única ubicación donde debes configurar la URL de la API.
 * No modifiques esta URL en ningún otro archivo.
 *
 * La URL puede ser:
 * - Con index.php: https://ejemplo.com/api_claude_5/index.php
 * - Sin index.php: https://ejemplo.com/api_claude_5
 *
 * El sistema detectará automáticamente el formato y ajustará las peticiones.
 */
define('AP_API_URL_DEFAULT', 'https://www.bocetosmarketing.com/api_claude_5');

// ============================================================================
// CONFIGURACIÓN DE VERSIÓN
// ============================================================================

/**
 * Versión del plugin
 */
define('AP_VERSION', '7.0.90');

/**
 * Versión mínima de PHP requerida
 */
define('AP_MIN_PHP', '7.4');

// ============================================================================
// RUTAS DEL PLUGIN
// ============================================================================

/**
 * Directorio raíz del plugin
 */
define('AP_PLUGIN_DIR', plugin_dir_path(__FILE__));

/**
 * URL raíz del plugin
 */
define('AP_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Directorio del core
 */
define('AP_CORE_DIR', AP_PLUGIN_DIR . 'core/');

/**
 * Directorio de módulos
 */
define('AP_MODULES_DIR', AP_PLUGIN_DIR . 'modules/');

// ============================================================================
// CONFIGURACIÓN DE RENDIMIENTO Y LÍMITES
// ============================================================================

/**
 * Timeout máximo para peticiones API (en segundos)
 */
define('AP_MAX_TIMEOUT', 120); // 2 minutos

/**
 * Ventana de rate limiting (en segundos)
 */
define('AP_RATE_LIMIT_WINDOW', 300); // 5 minutos

/**
 * Duración de caché (en segundos)
 */
define('AP_CACHE_DURATION', HOUR_IN_SECONDS); // 1 hora

/**
 * Tamaño máximo de logs (en bytes)
 */
define('AP_LOG_MAX_SIZE', 10 * 1024 * 1024); // 10MB

/**
 * Tamaño de lote para procesamiento
 */
define('AP_BATCH_SIZE', 5);

/**
 * Número máximo de reintentos
 */
define('AP_MAX_RETRIES', 3);

/**
 * Delay entre reintentos (en segundos)
 */
define('AP_RETRY_DELAY', 2);

// ============================================================================
// NOTAS PARA DESARROLLADORES
// ============================================================================

/**
 * CAMBIAR URL DE API:
 * Solo edita la constante AP_API_URL_DEFAULT en este archivo.
 * El plugin usará automáticamente la nueva URL en todos los componentes.
 *
 * ENTORNOS DIFERENTES:
 * Para desarrollo/staging, puedes crear un config-local.php que sobrescriba
 * estas constantes. Asegúrate de no commitearlo al repositorio.
 *
 * CONFIGURACIÓN AVANZADA:
 * Los usuarios pueden sobrescribir la URL de API desde:
 * - Panel de administración: GEO Writer > Configuración > URL de la API
 * - La configuración se guarda en: wp_options.ap_api_url
 * - Si no hay configuración manual, se usa AP_API_URL_DEFAULT
 */
