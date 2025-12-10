<?php
if (!defined('ABSPATH')) exit;

/**
 * ⭐ ESTABLECER CONTEXTO DE CAMPAÑA GLOBALMENTE
 * 
 * Este código se ejecuta al cargar el archivo, capturando campaign_id
 * de $_POST o $_GET y estableciendo las variables globales
 */
if (wp_doing_ajax() || (defined('DOING_AJAX') && DOING_AJAX)) {
    $campaign_id = null;
    
    // Buscar campaign_id en POST o GET
    if (isset($_POST['campaign_id']) && !empty($_POST['campaign_id'])) {
        $campaign_id = intval($_POST['campaign_id']);
    } elseif (isset($_GET['campaign_id']) && !empty($_GET['campaign_id'])) {
        $campaign_id = intval($_GET['campaign_id']);
    }
    
    // Si hay campaign_id, establecer variables globales
    if ($campaign_id) {
        global $wpdb;
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT id, name FROM {$wpdb->prefix}ap_campaigns WHERE id = %d",
            $campaign_id
        ));
        
        if ($campaign) {
            // ✅ Usar SOLO el ID numérico
            $GLOBALS['ap_current_campaign_id'] = (string)$campaign->id;
            $GLOBALS['ap_current_campaign_name'] = $campaign->name ?? 'Sin nombre';

        }
    }
}

// Actualizar orden de cola
add_action('wp_ajax_ap_update_queue_order', 'ap_update_queue_order_ajax');
function ap_update_queue_order_ajax() {
    ap_verify_ajax_request();

    global $wpdb;
    $order = $_POST['order'] ?? [];
    $campaign_id = intval($_POST['campaign_id'] ?? 0);

    if (empty($order) || !$campaign_id) {
        wp_send_json_error(['message' => 'Sin datos']);
    }

    // 1. Obtener todas las fechas actuales ordenadas por posición
    $current_dates = $wpdb->get_results($wpdb->prepare(
        "SELECT id, scheduled_date
         FROM {$wpdb->prefix}ap_queue
         WHERE campaign_id = %d
         ORDER BY scheduled_date ASC",
        $campaign_id
    ), ARRAY_A);

    // Crear array solo con las fechas en orden
    $dates_in_order = array_column($current_dates, 'scheduled_date');

    // 2. Actualizar cada item con su nueva posición Y su fecha correspondiente
    foreach ($order as $index => $item) {
        $new_position = intval($item['position']);
        $item_id = intval($item['id']);

        // La fecha del slot en esta nueva posición (índice comienza en 0)
        $new_date = $dates_in_order[$index] ?? null;

        if ($new_date) {
            $wpdb->update(
                $wpdb->prefix . 'ap_queue',
                [
                    'position' => $new_position,
                    'scheduled_date' => $new_date
                ],
                ['id' => $item_id],
                ['%d', '%s'],
                ['%d']
            );
        } else {
            // Si no hay fecha, solo actualizar posición
            $wpdb->update(
                $wpdb->prefix . 'ap_queue',
                ['position' => $new_position],
                ['id' => $item_id],
                ['%d'],
                ['%d']
            );
        }
    }

    wp_send_json_success(['message' => 'Orden y fechas actualizados']);
}

// Actualizar campo individual de un item de cola
add_action('wp_ajax_ap_update_queue_field', 'ap_update_queue_field_ajax');
function ap_update_queue_field_ajax() {
    ap_verify_ajax_request();

    global $wpdb;

    $id = intval($_POST['id'] ?? 0);
    $field = sanitize_text_field($_POST['field'] ?? '');
    $value = sanitize_text_field($_POST['value'] ?? '');

    if (!$id || !$field) {
        wp_send_json_error(['message' => 'Datos incompletos']);
    }

    // Lista blanca de campos editables
    $allowed_fields = ['title', 'scheduled_date', 'image_keywords'];

    if (!in_array($field, $allowed_fields)) {
        wp_send_json_error(['message' => 'Campo no permitido']);
    }

    // Formato de datos según campo
    $format = '%s';
    if ($field === 'scheduled_date') {
        $format = '%s'; // datetime
    }

    $result = $wpdb->update(
        $wpdb->prefix . 'ap_queue',
        [$field => $value],
        ['id' => $id],
        [$format],
        ['%d']
    );

    if ($result !== false) {
        wp_send_json_success(['message' => 'Actualizado correctamente']);
    } else {
        wp_send_json_error(['message' => 'Error al actualizar']);
    }
}

