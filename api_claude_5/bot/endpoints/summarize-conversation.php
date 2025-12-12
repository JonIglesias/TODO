<?php
/**
 * Endpoint: Summarize Conversation
 *
 * Genera un resumen en viñetas de una conversación basándose SOLO
 * en los mensajes del usuario (no incluye respuestas del bot).
 *
 * @version 1.0
 */

defined('API_ACCESS') or die('Direct access not permitted');

require_once API_BASE_DIR . '/core/Response.php';
require_once API_BASE_DIR . '/bot/config.php';
require_once API_BASE_DIR . '/bot/services/BotLicenseValidator.php';
require_once API_BASE_DIR . '/bot/services/BotTokenManager.php';
require_once API_BASE_DIR . '/bot/services/BotOpenAIProxy.php';

class BotSummarizeConversationEndpoint {

    public function handle() {
        // Obtener parámetros
        $input = Response::getJsonInput();
        $licenseKey = $input['license_key'] ?? null;
        $domain = $input['domain'] ?? null;
        $userMessages = $input['user_messages'] ?? null;
        $summaryPrompt = $input['summary_prompt'] ?? null;

        // Validar parámetros requeridos
        if (!$licenseKey) {
            Response::error('license_key is required', 400);
        }

        if (!$domain) {
            Response::error('domain is required', 400);
        }

        if (!$userMessages || !is_array($userMessages)) {
            Response::error('user_messages is required and must be an array', 400);
        }

        // Prompt por defecto si no se proporciona
        $defaultPrompt = "Lee exclusivamente lo que escribe el usuario y resume en 3-5 viñetas claras: qué solicita, datos relevantes (fechas, cantidades, referencias), dudas y señales de intención/urgencia. Sé conciso, español neutro, sin adornos ni conclusiones.";

        if (!$summaryPrompt || trim($summaryPrompt) === '') {
            $summaryPrompt = $defaultPrompt;
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
            Response::error('Insufficient tokens to summarize conversation', 402, [
                'code' => 'TOKEN_LIMIT_EXCEEDED',
                'tokens_available' => $tokenManager->getAvailableTokens($license)
            ]);
        }

        // Construir corpus SOLO con mensajes del usuario
        $corpus = '';
        foreach ($userMessages as $msg) {
            $content = trim($msg);
            if ($content !== '') {
                $corpus .= "U: {$content}\n";
            }
        }

        if (trim($corpus) === '') {
            Response::error('No user messages provided', 400);
        }

        // Addendum importante
        $addendum = "IMPORTANTE (no inventes): si aparecen nombre, email o teléfono, inclúyelos explícitamente.\n".
                    "Si hay prefijo internacional y número, devuelve el teléfono en formato E.164 (sin espacios ni guiones), p. ej. +34600111222.\n".
                    "Incluye estos datos solo si aparecen; no asumas ni completes faltantes.";

        $promptEffective = trim($summaryPrompt) . "\n\n" . $addendum;
        $fullPrompt = $promptEffective . "\n\nConversación (solo el usuario):\n" . $corpus;

        $messages = [
            ['role' => 'system', 'content' => 'Eres un analista de leads. Responde solo con viñetas, sin prefacios.'],
            ['role' => 'user', 'content' => $fullPrompt]
        ];

        // Llamar a OpenAI a través del proxy
        $openAIProxy = new BotOpenAIProxy();
        $result = $openAIProxy->generateResponse([
            'message' => $fullPrompt,
            'settings' => [
                'model' => BOT_DEFAULT_MODEL,
                'temperature' => 0.2,
                'max_tokens' => 600,
                'system_prompt' => 'Eres un analista de leads. Responde solo con viñetas, sin prefacios.'
            ]
        ]);

        if (!$result['success']) {
            Response::error($result['error'] ?? 'Failed to summarize conversation', 500);
        }

        // Obtener resumen (texto plano, no JSON)
        $summary = trim($result['response']);

        if ($summary === '') {
            Response::error('AI returned empty summary', 500);
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
            'bot_summarize',
            $tokensInputAjustado,
            $tokensOutput,
            BOT_DEFAULT_MODEL
        );

        // Respuesta exitosa
        Response::success([
            'summary' => $summary,
            'usage' => [
                'prompt_tokens' => $tokensInputAjustado,
                'completion_tokens' => $tokensOutput,
                'total_tokens' => $tokensTotal
            ]
        ]);
    }
}
