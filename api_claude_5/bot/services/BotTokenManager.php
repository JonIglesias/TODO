<?php
/**
 * BotTokenManager Service
 *
 * Gestiona el consumo de tokens y tracking de uso para el chatbot
 *
 * @version 1.0
 */

defined('API_ACCESS') or die('Direct access not permitted');

require_once API_BASE_DIR . '/models/License.php';
require_once API_BASE_DIR . '/models/UsageTracking.php';
require_once API_BASE_DIR . '/bot/config.php';

class BotTokenManager {

    /**
     * Trackear uso de tokens de una conversación
     *
     * @param int $licenseId
     * @param int $tokensInput Tokens del prompt (mensaje + contexto)
     * @param int $tokensOutput Tokens de la respuesta generada
     * @param string $model Modelo usado (ej: gpt-4o)
     * @param string|null $conversationId ID de la conversación
     * @param array $metadata Metadata adicional (opcional)
     * @return array
     */
    public function trackUsage($licenseId, $tokensInput, $tokensOutput, $model, $conversationId = null, $metadata = []) {
        return $this->trackUsageByType($licenseId, BOT_OPERATION_TYPE, $tokensInput, $tokensOutput, $model, $conversationId);
    }

    /**
     * Trackear uso de tokens con tipo de operación específico
     *
     * @param int $licenseId
     * @param string $operationType Tipo de operación (bot_chat, bot_translate, bot_kb)
     * @param int $tokensInput Tokens del prompt
     * @param int $tokensOutput Tokens de la respuesta
     * @param string $model Modelo usado
     * @param string|null $conversationId ID de la conversación (opcional)
     * @return array
     */
    public function trackUsageByType($licenseId, $operationType, $tokensInput, $tokensOutput, $model, $conversationId = null) {
        $tokensTotal = $tokensInput + $tokensOutput;

        // 1. Incrementar tokens_used_this_period en api_licenses
        $this->incrementLicenseTokens($licenseId, $tokensTotal);

        // 2. Preparar metadata adicional para logging
        if ($conversationId) {
            Logger::api('info', 'Bot operation tracked', [
                'license_id' => $licenseId,
                'conversation_id' => $conversationId,
                'operation_type' => $operationType,
                'tokens' => $tokensTotal,
                'model' => $model
            ]);
        }

        // 3. Registrar en api_usage_tracking
        $trackingData = [
            'license_id' => $licenseId,
            'operation_type' => $operationType,
            'tokens_input' => $tokensInput,
            'tokens_output' => $tokensOutput,
            'tokens_total' => $tokensTotal,
            'model' => $model
        ];

        $usageTracking = new UsageTracking();
        $usageTracking->track($trackingData);

        return [
            'success' => true,
            'tokens_tracked' => $tokensTotal,
            'tokens_input' => $tokensInput,
            'tokens_output' => $tokensOutput
        ];
    }

    /**
     * Incrementar tokens en la licencia
     */
    private function incrementLicenseTokens($licenseId, $tokensUsed) {
        $licenseModel = new License();
        return $licenseModel->incrementTokens($licenseId, $tokensUsed);
    }

    /**
     * Verificar si hay tokens disponibles
     */
    public function hasTokensAvailable($license, $tokensNeeded = 0) {
        $available = $license['tokens_limit'] - $license['tokens_used_this_period'];
        return $available >= $tokensNeeded;
    }

    /**
     * Obtener tokens disponibles
     */
    public function getAvailableTokens($license) {
        return max(0, $license['tokens_limit'] - $license['tokens_used_this_period']);
    }

    /**
     * Obtener estadísticas de uso del chatbot para una licencia
     *
     * @param int $licenseId
     * @param int $days Días hacia atrás (default: 30)
     * @return array
     */
    public function getUsageStats($licenseId, $days = 30) {
        $usageTracking = new UsageTracking();
        $db = Database::getInstance();

        $startDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // Obtener resumen del periodo
        $sql = "SELECT
                    COUNT(*) as total_conversations,
                    SUM(tokens_total) as tokens_used,
                    SUM(tokens_input) as tokens_input,
                    SUM(tokens_output) as tokens_output,
                    SUM(cost_total) as cost_total
                FROM " . DB_PREFIX . "usage_tracking
                WHERE license_id = ?
                AND operation_type = ?
                AND created_at >= ?";

        $summary = $db->fetchOne($sql, [$licenseId, BOT_OPERATION_TYPE, $startDate]);

        // Obtener datos diarios
        $sqlDaily = "SELECT
                        DATE(created_at) as date,
                        COUNT(*) as conversations,
                        SUM(tokens_total) as tokens
                    FROM " . DB_PREFIX . "usage_tracking
                    WHERE license_id = ?
                    AND operation_type = ?
                    AND created_at >= ?
                    GROUP BY DATE(created_at)
                    ORDER BY date DESC";

        $daily = $db->query($sqlDaily, [$licenseId, BOT_OPERATION_TYPE, $startDate]);

        // Obtener datos agrupados por tipo de operación (todos los bot_*)
        $sqlByOperation = "SELECT
                            operation_type,
                            COUNT(*) as count,
                            SUM(tokens_total) as tokens
                        FROM " . DB_PREFIX . "usage_tracking
                        WHERE license_id = ?
                        AND operation_type LIKE 'bot_%'
                        AND created_at >= ?
                        GROUP BY operation_type
                        ORDER BY tokens DESC";

        $byOperation = $db->query($sqlByOperation, [$licenseId, $startDate]);

        // Formatear datos by_operation para el frontend
        $operationsList = [];
        if ($byOperation) {
            foreach ($byOperation as $op) {
                $operationsList[] = [
                    'operation' => str_replace('bot_', '', $op['operation_type']),
                    'count' => (int)$op['count'],
                    'tokens' => (int)$op['tokens']
                ];
            }
        }

        return [
            'period' => [
                'from' => date('Y-m-d', strtotime($startDate)),
                'to' => date('Y-m-d'),
                'days' => $days
            ],
            'summary' => [
                'total_conversations' => (int)($summary['total_conversations'] ?? 0),
                'tokens_used' => (int)($summary['tokens_used'] ?? 0),
                'tokens_input' => (int)($summary['tokens_input'] ?? 0),
                'tokens_output' => (int)($summary['tokens_output'] ?? 0),
                'cost_total' => (float)($summary['cost_total'] ?? 0),
                'by_operation' => $operationsList
            ],
            'daily' => $daily ?: []
        ];
    }

    /**
     * Verificar si se necesita resetear el periodo
     * (Este método lo llamará el cron automático)
     */
    public function checkAndResetIfNeeded($license) {
        if (!$license['period_ends_at']) {
            return false;
        }

        // Si el periodo ha expirado
        if (strtotime($license['period_ends_at']) < time()) {
            $this->resetPeriod($license['id']);
            return true;
        }

        return false;
    }

    /**
     * Resetear periodo de tokens
     */
    private function resetPeriod($licenseId) {
        $licenseModel = new License();
        return $licenseModel->resetPeriodTokens($licenseId);
    }
}