// Endpoint para verificar estado de bloqueos
add_action('wp_ajax_ap_check_lock_status', 'ap_check_lock_status_ajax');
function ap_check_lock_status_ajax() {
    ap_verify_ajax_request();
    
    $generate_locked = AP_Bloqueo_System::is_locked('generate');
    $execute_locked = AP_Bloqueo_System::is_locked('execute');
    
    wp_send_json_success([
        'generate_locked' => $generate_locked,
        'execute_locked' => $execute_locked,
        'any_locked' => $generate_locked || $execute_locked,
        'generate_info' => AP_Bloqueo_System::get_lock_info('generate'),
        'execute_info' => AP_Bloqueo_System::get_lock_info('execute')
    ]);
}

// Endpoint para abortar generación en curso
add_action('wp_ajax_ap_abort_generation', 'ap_abort_generation_ajax');
function ap_abort_generation_ajax() {
    ap_verify_ajax_request();
    
    
    // Liberar todos los bloqueos (CORREGIDO: usar release sin _lock)
    AP_Bloqueo_System::release('generate');
    AP_Bloqueo_System::release('execute');
    
    // Limpiar cualquier estado temporal
    delete_transient('ap_generation_progress');
    delete_transient('ap_execution_progress');
    
    
    wp_send_json_success([
        'message' => 'Generación abortada correctamente. Todos los bloqueos han sido limpiados.'
    ]);
}

// Endpoint para verificar progreso de generación
add_action('wp_ajax_ap_check_queue_progress', 'ap_check_queue_progress_ajax');
function ap_check_queue_progress_ajax() {
    ap_verify_ajax_request();
    
    $campaign_id = intval($_POST['campaign_id'] ?? 0);
    if (!$campaign_id) wp_send_json_error(['message' => 'ID inválido']);
    
    $state = AP_Bloqueo_System::check_queue_state($campaign_id);
    
    wp_send_json_success([
        'count' => $state['total_in_queue'],
        'expected' => $state['expected'],
        'pending' => $state['pending'],
        'processing' => $state['processing'],
        'completed' => $state['completed'],
        'errors' => $state['errors'],
        'missing' => $state['missing'],
        'is_complete' => $state['is_complete'],
        'timestamp' => time()
    ]);
}

// Endpoint para obtener posts nuevos (para mostrar en tiempo real)
add_action('wp_ajax_ap_get_new_queue_posts', 'ap_get_new_queue_posts_ajax');
function ap_get_new_queue_posts_ajax() {
    ap_verify_ajax_request();
    
    global $wpdb;
    $campaign_id = intval($_POST['campaign_id'] ?? 0);
    $loaded_ids = $_POST['loaded_ids'] ?? [];
    
    if (!$campaign_id) wp_send_json_error(['message' => 'ID inválido']);
    
    // Construir query excluyendo IDs ya cargados
    $query = "SELECT id, title, scheduled_date, status, featured_image_thumb, inner_image_thumb, image_keywords 
              FROM {$wpdb->prefix}ap_queue 
              WHERE campaign_id = %d";
    
    $params = [$campaign_id];
    
    if (!empty($loaded_ids) && is_array($loaded_ids)) {
        $loaded_ids = array_map('intval', $loaded_ids);
        if (count($loaded_ids) > 0) {
            $placeholders = implode(',', array_fill(0, count($loaded_ids), '%d'));
            $query .= " AND id NOT IN ($placeholders)";
            $params = array_merge($params, $loaded_ids);
        }
    }
    
    $query .= " ORDER BY position ASC, id ASC";
    
    $posts = $wpdb->get_results($wpdb->prepare($query, $params), ARRAY_A);
    
    wp_send_json_success(['posts' => $posts]);
}

