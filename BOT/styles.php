<?php
// File: styles.php
if (!defined('ABSPATH')) exit;

/* -------------------------------------------
 * 1) FRONT: hoja de estilos del widget/chat
 * ----------------------------------------- */
add_action('wp_enqueue_scripts', function () {
    if (is_admin() || !function_exists('phsbot_setting') || !phsbot_setting('chat_active', true)) return;

    $css_file = 'styles.css'; // junto a este styles.php
    $path = plugin_dir_path(__FILE__) . $css_file;
    $url  = plugins_url($css_file, __FILE__);

    if (file_exists($path)) {
        wp_enqueue_style('phsbot-styles', $url, array(), filemtime($path));
    }
}, 20);


/* --------------------------------------------------------
 * 2) ADMIN: CSS específico para la pantalla phsbot-inject
 * ------------------------------------------------------ */
add_action('admin_enqueue_scripts', function ($hook) {

    // ¿Estamos en la pantalla del submenú "Inyecciones"?
    $is_inject = (isset($_GET['page']) && $_GET['page'] === 'phsbot-inject');

    if (!$is_inject && function_exists('get_current_screen')) {
        $scr = get_current_screen();
        if ($scr) {
            $id   = isset($scr->id)   ? (string)$scr->id   : '';
            $base = isset($scr->base) ? (string)$scr->base : '';
            // Coincidencias típicas en WP
            if (strpos($id, 'phsbot-inject') !== false || strpos($base, 'phsbot-inject') !== false) {
                $is_inject = true;
            }
            // A veces WP compone el id/base con el slug del plugin
            if (strpos($id, 'phsbot_page_phsbot-inject') !== false || strpos($base, 'phsbot_page_phsbot-inject') !== false) {
                $is_inject = true;
            }
        }
    }

    if (!$is_inject) return;

    // Localiza el CSS en varios lugares posibles:
    // - mismo dir que styles.php
    // - /assets/
    // - /admin/
    // - /css/
    $candidates = array(
        array('file' => 'admin-inject.css'),
        array('file' => 'assets/admin-inject.css'),
        array('file' => 'admin/admin-inject.css'),
        array('file' => 'css/admin-inject.css'),
    );

    $found_path = null;
    $found_url  = null;
    $found_ver  = null;

    foreach ($candidates as $c) {
        $p = plugin_dir_path(__FILE__) . $c['file'];
        if (file_exists($p)) {
            $found_path = $p;
            $found_url  = plugins_url($c['file'], __FILE__);
            $found_ver  = filemtime($p);
            break;
        }
    }

    if ($found_path && $found_url) {
        wp_enqueue_style('phsbot-inject-admin', $found_url, array(), $found_ver);
        return;
    }

    // Fallback: inyecta CSS mínimo inline para comprobar hook
    wp_register_style('phsbot-inject-admin-fallback', false);
    wp_enqueue_style('phsbot-inject-admin-fallback');
    $inline = <<<CSS
/* Fallback de comprobación: si ves este borde azul, el hook funciona,
   pero el archivo admin-inject.css no se encontró en las rutas previstas. */
#phsbot-inject-table { outline: 3px solid #5a8dee !important; border-radius: 10px; }
.wrap h1::after { content: " (fallback CSS)"; color:#c00; font-size:12px; margin-left:6px; }
CSS;
    wp_add_inline_style('phsbot-inject-admin-fallback', $inline);
}, 99);


