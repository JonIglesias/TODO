<?php
/**
 * Endpoint: Score Lead
 *
 * Calcula el score de un lead (0-10) basado en la conversación
 * usando el prompt de scoring configurado.
 *
 * @version 1.0
 */

defined('API_ACCESS') or die('Direct access not permitted');

require_once API_BASE_DIR . '/core/Response.php';
require_once API_BASE_DIR . '/bot/config.php';
require_once API_BASE_DIR . '/bot/services/BotLicenseValidator.php';
require_once API_BASE_DIR . '/bot/services/BotTokenManager.php';
require_once API_BASE_DIR . '/bot/services/BotOpenAIProxy.php';

class BotScoreLeadEndpoint {

    public function handle() {
        // Obtener parámetros
        $input = Response::getJsonInput();
        $licenseKey = $input['license_key'] ?? null;
        $domain = $input['domain'] ?? null;
        $conversation = $input['conversation'] ?? null;
        $scoringPrompt = $input['scoring_prompt'] ?? null;

        // Validar parámetros requeridos
        if (!$licenseKey) {
            Response::error('license_key is required', 400);
        }

        if (!$domain) {
            Response::error('domain is required', 400);
        }

        if (!$conversation || !is_array($conversation)) {
            Response::error('conversation is required and must be an array', 400);
        }

        if (!$scoringPrompt || trim($scoringPrompt) === '') {
            Response::error('scoring_prompt is required', 400);
        }

        // Validar licencia
        $validator = new BotLicenseValidator();
        $validation = $validator->validate($licenseKey, $domain);

        if (!$validation['valid']) {
            Response::error($validation['reason'] ?? 'Invalid license', 401);
        }

        $license = $validation['license'];

        // Verificar tokens mínimos disponibles (estimado: ~300-600 tokens)
        $tokenManager = new BotTokenManager();
        if (!$tokenManager->hasTokensAvailable($license, 700)) {
            Response::error('Insufficient tokens to score lead', 402, [
                'code' => 'TOKEN_LIMIT_EXCEEDED',
                'tokens_available' => $tokenManager->getAvailableTokens($license)
            ]);
        }

        // Construir conversación formateada (U/A)
        $conversationText = '';
        foreach ($conversation as $msg) {
            $role = ($msg['role'] === 'user') ? 'U' : 'A';
            $content = trim($msg['content'] ?? '');
            $conversationText .= "{$role}: {$content}\n";
        }

        if (trim($conversationText) === '') {
            Response::error('Conversation is empty', 400);
        }

        // Construir prompt final
        $prompt = trim($scoringPrompt) . "\n\nConversación:\n" . $conversationText . "\n";

        $messages = [
            ['role' => 'system', 'content' => 'Eres un analista comercial objetivo.'],
            ['role' => 'user', 'content' => $prompt]
        ];

        // Llamar a OpenAI a través del proxy
        $openAIProxy = new BotOpenAIProxy();
        $result = $openAIProxy->generateResponse([
            'message' => $prompt,
            'settings' => [
                'model' => BOT_DEFAULT_MODEL,
                'temperature' => 0.2,
                'max_tokens' => 600,
                'system_prompt' => 'Eres un analista comercial objetivo.'
            ]
        ]);

        if (!$result['success']) {
            Response::error($result['error'] ?? 'Failed to score lead', 500);
        }

        // Parsear respuesta JSON
        $responseText = $result['response'];
        if (!preg_match('/\{.*\}/s', $responseText, $matches)) {
            Response::error('Invalid response format from AI', 500);
        }

        $scoreData = json_decode($matches[0], true);
        if (!is_array($scoreData)) {
            Response::error('Failed to parse AI response', 500);
        }

        $score = isset($scoreData['score']) ? floatval($scoreData['score']) : null;
        $rationale = isset($scoreData['rationale']) ? trim($scoreData['rationale']) : '';

        // Validar que el score esté en rango 0-10
        if ($score !== null && ($score < 0 || $score > 10)) {
            $score = max(0, min(10, $score));
        }

        // Trackear uso de tokens
        $usage = $result['usage'];
        $tokensInput = $usage['prompt_tokens'] ?? 0;
        $tokensOutput = $usage['completion_tokens'] ?? 0;
        $cachedTokens = $usage['cached_tokens'] ?? 0;

        // Ajustar tokens por caché
        $tokensNoCacheados = $tokensInput - $cachedTokens;
        $tokensCacheadosAjustados = (int)($cachedTokens * 0.5);
        $tokensInputAjustado = $tokensNoCacheados + $tokensCacheadosAjustados;
        $tokensTotal = $tokensInputAjustado + $tokensOutput;

        $tokenManager->trackUsageByType(
            $license['id'],
            'bot_score_lead',
            $tokensInputAjustado,
            $tokensOutput,
            BOT_DEFAULT_MODEL
        );

        // Respuesta exitosa
        Response::success([
            'score' => $score,
            'rationale' => $rationale,
            'usage' => [
                'prompt_tokens' => $tokensInputAjustado,
                'completion_tokens' => $tokensOutput,
                'total_tokens' => $tokensTotal
            ]
        ]);
    }
}