// Endpoint para obtener progreso detallado (con imágenes)
add_action('wp_ajax_ap_check_queue_detailed_progress', 'ap_check_queue_detailed_progress_ajax');
function ap_check_queue_detailed_progress_ajax() {
    ap_verify_ajax_request();

    global $wpdb;
    $campaign_id = intval($_POST['campaign_id'] ?? 0);

    if (!$campaign_id) wp_send_json_error(['message' => 'ID inválido']);

    // Contar posts totales en cola
    $total = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}ap_queue WHERE campaign_id = %d",
        $campaign_id
    ));

    // Contar posts completados/publicados
    $completed = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}ap_queue WHERE campaign_id = %d AND status = 'completed'",
        $campaign_id
    ));

    // Contar posts pendientes
    $pending = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}ap_queue WHERE campaign_id = %d AND status = 'pending'",
        $campaign_id
    ));

    $campaign = $wpdb->get_row($wpdb->prepare(
        "SELECT num_posts, queue_generated FROM {$wpdb->prefix}ap_campaigns WHERE id = %d",
        $campaign_id
    ));


    wp_send_json_success([
        'current' => intval($total),        // Total en cola
        'total' => intval($total),          // Alias para compatibilidad
        'completed' => intval($completed),  // Posts publicados
        'pending' => intval($pending),      // Posts pendientes
        'expected' => intval($campaign->num_posts ?? 0),
        'queue_generated' => intval($campaign->queue_generated ?? 0)
    ]);
}

// Endpoint para renovar bloqueo
add_action('wp_ajax_ap_renew_lock', 'ap_renew_lock_ajax');
function ap_renew_lock_ajax() {
    ap_verify_ajax_request();
    
    $type = sanitize_text_field($_POST['type'] ?? '');
    $campaign_id = intval($_POST['campaign_id'] ?? 0);
    
    if (!in_array($type, ['generate', 'execute'])) {
        wp_send_json_error(['message' => 'Tipo inválido']);
    }
    
    if (!$campaign_id) {
        wp_send_json_error(['message' => 'Campaign ID requerido']);
    }
    
    $renewed = AP_Bloqueo_System::renew($type, $campaign_id);
    
    wp_send_json_success(['renewed' => $renewed]);
}

