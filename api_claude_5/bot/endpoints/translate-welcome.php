<?php
/**
 * Endpoint: Translate Welcome Message
 *
 * Traduce el mensaje de bienvenida del chatbot a múltiples idiomas
 * Trackea el uso de tokens
 *
 * @version 1.0
 */

defined('API_ACCESS') or die('Direct access not permitted');

require_once API_BASE_DIR . '/core/Response.php';
require_once API_BASE_DIR . '/bot/services/BotLicenseValidator.php';
require_once API_BASE_DIR . '/bot/services/BotTokenManager.php';
require_once API_BASE_DIR . '/core/Database.php';

class BotTranslateWelcomeEndpoint {

    public function handle() {
        // Obtener parámetros
        $input = Response::getJsonInput();
        $licenseKey = $input['license_key'] ?? null;
        $domain = $input['domain'] ?? null;
        $text = $input['text'] ?? null;
        $languages = $input['languages'] ?? ['es', 'en', 'fr', 'de', 'it', 'pt'];

        if (!$licenseKey) {
            Response::error('license_key is required', 400);
        }

        if (!$domain) {
            Response::error('domain is required', 400);
        }

        if (!$text || trim($text) === '') {
            Response::error('text is required', 400);
        }

        // Validar licencia
        $validator = new BotLicenseValidator();
        $validation = $validator->validate($licenseKey, $domain);

        if (!$validation['valid']) {
            Response::error($validation['reason'] ?? 'Invalid license', 401);
        }

        $license = $validation['license'];

        // Verificar tokens disponibles (estimado: ~50-100 tokens para traducción)
        $tokenManager = new BotTokenManager();
        if (!$tokenManager->hasTokensAvailable($license, 100)) {
            Response::error('Insufficient tokens to translate welcome message', 402, [
                'code' => 'TOKEN_LIMIT_EXCEEDED',
                'tokens_available' => $tokenManager->getAvailableTokens($license)
            ]);
        }

        // Traducir mensaje usando OpenAI
        $result = $this->translateText($text, $languages);

        if (!$result['success']) {
            Response::error($result['error'] ?? 'Translation failed', 500);
        }

        // Trackear uso de tokens con tipo específico
        $tokenManager->trackUsageByType(
            $license['id'],
            'bot_translate',
            $result['usage']['prompt_tokens'] ?? 0,
            $result['usage']['completion_tokens'] ?? 0,
            'gpt-4o-mini'
        );

        Response::success([
            'translations' => $result['translations'],
            'usage' => [
                'prompt_tokens' => $result['usage']['prompt_tokens'] ?? 0,
                'completion_tokens' => $result['usage']['completion_tokens'] ?? 0,
                'total_tokens' => $result['usage']['total_tokens'] ?? 0
            ]
        ]);
    }

    /**
     * Traducir texto usando OpenAI
     */
    private function translateText($text, $languages) {
        // Leer API key desde base de datos (igual que OpenAIService)
        $apiKey = '';
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("SELECT setting_value FROM " . DB_PREFIX . "settings WHERE setting_key = 'openai_api_key' LIMIT 1");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $apiKey = $result['setting_value'] ?? '';
        } catch (Exception $e) {
            // Si falla la BD, intentar config como fallback
            $apiKey = defined('OPENAI_API_KEY') ? OPENAI_API_KEY : getenv('OPENAI_API_KEY');
        }

        if (!$apiKey) {
            return [
                'success' => false,
                'error' => 'OpenAI API key not configured on server'
            ];
        }

        $prompt = "Traduce el saludo entre <<< y >>> a estos idiomas: " . implode(', ', $languages) . ".\n" .
                  "Devuelve SOLO un objeto JSON {\"es\":\"...\",\"en\":\"...\"} sin texto extra.\n" .
                  "<<<" . $text . ">>>";

        $payload = [
            'model' => 'gpt-4o-mini',
            'temperature' => 0.2,
            'messages' => [
                ['role' => 'system', 'content' => 'Eres un traductor profesional. Devuelves exclusivamente JSON válido.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => 300
        ];

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return [
                'success' => false,
                'error' => 'OpenAI API request failed'
            ];
        }

        $data = json_decode($response, true);
        $content = $data['choices'][0]['message']['content'] ?? '';
        $usage = $data['usage'] ?? [];

        // Extraer JSON del contenido
        $start = strpos($content, '{');
        $end = strrpos($content, '}');

        if ($start === false || $end === false) {
            return [
                'success' => false,
                'error' => 'Invalid response format from OpenAI'
            ];
        }

        $jsonStr = substr($content, $start, $end - $start + 1);
        $translations = json_decode($jsonStr, true);

        if (!is_array($translations)) {
            return [
                'success' => false,
                'error' => 'Failed to parse translations JSON'
            ];
        }

        return [
            'success' => true,
            'translations' => $translations,
            'usage' => $usage
        ];
    }
}
