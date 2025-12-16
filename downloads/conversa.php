<?php
/**
 * Descarga automática del último release de Conversa desde GitHub
 * Compatible con verificaciones de WooCommerce
 */

// Log para debugging (comentar en producción si no se necesita)
$log_enabled = false; // Cambiar a true para activar logs
$log_file = __DIR__ . '/download-debug.log';

function debug_log($message) {
    global $log_enabled, $log_file;
    if ($log_enabled) {
        file_put_contents($log_file, date('Y-m-d H:i:s') . " [Conversa] " . $message . "\n", FILE_APPEND);
    }
}

debug_log("Request: " . $_SERVER['REQUEST_METHOD'] . " from " . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'));

// Si es un HEAD request (WooCommerce verificando), responder como si fuera un ZIP válido
if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
    debug_log("HEAD request - responding with ZIP headers");
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="conversa.zip"');
    header('Content-Length: 8388608'); // Simular 8MB
    header('Accept-Ranges: bytes');
    http_response_code(200);
    exit;
}

// Obtener el último release de GitHub API
$api_url = 'https://api.github.com/repos/bocetosmarketing/conversa-bot/releases/latest';

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
    header('Location: https://github.com/bocetosmarketing/conversa-bot/archive/refs/heads/main.zip');
    exit;
}

$release = json_decode($response, true);

debug_log("Release data: " . json_encode(['tag' => $release['tag_name'] ?? 'unknown', 'has_zipball' => isset($release['zipball_url'])]));

// Redirigir al zipball (código fuente del release)
if (isset($release['zipball_url'])) {
    $redirect_url = $release['zipball_url'];
    debug_log("Redirecting to: $redirect_url");
    header('Location: ' . $redirect_url);
    exit;
}

// Fallback si algo falla
debug_log("No zipball_url found - Using fallback");
header('Location: https://github.com/bocetosmarketing/conversa-bot/archive/refs/heads/main.zip');
exit;
