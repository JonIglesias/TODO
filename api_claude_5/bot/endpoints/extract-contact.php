<?php
/**
 * Endpoint: Extract Contact Data
 *
 * Analiza una conversación completa para extraer y reconciliar datos de contacto
 * (nombre, email, teléfono) priorizando la información más reciente del usuario.
 *
 * @version 1.0
 */

defined('API_ACCESS') or die('Direct access not permitted');

require_once API_BASE_DIR . '/core/Response.php';
require_once API_BASE_DIR . '/bot/config.php';
require_once API_BASE_DIR . '/bot/services/BotLicenseValidator.php';
require_once API_BASE_DIR . '/bot/services/BotTokenManager.php';
require_once API_BASE_DIR . '/bot/services/BotOpenAIProxy.php';

class BotExtractContactEndpoint {

    public function handle() {
        // Obtener parámetros
        $input = Response::getJsonInput();
        $licenseKey = $input['license_key'] ?? null;
        $domain = $input['domain'] ?? null;
        $conversation = $input['conversation'] ?? null;
        $currentValues = $input['current_values'] ?? [];

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

        // Validar licencia
        $validator = new BotLicenseValidator();
        $validation = $validator->validate($licenseKey, $domain);

        if (!$validation['valid']) {
            Response::error($validation['reason'] ?? 'Invalid license', 401);
        }

        $license = $validation['license'];

        // Verificar tokens mínimos disponibles (estimado: ~200-400 tokens)
        $tokenManager = new BotTokenManager();
        if (!$tokenManager->hasTokensAvailable($license, 500)) {
            Response::error('Insufficient tokens to extract contact data', 402, [
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

        // Valores actuales
        $current = [
            'name'  => trim($currentValues['name'] ?? ''),
            'email' => trim($currentValues['email'] ?? ''),
            'phone' => trim($currentValues['phone'] ?? '')
        ];

        // Construir prompt
        $instructions = "Eres un asistente de datos. Lee la conversación completa (mensajes U/A). ".
                        "Tareas:\n".
                        "1) Si el USUARIO aporta o corrige NOMBRE, EMAIL o TELÉFONO en cualquier momento, toma el ÚLTIMO dato confirmado.\n".
                        "2) Si antes había número sin prefijo y más tarde aporta prefijo de país, combínalos y devuelve TELÉFONO en formato E.164 (sin espacios ni guiones), p. ej. +34600111222.\n".
                        "3) NO inventes datos. Si un dato no aparece claro, devuélvelo vacío.\n".
                        "4) Devuelve SOLO un JSON con esta forma:\n".
                        "{\n".
                        "  \"name\": \"\",\n".
                        "  \"email\": \"\",\n".
                        "  \"phone\": \"\",\n".
                        "  \"changed\": {\"name\":true|false, \"email\":true|false, \"phone\":true|false}\n".
                        "}\n".
                        "Reglas: prioriza lo más reciente dicho por el USUARIO. Si duda o se desdice, elige la última versión afirmativa.";

        $userContent = "Valores actuales:\n" . json_encode($current, JSON_UNESCAPED_UNICODE) . "\n\nConversación:\n" . $conversationText;

        $messages = [
            ['role' => 'system', 'content' => $instructions],
            ['role' => 'user', 'content' => $userContent]
        ];

        // Llamar a OpenAI a través del proxy
        $openAIProxy = new BotOpenAIProxy();
        $result = $openAIProxy->generateResponse([
            'message' => $userContent,
            'settings' => [
                'model' => BOT_DEFAULT_MODEL,
                'temperature' => 0.0,
                'max_tokens' => 400,
                'system_prompt' => $instructions
            ]
        ]);

        if (!$result['success']) {
            Response::error($result['error'] ?? 'Failed to extract contact data', 500);
        }

        // Parsear respuesta JSON
        $responseText = $result['response'];
        if (!preg_match('/\{.*\}/s', $responseText, $matches)) {
            Response::error('Invalid response format from AI', 500);
        }

        $extracted = json_decode($matches[0], true);
        if (!is_array($extracted)) {
            Response::error('Failed to parse AI response', 500);
        }

        // Normalizar y validar datos extraídos
        $extractedData = [
            'name'  => trim($extracted['name'] ?? ''),
            'email' => trim($extracted['email'] ?? ''),
            'phone' => trim($extracted['phone'] ?? ''),
            'changed' => $extracted['changed'] ?? ['name' => false, 'email' => false, 'phone' => false]
        ];

        // Trackear uso de tokens
        $usage = $result['usage'];
        $tokensInput = $usage['prompt_tokens'] ?? 0;
        $tokensOutput = $usage['completion_tokens'] ?? 0;
        $cachedTokens = $usage['cached_tokens'] ?? 0;

        // Ajustar tokens por caché (igual que en chat.php)
        $tokensNoCacheados = $tokensInput - $cachedTokens;
        $tokensCacheadosAjustados = (int)($cachedTokens * 0.5);
        $tokensInputAjustado = $tokensNoCacheados + $tokensCacheadosAjustados;
        $tokensTotal = $tokensInputAjustado + $tokensOutput;

        $tokenManager->trackUsageByType(
            $license['id'],
            'bot_extract_contact',
            $tokensInputAjustado,
            $tokensOutput,
            BOT_DEFAULT_MODEL
        );

        // Respuesta exitosa
        Response::success([
            'name'  => $extractedData['name'],
            'email' => $extractedData['email'],
            'phone' => $extractedData['phone'],
            'changed' => $extractedData['changed'],
            'usage' => [
                'prompt_tokens' => $tokensInputAjustado,
                'completion_tokens' => $tokensOutput,
                'total_tokens' => $tokensTotal
            ]
        ]);
    }
}
