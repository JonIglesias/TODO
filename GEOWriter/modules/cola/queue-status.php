<?php
/**
 * Sistema centralizado de verificación de estados de colas
 *
 * Este archivo contiene la lógica unificada para determinar el estado
 * real de cada campaña, incluyendo generación, ejecución, y completitud.
 */

if (!defined('ABSPATH')) exit;

/**
 * Obtiene el estado completo de una campaña
 *
 * @param int $campaign_id ID de la campaña
 * @return array Estado completo con toda la información
 */
function ap_get_campaign_status($campaign_id) {
    global $wpdb;

    $status = [
        'campaign_id' => $campaign_id,
        'is_generating' => false,
        'is_executing' => false,
        'has_generation_lock' => false,
        'has_execution_lock' => false,
        'queue_count' => 0,
        'published_count' => 0,
        'expected_count' => 0,
        'pending_count' => 0,
        'processing_count' => 0,
        'completed_count' => 0,
        'is_queue_complete' => false,
        'is_all_published' => false,
        'missing_posts' => 0,
        'posts_to_execute' => 0,
        'has_interrupted_execution' => false,
        'state' => 'unknown', // Estado principal
        'action' => 'none', // Acción sugerida
        'button_text' => '',
        'button_class' => '',
        'button_disabled' => false,
        'status_label' => '',
        'status_bg' => '',
        'status_color' => '',
        'show_two_badges' => false,
        'queue_badge' => '',
        'published_badge' => ''
    ];

    // Obtener campaña
    $campaign = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ap_campaigns WHERE id = %d",
        $campaign_id
    ));

    if (!$campaign) {
        $status['state'] = 'not_found';
        return $status;
    }

    $status['expected_count'] = intval($campaign->num_posts);

    // Verificar locks activos
    $generate_lock = AP_Bloqueo_System::get_lock_info('generate', $campaign_id);
    $execute_lock = AP_Bloqueo_System::get_lock_info('execute', $campaign_id);

    $status['has_generation_lock'] = !empty($generate_lock);
    $status['has_execution_lock'] = !empty($execute_lock);
    $status['is_generating'] = $status['has_generation_lock'];
    $status['is_executing'] = $status['has_execution_lock'];

    // Contar posts en cola por estado
    $counts = $wpdb->get_row($wpdb->prepare(
        "SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
        FROM {$wpdb->prefix}ap_queue
        WHERE campaign_id = %d",
        $campaign_id
    ));

    $status['queue_count'] = intval($counts->total ?? 0);
    $status['pending_count'] = intval($counts->pending ?? 0);
    $status['processing_count'] = intval($counts->processing ?? 0);
    $status['completed_count'] = intval($counts->completed ?? 0);

    // Contar posts publicados
    $status['published_count'] = intval($wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}posts
        WHERE post_type = 'post'
        AND post_status = 'publish'
        AND ID IN (
            SELECT post_id FROM {$wpdb->prefix}ap_queue
            WHERE campaign_id = %d AND post_id IS NOT NULL
        )",
        $campaign_id
    )));

    // Calcular valores derivados
    $status['is_queue_complete'] = ($status['queue_count'] > 0 && $status['queue_count'] >= $status['expected_count']);
    $status['is_all_published'] = ($status['published_count'] === $status['expected_count'] && $status['is_queue_complete']);
    $status['missing_posts'] = max(0, $status['expected_count'] - $status['queue_count']);
    $status['posts_to_execute'] = $status['pending_count'];
    $status['has_interrupted_execution'] = ($status['processing_count'] > 0 && !$status['is_executing']);

    // DETERMINAR ESTADO PRINCIPAL Y ACCIÓN
    if ($status['is_executing']) {
        // Ejecución en curso
        $status['state'] = 'executing';
        $status['action'] = 'show_executing';
        $status['button_text'] = 'Publicando...';
        $status['button_class'] = 'ap-btn-primary';
        $status['button_disabled'] = true;
        $status['status_label'] = 'Publicando Posts';
        $status['status_bg'] = '#000000';
        $status['status_color'] = '#000000';

    } elseif ($status['is_generating']) {
        // Generación en curso
        $status['state'] = 'generating';
        $status['action'] = 'show_generating';
        $status['button_text'] = 'Generando Cola...';
        $status['button_class'] = 'ap-btn-primary';
        $status['button_disabled'] = true;
        $status['status_label'] = 'Generando Cola';
        $status['status_bg'] = '#FEF3C7';
        $status['status_color'] = '#92400E';

    } elseif ($status['is_all_published']) {
        // Todo publicado
        $status['state'] = 'all_published';
        $status['action'] = 'view_queue';
        $status['button_text'] = 'Ver Cola';
        $status['button_class'] = 'ap-btn-primary';
        $status['status_label'] = '✓ Todos publicados';
        $status['status_bg'] = '#D1FAE5';
        $status['status_color'] = '#065F46';

    } elseif ($status['has_interrupted_execution']) {
        // Ejecución interrumpida
        $status['state'] = 'execution_interrupted';
        $status['action'] = 'resume_execution';
        $status['button_text'] = 'Publicar ' . $status['posts_to_execute'] . ' posts restantes';
        $status['button_class'] = 'ap-btn-warning';
        $status['show_two_badges'] = ($status['published_count'] > 0);

        if ($status['show_two_badges']) {
            $status['queue_badge'] = $status['is_queue_complete']
                ? '<span style="background: #D1FAE5; color: #065F46; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500;">✓ Cola completa</span>'
                : '<span style="background: #FEF3C7; color: #92400E; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500;">⚠ ' . $status['queue_count'] . ' de ' . $status['expected_count'] . ' en cola</span>';

            $status['published_badge'] = '<span style="background: #E0E7FF; color: #3730A3; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500;">' . $status['published_count'] . ' de ' . $status['expected_count'] . ' publicados</span>';
        } else {
            $status['status_label'] = $status['processing_count'] . ' posts interrumpidos';
            $status['status_bg'] = '#FEF3C7';
            $status['status_color'] = '#92400E';
        }

    } elseif ($status['is_queue_complete'] && $status['published_count'] < $status['queue_count']) {
        // Cola completa pero no publicada
        $status['state'] = 'ready_to_publish';
        $status['action'] = 'view_queue';
        $status['button_text'] = 'Ver Cola';
        $status['button_class'] = 'ap-btn-primary';
        $status['show_two_badges'] = ($status['published_count'] > 0);

        if ($status['show_two_badges']) {
            $status['queue_badge'] = '<span style="background: #D1FAE5; color: #065F46; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500;">✓ Cola completa</span>';
            $status['published_badge'] = '<span style="background: #E0E7FF; color: #3730A3; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500;">' . $status['published_count'] . ' de ' . $status['expected_count'] . ' publicados</span>';
        } else {
            $status['status_label'] = '✓ Cola completa';
            $status['status_bg'] = '#D1FAE5';
            $status['status_color'] = '#065F46';
        }

    } elseif (!$status['is_queue_complete'] && $status['queue_count'] > 0) {
        // Cola incompleta
        $status['state'] = 'queue_incomplete';
        $status['action'] = 'view_queue';
        $status['button_text'] = 'Ver Cola';
        $status['button_class'] = 'ap-btn-primary';
        $status['show_two_badges'] = ($status['published_count'] > 0);

        if ($status['show_two_badges']) {
            $status['queue_badge'] = '<span style="background: #FEF3C7; color: #92400E; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500;">⚠ ' . $status['queue_count'] . ' de ' . $status['expected_count'] . ' en cola</span>';
            $status['published_badge'] = '<span style="background: #E0E7FF; color: #3730A3; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500;">' . $status['published_count'] . ' de ' . $status['expected_count'] . ' publicados</span>';
        } else {
            $status['status_label'] = $status['queue_count'] . ' de ' . $status['expected_count'] . ' en cola';
            $status['status_bg'] = '#FEF3C7';
            $status['status_color'] = '#92400E';
        }

    } else {
        // Sin cola
        $status['state'] = 'no_queue';
        $status['action'] = 'generate_queue';
        $status['button_text'] = 'Generar Cola';
        $status['button_class'] = 'ap-btn-primary';
        $status['status_label'] = 'Sin cola';
        $status['status_bg'] = '#FEE2E2';
        $status['status_color'] = '#991B1B';

        // Verificar si la campaña está completa para habilitar/deshabilitar
        if (!ap_is_campaign_complete($campaign)) {
            $status['button_disabled'] = true;
        }
    }

    return $status;
}

/**
 * Obtiene los estados de múltiples campañas
 *
 * @param array $campaign_ids Array de IDs de campañas
 * @return array Array asociativo [campaign_id => status]
 */
function ap_get_campaigns_statuses($campaign_ids) {
    $statuses = [];
    foreach ($campaign_ids as $campaign_id) {
        $statuses[$campaign_id] = ap_get_campaign_status($campaign_id);
    }
    return $statuses;
}

/**
 * Endpoint AJAX para obtener estados de campañas
 */
add_action('wp_ajax_ap_get_campaigns_statuses', 'ap_ajax_get_campaigns_statuses');

function ap_ajax_get_campaigns_statuses() {
    check_ajax_referer('ap-nonce', 'nonce');

    $campaign_ids = isset($_POST['campaign_ids']) ? array_map('intval', $_POST['campaign_ids']) : [];

    if (empty($campaign_ids)) {
        wp_send_json_error('No campaign IDs provided');
        return;
    }

    $statuses = ap_get_campaigns_statuses($campaign_ids);

    wp_send_json_success($statuses);
}
