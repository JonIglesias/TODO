<?php
/**
 * Endpoint: Keywords de Imágenes Específicas
 * VERSIÓN CON PROMPT DINÁMICO - 1 consulta única
 */

defined('API_ACCESS') or die('Direct access not permitted');

require_once API_BASE_DIR . '/core/BaseEndpoint.php';

class KeywordsImagenesEndpoint extends BaseEndpoint {

    public function handle() {
        $this->validateLicense();

        $title = $this->params['title'] ?? null;
        $dynamicPrompt = $this->params['dynamic_prompt'] ?? null;

        if (!$title) {
            Response::error('title es requerido', 400);
        }

        if (!$dynamicPrompt) {
            Response::error('dynamic_prompt es requerido', 400);
        }

        // Reemplazar {{title}} en el prompt dinámico con el título real
        $finalPrompt = str_replace('{{title}}', $title, $dynamicPrompt);

        // Generar la keyword de imagen usando el prompt dinámico
        $result = $this->openai->generateContent([
            'prompt' => $finalPrompt,
            'max_tokens' => 100,
            'temperature' => 0.8
        ]);

        if (!$result['success']) {
            Response::error($result['error'], 500);
        }

        $keyword = trim($result['content']);

        // Validar que la keyword no esté vacía
        if (empty($keyword)) {
            Response::error('No se generó ninguna keyword', 500);
        }

        $this->trackUsage('keywords_images', $result);

        Response::success(['keywords' => $keyword]);
    }
}
