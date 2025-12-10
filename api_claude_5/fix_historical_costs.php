<?php
/**
 * Script para recalcular costes históricos en api_usage_tracking
 * usando los precios correctos según el modelo
 *
 * IMPORTANTE: Hacer backup de la tabla antes de ejecutar
 *
 * USO:
 * - Ver qué se cambiaría: ?preview=1
 * - Ejecutar UPDATE: ?execute=1&confirm=yes
 */

define('API_ACCESS', true);
define('API_BASE_DIR', __DIR__);

require_once API_BASE_DIR . '/config.php';
require_once API_BASE_DIR . '/core/Database.php';
require_once API_BASE_DIR . '/services/ModelPricingService.php';

header('Content-Type: text/html; charset=UTF-8');

$preview = isset($_GET['preview']) && $_GET['preview'] == '1';
$execute = isset($_GET['execute']) && $_GET['execute'] == '1';
$confirm = isset($_GET['confirm']) && $_GET['confirm'] == 'yes';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Recalcular Costes Históricos</title>
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
        .diff-positive { color: #4ec9b0; }
        .diff-negative { color: #ce9178; }
        .button { display: inline-block; padding: 12px 24px; margin: 10px 5px;
                  background: #0e639c; color: white; text-decoration: none;
                  border-radius: 4px; cursor: pointer; }
        .button:hover { background: #1177bb; }
        .button-danger { background: #d32f2f; }
        .button-danger:hover { background: #f44336; }
        pre { background: #252526; padding: 15px; overflow-x: auto; border-left: 3px solid #0e639c; }
        .summary { background: #2d2d30; padding: 20px; margin: 20px 0; border-radius: 4px; }
        .summary-item { display: inline-block; margin: 10px 20px 10px 0; }
        .summary-label { color: #858585; }
        .summary-value { color: #4ec9b0; font-size: 1.2em; font-weight: bold; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔧 Recalcular Costes Históricos</h1>

    <div class="info">
        <strong>ℹ️ Información:</strong><br>
        Este script recalcula los costes en <code>api_usage_tracking</code> usando los precios correctos
        después del fix del bug de detección de modelos (gpt-4.1 detectado como gpt-4).
    </div>

<?php

try {
    $db = Database::getInstance();

    // Obtener todos los registros con modelo y tokens
    $records = $db->query("
        SELECT id, campaign_id, batch_id, endpoint, model,
               tokens_input, tokens_output,
               cost_input, cost_output, cost_total,
               created_at
        FROM " . DB_PREFIX . "usage_tracking
        WHERE model IS NOT NULL
          AND model != ''
          AND (tokens_input > 0 OR tokens_output > 0)
        ORDER BY id ASC
    ");

    if (empty($records)) {
        echo '<div class="warning">⚠️ No hay registros para procesar.</div>';
        exit;
    }

    // Calcular diferencias
    $records_with_diff = [];
    $total_correction = 0;
    $affected_campaigns = [];

    foreach ($records as $record) {
        $id = $record['id'];
        $model = $record['model'];
        $tokens_input = floatval($record['tokens_input']);
        $tokens_output = floatval($record['tokens_output']);
        $old_cost_total = floatval($record['cost_total']);

        // Obtener precios correctos
        $prices = ModelPricingService::getPrices($model);

        // Calcular costes correctos
        $new_cost_input = ($tokens_input / 1000000) * $prices['input'];
        $new_cost_output = ($tokens_output / 1000000) * $prices['output'];
        $new_cost_total = $new_cost_input + $new_cost_output;

        $diff = $old_cost_total - $new_cost_total;

        // Solo incluir si hay diferencia significativa
        if (abs($diff) > 0.0001) {
            $record['new_cost_input'] = $new_cost_input;
            $record['new_cost_output'] = $new_cost_output;
            $record['new_cost_total'] = $new_cost_total;
            $record['diff'] = $diff;
            $record['prices'] = $prices;
            $records_with_diff[] = $record;

            $total_correction += $diff;

            if (!isset($affected_campaigns[$record['campaign_id']])) {
                $affected_campaigns[$record['campaign_id']] = [
                    'count' => 0,
                    'old_total' => 0,
                    'new_total' => 0
                ];
            }
            $affected_campaigns[$record['campaign_id']]['count']++;
            $affected_campaigns[$record['campaign_id']]['old_total'] += $old_cost_total;
            $affected_campaigns[$record['campaign_id']]['new_total'] += $new_cost_total;
        }
    }

    // SUMMARY
    echo '<div class="summary">';
    echo '<div class="summary-item">';
    echo '<div class="summary-label">Total Registros</div>';
    echo '<div class="summary-value">' . number_format(count($records)) . '</div>';
    echo '</div>';
    echo '<div class="summary-item">';
    echo '<div class="summary-label">Registros a Actualizar</div>';
    echo '<div class="summary-value">' . number_format(count($records_with_diff)) . '</div>';
    echo '</div>';
    echo '<div class="summary-item">';
    echo '<div class="summary-label">Corrección Total</div>';
    echo '<div class="summary-value class="diff-positive">$' . number_format($total_correction, 5) . '</div>';
    echo '</div>';
    echo '<div class="summary-item">';
    echo '<div class="summary-label">Campañas Afectadas</div>';
    echo '<div class="summary-value">' . count($affected_campaigns) . '</div>';
    echo '</div>';
    echo '</div>';

    // PREVIEW MODE (default)
    if (!$execute) {
        echo '<h2>📋 Vista Previa - Top 50 Registros con Mayor Diferencia</h2>';

        echo '<div class="warning">';
        echo '<strong>⚠️ IMPORTANTE:</strong><br>';
        echo '• Esta es solo una vista previa. No se ha modificado ningún dato.<br>';
        echo '• Haz backup antes de ejecutar: <code>mysqldump -u usuario -p basedatos api_usage_tracking > backup.sql</code>';
        echo '</div>';

        // Ordenar por diferencia absoluta (mayor primero)
        usort($records_with_diff, function($a, $b) {
            return abs($b['diff']) <=> abs($a['diff']);
        });

        echo '<table>';
        echo '<tr>';
        echo '<th>ID</th>';
        echo '<th>Campaña</th>';
        echo '<th>Modelo</th>';
        echo '<th>Tokens</th>';
        echo '<th>Precio/1M</th>';
        echo '<th>Coste Actual</th>';
        echo '<th>Coste Correcto</th>';
        echo '<th>Diferencia</th>';
        echo '</tr>';

        $shown = 0;
        foreach ($records_with_diff as $rec) {
            if ($shown >= 50) break;

            echo '<tr>';
            echo '<td>' . $rec['id'] . '</td>';
            echo '<td>' . htmlspecialchars($rec['campaign_id']) . '</td>';
            echo '<td>' . htmlspecialchars($rec['model']) . '</td>';
            echo '<td>' . number_format($rec['tokens_input']) . 'i / ' . number_format($rec['tokens_output']) . 'o</td>';
            echo '<td>$' . $rec['prices']['input'] . '/$' . $rec['prices']['output'] . '</td>';
            echo '<td>$' . number_format($rec['cost_total'], 5) . '</td>';
            echo '<td>$' . number_format($rec['new_cost_total'], 5) . '</td>';
            echo '<td class="diff-positive">$' . number_format($rec['diff'], 5) . '</td>';
            echo '</tr>';

            $shown++;
        }
        echo '</table>';

        // Resumen por campaña
        echo '<h2>📊 Resumen por Campaña</h2>';
        echo '<table>';
        echo '<tr>';
        echo '<th>Campaña ID</th>';
        echo '<th>Operaciones</th>';
        echo '<th>Coste Actual</th>';
        echo '<th>Coste Correcto</th>';
        echo '<th>Sobrecargo</th>';
        echo '</tr>';

        foreach ($affected_campaigns as $camp_id => $data) {
            $diff = $data['old_total'] - $data['new_total'];
            echo '<tr>';
            echo '<td>' . htmlspecialchars($camp_id) . '</td>';
            echo '<td>' . $data['count'] . '</td>';
            echo '<td>$' . number_format($data['old_total'], 4) . '</td>';
            echo '<td>$' . number_format($data['new_total'], 4) . '</td>';
            echo '<td class="diff-positive">$' . number_format($diff, 4) . '</td>';
            echo '</tr>';
        }
        echo '</table>';

        // Botón para ejecutar
        echo '<div style="margin: 40px 0; text-align: center;">';
        echo '<a href="?execute=1" class="button button-danger">⚠️ EJECUTAR ACTUALIZACIÓN</a>';
        echo '<div style="margin-top: 10px; color: #858585;">Esto te pedirá confirmación final</div>';
        echo '</div>';

    // EXECUTE MODE
    } else if ($execute && $confirm === 'yes') {
        echo '<h2>⚙️ Ejecutando Actualización...</h2>';

        $updated = 0;
        $errors = 0;

        foreach ($records_with_diff as $rec) {
            try {
                $db->query("
                    UPDATE " . DB_PREFIX . "usage_tracking
                    SET
                        cost_input = ?,
                        cost_output = ?,
                        cost_total = ?
                    WHERE id = ?
                ", [
                    $rec['new_cost_input'],
                    $rec['new_cost_output'],
                    $rec['new_cost_total'],
                    $rec['id']
                ]);
                $updated++;
            } catch (Exception $e) {
                $errors++;
                echo '<div class="warning">ERROR en ID ' . $rec['id'] . ': ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }

        echo '<div class="success">';
        echo '<strong>✅ ACTUALIZACIÓN COMPLETADA</strong><br><br>';
        echo '<strong>Registros actualizados:</strong> ' . $updated . '<br>';
        echo '<strong>Errores:</strong> ' . $errors . '<br>';
        echo '<strong>Corrección total:</strong> $' . number_format($total_correction, 5) . '<br>';
        echo '</div>';

        echo '<div style="margin: 30px 0;">';
        echo '<a href="?" class="button">← Ver Resultado Final</a>';
        echo '</div>';

    // CONFIRM PAGE
    } else if ($execute && $confirm !== 'yes') {
        echo '<h2>⚠️ Confirmación Final</h2>';

        echo '<div class="warning">';
        echo '<strong>⚠️ ¡ÚLTIMA ADVERTENCIA!</strong><br><br>';
        echo 'Estás a punto de modificar <strong>' . count($records_with_diff) . ' registros</strong> en la base de datos.<br><br>';
        echo '<strong>¿Has hecho backup?</strong><br>';
        echo '<code>mysqldump -u usuario -p basedatos api_usage_tracking > backup_' . date('Ymd_His') . '.sql</code>';
        echo '</div>';

        echo '<div style="margin: 40px 0; text-align: center;">';
        echo '<a href="?execute=1&confirm=yes" class="button button-danger">✓ SÍ, EJECUTAR AHORA</a> ';
        echo '<a href="?" class="button">✗ Cancelar</a>';
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
