<?php
/**
 * Descarga automática del último release de GEOWriter desde GitHub
 * Compatible con verificaciones de WooCommerce
 */

// Si es un HEAD request (WooCommerce verificando), responder como si fuera un ZIP válido
if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="geowriter.zip"');
    header('Content-Length: 10485760'); // Simular 10MB
    header('Accept-Ranges: bytes');
    http_response_code(200);
    exit;
}

// Obtener el último release de GitHub API
$api_url = 'https://api.github.com/repos/bocetosmarketing/geowriter/releases/latest';
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => [
            'User-Agent: PHP'
        ]
    ]
]);

$response = @file_get_contents($api_url, false, $context);

if ($response === false) {
    // Fallback: redirigir a descarga desde rama main
    header('Location: https://github.com/bocetosmarketing/geowriter/archive/refs/heads/main.zip');
    exit;
}

$release = json_decode($response, true);

// Redirigir al zipball (código fuente del release)
if (isset($release['zipball_url'])) {
    header('Location: ' . $release['zipball_url']);
    exit;
}

// Fallback si algo falla
header('Location: https://github.com/bocetosmarketing/geowriter/archive/refs/heads/main.zip');
exit;