add_action('wp_ajax_ap_generate_queue', 'ap_generate_queue_ajax');
function ap_generate_queue_ajax() {
    // Aplicar rate limiting: máximo 5 intentos de generación cada 5 minutos
    AP_Rate_Limiter::enforce('ap_generate_queue', 5, AP_RATE_LIMIT_WINDOW);

    // Obtener timeout del plan
    $api = new AP_API_Client();
    $plan = $api->get_active_plan();
    $timeout = 300; // Fallback por defecto

    if ($plan && isset($plan['timing']['api_timeout'])) {
        $timeout = max(300, (int)$plan['timing']['api_timeout'] * 3); // x3 para procesos batch
    }

    @set_time_limit($timeout);
    @ini_set('max_execution_time', $timeout);


    ap_verify_ajax_request();
    
    $campaign_id = intval($_POST['campaign_id'] ?? 0);
    
    
    if (!$campaign_id) {
        wp_send_json_error(['message' => 'ID inválido']);
    }
    
    $force = isset($_POST['force_complete']) && $_POST['force_complete'] == '1';
    
    // VERIFICAR SI SE PUEDE GENERAR
    
    $check = AP_Bloqueo_System::can_generate($campaign_id, $force);
    
    
    if (!$check['can']) {
        
        // Enviar respuesta apropiada según el motivo
        if ($check['allow_complete'] ?? false) {
            wp_send_json_error([
                'message' => $check['message'],
                'allow_complete' => true,
                'state' => $check['state']
            ]);
        } else {
            wp_send_json_error(['message' => $check['message']]);
        }
    }
    
    // ADQUIRIR BLOQUEO
    
    if (!AP_Bloqueo_System::acquire('generate', $campaign_id)) {
        wp_send_json_error(['message' => 'No se pudo adquirir bloqueo. Reintenta.']);
    }
    
    
    global $wpdb;
    
    // Marcar como generando
    $wpdb->update(
        $wpdb->prefix . 'ap_campaigns',
        ['queue_generated' => 2],
        ['id' => $campaign_id],
        ['%d'],
        ['%d']
    );
    
    try {
        
        $generator = new AP_Queue_Generator($campaign_id);
        
        
        $result = $generator->generate();
        
        
        if ($result['success']) {
            // Marcar como generada
            $wpdb->update(
                $wpdb->prefix . 'ap_campaigns',
                ['queue_generated' => 1],
                ['id' => $campaign_id],
                ['%d'],
                ['%d']
            );
            
        } else {
            // Resetear si falla
            $wpdb->update(
                $wpdb->prefix . 'ap_campaigns',
                ['queue_generated' => 0],
                ['id' => $campaign_id],
                ['%d'],
                ['%d']
            );
            
        }
        
        // LIBERAR BLOQUEO
        AP_Bloqueo_System::release('generate');
        
        if ($result['success']) {
            $result['redirect'] = admin_url('admin.php?page=autopost-queue&campaign_id=' . $campaign_id);
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    } catch (Exception $e) {
        
        // Resetear en caso de error
        $wpdb->update(
            $wpdb->prefix . 'ap_campaigns',
            ['queue_generated' => 0],
            ['id' => $campaign_id],
            ['%d'],
            ['%d']
        );
        
        // LIBERAR BLOQUEO
        AP_Bloqueo_System::release('generate');
        
        
        wp_send_json_error(['message' => 'Error: ' . $e->getMessage()]);
    }
}

add_action('wp_ajax_ap_execute_selected', 'ap_execute_selected_ajax');
function ap_execute_selected_ajax() {
    // Aplicar rate limiting: máximo 10 ejecuciones cada 5 minutos
    AP_Rate_Limiter::enforce('ap_execute_selected', 10, AP_RATE_LIMIT_WINDOW);

    ap_verify_ajax_request();

    $ids = $_POST['ids'] ?? [];
    if (empty($ids) || !is_array($ids)) wp_send_json_error(['message' => 'IDs inválidos']);
    
    $ids = array_map('intval', $ids);
    
    // Obtener campaign_id del primer item
    global $wpdb;
    $campaign_id = $wpdb->get_var($wpdb->prepare(
        "SELECT campaign_id FROM {$wpdb->prefix}ap_queue WHERE id = %d LIMIT 1",
        $ids[0]
    ));
    
    if (!$campaign_id) wp_send_json_error(['message' => 'Campaña no encontrada']);
    
    // VERIFICAR SI SE PUEDE EJECUTAR
    $check = AP_Bloqueo_System::can_execute($campaign_id);
    
    if (!$check['can']) {
        wp_send_json_error(['message' => $check['message']]);
    }
    
    // ADQUIRIR BLOQUEO
    if (!AP_Bloqueo_System::acquire('execute', $campaign_id)) {
        wp_send_json_error(['message' => 'No se pudo adquirir bloqueo. Reintenta.']);
    }
    
    $results = ['success' => 0, 'failed' => 0, 'errors' => []];
    
    try {
        $api = new AP_API_Client();
        $plan = $api->get_active_plan();
        $delay = $plan['timing']['post_delay'] ?? 3;
        
        foreach ($ids as $index => $queue_id) {
            if ($index > 0) sleep($delay);
            
            // Renovar bloqueo cada 3 posts
            if ($index % 3 === 0) {
                AP_Bloqueo_System::renew('execute', $campaign_id);
            }
            
            $result = AP_Queue_Executor::process_queue_item($queue_id);
            if ($result) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][] = "Post {$queue_id} falló";
            }
        }
        
        // LIBERAR BLOQUEO
        AP_Bloqueo_System::release('execute');
        
        if ($results['success'] > 0) {
            wp_send_json_success([
                'message' => "{$results['success']} posts generados" . 
                            ($results['failed'] > 0 ? ", {$results['failed']} fallaron" : ""),
                'stats' => $results
            ]);
        } else {
            wp_send_json_error(['message' => 'Ningún post pudo generarse', 'stats' => $results]);
        }
    } catch (Exception $e) {
        // LIBERAR BLOQUEO EN CASO DE ERROR
        AP_Bloqueo_System::release('execute');
        
        
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}


