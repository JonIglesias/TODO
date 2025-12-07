<?php
/**
 * PHSBOT - AJAX Handlers para Estadísticas
 */

if (!defined('ABSPATH')) exit;

/**
 * AJAX: Obtener estadísticas desde la API
 */
add_action('wp_ajax_phsbot_get_stats', 'phsbot_get_stats_ajax');
function phsbot_get_stats_ajax() {
    check_ajax_referer('phsbot_stats_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'No tienes permisos']);
        return;
    }

    try {
        // Obtener configuración
        $settings = get_option('phsbot_settings', []);
        $license_key = $settings['bot_license_key'] ?? '';
        $api_url = $settings['bot_api_url'] ?? 'https://bocetosmarketing.com/api_claude_5/index.php';

        if (empty($license_key)) {
            wp_send_json_error(['message' => 'No hay licencia configurada']);
            return;
        }

        $domain = parse_url(home_url(), PHP_URL_HOST);

        // Obtener período
        $period = $_POST['period'] ?? 'current';
        $days = 30;

        // Convertir período a días
        if ($period !== 'current') {
            $days = intval($period);
        }

        // Obtener información del plan (bot/status)
        $status_url = trailingslashit($api_url) . '?route=bot/status';
        $status_response = wp_remote_get(add_query_arg([
            'license_key' => $license_key
        ], $status_url), ['timeout' => 10]);

        if (is_wp_error($status_response)) {
            wp_send_json_error(['message' => 'Error al obtener información del plan: ' . $status_response->get_error_message()]);
            return;
        }

        $status_data = json_decode(wp_remote_retrieve_body($status_response), true);

        if (!$status_data || !$status_data['success']) {
            wp_send_json_error(['message' => 'Error al obtener información del plan']);
            return;
        }

        $plan_info = $status_data['data']['license'] ?? [];

        // Obtener estadísticas de uso (bot/usage)
        $usage_url = trailingslashit($api_url) . '?route=bot/usage';
        $usage_response = wp_remote_get(add_query_arg([
            'license_key' => $license_key,
            'days' => $days
        ], $usage_url), ['timeout' => 10]);

        if (is_wp_error($usage_response)) {
            wp_send_json_error(['message' => 'Error al obtener estadísticas: ' . $usage_response->get_error_message()]);
            return;
        }

        $usage_data = json_decode(wp_remote_retrieve_body($usage_response), true);

        if (!$usage_data || !$usage_data['success']) {
            wp_send_json_error(['message' => 'Error al obtener estadísticas de uso']);
            return;
        }

        $stats = $usage_data['data'] ?? [];

        // Procesar datos para el frontend
        $processed = phsbot_process_stats_for_display($stats, $plan_info);

        wp_send_json_success($processed);

    } catch (Exception $e) {
        wp_send_json_error(['message' => 'Error: ' . $e->getMessage()]);
    }
}

/**
 * Procesar estadísticas para vista del usuario
 */
function phsbot_process_stats_for_display($stats, $plan_info = []) {
    $summary_data = $stats['summary'] ?? [];

    // Resumen general
    $summary = [
        'total_conversations' => intval($summary_data['total_conversations'] ?? 0),
        'total_messages' => intval($summary_data['total_messages'] ?? 0),
        'total_tokens' => intval($summary_data['total_tokens'] ?? 0),
        'tokens_limit' => intval($plan_info['tokens_limit'] ?? 0),
        'tokens_available' => intval($plan_info['tokens_remaining'] ?? 0),
        'usage_percentage' => floatval($plan_info['usage_percentage'] ?? 0)
    ];

    // Información del plan
    $plan = [
        'name' => $plan_info['plan_name'] ?? 'Desconocido',
        'renewal_date' => null,
        'days_remaining' => intval($plan_info['days_remaining'] ?? 0),
        'tokens_limit' => $summary['tokens_limit']
    ];

    if (!empty($plan_info['period_ends_at'])) {
        $plan['renewal_date'] = date('d/m/Y', strtotime($plan_info['period_ends_at']));
    }

    // Evolución diaria
    $daily_timeline = [];
    $daily_data = $stats['daily'] ?? [];

    foreach ($daily_data as $day) {
        $date = $day['date'] ?? '';
        $daily_timeline[] = [
            'date' => $date,
            'date_formatted' => date('d M', strtotime($date)),
            'conversations' => intval($day['conversations'] ?? 0),
            'messages' => intval($day['messages'] ?? 0),
            'tokens' => intval($day['tokens'] ?? 0)
        ];
    }

    // Operaciones por tipo
    $by_operation = [];
    $operations_data = $summary_data['by_operation'] ?? [];

    foreach ($operations_data as $op) {
        $by_operation[] = [
            'type' => $op['operation'] ?? 'unknown',
            'count' => intval($op['count'] ?? 0),
            'tokens' => intval($op['tokens'] ?? 0)
        ];
    }

    return [
        'summary' => $summary,
        'plan' => $plan,
        'daily_timeline' => $daily_timeline,
        'by_operation' => $by_operation,
        'billing_period' => [
            'start' => $plan_info['period_starts_at'] ?? null,
            'end' => $plan_info['period_ends_at'] ?? null
        ]
    ];
}
