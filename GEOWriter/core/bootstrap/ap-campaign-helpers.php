<?php
if (!defined('ABSPATH')) exit;

/**
 * Helper para validar si una campaña está completamente configurada
 *
 * @param object $campaign Objeto de campaña
 * @return bool True si está completa, false si no
 */
function ap_is_campaign_complete($campaign): bool {
    if (!$campaign) {
        return false;
    }

    // Verificar campos obligatorios
    $required_fields = [
        'name',
        'domain',
        'company_desc',
        'niche',
        'num_posts',
        'post_length',
        'keywords_seo',
        'prompt_titles',
        'prompt_content',
        'category_id'
    ];

    foreach ($required_fields as $field) {
        if (empty($campaign->$field) || trim($campaign->$field) === '') {
            return false;
        }
    }

    return true;
}

/**
 * Obtiene campos faltantes en una campaña
 *
 * @param object $campaign Objeto de campaña
 * @return array Array con nombres de campos faltantes
 */
function ap_get_campaign_missing_fields($campaign): array {
    if (!$campaign) {
        return ['Campaña no encontrada'];
    }

    $missing = [];
    $field_labels = [
        'name' => 'Nombre de Campaña',
        'domain' => 'Dominio',
        'company_desc' => 'Descripción de Empresa',
        'niche' => 'Nicho',
        'num_posts' => 'Número de Posts',
        'post_length' => 'Extensión del Post',
        'keywords_seo' => 'Keywords SEO',
        'prompt_titles' => 'Prompt para Títulos',
        'prompt_content' => 'Prompt para Contenido',
        'category_id' => 'Categoría'
    ];

    foreach ($field_labels as $field => $label) {
        if (empty($campaign->$field) || trim($campaign->$field) === '') {
            $missing[] = $label;
        }
    }

    return $missing;
}
