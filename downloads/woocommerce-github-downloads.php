<?php
/**
 * Plugin Name: WooCommerce GitHub Downloads
 * Description: Redirige las descargas de plugins a GitHub releases automáticamente
 * Version: 1.0
 * Author: Bocetos Marketing
 */

if (!defined('ABSPATH')) exit;

/**
 * Interceptar descargas de WooCommerce y redirigir a GitHub
 */
add_filter('woocommerce_download_file_redirect', 'wc_github_download_redirect', 10, 2);
function wc_github_download_redirect($redirect, $download) {
    $file_path = $download->get_file();

    // Verificar si es una descarga de GitHub (detectar por nombre de archivo)
    if (strpos($file_path, 'geowriter') !== false || strpos($file_path, 'GEOWRITER') !== false) {
        // Obtener el último release de GEOWriter
        $github_url = wc_get_latest_github_release('bocetosmarketing', 'geowriter');
        if ($github_url) {
            wp_redirect($github_url);
            exit;
        }
    }

    if (strpos($file_path, 'conversa') !== false || strpos($file_path, 'CONVERSA') !== false) {
        // Obtener el último release de Conversa
        $github_url = wc_get_latest_github_release('bocetosmarketing', 'conversa-bot');
        if ($github_url) {
            wp_redirect($github_url);
            exit;
        }
    }

    return $redirect;
}

/**
 * Obtener URL de descarga del último release de GitHub
 */
function wc_get_latest_github_release($owner, $repo) {
    $transient_key = 'github_release_' . $owner . '_' . $repo;

    // Usar caché de 1 hora
    $cached_url = get_transient($transient_key);
    if ($cached_url !== false) {
        return $cached_url;
    }

    $api_url = "https://api.github.com/repos/{$owner}/{$repo}/releases/latest";

    $response = wp_remote_get($api_url, [
        'headers' => [
            'User-Agent' => 'WordPress/WooCommerce'
        ],
        'timeout' => 15
    ]);

    if (is_wp_error($response)) {
        // Fallback: descargar desde rama main
        return "https://github.com/{$owner}/{$repo}/archive/refs/heads/main.zip";
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (isset($data['zipball_url'])) {
        $url = $data['zipball_url'];
        // Guardar en caché por 1 hora
        set_transient($transient_key, $url, HOUR_IN_SECONDS);
        return $url;
    }

    // Fallback
    return "https://github.com/{$owner}/{$repo}/archive/refs/heads/main.zip";
}

/**
 * Limpiar caché cuando se publique un nuevo release
 * (puedes llamar a esta función manualmente o con un webhook)
 */
function wc_clear_github_release_cache() {
    delete_transient('github_release_bocetosmarketing_geowriter');
    delete_transient('github_release_bocetosmarketing_conversa-bot');
}
