<?php
/**
 * Script para actualizar el modelo de OpenAI para GeoWriter
 * Uso: php update-geowriter-model.php [modelo]
 * Ejemplo: php update-geowriter-model.php gpt-4o
 */

define('API_ACCESS', true);
require_once __DIR__ . '/config.php';
require_once API_BASE_DIR . '/core/Database.php';

echo "=== ACTUALIZAR MODELO OPENAI PARA GEOWRITER ===" . PHP_EOL . PHP_EOL;

// Obtener modelo actual
$db = Database::getInstance();
$stmt = $db->prepare("SELECT setting_value FROM " . DB_PREFIX . "settings WHERE setting_key = 'geowrite_ai_model'");
$stmt->execute();
$current = $stmt->fetch(PDO::FETCH_ASSOC);
$currentModel = $current['setting_value'] ?? 'NO CONFIGURADO';

echo "Modelo actual: " . $currentModel . PHP_EOL . PHP_EOL;

// Modelos disponibles
$availableModels = [
    'gpt-4o',
    'gpt-4o-mini',
    'gpt-4-turbo',
    'gpt-4',
    'gpt-3.5-turbo',
    'o1-preview',
    'o1-mini'
];

// Si se pasa un modelo por argumento, usarlo
if (isset($argv[1])) {
    $newModel = $argv[1];
} else {
    // Si no, mostrar menú
    echo "Modelos disponibles:" . PHP_EOL;
    foreach ($availableModels as $index => $model) {
        echo ($index + 1) . ". " . $model . PHP_EOL;
    }
    echo PHP_EOL;
    echo "Ingresa el número del modelo o escribe el nombre: ";
    $input = trim(fgets(STDIN));

    if (is_numeric($input) && $input > 0 && $input <= count($availableModels)) {
        $newModel = $availableModels[$input - 1];
    } else {
        $newModel = $input;
    }
}

echo PHP_EOL . "Actualizando modelo a: " . $newModel . PHP_EOL;

// Actualizar en BD
$stmt = $db->prepare("INSERT INTO " . DB_PREFIX . "settings (setting_key, setting_value, setting_type)
                      VALUES ('geowrite_ai_model', ?, 'string')
                      ON DUPLICATE KEY UPDATE setting_value = ?");
$stmt->execute([$newModel, $newModel]);

echo "✓ Modelo actualizado correctamente en la base de datos" . PHP_EOL . PHP_EOL;

// Verificar
$stmt = $db->prepare("SELECT setting_value FROM " . DB_PREFIX . "settings WHERE setting_key = 'geowrite_ai_model'");
$stmt->execute();
$updated = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Modelo configurado ahora: " . $updated['setting_value'] . PHP_EOL;
echo PHP_EOL;
echo "IMPORTANTE: Si estás usando caché de opcodes (OPcache), reinicia PHP-FPM o Apache para que los cambios surtan efecto." . PHP_EOL;
echo "También puedes limpiar la caché manualmente ejecutando: php -r 'opcache_reset();'" . PHP_EOL;
