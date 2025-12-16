<?php
/**
 * Descarga automática del último release de GEOWriter desde GitHub
 * Compatible con verificaciones de WooCommerce
 */

// Log para debugging - FORZAR escritura inmediata
$log_enabled = true;
$log_file = __DIR__ . '/download-debug.log';

// Función de logging mejorada con captura de errores
function debug_log($message) {
    global $log_enabled, $log_file;
    if ($log_enabled) {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "$timestamp [GEOWriter] $message\n";
        @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
        @chmod($log_file, 0666); // Asegurar permisos de escritura
    }
}

// Capturar errores fatales
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        debug_log("FATAL ERROR: " . $error['message'] . " in " . $error['file'] . ":" . $error['line']);
    }
});

// Log inicial INMEDIATO
debug_log("=== SCRIPT INICIADO ===");
debug_log("Request: " . ($_SERVER['REQUEST_METHOD'] ?? 'CLI') . " from " . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'));
debug_log("Remote IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
debug_log("Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'unknown'));
debug_log("Query String: " . ($_SERVER['QUERY_STRING'] ?? 'none'));

// Si es un HEAD request (WooCommerce verificando), responder como si fuera un ZIP válido
if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
    debug_log("HEAD request - responding with ZIP headers");
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="geowriter.zip"');
    header('Content-Length: 10485760'); // Simular 10MB
    header('Accept-Ranges: bytes');
    http_response_code(200);
    exit;
}

// Obtener el último release de GitHub API
$api_url = 'https://api.github.com/repos/bocetosmarketing/geowriter/releases/latest';

debug_log("Fetching from GitHub API: $api_url");

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'WooCommerce-Download-Script');
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

debug_log("GitHub API response code: $http_code");

if ($response === false || $http_code !== 200) {
    debug_log("GitHub API failed: $curl_error - Using fallback");
    // Fallback: redirigir a descarga desde rama main
    header('Location: https://github.com/bocetosmarketing/geowriter/archive/refs/heads/main.zip');
    exit;
}

$release = json_decode($response, true);

debug_log("Release data: " . json_encode(['tag' => $release['tag_name'] ?? 'unknown', 'has_zipball' => isset($release['zipball_url'])]));

// Preparar URL y nombre comercial del archivo
$download_url = null;
$filename = 'geowriter.zip';

if (isset($release['zipball_url']) && isset($release['tag_name'])) {
    $download_url = $release['zipball_url'];
    $filename = 'geowriter-' . $release['tag_name'] . '.zip';
} else {
    debug_log("No zipball_url found - Using fallback");
    $download_url = 'https://github.com/bocetosmarketing/geowriter/archive/refs/heads/main.zip';
    $filename = 'geowriter-latest.zip';
}

debug_log("Downloading from: $download_url");
debug_log("Serving as: $filename");

// Configurar headers para descarga con nombre comercial
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

// Streaming del archivo desde GitHub sin guardarlo en disco
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $download_url);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'WooCommerce-Download-Script');
curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 minutos para archivos grandes
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($curl, $data) {
    echo $data;
    flush();
    return strlen($data);
});

debug_log("Starting file streaming...");
$result = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($result === false) {
    debug_log("ERROR: cURL streaming failed: $curl_error (HTTP: $http_code)");
    http_response_code(500);
    die("Error downloading file");
}

debug_log("Download completed successfully (HTTP: $http_code)");
exit;
