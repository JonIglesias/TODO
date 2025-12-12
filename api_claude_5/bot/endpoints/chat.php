<?php
/**
 * Endpoint: Chat del Chatbot
 *
 * Procesa mensajes del usuario y genera respuestas con IA
 * Valida licencia, dominio, y tokens disponibles
 *
 * @version 1.0
 */

defined('API_ACCESS') or die('Direct access not permitted');

require_once API_BASE_DIR . '/core/Response.php';
require_once API_BASE_DIR . '/bot/services/BotLicenseValidator.php';
require_once API_BASE_DIR . '/bot/services/BotTokenManager.php';
require_once API_BASE_DIR . '/bot/services/BotOpenAIProxy.php';

class BotChatEndpoint {

    public function handle() {
        // Obtener input JSON
        $input = Response::getJsonInput();

        // Validar parámetros requeridos
        $licenseKey = $input['license_key'] ?? null;
        $domain = $input['domain'] ?? null;
        $message = $input['message'] ?? null;

        if (!$licenseKey) {
            Response::error('license_key is required', 400);
        }

        if (!$domain) {
            Response::error('domain is required', 400);
        }

        if (!$message || trim($message) === '') {
            Response::error('message is required and cannot be empty', 400);
        }

        // Validar longitud del mensaje
        if (strlen($message) > BOT_MAX_MESSAGE_LENGTH) {
            Response::error('Message exceeds maximum length of ' . BOT_MAX_MESSAGE_LENGTH . ' characters', 400);
        }

        // Validar licencia
        $validator = new BotLicenseValidator();
        $validation = $validator->validate($licenseKey, $domain);

        if (!$validation['valid']) {
            $errorData = [
                'code' => $this->getErrorCode($validation),
                'message' => $validation['reason'] ?? 'Invalid license'
            ];

            // Si es error de tokens, incluir información adicional
            if (isset($validation['tokens_used']) && isset($validation['tokens_limit'])) {
                $errorData['tokens_used'] = $validation['tokens_used'];
                $errorData['tokens_limit'] = $validation['tokens_limit'];
                $errorData['period_ends_at'] = $validation['period_ends_at'] ?? null;
                $errorData['upgrade_url'] = 'https://bocetosmarketing.com/upgrade';
            }

            Response::error($validation['reason'] ?? 'Invalid license', 401, $errorData);
        }

        $license = $validation['license'];

        // Verificar tokens mínimos disponibles
        $tokenManager = new BotTokenManager();
        if (!$tokenManager->hasTokensAvailable($license, BOT_MIN_TOKENS_REQUIRED)) {
            Response::error(
                'Insufficient tokens available. Minimum required: ' . BOT_MIN_TOKENS_REQUIRED,
                402,
                [
                    'code' => 'TOKEN_LIMIT_EXCEEDED',
                    'tokens_available' => $tokenManager->getAvailableTokens($license),
                    'tokens_limit' => $license['tokens_limit'],
                    'period_ends_at' => $license['period_ends_at'],
                    'upgrade_url' => 'https://bocetosmarketing.com/upgrade'
                ]
            );
        }

        // Obtener parámetros opcionales
        $conversationId = $input['conversation_id'] ?? null;
        $context = $input['context'] ?? [];
        $settings = $input['settings'] ?? [];

        // Generar respuesta con OpenAI
        $openAIProxy = new BotOpenAIProxy();
        $result = $openAIProxy->generateResponse([
            'message' => $message,
            'context' => $context,
            'settings' => $settings
        ]);

        if (!$result['success']) {
            Response::error(
                $result['error'] ?? 'Failed to generate response',
                500,
                [
                    'code' => $result['code'] ?? 'OPENAI_ERROR',
                    'message' => $result['error'] ?? 'Unknown error'
                ]
            );
        }

        // Obtener uso de tokens
        $usage = $result['usage'];
        $tokensInputRaw = $usage['prompt_tokens'] ?? 0;
        $tokensOutput = $usage['completion_tokens'] ?? 0;
        $cachedTokens = $usage['cached_tokens'] ?? 0;

        // AJUSTAR TOKENS DE ENTRADA APLICANDO DESCUENTO DE CACHÉ
        // OpenAI aplica 50% descuento a cached_tokens automáticamente
        // Para simplificar tracking, contamos cached_tokens al 50% de su valor
        // Ejemplo: 3700 tokens totales, 3400 cacheados
        //   → Tokens NO cacheados: 300
        //   → Tokens cacheados ajustados: 3400 × 0.5 = 1700
        //   → Total ajustado: 2000 tokens (equivalente al costo real)
        $tokensNoCacheados = $tokensInputRaw - $cachedTokens;
        $tokensCacheadosAjustados = (int)($cachedTokens * 0.5);
        $tokensInput = $tokensNoCacheados + $tokensCacheadosAjustados;

        // Total ajustado (input ajustado + output sin cambios)
        $tokensTotal = $tokensInput + $tokensOutput;

        // Trackear uso de tokens (con valores ajustados por caché)
        $model = $result['model'] ?? BOT_DEFAULT_MODEL;
        $tokenManager->trackUsage(
            $license['id'],
            $tokensInput,        // Tokens ajustados (con descuento caché aplicado)
            $tokensOutput,       // Output sin cambios
            $model,
            $conversationId,
            [
                'page_url' => $context['page_url'] ?? null,
                'page_title' => $context['page_title'] ?? null,
                'cached_tokens_raw' => $cachedTokens  // Guardar valor original para referencia
            ]
        );

        // Calcular tokens restantes
        $tokensUsedAfter = $license['tokens_used_this_period'] + $tokensTotal;
        $tokensRemaining = max(0, $license['tokens_limit'] - $tokensUsedAfter);

        // Respuesta exitosa
        $responseData = [
            'response' => $result['response'],
            'conversation_id' => $conversationId,
            'usage' => [
                'prompt_tokens' => $tokensInput,           // Tokens ajustados (con descuento caché)
                'completion_tokens' => $tokensOutput,
                'total_tokens' => $tokensTotal,            // Total ajustado
                'tokens_remaining' => $tokensRemaining
            ],
            'license' => [
                'tokens_used' => $tokensUsedAfter,
                'tokens_limit' => $license['tokens_limit'],
                'tokens_remaining' => $tokensRemaining,
                'period_ends_at' => $license['period_ends_at']
            ],
            'model' => $model
        ];

        // Incluir información de caché SI OpenAI la proporcionó (para debugging/transparencia)
        if ($cachedTokens > 0) {
            $responseData['cache_info'] = [
                'cached_tokens_raw' => $cachedTokens,           // Tokens que vinieron del caché
                'prompt_tokens_raw' => $tokensInputRaw,         // Tokens totales reportados por OpenAI
                'non_cached_tokens' => $tokensNoCacheados,      // Tokens NO cacheados (precio completo)
                'cached_tokens_adjusted' => $tokensCacheadosAjustados,  // Tokens cacheados contados al 50%
                'cache_hit_rate' => round(($cachedTokens / $tokensInputRaw) * 100, 1) . '%'
            ];
        }

        Response::success($responseData);
    }

    /**
     * Obtener código de error basado en la validación
     */
    private function getErrorCode($validation) {
        $reason = $validation['reason'] ?? '';

        if (stripos($reason, 'token limit') !== false) {
            return 'TOKEN_LIMIT_EXCEEDED';
        }
        if (stripos($reason, 'expired') !== false) {
            return 'LICENSE_EXPIRED';
        }
        if (stripos($reason, 'domain') !== false) {
            return 'DOMAIN_MISMATCH';
        }
        if (stripos($reason, 'not found') !== false) {
            return 'LICENSE_NOT_FOUND';
        }
        if (stripos($reason, 'suspended') !== false || stripos($reason, 'cancelled') !== false) {
            return 'LICENSE_INACTIVE';
        }

        return 'INVALID_LICENSE';
    }
}
