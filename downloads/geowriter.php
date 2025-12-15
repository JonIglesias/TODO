<?php
/**
 * Descarga automática del último release de GEOWriter desde GitHub
 */

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
