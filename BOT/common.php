<?php
// File: common.php
if (!defined('ABSPATH')) exit;

/* ===========================================================================
 *  PHSBot – Common Loader (compatible; prioridad carpeta/<mod>.php)
 * ========================================================================== */

if (!defined('PHSBOT_DIR'))  define('PHSBOT_DIR',  plugin_dir_path(__FILE__));
if (!defined('PHSBOT_URL'))  define('PHSBOT_URL',  plugins_url('', __FILE__));
if (!defined('PHSBOT_SLUG')) define('PHSBOT_SLUG', 'phsbot');

/* I18N */
if (!function_exists('phsbot_load_textdomain')) {
    function phsbot_load_textdomain() {
        load_plugin_textdomain('phsbot', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
}
add_action('init', 'phsbot_load_textdomain');

/* Parches opcionales */
if (!function_exists('phsbot_load_patches')) {
    function phsbot_load_patches() {
        $patch = PHSBOT_DIR . 'patches/hardening.php';
        if (file_exists($patch)) require_once $patch;
    }
}
phsbot_load_patches();

/* Loader compatible (carpeta primero) */
if (!function_exists('phsbot_load_module')) {
    /**
     * Busca en:
     *   1) /<mod>/<mod>.php   (estructura nueva)
     *   2) /<mod>.php         (legacy raíz)
     *   3) /modules/<mod>.php (legacy muy antiguo)
     */
    function phsbot_load_module($mod) {
        $base = PHSBOT_DIR;
        $paths = array(
            $base . $mod . '/' . $mod . '.php', // carpeta primero
            $base . $mod . '.php',
            $base . 'modules/' . $mod . '.php',
        );
        foreach ($paths as $p) {
            if (file_exists($p)) { require_once $p; return true; }
        }
        return false;
    }
}

/* Lista de módulos (sin settings; el panel lo pinta PHSBOT_Plugin en phsbot.php) */
$__phsbot_modules = array(
    'menu',
    'config',
    'styles',
    'chat',
    'kb',
    'leads',
    'estadisticas',
    'integrations',
    'inject',
    'logs',
    'formuchat',
    'voice_ui',
    'mobile_patch',
);

$__phsbot_modules = apply_filters('phsbot_modules', $__phsbot_modules);

/* Carga en orden */
foreach ($__phsbot_modules as $__m) { phsbot_load_module($__m); }
unset($__phsbot_modules, $__m);

/* Utils */
if (!function_exists('phsbot_array_get')) {
    function phsbot_array_get($arr, $key, $default = null) {
        return (is_array($arr) && array_key_exists($key, $arr)) ? $arr[$key] : $default;
    }
}
if (!function_exists('phsbot_is_admin_screen')) {
    function phsbot_is_admin_screen($needle) {
        if (!is_admin()) return false;
        if (function_exists('get_current_screen')) {
            $s = get_current_screen();
            if ($s && !empty($s->id)) return (stripos($s->id, $needle) !== false);
        }
        if (isset($_GET['page'])) {
            $p = strtolower(sanitize_text_field(wp_unslash($_GET['page'])));
            return (stripos($p, $needle) !== false);
        }
        return false;
    }
}