// GENERAR PROMPT DE CONTENIDO CON IA
add_action('wp_ajax_ap_generate_queue_prompt', 'ap_generate_queue_prompt_ajax');
function ap_generate_queue_prompt_ajax() {
    // Obtener timeout del plan
    $api = new AP_API_Client();
    $plan = $api->get_active_plan();
    $timeout = 300; // Fallback
    
    if ($plan && isset($plan['timing']['api_timeout'])) {
        $timeout = max(300, (int)$plan['timing']['api_timeout'] * 2);
    }
    
    @set_time_limit($timeout);
    @ini_set('max_execution_time', $timeout);
    
    ap_verify_ajax_request();
    
    $campaign_id = intval($_POST['campaign_id'] ?? 0);
    
    if (!$campaign_id) {
        wp_send_json_error(['message' => 'ID de campaña inválido']);
    }
    
    global $wpdb;
    $campaign = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ap_campaigns WHERE id = %d",
        $campaign_id
    ));
    
    if (!$campaign) {
        wp_send_json_error(['message' => 'Campaña no encontrada']);
    }
    
    try {
        $api = new AP_API_Client();
        $result = $api->generate_content_prompt(
            $campaign->niche,
            $campaign->company_desc,
            $campaign->keywords_seo
        );
        
        if ($result['success']) {
            wp_send_json_success(['prompt' => $result['prompt']]);
        } else {
            wp_send_json_error(['message' => $result['error'] ?? 'Error al generar prompt']);
        }
    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}


// MANTENER OTROS ENDPOINTS SIN CAMBIOS

// ELIMINAR ITEM INDIVIDUAL
add_action('wp_ajax_ap_delete_queue_item', 'ap_delete_queue_item_ajax');
function ap_delete_queue_item_ajax() {
    // Aplicar rate limiting: máximo 50 eliminaciones individuales cada 5 minutos
    AP_Rate_Limiter::enforce('ap_delete_queue_item', 50, AP_RATE_LIMIT_WINDOW);

    ap_verify_ajax_request();

    global $wpdb;
    $id = intval($_POST['id'] ?? 0);

    if ($id <= 0) {
        wp_send_json_error(['message' => 'ID inválido']);
    }

    $deleted = $wpdb->delete(
        $wpdb->prefix . 'ap_queue',
        ['id' => $id],
        ['%d']
    );

    if ($deleted) {
        wp_send_json_success(['deleted' => $deleted]);
    } else {
        wp_send_json_error(['message' => 'No se pudo eliminar el item']);
    }
}

// ELIMINAR MÚLTIPLES ITEMS
add_action('wp_ajax_ap_bulk_delete_queue', 'ap_bulk_delete_queue_ajax');
function ap_bulk_delete_queue_ajax() {
    // Aplicar rate limiting: máximo 20 borrados masivos cada 5 minutos
    AP_Rate_Limiter::enforce('ap_bulk_delete_queue', 20, AP_RATE_LIMIT_WINDOW);

    ap_verify_ajax_request();

    global $wpdb;
    $ids = $_POST['ids'] ?? [];

    if (empty($ids)) {
        wp_send_json_error(['message' => 'IDs vacíos']);
    }

    $ids = array_map('intval', $ids);
    $placeholders = implode(',', array_fill(0, count($ids), '%d'));

    $deleted = $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}ap_queue WHERE id IN ($placeholders)",
            $ids
        )
    );

    wp_send_json_success(['deleted' => $deleted]);
}

