<?php
/**
 * Endpoint: Generar Prompt Dinámico para Keywords de Imagen
 * Genera un prompt personalizado que luego se usa para crear keywords de imágenes
 */

defined('API_ACCESS') or die('Direct access not permitted');

require_once API_BASE_DIR . '/core/BaseEndpoint.php';

class GenerateImagePromptEndpoint extends BaseEndpoint {

    public function handle() {
        $this->validateLicense();

        $companyDesc = $this->params['company_description'] ?? null;
        $niche = $this->params['niche'] ?? '';
        $title = $this->params['title'] ?? '';
        $imageStyle = $this->params['image_style_selected'] ?? null;

        if (!$companyDesc) {
            Response::error('company_description es requerido', 400);
        }

        if (!$imageStyle) {
            Response::error('image_style_selected es requerido', 400);
        }

        // Validar que el estilo sea uno de los permitidos
        $validStyles = ['lifestyle', 'technical', 'luxury', 'natural', 'documentary', 'minimalist', 'editorial', 'corporate'];
        if (!in_array($imageStyle, $validStyles)) {
            Response::error('image_style_selected debe ser uno de: ' . implode(', ', $validStyles), 400);
        }

        // Cargar metaprompt
        $metaPromptTemplate = $this->loadPrompt('metaprompt_genera_prompt_de_key_imagen');
        if (!$metaPromptTemplate) {
            Response::error('Error cargando metaprompt', 500);
        }

        // Reemplazar variables en el metaprompt
        $metaPrompt = $this->replaceVariables($metaPromptTemplate, [
            'company_description' => $companyDesc,
            'niche' => $niche,
            'title' => $title,
            'image_style_selected' => $imageStyle
        ]);

        // Generar el prompt dinámico
        $result = $this->openai->generateContent([
            'prompt' => $metaPrompt,
            'max_tokens' => 800,
            'temperature' => 0.7
        ]);

        if (!$result['success']) {
            Response::error($result['error'], 500);
        }

        $dynamicPrompt = trim($result['content']);

        // Validar que el prompt generado no esté vacío
        if (empty($dynamicPrompt)) {
            Response::error('El prompt generado está vacío', 500);
        }

        $this->trackUsage('generate_image_prompt', $result);

        Response::success([
            'dynamic_prompt' => $dynamicPrompt
        ]);
    }
}
