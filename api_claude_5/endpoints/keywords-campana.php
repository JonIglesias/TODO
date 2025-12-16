<?php
/**
 * Endpoint: Keywords Base de Campaña
 * Sistema de 2 consultas para keywords aspiracionales fine art
 */

defined('API_ACCESS') or die('Direct access not permitted');

require_once API_BASE_DIR . '/core/BaseEndpoint.php';

class KeywordsCampanaEndpoint extends BaseEndpoint {

    public function handle() {
        $this->validateLicense();

        $niche = $this->params['niche'] ?? null;
        $companyDesc = $this->params['company_description'] ?? null;

        if (!$niche) {
            Response::error('niche es requerido', 400);
        }

        if (!$companyDesc) {
            Response::error('company_description es requerido', 400);
        }

        $keywordsSEO = $this->params['keywords_seo'] ?? '';

        // ========================================
        // CONSULTA 1: Análisis de Transformación
        // ========================================

        $analysisTemplate = $this->loadPrompt('keywords-campana-analysis');
        if (!$analysisTemplate) {
            Response::error('Error cargando template de análisis', 500);
        }

        $seoSection = $keywordsSEO ? "SEO Keywords: {$keywordsSEO}\n" : '';

        $analysisPrompt = $this->replaceVariables($analysisTemplate, [
            'niche' => $niche,
            'company_description' => $companyDesc,
            'keywords_seo_section' => $seoSection
        ]);

        $analysisResult = $this->openai->generateContent([
            'prompt' => $analysisPrompt,
            'max_tokens' => 500,
            'temperature' => 0.7
        ]);

        if (!$analysisResult['success']) {
            Response::error('Error en análisis: ' . $analysisResult['error'], 500);
        }

        $transformationAnalysis = trim($analysisResult['content']);

        // ========================================
        // CONSULTA 2: Generación de Keywords
        // ========================================

        $generationTemplate = $this->loadPrompt('keywords-campana-generation');
        if (!$generationTemplate) {
            Response::error('Error cargando template de generación', 500);
        }

        $generationPrompt = $this->replaceVariables($generationTemplate, [
            'transformation_analysis' => $transformationAnalysis
        ]);

        $keywordsResult = $this->openai->generateContent([
            'prompt' => $generationPrompt,
            'max_tokens' => 400,
            'temperature' => 0.8
        ]);

        if (!$keywordsResult['success']) {
            Response::error('Error en generación: ' . $keywordsResult['error'], 500);
        }

        // Track usage (ambas consultas)
        $totalTokens = ($analysisResult['tokens_used'] ?? 0) + ($keywordsResult['tokens_used'] ?? 0);
        $this->trackUsage('campaign_image_keywords', [
            'success' => true,
            'tokens_used' => $totalTokens,
            'content' => $keywordsResult['content']
        ]);

        // FORMATO V4: Solo 'keywords'
        Response::success(['keywords' => trim($keywordsResult['content'])]);
    }
}
