<?php
/**
 * Endpoint: Generate Knowledge Base
 *
 * Genera contenido de base de conocimiento usando OpenAI
 * Trackea uso intensivo de tokens (puede ser 8000+ tokens)
 *
 * @version 1.0
 */

defined('API_ACCESS') or die('Direct access not permitted');

require_once API_BASE_DIR . '/core/Response.php';
require_once API_BASE_DIR . '/bot/services/BotLicenseValidator.php';
require_once API_BASE_DIR . '/bot/services/BotTokenManager.php';
require_once API_BASE_DIR . '/services/OpenAIService.php';
require_once API_BASE_DIR . '/core/Database.php';

class BotGenerateKBEndpoint {

    public function handle() {
        // Obtener parámetros
        $input = Response::getJsonInput();
        $licenseKey = $input['license_key'] ?? null;
        $domain = $input['domain'] ?? null;
        $model = $input['model'] ?? 'gpt-4o-mini';

        // Soportar tanto 'prompt' como 'user_prompt'/'system_prompt' (para KB)
        $userPrompt = $input['user_prompt'] ?? $input['prompt'] ?? null;
        $systemPrompt = $input['system_prompt'] ?? 'Eres un analista de contenidos web senior y redactas en español en HTML semántico válido, sin Markdown ni fences.';

        $maxTokens = $input['max_tokens'] ?? 8000;
        $temperature = $input['temperature'] ?? 0.2;

        if (!$licenseKey) {
            Response::error('license_key is required', 400);
        }

        if (!$domain) {
            Response::error('domain is required', 400);
        }

        if (!$userPrompt || trim($userPrompt) === '') {
            Response::error('user_prompt is required', 400);
        }

        // Validar licencia
        $validator = new BotLicenseValidator();
        $validation = $validator->validate($licenseKey, $domain);

        if (!$validation['valid']) {
            Response::error($validation['reason'] ?? 'Invalid license', 401);
        }

        $license = $validation['license'];

        // Verificar tokens disponibles (estimado conservador para KB)
        $estimatedTokens = min($maxTokens * 1.5, 12000); // Incluir prompt + completion
        $tokenManager = new BotTokenManager();

        if (!$tokenManager->hasTokensAvailable($license, $estimatedTokens)) {
            Response::error('Insufficient tokens for KB generation', 402, [
                'code' => 'TOKEN_LIMIT_EXCEEDED',
                'tokens_available' => $tokenManager->getAvailableTokens($license),
                'tokens_required' => $estimatedTokens
            ]);
        }

        // Generar contenido KB usando OpenAI
        $result = $this->generateKBContent($model, $systemPrompt, $userPrompt, $maxTokens, $temperature);

        if (!$result['success']) {
            Response::error($result['error'] ?? 'KB generation failed', 500);
        }

        // Trackear uso de tokens con tipo específico
        $tokenManager->trackUsageByType(
            $license['id'],
            'bot_kb',
            $result['usage']['prompt_tokens'] ?? 0,
            $result['usage']['completion_tokens'] ?? 0,
            $model
        );

        Response::success([
            'content' => $result['content'],
            'usage' => [
                'prompt_tokens' => $result['usage']['prompt_tokens'] ?? 0,
                'completion_tokens' => $result['usage']['completion_tokens'] ?? 0,
                'total_tokens' => $result['usage']['total_tokens'] ?? 0,
                'tokens_remaining' => $tokenManager->getAvailableTokens($license) - ($result['usage']['total_tokens'] ?? 0)
            ],
            'model' => $model
        ]);
    }

    /**
     * Generar contenido KB usando OpenAI
     */
    private function generateKBContent($model, $systemPrompt, $userPrompt, $maxTokens, $temperature) {
        // Usar OpenAIService (lee API key de BD, igual que GEOWriter)
        $openAI = new OpenAIService();

        // Construir prompt completo combinando system y user
        $fullPrompt = $systemPrompt . "\n\n" . $userPrompt;

        try {
            $result = $openAI->generateContent(
                $fullPrompt,
                $maxTokens,
                $temperature,
                $model
            );

            if (!$result['success']) {
                return [
                    'success' => false,
                    'error' => $result['error'] ?? 'OpenAI generation failed'
                ];
            }

            return [
                'success' => true,
                'content' => $result['content'],
                'usage' => [
                    'prompt_tokens' => $result['usage']['prompt_tokens'] ?? 0,
                    'completion_tokens' => $result['usage']['completion_tokens'] ?? 0,
                    'total_tokens' => $result['usage']['total_tokens'] ?? 0
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage()
            ];
        }
    }
}
