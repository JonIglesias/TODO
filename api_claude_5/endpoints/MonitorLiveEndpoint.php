<?php
/**
 * Monitor Live Endpoint
 *
 * Endpoint para monitoreo en tiempo real de peticiones a la API
 * Solo consulta datos (lectura), no modifica nada
 *
 * @version 1.0
 */

defined('API_ACCESS') or die('Direct access not permitted');

require_once API_BASE_DIR . '/core/Response.php';
require_once API_BASE_DIR . '/core/Database.php';

class MonitorLiveEndpoint {

    private $db;
    private $usdToEur = 0.92; // Tasa de conversión USD a EUR

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Maneja la petición GET para obtener datos en tiempo real
     */
    public function handle() {
        // Parámetros opcionales
        $minutes = isset($_GET['minutes']) ? intval($_GET['minutes']) : 5;
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;

        // Limitar valores razonables
        $minutes = max(1, min($minutes, 60)); // Entre 1 y 60 minutos
        $limit = max(10, min($limit, 500)); // Entre 10 y 500 registros

        try {
            // Obtener operaciones recientes
            $operations = $this->getRecentOperations($minutes, $limit);

            // Calcular métricas
            $metrics = $this->calculateMetrics($operations, $minutes);

            Response::success([
                'operations' => $operations,
                'metrics' => $metrics,
                'config' => [
                    'minutes' => $minutes,
                    'limit' => $limit,
                    'usd_to_eur' => $this->usdToEur
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ]);

        } catch (Exception $e) {
            Response::error('Error al obtener datos del monitor: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene operaciones recientes de la base de datos
     */
    private function getRecentOperations($minutes, $limit) {
        $sql = "SELECT
                    ut.id,
                    ut.endpoint,
                    ut.operation_type,
                    ut.model,
                    ut.tokens_input,
                    ut.tokens_output,
                    ut.tokens_total,
                    ut.cost_input,
                    ut.cost_output,
                    ut.cost_total,
                    ut.license_id,
                    ut.campaign_id,
                    ut.batch_id,
                    ut.batch_type,
                    ut.created_at,
                    l.license_key,
                    l.user_email
                FROM " . DB_PREFIX . "usage_tracking ut
                LEFT JOIN " . DB_PREFIX . "licenses l ON ut.license_id = l.id
                WHERE ut.created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
                ORDER BY ut.created_at DESC
                LIMIT ?";

        $results = $this->db->query($sql, [$minutes, $limit]);

        // Procesar resultados y convertir a EUR
        $operations = [];
        foreach ($results as $row) {
            // Si endpoint está vacío o NULL, usar operation_type como fallback
            $endpoint = !empty($row['endpoint']) ? $row['endpoint'] : $row['operation_type'];

            // Si batch_type está vacío, poner NULL explícitamente
            $batchType = !empty($row['batch_type']) ? $row['batch_type'] : null;

            $operations[] = [
                'id' => $row['id'],
                'endpoint' => $endpoint ?? 'unknown',
                'operation_type' => $row['operation_type'],
                'model' => $row['model'] ?? 'gpt-4o-mini',
                'tokens' => [
                    'input' => intval($row['tokens_input'] ?? 0),
                    'output' => intval($row['tokens_output'] ?? 0),
                    'total' => intval($row['tokens_total'] ?? 0)
                ],
                'cost_usd' => [
                    'input' => floatval($row['cost_input'] ?? 0),
                    'output' => floatval($row['cost_output'] ?? 0),
                    'total' => floatval($row['cost_total'] ?? 0)
                ],
                'cost_eur' => [
                    'input' => floatval($row['cost_input'] ?? 0) * $this->usdToEur,
                    'output' => floatval($row['cost_output'] ?? 0) * $this->usdToEur,
                    'total' => floatval($row['cost_total'] ?? 0) * $this->usdToEur
                ],
                'license' => [
                    'id' => $row['license_id'],
                    'key' => $row['license_key'] ? substr($row['license_key'], 0, 12) . '...' : 'N/A',
                    'email' => $row['user_email'] ?? 'N/A'
                ],
                'batch_type' => $batchType,
                'campaign_id' => $row['campaign_id'],
                'batch_id' => $row['batch_id'],
                'timestamp' => $row['created_at'],
                'time_ago' => $this->timeAgo($row['created_at'])
            ];
        }

        return $operations;
    }

    /**
     * Calcula métricas agregadas
     */
    private function calculateMetrics($operations, $minutes) {
        if (empty($operations)) {
            return [
                'total_requests' => 0,
                'total_tokens' => 0,
                'total_cost_eur' => 0,
                'requests_per_minute' => 0,
                'tokens_per_minute' => 0,
                'cost_per_hour_eur' => 0,
                'top_endpoint' => 'N/A',
                'top_model' => 'N/A',
                'unique_licenses' => 0
            ];
        }

        $totalRequests = count($operations);
        $totalTokens = array_sum(array_column(array_column($operations, 'tokens'), 'total'));
        $totalCostEur = array_sum(array_column(array_column($operations, 'cost_eur'), 'total'));

        // Contar endpoints
        $endpoints = array_count_values(array_column($operations, 'endpoint'));
        arsort($endpoints);
        $topEndpoint = array_key_first($endpoints) ?? 'N/A';

        // Contar modelos
        $models = array_count_values(array_column($operations, 'model'));
        arsort($models);
        $topModel = array_key_first($models) ?? 'N/A';

        // Licencias únicas
        $uniqueLicenses = count(array_unique(array_column(array_column($operations, 'license'), 'id')));

        return [
            'total_requests' => $totalRequests,
            'total_tokens' => $totalTokens,
            'total_cost_eur' => round($totalCostEur, 4),
            'requests_per_minute' => round($totalRequests / $minutes, 2),
            'tokens_per_minute' => round($totalTokens / $minutes, 0),
            'cost_per_hour_eur' => round(($totalCostEur / $minutes) * 60, 4),
            'top_endpoint' => $topEndpoint,
            'top_model' => $topModel,
            'unique_licenses' => $uniqueLicenses,
            'endpoints_breakdown' => $endpoints,
            'models_breakdown' => $models
        ];
    }

    /**
     * Calcula tiempo transcurrido desde una fecha
     */
    private function timeAgo($datetime) {
        $timestamp = strtotime($datetime);
        $diff = time() - $timestamp;

        if ($diff < 60) {
            return $diff . 's';
        } elseif ($diff < 3600) {
            return floor($diff / 60) . 'm';
        } elseif ($diff < 86400) {
            return floor($diff / 3600) . 'h';
        } else {
            return floor($diff / 86400) . 'd';
        }
    }
}
