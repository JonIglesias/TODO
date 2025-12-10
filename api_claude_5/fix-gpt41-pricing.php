<?php
/**
 * Corrección de precios GPT-4.1 en la base de datos
 *
 * PROBLEMA:
 * - Los modelos GPT-4.1 se guardaron con precios de GPT-4 ($0.03/$0.06)
 * - Los precios correctos son $0.002/$0.008 (15x más baratos)
 * - GPT-4.1 SÍ EXISTE (lanzado en abril 2025)
 *
 * USO:
 * - Ver análisis: sin parámetros
 * - Ejecutar fix: ?execute=1&confirm=yes
 */

define('API_ACCESS', true);

if (!defined('API_BASE_DIR')) {
    define('API_BASE_DIR', __DIR__);
}

require_once API_BASE_DIR . '/config.php';
require_once API_BASE_DIR . '/core/Database.php';

header('Content-Type: text/html; charset=UTF-8');

$execute = isset($_GET['execute']) && $_GET['execute'] == '1';
$confirm = isset($_GET['confirm']) && $_GET['confirm'] == 'yes';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Corrección de Precios GPT-4.1</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 { color: #4ec9b0; }
        h2 { color: #569cd6; margin-top: 30px; }
        .warning { background: #3e2723; border-left: 4px solid #ff5722; padding: 15px; margin: 20px 0; }
        .success { background: #1b5e20; border-left: 4px solid #4caf50; padding: 15px; margin: 20px 0; }
        .info { background: #01579b; border-left: 4px solid #03a9f4; padding: 15px; margin: 20px 0; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; background: #252526; }
        th { background: #2d2d30; color: #4ec9b0; padding: 10px; text-align: left; }
        td { padding: 8px; border-bottom: 1px solid #3e3e42; }
        .button { display: inline-block; padding: 12px 24px; margin: 10px 5px;
                  background: #0e639c; color: white; text-decoration: none;
                  border-radius: 4px; cursor: pointer; }
        .button:hover { background: #1177bb; }
        .button-danger { background: #d32f2f; }
        .button-danger:hover { background: #f44336; }
        .invalid { color: #f44336; font-weight: bold; }
        pre { background: #252526; padding: 15px; overflow-x: auto; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔧 Corrección de Precios GPT-4.1</h1>

    <div class="warning">
        <strong>⚠️ PROBLEMA IDENTIFICADO:</strong><br><br>
        Los modelos <strong>gpt-4.1, gpt-4.1-mini, gpt-4.1-nano</strong> tienen precios INCORRECTOS en la base de datos.<br>
        Se les aplicaron precios de GPT-4 cuando deberían tener precios de GPT-4.1 (15x más baratos).<br><br>
        <strong>¿Por qué pasó esto?</strong><br>
        • GPT-4.1 SÍ EXISTE (lanzado abril 2025) pero no estaba en knownPrices del sync<br>
        • El pattern matching detectó "gpt-4" en "gpt-4.1" y aplicó precios de GPT-4<br>
        • Resultado: sobrecosto de 15x en todas las operaciones GPT-4.1<br><br>
        <strong>Precios aplicados vs correctos:</strong><br>
        • gpt-4.1 actual (INCORRECTO): $0.03/$0.06 por 1K tokens<br>
        • gpt-4.1 correcto: $0.002/$0.008 por 1K tokens<br>
        • Diferencia: <span class="invalid">¡15x sobrecosto!</span>
    </div>

<?php

try {
    $db = Database::getInstance();

    // Precios correctos de GPT-4.1 (USD por 1.000 tokens)
    $correctPrices = [
        'gpt-4.1' => ['input' => 0.002, 'output' => 0.008],
        'gpt-4.1-mini' => ['input' => 0.0004, 'output' => 0.0016],
        'gpt-4.1-nano' => ['input' => 0.0002, 'output' => 0.0008],
    ];

    // 1. Buscar modelos gpt-4.1* en model_prices
    echo '<h2>1. Estado Actual de Precios GPT-4.1</h2>';

    $gpt41_models = $db->query("
        SELECT * FROM " . DB_PREFIX . "model_prices
        WHERE model_name LIKE 'gpt-4.1%'
        ORDER BY is_active DESC, model_name
    ");

    $needsCorrection = [];

    if (empty($gpt41_models)) {
        echo '<div class="warning">⚠️ No hay modelos gpt-4.1 en la tabla model_prices</div>';
    } else {
        echo '<table>';
        echo '<tr><th>ID</th><th>Modelo</th><th>Estado</th><th>Input/1K</th><th>Output/1K</th><th>¿Correcto?</th><th>Fuente</th></tr>';
        foreach ($gpt41_models as $m) {
            // Determinar modelo base para comparar precios
            $modelBase = null;
            foreach (array_keys($correctPrices) as $base) {
                if (strpos($m['model_name'], $base) === 0) {
                    $modelBase = $base;
                    break;
                }
            }

            $isCorrect = false;
            if ($modelBase && isset($correctPrices[$modelBase])) {
                $isCorrect = (
                    abs($m['price_input_per_1k'] - $correctPrices[$modelBase]['input']) < 0.000001 &&
                    abs($m['price_output_per_1k'] - $correctPrices[$modelBase]['output']) < 0.000001
                );
            }

            if (!$isCorrect && $m['is_active']) {
                $needsCorrection[] = [
                    'id' => $m['id'],
                    'model_name' => $m['model_name'],
                    'current_input' => $m['price_input_per_1k'],
                    'current_output' => $m['price_output_per_1k'],
                    'correct_input' => $correctPrices[$modelBase]['input'],
                    'correct_output' => $correctPrices[$modelBase]['output'],
                    'base' => $modelBase
                ];
            }

            $status = $m['is_active'] ? 'ACTIVO' : 'Inactivo';
            $correctStatus = $isCorrect ? '<span style="color: #4caf50;">✓ CORRECTO</span>' : '<span class="invalid">✗ INCORRECTO</span>';

            echo '<tr>';
            echo '<td>' . $m['id'] . '</td>';
            echo '<td>' . htmlspecialchars($m['model_name']) . '</td>';
            echo '<td>' . $status . '</td>';
            echo '<td>$' . number_format($m['price_input_per_1k'], 6) . '</td>';
            echo '<td>$' . number_format($m['price_output_per_1k'], 6) . '</td>';
            echo '<td>' . $correctStatus . '</td>';
            echo '<td>' . htmlspecialchars($m['source']) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }

    // 2. Buscar en settings
    echo '<h2>2. Configuración de Modelos en Settings</h2>';

    $settings = $db->query("
        SELECT * FROM " . DB_PREFIX . "settings
        WHERE setting_key LIKE '%_model'
        ORDER BY setting_key
    ");

    if (empty($settings)) {
        echo '<div class="info">No hay configuraciones de modelo en settings</div>';
    } else {
        echo '<table>';
        echo '<tr><th>Configuración</th><th>Modelo Configurado</th></tr>';
        foreach ($settings as $s) {
            $has_gpt41 = strpos($s['setting_value'], 'gpt-4.1') !== false;
            $class = $has_gpt41 ? 'style="font-weight: bold;"' : '';

            echo '<tr>';
            echo '<td>' . htmlspecialchars($s['setting_key']) . '</td>';
            echo '<td ' . $class . '>' . htmlspecialchars($s['setting_value']) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }

    // 3. Buscar uso en tracking
    echo '<h2>3. Uso de gpt-4.1 en api_usage_tracking</h2>';

    $tracking = $db->query("
        SELECT
            model,
            COUNT(*) as operations,
            SUM(cost_total) as total_cost,
            MIN(created_at) as first_use,
            MAX(created_at) as last_use
        FROM " . DB_PREFIX . "usage_tracking
        WHERE model LIKE 'gpt-4.1%'
        GROUP BY model
    ");

    if (empty($tracking)) {
        echo '<div class="success">✅ No se ha usado gpt-4.1 en operaciones</div>';
    } else {
        echo '<table>';
        echo '<tr><th>Modelo</th><th>Operaciones</th><th>Coste Total</th><th>Primera Uso</th><th>Último Uso</th></tr>';
        $total_operations = 0;
        $total_cost = 0;
        foreach ($tracking as $t) {
            echo '<tr>';
            echo '<td class="invalid">' . htmlspecialchars($t['model']) . '</td>';
            echo '<td>' . number_format($t['operations']) . '</td>';
            echo '<td class="invalid">$' . number_format($t['total_cost'], 4) . '</td>';
            echo '<td>' . $t['first_use'] . '</td>';
            echo '<td>' . $t['last_use'] . '</td>';
            echo '</tr>';
            $total_operations += $t['operations'];
            $total_cost += $t['total_cost'];
        }
        echo '<tr style="font-weight: bold; background: #3e2723;">';
        echo '<td>TOTAL</td>';
        echo '<td>' . number_format($total_operations) . '</td>';
        echo '<td class="invalid">$' . number_format($total_cost, 4) . '</td>';
        echo '<td colspan="2"></td>';
        echo '</tr>';
        echo '</table>';

        // Calcular sobrecosto
        echo '<div class="warning">';
        echo '<strong>💰 Análisis de Sobrecosto:</strong><br><br>';
        echo 'Estas operaciones se cobraron con precios de GPT-4 en vez de GPT-4.1:<br>';

        // Estimar sobrecosto (gpt-4 $0.03/$0.06 es ~15x más caro que gpt-4.1 $0.002/$0.008)
        $estimated_correct_cost = $total_cost / 15;
        $overcharge = $total_cost - $estimated_correct_cost;

        echo '• Coste calculado (con precios de GPT-4): <span class="invalid">$' . number_format($total_cost, 4) . '</span><br>';
        echo '• Coste real (con precios de GPT-4.1): $' . number_format($estimated_correct_cost, 4) . '<br>';
        echo '• Sobrecosto estimado: <span class="invalid">$' . number_format($overcharge, 4) . ' (15x)</span><br>';
        echo '<br><small>NOTA: Los costes en tracking no se pueden modificar (solo para auditoría). Solo corregiremos los precios para operaciones futuras.</small>';
        echo '</div>';
    }

    // 4. MODO EJECUCIÓN
    if ($execute && $confirm === 'yes') {
        echo '<h2>⚙️ Ejecutando Correcciones...</h2>';

        $corrected = 0;
        $errors = [];

        // Corregir precios de modelos gpt-4.1
        if (!empty($needsCorrection)) {
            foreach ($needsCorrection as $nc) {
                try {
                    $stmt = $db->prepare("UPDATE " . DB_PREFIX . "model_prices
                        SET price_input_per_1k = ?,
                            price_output_per_1k = ?,
                            source = 'fix_script_gpt41_pricing',
                            updated_at = NOW()
                        WHERE id = ?");

                    $stmt->execute([
                        $nc['correct_input'],
                        $nc['correct_output'],
                        $nc['id']
                    ]);

                    $corrected++;

                    echo '<div class="success">';
                    echo '✅ Corregido: <strong>' . htmlspecialchars($nc['model_name']) . '</strong><br>';
                    echo '&nbsp;&nbsp;&nbsp;Input: $' . number_format($nc['current_input'], 6) . ' → $' . number_format($nc['correct_input'], 6) . '<br>';
                    echo '&nbsp;&nbsp;&nbsp;Output: $' . number_format($nc['current_output'], 6) . ' → $' . number_format($nc['correct_output'], 6);
                    echo '</div>';

                } catch (Exception $e) {
                    $errors[] = 'Error al corregir ' . $nc['model_name'] . ': ' . $e->getMessage();
                }
            }
        }

        if (!empty($errors)) {
            echo '<div class="warning">';
            echo '<strong>⚠️ Errores durante la corrección:</strong><br>';
            foreach ($errors as $err) {
                echo '- ' . htmlspecialchars($err) . '<br>';
            }
            echo '</div>';
        }

        if ($corrected === 0) {
            echo '<div class="info">No había modelos que corregir</div>';
        } else {
            echo '<div class="success">';
            echo '<strong>✅ Corrección completada: ' . $corrected . ' modelo(s) actualizado(s)</strong>';
            echo '</div>';
        }

        echo '<div style="margin: 30px 0;">';
        echo '<a href="?" class="button">← Ver Estado Actualizado</a>';
        echo '</div>';

    } elseif ($execute && $confirm !== 'yes') {
        echo '<h2>⚠️ Confirmación Final</h2>';

        echo '<div class="warning">';
        echo '<strong>Cambios que se aplicarán:</strong><br><ul>';

        if (!empty($needsCorrection)) {
            echo '<li>Corregir precios de ' . count($needsCorrection) . ' modelo(s) GPT-4.1 en model_prices:</li>';
            echo '<ul>';
            foreach ($needsCorrection as $nc) {
                echo '<li>' . htmlspecialchars($nc['model_name']) . ': ';
                echo '$' . number_format($nc['current_input'], 6) . '/$' . number_format($nc['current_output'], 6);
                echo ' → ';
                echo '$' . number_format($nc['correct_input'], 6) . '/$' . number_format($nc['correct_output'], 6);
                echo '</li>';
            }
            echo '</ul>';
        } else {
            echo '<li>No hay modelos que necesiten corrección</li>';
        }

        echo '</ul>';
        echo '<br><strong>NOTA:</strong> Los registros históricos en usage_tracking NO se modificarán (solo para auditoría). Solo se corrigen los precios para operaciones futuras.';
        echo '</div>';

        echo '<div style="margin: 40px 0; text-align: center;">';
        echo '<a href="?execute=1&confirm=yes" class="button button-danger">✓ SÍ, EJECUTAR AHORA</a> ';
        echo '<a href="?" class="button">✗ Cancelar</a>';
        echo '</div>';

    } else {
        // Modo vista previa
        echo '<h2>📋 Acciones Recomendadas</h2>';

        if (!empty($needsCorrection)) {
            echo '<div class="warning">';
            echo '<strong>⚠️ Se encontraron ' . count($needsCorrection) . ' modelo(s) con precios incorrectos</strong><br><br>';
            echo '<strong>Pasos a seguir:</strong><br><ol>';
            echo '<li>Revisar los datos arriba para confirmar el problema</li>';
            echo '<li>Hacer clic en "Ejecutar Corrección" abajo</li>';
            echo '<li>Confirmar los cambios</li>';
            echo '<li>Verificar que los nuevos registros usen los precios correctos</li>';
            echo '</ol></div>';

            echo '<div style="margin: 40px 0; text-align: center;">';
            echo '<a href="?execute=1" class="button button-danger">⚠️ EJECUTAR CORRECCIÓN</a>';
            echo '</div>';
        } else {
            echo '<div class="success">';
            echo '<strong>✅ Todos los precios GPT-4.1 son correctos</strong><br>';
            echo 'No se requiere ninguna corrección.';
            echo '</div>';
        }

        // Información de referencia
        echo '<h2>Información de Referencia</h2>';
        echo '<div class="info">';
        echo '<strong>Precios Oficiales GPT-4.1 (USD por 1.000 tokens):</strong><br><br>';
        echo '<table>';
        echo '<tr><th>Modelo</th><th>Input</th><th>Output</th></tr>';
        foreach ($correctPrices as $model => $price) {
            echo '<tr>';
            echo '<td>' . $model . '</td>';
            echo '<td>$' . number_format($price['input'], 6) . '</td>';
            echo '<td>$' . number_format($price['output'], 6) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        echo '<br><small>Fuente: OpenAI Pricing (Abril 2025)</small>';
        echo '</div>';
    }

} catch (Exception $e) {
    echo '<div class="warning">';
    echo '<strong>❌ ERROR:</strong><br>';
    echo htmlspecialchars($e->getMessage());
    echo '</div>';
}

?>

</div>
</body>
</html>
