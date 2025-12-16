<?php
/**
 * Endpoint: Keywords de Imágenes Específicas
 * Sistema de 2 consultas para keywords únicas por post
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

        // ========================================
        // CONSULTA 1: Extracción de Conceptos Únicos
        // ========================================

        $conceptsTemplate = $this->loadPrompt('keywords-post-concepts');
        if (!$conceptsTemplate) {
            Response::error('Error cargando template de conceptos', 500);
        }

        $conceptsPrompt = $this->replaceVariables($conceptsTemplate, [
            'title' => $title,
            'company_description' => $companyDesc,
            'keywords_images_base' => $baseImageKeywords ?: 'No base keywords provided'
        ]);

        $conceptsResult = $this->openai->generateContent([
            'prompt' => $conceptsPrompt,
            'max_tokens' => 500,
            'temperature' => 0.7
        ]);

        if (!$conceptsResult['success']) {
            Response::error('Error extrayendo conceptos: ' . $conceptsResult['error'], 500);
        }

        $uniqueConcepts = trim($conceptsResult['content']);

        // ========================================
        // CONSULTA 2: Generación de Mix de Keywords
        // ========================================

        $mixTemplate = $this->loadPrompt('keywords-post-mix');
        if (!$mixTemplate) {
            Response::error('Error cargando template de mix', 500);
        }

        $mixPrompt = $this->replaceVariables($mixTemplate, [
            'title' => $title,
            'unique_concepts' => $uniqueConcepts,
            'keywords_images_base' => $baseImageKeywords ?: 'No base keywords - create all unique'
        ]);

        $keywordsResult = $this->openai->generateContent([
            'prompt' => $mixPrompt,
            'max_tokens' => 450,
            'temperature' => 0.85
        ]);

        if (!$keywordsResult['success']) {
            Response::error('Error generando keywords: ' . $keywordsResult['error'], 500);
        }

        // Track usage (ambas consultas)
        $totalTokens = ($conceptsResult['tokens_used'] ?? 0) + ($keywordsResult['tokens_used'] ?? 0);
        $this->trackUsage('keywords_images', [
            'success' => true,
            'tokens_used' => $totalTokens,
            'content' => $keywordsResult['content']
        ]);

        Response::success(['keywords' => trim($keywordsResult['content'])]);
    }
}
