<?php
/**
 * Script para recalcular costes históricos en api_usage_tracking
 * usando los precios correctos según el modelo
 *
 * IMPORTANTE: Hacer backup de la tabla antes de ejecutar
 */

define('API_ACCESS', true);
define('API_BASE_DIR', __DIR__ . '/api_claude_5');

require_once API_BASE_DIR . '/config.php';
require_once API_BASE_DIR . '/core/Database.php';
require_once API_BASE_DIR . '/services/ModelPricingService.php';

echo "=== RECALCULAR COSTES HISTÓRICOS ===\n\n";

$db = Database::getInstance();

// 1. Obtener todos los registros con modelo y tokens
$records = $db->query("
    SELECT id, model, tokens_input, tokens_output,
           cost_input, cost_output, cost_total
    FROM " . DB_PREFIX . "usage_tracking
    WHERE model IS NOT NULL
      AND model != ''
      AND (tokens_input > 0 OR tokens_output > 0)
    ORDER BY id ASC
");

if (empty($records)) {
    echo "No hay registros para procesar.\n";
    exit;
}

echo "Registros encontrados: " . count($records) . "\n";
echo "¿Deseas continuar? (yes/no): ";
$confirm = trim(fgets(STDIN));

if (strtolower($confirm) !== 'yes') {
    echo "Operación cancelada.\n";
    exit;
}

echo "\nProcesando...\n\n";

$updated = 0;
$errors = 0;
$total_correction = 0;

foreach ($records as $record) {
    $id = $record['id'];
    $model = $record['model'];
    $tokens_input = floatval($record['tokens_input']);
    $tokens_output = floatval($record['tokens_output']);
    $old_cost_total = floatval($record['cost_total']);

    // Obtener precios correctos para este modelo
    $prices = ModelPricingService::getPrices($model);

    // Calcular costes correctos
    $new_cost_input = ($tokens_input / 1000000) * $prices['input'];
    $new_cost_output = ($tokens_output / 1000000) * $prices['output'];
    $new_cost_total = $new_cost_input + $new_cost_output;

    // Solo actualizar si hay diferencia significativa (> $0.0001)
    $diff = abs($new_cost_total - $old_cost_total);

    if ($diff > 0.0001) {
        try {
            $db->query("
                UPDATE " . DB_PREFIX . "usage_tracking
                SET
                    cost_input = ?,
                    cost_output = ?,
                    cost_total = ?
                WHERE id = ?
            ", [
                $new_cost_input,
                $new_cost_output,
                $new_cost_total,
                $id
            ]);

            $updated++;
            $total_correction += ($old_cost_total - $new_cost_total);

            // Mostrar registros con diferencias significativas
            if ($diff > 0.01) {
                echo "ID {$id} - Modelo: {$model}\n";
                echo "  Tokens: {$tokens_input}i / {$tokens_output}o\n";
                echo "  Precio usado: \${$prices['input']}/\${$prices['output']} por millón\n";
                echo "  Coste anterior: \${$old_cost_total}\n";
                echo "  Coste correcto: \${$new_cost_total}\n";
                echo "  Diferencia: \$" . ($old_cost_total - $new_cost_total) . "\n\n";
            }

        } catch (Exception $e) {
            $errors++;
            echo "ERROR en ID {$id}: " . $e->getMessage() . "\n";
        }
    }
}

echo "\n===================================\n";
echo "RESUMEN:\n";
echo "  Registros procesados: " . count($records) . "\n";
echo "  Registros actualizados: {$updated}\n";
echo "  Errores: {$errors}\n";
echo "  Corrección total: \$" . number_format($total_correction, 5) . "\n";

if ($total_correction > 0) {
    echo "\n✅ Se corrigió un sobrecargo de \$" . number_format($total_correction, 5) . "\n";
} else if ($total_correction < 0) {
    echo "\n⚠️  Se agregó \$" . number_format(abs($total_correction), 5) . " (algunos registros estaban subestimados)\n";
} else {
    echo "\n✅ Todos los costes ya estaban correctos.\n";
}

echo "\nFinalizado.\n";
