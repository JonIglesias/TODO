<?php
if (!defined('ABSPATH')) exit;

/**
 * AJAX: Obtener estadísticas desde la API
 */
add_action('wp_ajax_ap_get_stats', 'ap_get_stats_ajax');
function ap_get_stats_ajax() {
    try {
        $api = new AP_API_Client();
        $license_key = get_option('ap_license_key', '');
        
        if (empty($license_key)) {
            wp_send_json_error(['message' => 'No hay licencia configurada']);
            return;
        }
        
        // Obtener período
        $period = $_POST['period'] ?? 'current';
        
        // Si es "current", obtener el período de facturación actual
        if ($period === 'current') {
            $plan_info = $api->get_active_plan();
            if ($plan_info && isset($plan_info['billing_cycle'])) {
                // Calcular desde la fecha de inicio del período actual
                $date_to = date('Y-m-d');
                $date_from = date('Y-m-d', strtotime('-30 days')); // Por defecto 30 días
            } else {
                // Fallback a 30 días
                $date_from = date('Y-m-d', strtotime('-30 days'));
                $date_to = date('Y-m-d');
            }
        } else {
            // Período numérico (días)
            $days = intval($period);
            $date_from = date('Y-m-d', strtotime("-{$days} days"));
            $date_to = date('Y-m-d');
        }
        
        // Obtener estadísticas
        $response = $api->get_detailed_stats($date_from, $date_to);
        
        if (!$response || !isset($response['success']) || !$response['success']) {
            $error_msg = $response['error'] ?? $response['data']['message'] ?? 'Error desconocido';
            wp_send_json_error(['message' => 'Error al obtener estadísticas: ' . $error_msg]);
            return;
        }
        
        $stats = $response['data'] ?? [];
        
        // Obtener información del plan actual
        $plan_info = $api->get_active_plan();
        
        // Contar posts por día desde by_operation
        $daily_posts = calculate_daily_posts($stats['by_operation'] ?? []);
        
        // Procesar datos para el frontend
        $processed = process_stats_for_display($stats, $plan_info, $daily_posts);
        
        wp_send_json_success($processed);
        
    } catch (Exception $e) {
        wp_send_json_error(['message' => 'Error: ' . $e->getMessage()]);
    }
}

/**
 * Calcular número de posts generados por día
 */
function calculate_daily_posts($by_operation) {
    $daily_posts = [];
    
    foreach ($by_operation as $item) {
        // Solo contar operaciones de tipo "content" (posts completos)
        if (isset($item['operations'])) {
            foreach ($item['operations'] as $op) {
                if (($op['operation_type'] ?? '') === 'content') {
                    // Extraer fecha (asumimos que viene en el item)
                    $date = $item['date'] ?? null;
                    if ($date) {
                        if (!isset($daily_posts[$date])) {
                            $daily_posts[$date] = 0;
                        }
                        $daily_posts[$date] += $op['count'] ?? 0;
                    }
                }
            }
        }
    }
    
    return $daily_posts;
}

/**
 * Procesar estadísticas para vista del usuario
 */
function process_stats_for_display($stats, $plan_info = null, $daily_posts = []) {
    // Resumen general
    $summary = [
        'total_operations' => 0,
        'total_tokens' => 0,
        'tokens_limit' => 0,
        'tokens_available' => 0,
        'usage_percentage' => 0
    ];
    
    // Obtener límite de plan actual
    $usage_data = $stats['usage'] ?? [];
    $summary['tokens_limit'] = intval($usage_data['tokens_limit'] ?? 0);
    $summary['total_tokens'] = intval($usage_data['tokens_used'] ?? 0);
    $summary['tokens_available'] = max(0, $summary['tokens_limit'] - $summary['total_tokens']);
    $summary['usage_percentage'] = $summary['tokens_limit'] > 0 
        ? round(($summary['total_tokens'] / $summary['tokens_limit']) * 100, 1)
        : 0;
    
    // Información del plan
    $plan = [
        'name' => 'Desconocido',
        'renewal_date' => null,
        'tokens_limit' => $summary['tokens_limit']
    ];
    
    if ($plan_info) {
        $plan['name'] = $plan_info['name'] ?? 'Plan Desconocido';
        // Intentar obtener fecha de renovación del usage_data
        if (isset($usage_data['period_ends_at'])) {
            $plan['renewal_date'] = date('d/m/Y', strtotime($usage_data['period_ends_at']));
            // Calcular días restantes
            $days_remaining = ceil((strtotime($usage_data['period_ends_at']) - time()) / 86400);
            $plan['days_remaining'] = max(0, $days_remaining);
        }
    }
    
    // Procesar por campaña
    $campaigns = [];
    $by_operation = $stats['by_operation'] ?? [];
    
    foreach ($by_operation as $item) {
        if (!isset($item['is_campaign']) || !$item['is_campaign']) {
            continue;
        }
        
        $campaign_id = $item['campaign_id'] ?? '';
        $campaign_name = $item['campaign_name'] ?? 'Sin nombre';
        
        $campaigns[$campaign_id] = [
            'id' => $campaign_id,
            'name' => $campaign_name,
            'last_operation_at' => $item['last_operation_at'] ?? '',
            'total_operations' => $item['total_count'] ?? 0,
            'total_tokens' => $item['total_tokens'] ?? 0,
            'queues' => [],
            'operations' => []
        ];
        
        // Colas
        if (isset($item['queues_details'])) {
            foreach ($item['queues_details'] as $idx => $queue) {
                $campaigns[$campaign_id]['queues'][] = [
                    'number' => $idx + 1,
                    'date' => $queue['date'] ?? '',
                    'items' => $queue['subitems'] ?? [],
                    'tokens' => $queue['total_tokens'] ?? 0
                ];
            }
        }
        
        // Operaciones individuales
        if (isset($item['operations'])) {
            foreach ($item['operations'] as $op) {
                $campaigns[$campaign_id]['operations'][] = [
                    'type' => $op['operation_type'] ?? '',
                    'name' => $op['display_name'] ?? '',
                    'count' => $op['count'] ?? 0,
                    'tokens' => $op['tokens'] ?? 0
                ];
            }
        }
        
        $summary['total_operations'] += $item['total_count'] ?? 0;
    }
    
    // Ordenar campañas por última operación (más reciente primero)
    usort($campaigns, function($a, $b) {
        return strcmp($b['last_operation_at'], $a['last_operation_at']);
    });
    
    // Evolución diaria
    $daily_timeline = [];
    $timeline = $stats['timeline'] ?? [];
    
    foreach ($timeline as $day) {
        $date = $day['date'] ?? '';
        $daily_timeline[] = [
            'date' => $date,
            'date_formatted' => date('d M', strtotime($date)),
            'operations' => $day['operations'] ?? 0,
            'tokens' => $day['tokens'] ?? 0
        ];
    }
    
    // Crear array de posts por día alineado con timeline
    $posts_timeline = [];
    foreach ($daily_timeline as $day) {
        $posts_timeline[] = $daily_posts[$day['date']] ?? 0;
    }
    
    return [
        'summary' => $summary,
        'plan' => $plan,
        'campaigns' => array_values($campaigns),
        'daily_timeline' => $daily_timeline,
        'daily_posts' => $posts_timeline,
        'billing_period' => [
            'start' => $usage_data['period_starts_at'] ?? null,
            'end' => $usage_data['period_ends_at'] ?? null
        ]
    ];
}
