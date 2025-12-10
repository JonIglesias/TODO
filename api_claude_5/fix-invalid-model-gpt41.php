<?php
/**
 * Script para corregir el modelo ficticio gpt-4.1
 *
 * PROBLEMA:
 * - gpt-4.1 NO EXISTE en OpenAI
 * - Está aplicando precios de gpt-4 (20x más caro que gpt-4o)
 * - Debe reemplazarse por gpt-4o o gpt-4o-mini
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
    <title>Fix Modelo Ficticio gpt-4.1</title>
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
    <h1>🔧 Corregir Modelo Ficticio: gpt-4.1</h1>

    <div class="warning">
        <strong>⚠️ PROBLEMA IDENTIFICADO:</strong><br><br>
        El modelo <strong>gpt-4.1</strong> NO EXISTE en OpenAI.<br>
        Este modelo ficticio está causando que se apliquen precios de gpt-4 (20x más caros que gpt-4o).<br><br>
        <strong>Ejemplo de sobrecosto:</strong><br>
        • gpt-4.1 (ficticio): $0.03/$0.06 por 1K tokens<br>
        • gpt-4o (real): $0.0025/$0.01 por 1K tokens<br>
        • Diferencia: <span class="invalid">¡12x más caro!</span>
    </div>

<?php

try {
    $db = Database::getInstance();

    // 1. Buscar modelo gpt-4.1 en model_prices
    echo '<h2>1. Modelo gpt-4.1 en api_model_prices</h2>';

    $gpt41_models = $db->query("
        SELECT * FROM " . DB_PREFIX . "model_prices
        WHERE model_name LIKE '%gpt-4.1%'
        ORDER BY is_active DESC, model_name
    ");

    if (empty($gpt41_models)) {
        echo '<div class="success">✅ No hay modelos gpt-4.1 en la tabla model_prices</div>';
    } else {
        echo '<table>';
        echo '<tr><th>ID</th><th>Modelo</th><th>Estado</th><th>Input/1K</th><th>Output/1K</th><th>Fuente</th></tr>';
        foreach ($gpt41_models as $m) {
            $status = $m['is_active'] ? '<span class="invalid">ACTIVO</span>' : 'Inactivo';
            echo '<tr>';
            echo '<td>' . $m['id'] . '</td>';
            echo '<td class="invalid">' . htmlspecialchars($m['model_name']) . '</td>';
            echo '<td>' . $status . '</td>';
            echo '<td>$' . number_format($m['price_input_per_1k'], 6) . '</td>';
            echo '<td>$' . number_format($m['price_output_per_1k'], 6) . '</td>';
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
        echo '<tr><th>Configuración</th><th>Modelo Actual</th><th>Estado</th></tr>';
        foreach ($settings as $s) {
            $has_gpt41 = strpos($s['setting_value'], 'gpt-4.1') !== false;
            $status = $has_gpt41 ? '<span class="invalid">⚠️ USA GPT-4.1</span>' : '✅ OK';
            $class = $has_gpt41 ? 'invalid' : '';

            echo '<tr>';
            echo '<td>' . htmlspecialchars($s['setting_key']) . '</td>';
            echo '<td class="' . $class . '">' . htmlspecialchars($s['setting_value']) . '</td>';
            echo '<td>' . $status . '</td>';
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
        WHERE model LIKE '%gpt-4.1%'
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
        echo 'Si estas operaciones hubieran usado <strong>gpt-4o</strong> en vez de gpt-4.1:<br>';

        // Estimar sobrecosto (gpt-4 es ~12x más caro que gpt-4o)
        $estimated_correct_cost = $total_cost / 12;
        $overcharge = $total_cost - $estimated_correct_cost;

        echo '• Coste con gpt-4.1 (como gpt-4): <span class="invalid">$' . number_format($total_cost, 4) . '</span><br>';
        echo '• Coste estimado con gpt-4o: $' . number_format($estimated_correct_cost, 4) . '<br>';
        echo '• Sobrecosto estimado: <span class="invalid">$' . number_format($overcharge, 4) . '</span><br>';
        echo '</div>';
    }

    // 4. MODO EJECUCIÓN
    if ($execute && $confirm === 'yes') {
        echo '<h2>⚙️ Ejecutando Correcciones...</h2>';

        $fixed = [];

        // Desactivar modelos gpt-4.1 en model_prices
        if (!empty($gpt41_models)) {
            $ids = array_column($gpt41_models, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $db->prepare("UPDATE " . DB_PREFIX . "model_prices SET is_active = 0 WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            $fixed[] = "Desactivados " . count($ids) . " modelos gpt-4.1 en model_prices";
        }

        // Reemplazar en settings por gpt-4o
        foreach ($settings as $s) {
            if (strpos($s['setting_value'], 'gpt-4.1') !== false) {
                $new_value = 'gpt-4o'; // Reemplazar por modelo real
                $db->query("UPDATE " . DB_PREFIX . "settings SET setting_value = ? WHERE setting_key = ?",
                    [$new_value, $s['setting_key']]);
                $fixed[] = "Actualizado {$s['setting_key']}: {$s['setting_value']} → $new_value";
            }
        }

        if (empty($fixed)) {
            echo '<div class="info">No hay nada que corregir</div>';
        } else {
            echo '<div class="success">';
            echo '<strong>✅ CORRECCIONES APLICADAS:</strong><br><ul>';
            foreach ($fixed as $fix) {
                echo '<li>' . htmlspecialchars($fix) . '</li>';
            }
            echo '</ul></div>';
        }

        echo '<div style="margin: 30px 0;">';
        echo '<a href="?" class="button">← Ver Resultado Final</a>';
        echo '</div>';

    } elseif ($execute && $confirm !== 'yes') {
        echo '<h2>⚠️ Confirmación Final</h2>';

        echo '<div class="warning">';
        echo '<strong>Cambios que se aplicarán:</strong><br><ul>';

        if (!empty($gpt41_models)) {
            echo '<li>Desactivar ' . count($gpt41_models) . ' modelos gpt-4.1 en model_prices</li>';
        }

        $gpt41_settings = 0;
        foreach ($settings as $s) {
            if (strpos($s['setting_value'], 'gpt-4.1') !== false) {
                $gpt41_settings++;
            }
        }
        if ($gpt41_settings > 0) {
            echo '<li>Reemplazar ' . $gpt41_settings . ' configuraciones de gpt-4.1 → gpt-4o</li>';
        }

        echo '</ul>';
        echo '<br><strong>NOTA:</strong> Los registros históricos en usage_tracking NO se modificarán (solo para auditoría).';
        echo '</div>';

        echo '<div style="margin: 40px 0; text-align: center;">';
        echo '<a href="?execute=1&confirm=yes" class="button button-danger">✓ SÍ, EJECUTAR AHORA</a> ';
        echo '<a href="?" class="button">✗ Cancelar</a>';
        echo '</div>';

    } else {
        // Modo vista previa
        echo '<h2>📋 Acciones Recomendadas</h2>';

        echo '<div class="info">';
        echo '<strong>✓ Pasos a seguir:</strong><br><ol>';
        echo '<li>Revisar los datos arriba para confirmar el problema</li>';
        echo '<li>Hacer clic en "Ejecutar Corrección" abajo</li>';
        echo '<li>Confirmar los cambios</li>';
        echo '<li>Verificar que las nuevas operaciones usen gpt-4o</li>';
        echo '</ol></div>';

        if (!empty($gpt41_models) || !empty($settings)) {
            echo '<div style="margin: 40px 0; text-align: center;">';
            echo '<a href="?execute=1" class="button button-danger">⚠️ EJECUTAR CORRECCIÓN</a>';
            echo '</div>';
        }
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
