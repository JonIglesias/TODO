<?php
if (!defined('ABSPATH')) exit;

/**
 * GUARDAR PROMPT DE CONTENIDO
 */
add_action('wp_ajax_ap_save_prompt_content', 'ap_save_prompt_content_ajax');
function ap_save_prompt_content_ajax() {
    check_ajax_referer('ap_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permisos insuficientes']);
    }
    
    $campaign_id = intval($_POST['campaign_id'] ?? 0);
    $prompt_content = wp_kses_post($_POST['prompt_content'] ?? '');
    
    if (!$campaign_id || !$prompt_content) {
        wp_send_json_error(['message' => 'Datos inválidos']);
    }
    
    global $wpdb;
    $updated = $wpdb->update(
        $wpdb->prefix . 'ap_campaigns',
        ['prompt_content' => $prompt_content],
        ['id' => $campaign_id],
        ['%s'],
        ['%d']
    );

    if ($updated !== false) {
        wp_send_json_success(['message' => 'Prompt guardado correctamente']);
    } else {
        wp_send_json_error(['message' => 'Error al guardar en base de datos']);
    }
}

/**
 * GENERAR PROMPT DE CONTENIDO CON IA (basado en títulos de la cola)
 */
add_action('wp_ajax_ap_generate_prompt_content_from_titles', 'ap_generate_prompt_content_from_titles_ajax');
function ap_generate_prompt_content_from_titles_ajax() {
    check_ajax_referer('ap_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permisos insuficientes']);
    }
    
    $campaign_id = intval($_POST['campaign_id'] ?? 0);
    
    if (!$campaign_id) {
        wp_send_json_error(['message' => 'Campaign ID inválido']);
    }
    
    global $wpdb;
    
    // Obtener campaña
    $campaign = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ap_campaigns WHERE id = %d",
        $campaign_id
    ));
    
    if (!$campaign) {
        wp_send_json_error(['message' => 'Campaña no encontrada']);
    }
    
    // Obtener títulos de la cola
    $queue_titles = $wpdb->get_col($wpdb->prepare(
        "SELECT title FROM {$wpdb->prefix}ap_queue WHERE campaign_id = %d ORDER BY id ASC LIMIT 10",
        $campaign_id
    ));
    
    if (empty($queue_titles)) {
        wp_send_json_error(['message' => 'No hay títulos en la cola']);
    }
    
    // Preparar datos para la API
    $api = new AP_API_Client();
    
    $request_data = [
        'titles_sample' => implode("\n", $queue_titles),
        'niche' => $campaign->niche,
        'company_desc' => $campaign->company_desc,
        'keywords_seo' => $campaign->keywords_seo,
        'domain' => $campaign->domain
    ];

    try {
        $response = $api->call_endpoint('generate-content-prompt', $request_data);

        if (isset($response['prompt'])) {
            wp_send_json_success([
                'content' => $response['prompt'],
                'message' => 'Prompt generado con IA'
            ]);
        } else {
            throw new Exception('Respuesta inválida de la API');
        }

    } catch (Exception $e) {
        wp_send_json_error([
            'message' => 'Error al generar prompt: ' . $e->getMessage()
        ]);
    }
}
