<?php
/**
 * PHSBOT — Auto-loader de EXTRAS
 * Carga automáticamente cualquier .css / .js / .php que esté en esta carpeta,
 * excepto este propio archivo (extras.php).
 */
if (!defined('ABSPATH')) exit;

(function () {
    $base_dir  = plugin_dir_path(__FILE__); // .../Stable-1.2.2/extras/
    $base_url  = plugin_dir_url(__FILE__);  // .../Stable-1.2.2/extras/
    $self_name = basename(__FILE__);        // extras.php

    // 1) Incluir primero los .php (excepto este)
    foreach (scandir($base_dir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        if ($entry === $self_name) continue;
        if ($entry[0] === '.') continue;                  // ocultos
        $path = $base_dir . $entry;
        if (!is_file($path)) continue;

        $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
        if ($ext === 'php') {
            // Carga silenciosa sin romper si hay warnings
            require_once $path;
        }
    }

    // 2) Encolar CSS/JS solo en frontend
    add_action('wp_enqueue_scripts', function () use ($base_dir, $base_url, $self_name) {
        if (is_admin()) return;
        if (wp_doing_ajax()) return;

        $styles  = [];
        $scripts = [];

        foreach (scandir($base_dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            if ($entry === $self_name) continue;
            if ($entry[0] === '.') continue;
            $path = $base_dir . $entry;
            if (!is_file($path)) continue;

            $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            if ($ext === 'css') {
                $styles[] = $entry;
            } elseif ($ext === 'js') {
                $scripts[] = $entry;
            }
        }

        sort($styles, SORT_NATURAL | SORT_FLAG_CASE);
        sort($scripts, SORT_NATURAL | SORT_FLAG_CASE);

        // Encolar CSS
        foreach ($styles as $file) {
            $handle = 'phs-extra-' . substr(md5($file), 0, 8);
            $path   = $base_dir . $file;
            $url    = $base_url . $file;
            $ver    = @filemtime($path) ?: false;
            wp_enqueue_style($handle, $url, [], $ver);
        }

        // Encolar JS (en footer)
        foreach ($scripts as $file) {
            $handle = 'phs-extra-' . substr(md5($file), 0, 8);
            $path   = $base_dir . $file;
            $url    = $base_url . $file;
            $ver    = @filemtime($path) ?: false;
            wp_enqueue_script($handle, $url, [], $ver, true);
        }
    }, 120);
})();