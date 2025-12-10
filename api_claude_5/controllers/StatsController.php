<?php
/**
 * StatsController - Gestión de Estadísticas
 * Compatible EXACTAMENTE con endpoints de API V3
 * 
 * @version 4.0
 */

defined('API_ACCESS') or die('Direct access not permitted');

require_once API_BASE_DIR . '/core/Response.php';
require_once API_BASE_DIR . '/core/Database.php';
require_once API_BASE_DIR . '/models/License.php';

class StatsController {
    
    /**
     * GET/POST /get-stats
     * 
     * Obtener estadísticas detalladas de una licencia
     * REPLICA EXACTA del método Database::getDetailedStats() de V3
     */
    public function getStats() {
        // Leer de POST body (JSON) o GET params
        $input = Response::getJsonInput();
        
        $licenseKey = $input['license_key'] ?? $_GET['license_key'] ?? null;
        
        if (!$licenseKey) {
            Response::error('license_key es requerido', 400);
        }
        
        // Obtener licencia
        $licenseModel = new License();
        $license = $licenseModel->findByKey($licenseKey);
        
        if (!$license) {
            // Si no hay datos, devolver estructura vacía igual que V3
            Response::success([
                'operations' => [],
                'totals' => [
                    'total_operations' => 0,
                    'total_tokens' => 0,
                    'total_cost' => 0
                ],
                'period' => [
                    'from' => $input['date_from'] ?? $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days')),
                    'to' => $input['date_to'] ?? $_GET['date_to'] ?? date('Y-m-d')
                ]
            ]);
        }
        
        // Obtener fechas del filtro
        $dateFrom = $input['date_from'] ?? $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
        $dateTo = $input['date_to'] ?? $_GET['date_to'] ?? date('Y-m-d');
        
        // ==============================================================================
        // LÓGICA MODIFICADA: Agrupar por CAMPAÑA en lugar de por FECHA
        // ==============================================================================
        $db = Database::getInstance();

        // 1. Obtener registros agrupados por campaña, batch_type, endpoint y operation_type
        $rawData = $db->query("
            SELECT
                COALESCE(campaign_id, 'sin_campana') as campaign_group,
                COALESCE(campaign_name, 'Operaciones Individuales') as campaign_display_name,
                COALESCE(batch_type, 'otros') as batch_type,
                COALESCE(endpoint, operation_type) as endpoint,
                operation_type,
                COUNT(*) as count,
                SUM(tokens_total) as tokens,
                SUM(cost_total) as cost,
                MIN(created_at) as first_operation,
                MAX(created_at) as last_operation
            FROM " . DB_PREFIX . "usage_tracking
            WHERE license_id = ?
            AND DATE(created_at) BETWEEN ? AND ?
            GROUP BY campaign_group, campaign_display_name, batch_type, endpoint, operation_type
            ORDER BY MAX(created_at) DESC, campaign_group,
                CASE batch_type
                    WHEN 'SETUP' THEN 1
                    WHEN 'COLA' THEN 2
                    WHEN 'CONTENIDO' THEN 3
                    ELSE 4
                END,
                endpoint
        ", [$license['id'], $dateFrom, $dateTo]);
        
        // Si no hay datos, devolver estructura vacía
        if (empty($rawData)) {
            Response::success([
                'operations' => [],
                'totals' => [
                    'total_operations' => 0,
                    'total_tokens' => 0,
                    'total_cost' => 0
                ],
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ]
            ]);
        }
        
        // 2. Construir estructura jerárquica: Campaña → batch_type → endpoint
        // $campaignData = [
        //   'campaign_123' => [
        //     'campaign_name' => 'Mi Campaña',
        //     'first_operation' => '2025-12-09 10:00:00',
        //     'last_operation' => '2025-12-09 12:30:00',
        //     'operations_count' => 50,
        //     'tokens_total' => 18000,
        //     'cost_total' => 0.18,
        //     'processes' => [
        //       'SETUP' => [
        //         'operations_count' => 5,
        //         'tokens_total' => 3000,
        //         'cost_total' => 0.03,
        //         'endpoints' => [
        //           'descripcion-empresa' => ['count' => 1, 'tokens' => 500, 'cost' => 0.005],
        //           'keywords-seo' => ['count' => 1, 'tokens' => 400, 'cost' => 0.004],
        //           ...
        //         ]
        //       ],
        //       'COLA' => [...],
        //       'CONTENIDO' => [...]
        //     ]
        //   ]
        // ]
        $campaignData = [];
        foreach ($rawData as $row) {
            $campaignGroup = $row['campaign_group'];
            $campaignName = $row['campaign_display_name'];
            $batchType = $row['batch_type'];
            $endpoint = $row['endpoint'];

            // Inicializar campaña si no existe
            if (!isset($campaignData[$campaignGroup])) {
                $campaignData[$campaignGroup] = [
                    'campaign_name' => $campaignName,
                    'first_operation' => $row['first_operation'],
                    'last_operation' => $row['last_operation'],
                    'operations_count' => 0,
                    'tokens_total' => 0,
                    'cost_total' => 0,
                    'processes' => []
                ];
            }

            // Inicializar batch_type si no existe
            if (!isset($campaignData[$campaignGroup]['processes'][$batchType])) {
                $campaignData[$campaignGroup]['processes'][$batchType] = [
                    'operations_count' => 0,
                    'tokens_total' => 0,
                    'cost_total' => 0,
                    'endpoints' => []
                ];
            }

            $count = intval($row['count']);
            $tokens = intval($row['tokens']);
            $cost = floatval($row['cost']);

            // Actualizar totales de campaña
            $campaignData[$campaignGroup]['operations_count'] += $count;
            $campaignData[$campaignGroup]['tokens_total'] += $tokens;
            $campaignData[$campaignGroup]['cost_total'] += $cost;

            // Actualizar totales de batch_type
            $campaignData[$campaignGroup]['processes'][$batchType]['operations_count'] += $count;
            $campaignData[$campaignGroup]['processes'][$batchType]['tokens_total'] += $tokens;
            $campaignData[$campaignGroup]['processes'][$batchType]['cost_total'] += $cost;

            // Agregar endpoint al batch_type
            $campaignData[$campaignGroup]['processes'][$batchType]['endpoints'][$endpoint] = [
                'count' => $count,
                'tokens' => $tokens,
                'cost' => $cost
            ];

            // Actualizar fechas
            if ($row['first_operation'] < $campaignData[$campaignGroup]['first_operation']) {
                $campaignData[$campaignGroup]['first_operation'] = $row['first_operation'];
            }
            if ($row['last_operation'] > $campaignData[$campaignGroup]['last_operation']) {
                $campaignData[$campaignGroup]['last_operation'] = $row['last_operation'];
            }
        }
        
        // 3. Construir respuesta con estructura jerárquica
        $campaigns = [];
        $totalOps = 0;
        $totalTokens = 0;
        $totalCost = 0;

        foreach ($campaignData as $campaignId => $campaign) {
            // Construir array de procesos (SETUP, COLA, CONTENIDO)
            $processes = [];
            foreach ($campaign['processes'] as $processType => $processData) {
                // Construir array de endpoints dentro del proceso
                $endpoints = [];
                foreach ($processData['endpoints'] as $endpointName => $endpointStats) {
                    $endpoints[] = [
                        'endpoint' => $endpointName,
                        'quantity' => $endpointStats['count'],
                        'tokens' => $endpointStats['tokens'],
                        'cost' => round($endpointStats['cost'], 6)
                    ];
                }

                $processes[] = [
                    'process_type' => $processType,
                    'totals' => [
                        'total_operations' => $processData['operations_count'],
                        'total_tokens' => $processData['tokens_total'],
                        'total_cost' => round($processData['cost_total'], 6)
                    ],
                    'endpoints' => $endpoints
                ];
            }

            // Actualizar totales globales
            $totalOps += $campaign['operations_count'];
            $totalTokens += $campaign['tokens_total'];
            $totalCost += $campaign['cost_total'];

            $campaigns[] = [
                'campaign_id' => $campaignId,
                'campaign_name' => $campaign['campaign_name'],
                'period' => [
                    'from' => $campaign['first_operation'],
                    'to' => $campaign['last_operation']
                ],
                'totals' => [
                    'total_operations' => $campaign['operations_count'],
                    'total_tokens' => $campaign['tokens_total'],
                    'total_cost' => round($campaign['cost_total'], 6)
                ],
                'processes' => $processes
            ];
        }

        // 4. Respuesta con estructura de CAMPAÑAS
        Response::success([
            'campaigns' => $campaigns,
            'summary' => [
                'total_campaigns' => count($campaigns),
                'total_operations' => $totalOps,
                'total_tokens' => $totalTokens,
                'total_cost' => round($totalCost, 6)
            ],
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo
            ]
        ]);
    }
    
    /**
     * POST /reset-stats
     * 
     * Resetear estadísticas de una licencia (solo admin)
     * Compatible con V3
     */
    public function resetStats() {
        $data = Response::getJsonInput();
        
        $targetLicense = $data['target_license'] ?? null;
        
        if (!$targetLicense) {
            Response::error('target_license es requerido', 400);
        }
        
        // Obtener licencia
        $licenseModel = new License();
        $license = $licenseModel->findByKey($targetLicense);
        
        if (!$license) {
            Response::error('Licencia no encontrada', 404);
        }
        
        // Eliminar registros de tracking
        $db = Database::getInstance();
        $stmt = $db->prepare("DELETE FROM " . DB_PREFIX . "usage_tracking WHERE license_id = ?");
        $stmt->execute([$license['id']]);
        
        Response::success([
            'message' => 'Estadísticas reseteadas correctamente',
            'license' => $targetLicense
        ]);
    }
    
    /**
     * Registrar uso de tokens (llamado internamente por otros controladores)
     */
    public static function trackUsage($licenseId, $operationType, $tokensTotal, $syncStatus = 'fresh') {
        try {
            $db = Database::getInstance();
            
            $db->insert('usage_tracking', [
                'license_id' => $licenseId,
                'operation_type' => $operationType,
                'tokens_total' => $tokensTotal,
                'sync_status_at_time' => $syncStatus,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Actualizar contador de la licencia
            $stmt = $db->prepare("
                UPDATE " . DB_PREFIX . "licenses 
                SET tokens_used_this_period = tokens_used_this_period + ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$tokensTotal, $licenseId]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Error tracking usage: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener resumen de uso del mes actual (para endpoint /usage)
     */
    public static function getCurrentMonthUsage($licenseId) {
        $db = Database::getInstance();
        
        $firstDayOfMonth = date('Y-m-01 00:00:00');
        
        $result = $db->fetchOne("
            SELECT 
                COUNT(*) as total_operations,
                SUM(tokens_total) as total_tokens
            FROM " . DB_PREFIX . "usage_tracking
            WHERE license_id = ?
            AND created_at >= ?
        ", [$licenseId, $firstDayOfMonth]);
        
        return [
            'operations' => intval($result['total_operations'] ?? 0),
            'tokens' => intval($result['total_tokens'] ?? 0)
        ];
    }
}
