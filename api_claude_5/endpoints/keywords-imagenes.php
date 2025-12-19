<?php
/**
 * Endpoint: Keywords de Imágenes Específicas
 */

defined('API_ACCESS') or die('Direct access not permitted');

require_once API_BASE_DIR . '/core/BaseEndpoint.php';

class KeywordsImagenesEndpoint extends BaseEndpoint {

    public function handle() {
        $this->validateLicense();

        $title = $this->params['title'] ?? null;
        $companyDesc = $this->params['company_description'] ?? '';
        $baseImageKeywords = $this->params['keywords_images_base'] ?? '';

        if (!$title) {
            Response::error('title es requerido', 400);
        }

        // Cargar template desde archivo MD
        $template = $this->loadPrompt('keywords-imagenes');
        if (!$template) {
            Response::error('Error cargando template', 500);
        }

        // SOLO reemplazar variables simples - SIN construir contenido
        $prompt = $this->replaceVariables($template, [
            'title' => $title,
            'company_description' => $companyDesc,
            'keywords_images_base' => $baseImageKeywords
        ]);

        $result = $this->openai->generateContent([
            'prompt' => $prompt,
            'max_tokens' => 400,
            'temperature' => 0.8
        ]);

        if (!$result['success']) {
            Response::error($result['error'], 500);
        }

        $this->trackUsage('keywords_images', $result);

        Response::success(['keywords' => trim($result['content'])]);
    }
}