// REGENERAR IMAGEN
add_action('wp_ajax_ap_regenerate_image', 'ap_regenerate_image_ajax');
function ap_regenerate_image_ajax() {
    // Aplicar rate limiting: máximo 50 regeneraciones cada 5 minutos
    AP_Rate_Limiter::enforce('ap_regenerate_image', 50, AP_RATE_LIMIT_WINDOW);

    ap_verify_ajax_request();

    global $wpdb;
    $id = intval($_POST['id'] ?? 0);
    $type = sanitize_text_field($_POST['type'] ?? '');
    $provider = sanitize_text_field($_POST['provider'] ?? '');
    $custom_keywords = sanitize_text_field($_POST['keywords'] ?? '');
    
    if (!$id || !in_array($type, ['featured', 'inner']) || !$provider) {
        wp_send_json_error(['message' => 'Parámetros inválidos']);
    }
    
    // Obtener item
    $item = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ap_queue WHERE id = %d",
        $id
    ));
    
    if (!$item) {
        wp_send_json_error(['message' => 'Item no encontrado']);
    }
    
    // Usar keywords custom si se proporcionan, si no usar las del post
    $keywords = $custom_keywords ?: $item->image_keywords;
    
    // Limitar keywords a 15 palabras
    $words = explode(',', $keywords);
    $words = array_slice($words, 0, 15);
    $keywords = implode(',', $words);
    
    // Buscar imagen con offset aleatorio
    $offset = rand(0, 50);
    $result = AP_Image_Search::search_provider($keywords, $provider, $offset);
    
    // Si no hay resultado, probar con otros proveedores
    if (!$result) {
        $all_providers = [];
        if (get_option('ap_unsplash_key')) $all_providers[] = 'unsplash';
        if (get_option('ap_pixabay_key')) $all_providers[] = 'pixabay';
        if (get_option('ap_pexels_key')) $all_providers[] = 'pexels';
        
        foreach ($all_providers as $alt_provider) {
            if ($alt_provider === $provider) continue;
            $result = AP_Image_Search::search_provider($keywords, $alt_provider, $offset);
            if ($result) break;
        }
    }
    
    // Si no hay resultado después de todos los intentos, usar imagen dummy
    if (!$result) {
        $dummy_url = 'data:image/svg+xml;base64,' . base64_encode('<?xml version="1.0" encoding="UTF-8"?>
<svg width="800" height="600" xmlns="http://www.w3.org/2000/svg">
  <rect width="800" height="600" fill="#f0f0f0"/>
  <text x="400" y="280" font-family="Arial, sans-serif" font-size="24" fill="#666" text-anchor="middle" font-weight="bold">Sin resultados de imagen</text>
  <text x="400" y="320" font-family="Arial, sans-serif" font-size="18" fill="#999" text-anchor="middle">Modifica las Keywords</text>
  <text x="400" y="350" font-family="Arial, sans-serif" font-size="18" fill="#999" text-anchor="middle">de búsqueda de imagen</text>
</svg>');
        
        $result = [
            'large' => $dummy_url,
            'thumb' => $dummy_url
        ];
    }
    
    // Actualizar BD
    $field_url = $type === 'featured' ? 'featured_image_url' : 'inner_image_url';
    $field_thumb = $type === 'featured' ? 'featured_image_thumb' : 'inner_image_thumb';
    
    $updated = $wpdb->update(
        $wpdb->prefix . 'ap_queue',
        [
            $field_url => $result['large'],
            $field_thumb => $result['thumb']
        ],
        ['id' => $id],
        ['%s', '%s'],
        ['%d']
    );
    
    if ($updated !== false) {
        wp_send_json_success([
            'thumb_url' => $result['thumb'],
            'large_url' => $result['large']
        ]);
    } else {
        wp_send_json_error(['message' => 'Error actualizando BD']);
    }
}

