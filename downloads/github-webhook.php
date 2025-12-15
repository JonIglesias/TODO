<?php
/**
 * GitHub Release Webhook Receiver
 * Descarga automáticamente los releases de GitHub al servidor
 */

if (!defined('ABSPATH')) {
    // Si no está en WordPress, cargar configuración básica
    define('ABSPATH', dirname(__FILE__) . '/../../../../');
}

// Directorio donde se guardarán los plugins
$downloads_dir = ABSPATH . 'wp-content/uploads/woocommerce_downloads/';

// Crear directorio si no existe
if (!is_dir($downloads_dir)) {
    mkdir($downloads_dir, 0755, true);
}

// Verificar que sea una petición POST de GitHub
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method Not Allowed');
}

// Leer el payload de GitHub
$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

// Verificar que sea un evento de release
if (!isset($data['action']) || $data['action'] !== 'published') {
    http_response_code(200);
    die('Not a release event');
}

// Obtener información del release
$repo_name = $data['repository']['name'] ?? '';
$tag_name = $data['release']['tag_name'] ?? '';
$zipball_url = $data['release']['zipball_url'] ?? '';

// Log para debugging
$log_file = $downloads_dir . 'webhook.log';
file_put_contents($log_file, date('Y-m-d H:i:s') . " - Received release: {$repo_name} {$tag_name}\n", FILE_APPEND);

// Mapear repositorios a nombres de archivo
$repo_mapping = [
    'geowriter' => 'geowriter',
    'conversa-bot' => 'conversa'
];

if (!isset($repo_mapping[$repo_name])) {
    http_response_code(200);
    die('Unknown repository');
}

$plugin_name = $repo_mapping[$repo_name];
$zip_filename = "{$plugin_name}-{$tag_name}.zip";
$zip_filepath = $downloads_dir . $zip_filename;
$latest_filepath = $downloads_dir . "{$plugin_name}-latest.zip";

// Descargar el release desde GitHub
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => "User-Agent: PHP\r\n",
        'timeout' => 300
    ]
]);

$zip_content = @file_get_contents($zipball_url, false, $context);

if ($zip_content === false) {
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - ERROR: Failed to download from {$zipball_url}\n", FILE_APPEND);
    http_response_code(500);
    die('Failed to download release');
}

// Guardar el archivo con la versión
file_put_contents($zip_filepath, $zip_content);

// Crear/actualizar el enlace a la última versión
if (file_exists($latest_filepath)) {
    unlink($latest_filepath);
}
copy($zip_filepath, $latest_filepath);

// Log de éxito
file_put_contents($log_file, date('Y-m-d H:i:s') . " - SUCCESS: Downloaded {$zip_filename} (" . strlen($zip_content) . " bytes)\n", FILE_APPEND);

// Limpiar versiones antiguas (mantener solo las últimas 3)
$files = glob($downloads_dir . "{$plugin_name}-*.zip");
if (count($files) > 3) {
    usort($files, function($a, $b) {
        return filemtime($a) - filemtime($b);
    });
    // Eliminar las más antiguas
    for ($i = 0; $i < count($files) - 3; $i++) {
        if (strpos($files[$i], '-latest.zip') === false) {
            unlink($files[$i]);
        }
    }
}

http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => "Release {$tag_name} downloaded successfully",
    'file' => $zip_filename
]);
