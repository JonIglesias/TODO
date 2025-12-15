<?php
/**
 * Script para descargar manualmente el último release desde GitHub
 * Ejecutar: php manual-download.php geowriter
 * o desde navegador: manual-download.php?plugin=geowriter
 */

// Configuración
$downloads_dir = __DIR__ . '/../wp-content/uploads/woocommerce_downloads/';

// Crear directorio si no existe
if (!is_dir($downloads_dir)) {
    mkdir($downloads_dir, 0755, true);
}

// Obtener plugin a descargar
$plugin = isset($_GET['plugin']) ? $_GET['plugin'] : (isset($argv[1]) ? $argv[1] : '');

if (empty($plugin)) {
    die("Uso: php manual-download.php [geowriter|conversa]\n");
}

// Mapear plugins a repositorios de GitHub
$repos = [
    'geowriter' => 'bocetosmarketing/geowriter',
    'conversa' => 'bocetosmarketing/conversa-bot'
];

if (!isset($repos[$plugin])) {
    die("Plugin desconocido: {$plugin}\n");
}

$repo = $repos[$plugin];
$api_url = "https://api.github.com/repos/{$repo}/releases/latest";

echo "Consultando último release de {$repo}...\n";

// Obtener información del último release
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => "User-Agent: PHP\r\n",
        'timeout' => 30
    ]
]);

$response = @file_get_contents($api_url, false, $context);

if ($response === false) {
    die("ERROR: No se pudo conectar a la API de GitHub\n");
}

$data = json_decode($response, true);

if (!isset($data['tag_name']) || !isset($data['zipball_url'])) {
    die("ERROR: Respuesta inválida de GitHub\n");
}

$tag_name = $data['tag_name'];
$zipball_url = $data['zipball_url'];

echo "Descargando {$plugin} {$tag_name}...\n";

// Descargar el ZIP
$zip_content = @file_get_contents($zipball_url, false, $context);

if ($zip_content === false) {
    die("ERROR: No se pudo descargar el release\n");
}

// Guardar archivos
$zip_filename = "{$plugin}-{$tag_name}.zip";
$zip_filepath = $downloads_dir . $zip_filename;
$latest_filepath = $downloads_dir . "{$plugin}-latest.zip";

file_put_contents($zip_filepath, $zip_content);
echo "Guardado: {$zip_filepath} (" . strlen($zip_content) . " bytes)\n";

// Actualizar enlace a la última versión
if (file_exists($latest_filepath)) {
    unlink($latest_filepath);
}
copy($zip_filepath, $latest_filepath);
echo "Actualizado: {$latest_filepath}\n";

echo "\n✓ Descarga completada exitosamente\n";
echo "\nConfigura en WooCommerce:\n";
echo "- Nombre: {$plugin}-latest.zip\n";
echo "- URL: " . str_replace(__DIR__, 'https://www.bocetosmarketing.com/downloads', $latest_filepath) . "\n";
