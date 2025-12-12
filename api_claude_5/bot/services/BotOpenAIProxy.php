<?php
/**
 * BotOpenAIProxy Service
 *
 * Proxy para comunicación con OpenAI específico para el chatbot
 * Maneja contexto, historial, KB y construcción de mensajes
 *
 * @version 1.0
 */

defined('API_ACCESS') or die('Direct access not permitted');

require_once API_BASE_DIR . '/bot/config.php';
require_once API_BASE_DIR . '/core/Database.php';

class BotOpenAIProxy {

    private $apiKey;

    /**
     * Constructor
     */
    public function __construct() {
        // Obtener API key de settings en BD o de constante
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("SELECT setting_value FROM " . DB_PREFIX . "settings WHERE setting_key = 'openai_api_key' LIMIT 1");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->apiKey = $result['setting_value'] ?? OPENAI_API_KEY ?? '';
        } catch (Exception $e) {
            $this->apiKey = OPENAI_API_KEY ?? '';
        }
    }

    /**
     * Verificar si la API Key está configurada
     */
    public function hasApiKey() {
        return !empty($this->apiKey);
    }

    /**
     * Generar respuesta del chatbot
     *
     * @param array $params Parámetros de la petición
     *   - message: string (requerido) - Mensaje del usuario
     *   - context: array (opcional) - Contexto de la conversación
     *     - kb_content: string - Contenido de la Knowledge Base
     *     - history: array - Historial de mensajes
     *     - page_url: string - URL de la página actual
     *     - page_title: string - Título de la página actual
     *   - settings: array (opcional) - Configuración
     *     - model: string - Modelo a usar
     *     - temperature: float - Creatividad (0-1)
     *     - max_tokens: int - Máximo de tokens
     *     - system_prompt: string - Prompt del sistema
     *
     * @return array Respuesta con 'success', 'response', 'usage', etc.
     */
    public function generateResponse($params) {
        // Verificar API Key
        if (!$this->hasApiKey()) {
            return [
                'success' => false,
                'error' => 'OpenAI API Key is not configured in the API',
                'code' => 'OPENAI_API_KEY_MISSING'
            ];
        }

        // Validar parámetros requeridos
        if (empty($params['message'])) {
            return [
                'success' => false,
                'error' => 'Message is required',
                'code' => 'MESSAGE_REQUIRED'
            ];
        }

        try {
            // Obtener configuración
            $context = $params['context'] ?? [];
            $settings = $params['settings'] ?? [];

            $model = $settings['model'] ?? BOT_DEFAULT_MODEL;
            $temperature = $settings['temperature'] ?? BOT_TEMPERATURE;
            $maxTokens = $settings['max_tokens'] ?? BOT_MAX_TOKENS;
            $systemPrompt = $settings['system_prompt'] ?? $this->getDefaultSystemPrompt();

            // Construir mensajes
            $messages = $this->buildMessages($systemPrompt, $context, $params['message']);

            // Preparar payload para OpenAI
            $payload = [
                'model' => $model,
                'messages' => $messages,
                'max_tokens' => $maxTokens,
                'temperature' => $temperature
            ];

            // Realizar petición a OpenAI
            $response = $this->makeRequest('https://api.openai.com/v1/chat/completions', $payload);

            if (!$response['success']) {
                return $response;
            }

            // Procesar respuesta
            $data = $response['data'];

            if (!isset($data['choices'][0]['message']['content'])) {
                return [
                    'success' => false,
                    'error' => 'Invalid response from OpenAI',
                    'code' => 'INVALID_OPENAI_RESPONSE'
                ];
            }

            $content = trim($data['choices'][0]['message']['content']);
            $usage = $data['usage'] ?? [];

            // Extraer información de caché (OpenAI devuelve cached_tokens desde Oct 2024)
            // cached_tokens = tokens que vinieron del caché (tienen 50% descuento automático)
            $cachedTokens = 0;
            if (isset($usage['prompt_tokens_details']['cached_tokens'])) {
                $cachedTokens = (int)$usage['prompt_tokens_details']['cached_tokens'];
            }

            return [
                'success' => true,
                'response' => $content,
                'usage' => [
                    'prompt_tokens' => $usage['prompt_tokens'] ?? 0,
                    'completion_tokens' => $usage['completion_tokens'] ?? 0,
                    'total_tokens' => $usage['total_tokens'] ?? 0,
                    'cached_tokens' => $cachedTokens  // Tokens que vinieron del caché
                ],
                'model' => $data['model'] ?? $model,
                'finish_reason' => $data['choices'][0]['finish_reason'] ?? null
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => 'OPENAI_EXCEPTION'
            ];
        }
    }

    /**
     * Construir array de mensajes para OpenAI
     *
     * ORDEN OPTIMIZADO PARA PROMPT CACHING:
     * - OpenAI cachea automáticamente el prefijo estático de los mensajes
     * - Contenido que se repite (system prompt, KB) va primero → se cachea
     * - Contenido que cambia (historial, mensaje) va al final → no afecta caché
     * - Esto reduce costos y mejora latencia en conversaciones consecutivas
     */
    private function buildMessages($systemPrompt, $context, $currentMessage) {
        $messages = [];

        // 1. System prompt principal
        // Se cachea: contenido estático que se repite en cada request
        $messages[] = [
            'role' => 'system',
            'content' => $systemPrompt
        ];

        // 2. Knowledge Base (si existe)
        // Se cachea: contenido grande y estático, ideal para caché
        // Límite: 100,000 caracteres (~33,333 tokens)
        // Para GPT-4/GPT-4o con context window de 128K tokens, esto usa ~26% del contexto
        if (!empty($context['kb_content'])) {
            $kbContent = $this->truncateContent($context['kb_content'], 100000);
            $messages[] = [
                'role' => 'system',
                'content' => "Knowledge Base about this website:\n\n" . $kbContent
            ];
        }

        // 3. Contexto de la página actual (si existe)
        // Semi-dinámico: cambia cuando el usuario navega a otra página
        // No se cachea, pero es contenido pequeño (~100 chars)
        if (!empty($context['page_url']) || !empty($context['page_title'])) {
            $pageContext = "Current page context:\n";
            if (!empty($context['page_title'])) {
                $pageContext .= "- Page title: " . $context['page_title'] . "\n";
            }
            if (!empty($context['page_url'])) {
                $pageContext .= "- Page URL: " . $context['page_url'] . "\n";
            }
            $messages[] = [
                'role' => 'system',
                'content' => trim($pageContext)
            ];
        }

        // 4. Historial de conversación (limitado a últimos N mensajes)
        // Dinámico: crece con cada mensaje nuevo
        // No se cachea, pero permite continuidad conversacional
        if (!empty($context['history']) && is_array($context['history'])) {
            $history = array_slice($context['history'], -BOT_MAX_HISTORY_MESSAGES);
            foreach ($history as $msg) {
                if (isset($msg['role']) && isset($msg['content'])) {
                    $messages[] = [
                        'role' => $msg['role'],
                        'content' => $msg['content']
                    ];
                }
            }
        }

        // 5. Mensaje actual del usuario
        // Siempre único: nunca se cachea
        $messages[] = [
            'role' => 'user',
            'content' => $currentMessage
        ];

        return $messages;
    }

    /**
     * Truncar contenido a un máximo de caracteres
     *
     * Usado principalmente para limitar el tamaño de la Knowledge Base.
     * El límite de 100K caracteres (~33K tokens) permite KBs extensas
     * sin sobrepasar la ventana de contexto de los modelos GPT-4/GPT-4o (128K tokens).
     *
     * @param string $content Contenido a truncar
     * @param int $maxChars Máximo de caracteres permitidos
     * @return string Contenido truncado si excede el límite
     */
    private function truncateContent($content, $maxChars) {
        if (strlen($content) <= $maxChars) {
            return $content;
        }
        return substr($content, 0, $maxChars) . '...';
    }

    /**
     * Obtener system prompt por defecto
     */
    private function getDefaultSystemPrompt() {
        return "You are a helpful and friendly AI assistant embedded in a website. " .
               "Your role is to help visitors by answering their questions about the website, " .
               "products, services, and general inquiries. " .
               "Be concise, professional, and helpful. " .
               "Use the knowledge base provided to give accurate answers. " .
               "If you don't know something, be honest about it.";
    }

    /**
     * Realizar petición HTTP a OpenAI
     */
    private function makeRequest($url, $data) {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey
            ],
            CURLOPT_TIMEOUT => BOT_OPENAI_TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'error' => 'Connection error: ' . $error,
                'code' => 'CURL_ERROR'
            ];
        }

        $responseData = json_decode($response, true);

        if ($httpCode !== 200) {
            $errorMessage = $responseData['error']['message'] ?? 'Unknown error';
            $errorCode = $responseData['error']['code'] ?? 'OPENAI_ERROR';

            return [
                'success' => false,
                'error' => $errorMessage,
                'code' => $errorCode,
                'http_code' => $httpCode
            ];
        }

        return [
            'success' => true,
            'data' => $responseData
        ];
    }

    /**
     * Test de conexión con OpenAI
     */
    public function testConnection() {
        if (!$this->hasApiKey()) {
            return [
                'success' => false,
                'error' => 'API Key not configured'
            ];
        }

        $result = $this->generateResponse([
            'message' => 'Say "OK"',
            'settings' => [
                'max_tokens' => 10
            ]
        ]);

        if ($result['success']) {
            return [
                'success' => true,
                'message' => 'OpenAI connection is working',
                'model' => $result['model']
            ];
        }

        return $result;
    }
}