// Endpoint para establecer imagen desde biblioteca WP
add_action('wp_ajax_ap_set_library_image', 'ap_set_library_image_ajax');
function ap_set_library_image_ajax() {
    ap_verify_ajax_request();
    
    global $wpdb;
    $id = intval($_POST['id'] ?? 0);
    $type = sanitize_text_field($_POST['type'] ?? '');
    $url = esc_url_raw($_POST['url'] ?? '');
    
    if (!$id || !$type || !$url) {
        wp_send_json_error(['message' => 'Parámetros inválidos']);
    }
    
    // Actualizar BD
    $field_url = $type === 'featured' ? 'featured_image_url' : 'inner_image_url';
    $field_thumb = $type === 'featured' ? 'featured_image_thumb' : 'inner_image_thumb';
    
    $updated = $wpdb->update(
        $wpdb->prefix . 'ap_queue',
        [
            $field_url => $url,
            $field_thumb => $url // Usar misma URL para thumb
        ],
        ['id' => $id],
        ['%s', '%s'],
        ['%d']
    );
    
    if ($updated !== false) {
        wp_send_json_success([
            'url' => $url
        ]);
    } else {
        wp_send_json_error(['message' => 'Error actualizando BD']);
    }
}

// Endpoint para obtener keywords de un post
add_action('wp_ajax_ap_get_post_keywords', 'ap_get_post_keywords_ajax');
function ap_get_post_keywords_ajax() {
    ap_verify_ajax_request();
    
    global $wpdb;
    $id = intval($_POST['id'] ?? 0);
    
    if (!$id) wp_send_json_error(['message' => 'ID inválido']);
    
    $keywords = $wpdb->get_var($wpdb->prepare(
        "SELECT image_keywords FROM {$wpdb->prefix}ap_queue WHERE id = %d",
        $id
    ));
    
    wp_send_json_success(['keywords' => $keywords ?: '']);
}

// Endpoint para obtener estados de múltiples items
add_action('wp_ajax_ap_get_queue_items_status', 'ap_get_queue_items_status_ajax');
function ap_get_queue_items_status_ajax() {
    ap_verify_ajax_request();
    
    global $wpdb;
    $queue_ids = $_POST['queue_ids'] ?? [];
    
    if (empty($queue_ids) || !is_array($queue_ids)) {
        wp_send_json_error(['message' => 'IDs inválidos']);
    }
    
    // Sanitizar IDs
    $queue_ids = array_map('intval', $queue_ids);
    $placeholders = implode(',', array_fill(0, count($queue_ids), '%d'));
    
    // Obtener estados actuales
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT id, status FROM {$wpdb->prefix}ap_queue WHERE id IN ($placeholders)",
        ...$queue_ids
    ), OBJECT_K);
    
    // Crear array asociativo id => status
    $statuses = [];
    foreach ($results as $id => $row) {
        $statuses[$id] = $row->status;
    }
    
    wp_send_json_success($statuses);
}

// ============================================
// ENDPOINT: Renderizar fila de cola (fuente única de verdad)
// ============================================
add_action('wp_ajax_ap_render_queue_row', 'ap_render_queue_row_ajax');
function ap_render_queue_row_ajax() {
    ap_verify_ajax_request();

    $post_id = intval($_POST['post_id'] ?? 0);
    $position = intval($_POST['position'] ?? 1);
    $campaign_id = intval($_POST['campaign_id'] ?? 0);

    if (!$post_id || !$campaign_id) {
        wp_send_json_error(['message' => 'Parámetros inválidos']);
    }

    global $wpdb;

    // Obtener el post de la cola
    $item = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ap_queue WHERE id = %d AND campaign_id = %d",
        $post_id,
        $campaign_id
    ));

    if (!$item) {
        wp_send_json_error(['message' => 'Post no encontrado']);
    }

    // Verificar si la cola está bloqueada
    $is_queue_locked = AP_Bloqueo_System::is_locked('execute', $campaign_id) ||
                        AP_Bloqueo_System::is_locked('generate', $campaign_id);

    // Renderizar usando el template único
    $html = ap_render_queue_row($item, $position, $is_queue_locked);

    wp_send_json_success([
        'html' => $html,
        'post_id' => $post_id,
        'position' => $position
    ]);
}
