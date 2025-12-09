<?php
if (!defined('ABSPATH')) exit;

/**
 * Obtener el proveedor de imágenes por defecto
 */
if (!function_exists('ap_get_default_image_provider')) {
    function ap_get_default_image_provider() {
        if (get_option('ap_unsplash_key')) {
            return 'unsplash';
        }
        if (get_option('ap_pixabay_key')) {
            return 'pixabay';
        }
        if (get_option('ap_pexels_key')) {
            return 'pexels';
        }
        return '';
    }
}

/**
 * AJAX Handler para AutoPilot - Versión Mejorada
 * Fix: Garantiza que campaign_id se pasa correctamente entre pasos
 */
add_action('wp_ajax_ap_autopilot_step', 'ap_autopilot_step_handler_improved');

function ap_autopilot_step_handler_improved() {
    try {
        $step_action = $_POST['step_action'] ?? '';
        $form_data = $_POST['form_data'] ?? [];
        $results = $_POST['results'] ?? [];
        
        if (empty($step_action) || empty($form_data)) {
            wp_send_json_error('Datos incompletos');
            return;
        }
        
        // Logging para debug
        
        $api = new AP_API_Client();
        
        switch ($step_action) {
            case 'save_campaign_initial':
                $response = ap_step_save_campaign_initial($form_data);
                break;
                
            case 'generate_company_description':
                $response = ap_step_company_description($api, $form_data, $results);
                break;
                
            case 'generate_keywords_seo':
                $response = ap_step_keywords_seo($api, $form_data, $results);
                break;
                
            case 'generate_title_prompt':
                $response = ap_step_title_prompt($api, $form_data, $results);
                break;
                
            case 'generate_content_prompt':
                $response = ap_step_content_prompt($api, $form_data, $results);
                break;
                
            case 'generate_image_keywords':
                $response = ap_step_image_keywords($api, $form_data, $results);
                break;
                
            case 'update_campaign_final':
                $response = ap_step_update_campaign_final($form_data, $results);
                break;
                
            case 'generate_queue':
                $response = ap_step_generate_queue_improved($api, $form_data, $results);
                break;
                
            default:
                wp_send_json_error('Acción no válida');
                return;
        }
        
        if ($response['success']) {
            wp_send_json_success($response['data']);
        } else {
            // Propagar error_type si existe
            $error_data = ['message' => $response['error']];
            if (isset($response['error_type'])) {
                $error_data['error_type'] = $response['error_type'];
            }
            wp_send_json_error($error_data);
        }
        
    } catch (Exception $e) {
        wp_send_json_error('Error: ' . $e->getMessage());
    }
}

/**
 * Paso 0: Guardar campaña inicial (NUEVO)
 * Guarda campaña con datos mínimos ANTES de hacer llamadas API
 */
function ap_step_save_campaign_initial($form_data) {
    try {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ap_campaigns';

        // VALIDAR nombre de campaña - NUNCA PERMITIR VACÍO
        $campaign_name = sanitize_text_field($form_data['campaign_name'] ?? '');
        if (empty($campaign_name) || trim($campaign_name) === '') {
            return [
                'success' => false,
                'error' => '❌ El nombre de campaña es obligatorio. Por favor, introduce un nombre antes de continuar.'
            ];
        }

        // Generar campaign_id único
        $campaign_unique_id = 'campaign_' . time() . '_' . substr(md5(uniqid(rand(), true)), 0, 8);

        // Datos mínimos para crear la campaña
        $data = [
            'campaign_id' => $campaign_unique_id,
            'name' => $campaign_name,
            'domain' => sanitize_text_field($form_data['domain']),
            'niche' => sanitize_text_field($form_data['niche']),
            'num_posts' => intval($form_data['num_posts']),
            'start_date' => sanitize_text_field($form_data['start_date']),
            'publish_time' => sanitize_text_field($form_data['publish_time'] ?? '09:00'),
            'publish_days' => sanitize_text_field($form_data['publish_days'] ?? '1'),
            'category_id' => intval($form_data['category']),
            'image_provider' => ap_get_default_image_provider(),
            'post_length' => 'medio',
            'queue_generated' => 0,
            'created_at' => current_time('mysql')
        ];
        
        $inserted = $wpdb->insert($table_name, $data);
        
        if ($inserted === false) {
            return [
                'success' => false,
                'error' => 'Error al crear la campaña: ' . $wpdb->last_error
            ];
        }
        
        $db_id = $wpdb->insert_id;
        
        
        return [
            'success' => true,
            'data' => [
                'message' => 'Campaña creada',
                'campaign_id' => $campaign_unique_id,  // ✅ Usar campaign_unique_id para API tracking
                'campaign_db_id' => $db_id,  // ID de BD WordPress (solo para referencia interna)
                'campaign_name' => $data['name'] // AÑADIR para pasar a API
            ]
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Error: ' . $e->getMessage()
        ];
    }
}

/**
 * Paso 1: Generar descripción de empresa (ACTUALIZADO)
 */
function ap_step_company_description($api, $form_data, $results) {
    try {
        // Obtener datos de campaña del paso anterior
        $campaign_id = $results['save_campaign_initial']['campaign_id'] ?? null;
        $campaign_name = $results['save_campaign_initial']['campaign_name'] ?? $form_data['campaign_name'] ?? 'Sin nombre';
        
        // Generar batch_id para todo el proceso de setup
        $batch_id = 'setup_' . $campaign_id . '_' . time();
        
        $result = $api->generate_company_description(
            $form_data['domain'],
            $campaign_id,
            $batch_id,
            $campaign_name
        );

        if (!$result || !isset($result['success']) || !$result['success']) {
            $error_response = [
                'success' => false,
                'error' => $result['error'] ?? 'Error al generar descripción de empresa'
            ];
            // Propagar error_type si existe (para detectar límite de tokens)
            if (isset($result['error_type'])) {
                $error_response['error_type'] = $result['error_type'];
            }
            return $error_response;
        }
        
        return [
            'success' => true,
            'data' => [
                'message' => 'Descripción generada',
                'company_description' => $result['description'] ?? '',
                'setup_batch_id' => $batch_id, // Guardar para próximos pasos
                'campaign_name' => $campaign_name // Pasar al siguiente paso
            ]
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Error: ' . $e->getMessage()
        ];
    }
}

/**
 * Paso 2: Generar keywords SEO
 */
function ap_step_keywords_seo($api, $form_data, $results) {
    try {
        $company_desc = $results['generate_company_description']['company_description'] ?? '';
        
        if (empty($company_desc)) {
            return [
                'success' => false,
                'error' => 'No se encontró la descripción de empresa del paso anterior'
            ];
        }
        
        // Reutilizar batch_id y campaign_name del setup
        $batch_id = $results['generate_company_description']['setup_batch_id'] ?? null;
        $campaign_id = $results['save_campaign_initial']['campaign_id'] ?? null;
        $campaign_name = $results['generate_company_description']['campaign_name'] ?? null;
        
        $result = $api->generate_seo_keywords($form_data['niche'], $company_desc, $campaign_id, $campaign_name, $batch_id);

        if (!$result || !isset($result['success']) || !$result['success']) {
            $error_response = [
                'success' => false,
                'error' => $result['error'] ?? 'Error al generar keywords SEO'
            ];
            if (isset($result['error_type'])) {
                $error_response['error_type'] = $result['error_type'];
            }
            return $error_response;
        }
        
        return [
            'success' => true,
            'data' => [
                'message' => 'Keywords SEO generadas',
                'keywords_seo' => $result['keywords'] ?? '',
                'setup_batch_id' => $batch_id, // Pasar al siguiente paso
                'campaign_name' => $campaign_name
            ]
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Error: ' . $e->getMessage()
        ];
    }
}

/**
 * Paso 3: Generar prompt para títulos
 */
function ap_step_title_prompt($api, $form_data, $results) {
    try {
        $company_desc = $results['generate_company_description']['company_description'] ?? '';
        $keywords_seo = $results['generate_keywords_seo']['keywords_seo'] ?? '';
        
        if (empty($company_desc) || empty($keywords_seo)) {
            return [
                'success' => false,
                'error' => 'Faltan datos de pasos anteriores'
            ];
        }
        
        // Obtener tracking data
        $campaign_id = $results['save_campaign_initial']['campaign_id'] ?? null;
        $campaign_name = $results['generate_company_description']['campaign_name'] ?? null;
        $batch_id = $results['generate_keywords_seo']['setup_batch_id'] ?? null;
        
        $result = $api->generate_title_prompt(
            $form_data['niche'],
            $company_desc,
            $keywords_seo,
            $campaign_id,
            $campaign_name,
            $batch_id
        );

        if (!$result || !isset($result['success']) || !$result['success']) {
            $error_response = [
                'success' => false,
                'error' => $result['error'] ?? 'Error al generar prompt de títulos'
            ];
            if (isset($result['error_type'])) {
                $error_response['error_type'] = $result['error_type'];
            }
            return $error_response;
        }
        
        return [
            'success' => true,
            'data' => [
                'message' => 'Prompt de títulos generado',
                'title_prompt' => $result['prompt'] ?? '',
                'setup_batch_id' => $batch_id,
                'campaign_name' => $campaign_name
            ]
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Error: ' . $e->getMessage()
        ];
    }
}

/**
 * Paso 4: Generar prompt para contenido
 */
function ap_step_content_prompt($api, $form_data, $results) {
    try {
        $company_desc = $results['generate_company_description']['company_description'] ?? '';
        $keywords_seo = $results['generate_keywords_seo']['keywords_seo'] ?? '';
        
        if (empty($company_desc) || empty($keywords_seo)) {
            return [
                'success' => false,
                'error' => 'Faltan datos de pasos anteriores'
            ];
        }
        
        // Obtener tracking data
        $campaign_id = $results['save_campaign_initial']['campaign_id'] ?? null;
        $campaign_name = $results['generate_title_prompt']['campaign_name'] ?? null;
        $batch_id = $results['generate_title_prompt']['setup_batch_id'] ?? null;
        
        $result = $api->generate_content_prompt(
            $form_data['niche'],
            $company_desc,
            $keywords_seo,
            $campaign_id,
            $campaign_name,
            $batch_id
        );

        if (!$result || !isset($result['success']) || !$result['success']) {
            $error_response = [
                'success' => false,
                'error' => $result['error'] ?? 'Error al generar prompt de contenido'
            ];
            if (isset($result['error_type'])) {
                $error_response['error_type'] = $result['error_type'];
            }
            return $error_response;
        }
        
        return [
            'success' => true,
            'data' => [
                'message' => 'Prompt de contenido generado',
                'content_prompt' => $result['prompt'] ?? '',
                'setup_batch_id' => $batch_id,
                'campaign_name' => $campaign_name
            ]
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Error: ' . $e->getMessage()
        ];
    }
}

/**
 * Paso 5: Generar keywords de imágenes
 */
function ap_step_image_keywords($api, $form_data, $results) {
    try {
        // Obtener tracking data
        $campaign_id = $results['save_campaign_initial']['campaign_id'] ?? null;
        $campaign_name = $results['generate_content_prompt']['campaign_name'] ?? null;
        $batch_id = $results['generate_content_prompt']['setup_batch_id'] ?? null;
        $company_desc = $results['generate_company_description']['company_description'] ?? '';
        $keywords_seo = $results['generate_keywords_seo']['keywords_seo'] ?? '';
        
        $result = $api->generate_campaign_image_keywords(
            $form_data['niche'],
            $company_desc,           // Descripción, NO campaign_name
            $campaign_id,
            $campaign_name,
            $batch_id,
            $keywords_seo
        );

        if (!$result || !isset($result['success']) || !$result['success']) {
            $error_response = [
                'success' => false,
                'error' => $result['error'] ?? 'Error al generar keywords de imágenes'
            ];
            if (isset($result['error_type'])) {
                $error_response['error_type'] = $result['error_type'];
            }
            return $error_response;
        }
        
        return [
            'success' => true,
            'data' => [
                'message' => 'Keywords de imágenes generadas',
                'image_keywords' => $result['keywords'] ?? '',
                'setup_batch_id' => $batch_id,
                'campaign_name' => $campaign_name
            ]
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Error: ' . $e->getMessage()
        ];
    }
}

/**
 * Paso FINAL: Actualizar campaña con todos los datos generados
 */
function ap_step_update_campaign_final($form_data, $results) {
    try {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ap_campaigns';

        error_log('[AUTOPILOT FINAL] Iniciando actualización final de campaña');
        error_log('[AUTOPILOT FINAL] Results recibidos: ' . print_r(array_keys($results), true));

        // Obtener IDs del primer paso
        $campaign_id = $results['save_campaign_initial']['campaign_id'] ?? null;  // campaign_unique_id para API
        $campaign_db_id = $results['save_campaign_initial']['campaign_db_id'] ?? null;  // ID numérico de BD

        if (!$campaign_id || !$campaign_db_id) {
            error_log('[AUTOPILOT FINAL] ERROR: No se encontró campaign_id o campaign_db_id en results');
            return [
                'success' => false,
                'error' => 'No se encontró el ID de la campaña'
            ];
        }

        error_log('[AUTOPILOT FINAL] Campaign ID (unique): ' . $campaign_id);
        error_log('[AUTOPILOT FINAL] Campaign DB ID: ' . $campaign_db_id);

        // Preparar datos para actualizar
        $update_data = [
            'company_desc' => $results['generate_company_description']['company_description'] ?? '',
            'keywords_seo' => $results['generate_keywords_seo']['keywords_seo'] ?? '',
            'prompt_titles' => $results['generate_title_prompt']['title_prompt'] ?? '',
            'prompt_content' => $results['generate_content_prompt']['content_prompt'] ?? '',
            'keywords_images' => $results['generate_image_keywords']['image_keywords'] ?? '',
            'updated_at' => current_time('mysql')
        ];

        error_log('[AUTOPILOT FINAL] Datos a actualizar:');
        error_log('[AUTOPILOT FINAL] - company_desc length: ' . strlen($update_data['company_desc']));
        error_log('[AUTOPILOT FINAL] - keywords_seo length: ' . strlen($update_data['keywords_seo']));
        error_log('[AUTOPILOT FINAL] - prompt_titles length: ' . strlen($update_data['prompt_titles']));
        error_log('[AUTOPILOT FINAL] - prompt_content length: ' . strlen($update_data['prompt_content']));
        error_log('[AUTOPILOT FINAL] - keywords_images length: ' . strlen($update_data['keywords_images']));

        $updated = $wpdb->update(
            $table_name,
            $update_data,
            ['id' => $campaign_id],
            ['%s', '%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );

        if ($updated === false) {
            error_log('[AUTOPILOT FINAL] ERROR en wpdb->update: ' . $wpdb->last_error);
            return [
                'success' => false,
                'error' => 'Error al actualizar la campaña: ' . $wpdb->last_error
            ];
        }

        error_log('[AUTOPILOT FINAL] Campaña actualizada exitosamente. Rows affected: ' . $updated);

        return [
            'success' => true,
            'data' => [
                'message' => 'Campaña actualizada correctamente',
                'campaign_id' => $campaign_id
            ]
        ];
    } catch (Exception $e) {
        error_log('[AUTOPILOT FINAL] EXCEPTION: ' . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Error: ' . $e->getMessage()
        ];
    }
}

/**
 * Paso 6: Guardar campaña (DEPRECATED - ahora se usa save_campaign_initial + update_campaign_final)
 */
function ap_step_save_campaign_improved($form_data, $results) {
    try {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ap_campaigns';
        
        // Verificar que existen los datos necesarios
        if (!isset($results['generate_company_description']['company_description'])) {
            return [
                'success' => false,
                'error' => 'Falta descripción de empresa'
            ];
        }
        
        if (!isset($results['generate_keywords_seo']['keywords_seo'])) {
            return [
                'success' => false,
                'error' => 'Faltan keywords SEO'
            ];
        }
        
        // Generar campaign_id único
        $campaign_unique_id = 'campaign_' . time() . '_' . wp_generate_password(8, false);
        
        // Preparar datos para insertar
        $publish_days = sanitize_text_field($form_data['publish_days'] ?? '1');
        $publish_time = sanitize_text_field($form_data['publish_time'] ?? '09:00');
        
        
        $data = [
            'campaign_id' => $campaign_unique_id,
            'name' => sanitize_text_field($form_data['campaign_name']),
            'domain' => sanitize_text_field($form_data['domain']),
            'niche' => sanitize_text_field($form_data['niche']),
            'company_desc' => $results['generate_company_description']['company_description'] ?? '',
            'keywords_seo' => $results['generate_keywords_seo']['keywords_seo'] ?? '',
            'prompt_titles' => $results['generate_title_prompt']['title_prompt'] ?? '',
            'prompt_content' => $results['generate_content_prompt']['content_prompt'] ?? '',
            'keywords_images' => $results['generate_image_keywords']['image_keywords'] ?? '',
            'publish_days' => $publish_days,
            'start_date' => sanitize_text_field($form_data['start_date']),
            'publish_time' => $publish_time,
            'num_posts' => intval($form_data['num_posts']),
            'post_length' => 'medio',
            'category_id' => intval($form_data['category']),
            'image_provider' => ap_get_default_image_provider(),
            'created_at' => current_time('mysql')
        ];
        
        // Insertar en la base de datos
        $inserted = $wpdb->insert($table_name, $data);
        
        if ($inserted === false) {
            return [
                'success' => false,
                'error' => 'Error al guardar la campaña en la base de datos: ' . $wpdb->last_error
            ];
        }
        
        $db_id = $wpdb->insert_id;
        
        
        return [
            'success' => true,
            'data' => [
                'message' => 'Campaña guardada correctamente',
                'campaign_id' => $campaign_unique_id,  // ✅ Usar campaign_unique_id para API tracking
                'campaign_db_id' => $db_id  // ID de BD WordPress (solo para referencia interna)
            ]
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Error al guardar: ' . $e->getMessage()
        ];
    }
}

/**
 * Paso 7: Generar cola de posts (MEJORADO)
 */
function ap_step_generate_queue_improved($api, $form_data, $results) {
    try {
        global $wpdb;
        
        // CRÍTICO: Obtener campaign_id del paso inicial
        $campaign_db_id = null;
        
        // Primero intentar desde save_campaign_initial
        if (isset($results['save_campaign_initial']['campaign_id'])) {
            $campaign_db_id = intval($results['save_campaign_initial']['campaign_id']);
        }
        
        // Fallback: form_data
        if (!$campaign_db_id && isset($form_data['campaign_id']) && !empty($form_data['campaign_id'])) {
            $campaign_db_id = intval($form_data['campaign_id']);
        }
        
        // Fallback: results antiguo (por compatibilidad)
        if (!$campaign_db_id && isset($results['save_campaign']['campaign_id'])) {
            $campaign_db_id = intval($results['save_campaign']['campaign_id']);
        }
        
        if (!$campaign_db_id || $campaign_db_id < 1) {
            return [
                'success' => false,
                'error' => 'No se pudo obtener el ID de la campaña guardada. Por favor, verifica la campaña en "Ver/Editar Campañas" y genera la cola manualmente.'
            ];
        }
        
        $num_posts = intval($form_data['num_posts']);
        $category_id = intval($form_data['category']);
        
        if ($num_posts < 1) {
            return [
                'success' => false,
                'error' => 'Número de posts inválido'
            ];
        }
        
        // Obtener datos de la campaña
        $table_campaigns = $wpdb->prefix . 'ap_campaigns';
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_campaigns WHERE id = %d",
            $campaign_db_id
        ), ARRAY_A);
        
        if (!$campaign) {
            return [
                'success' => false,
                'error' => 'Campaña no encontrada (ID: ' . $campaign_db_id . '). Verifica en "Ver/Editar Campañas".'
            ];
        }
        
        
        // Generar títulos UNO A UNO
        $titles = [];
        $total_tokens = 0;
        
        for ($i = 0; $i < $num_posts; $i++) {
            
            // Contexto de títulos ya generados para evitar duplicados
            $existing_context = '';
            if (!empty($titles)) {
                $existing_context = "\n\n⚠️ NO repitas estos títulos ya generados:\n- " . implode("\n- ", $titles);
            }
            
            $result = $api->generate_post_title(
                $campaign['prompt_titles'] . $existing_context,
                $campaign['company_desc'],
                $campaign['keywords_seo']
            );
            
            if (!$result || !isset($result['success']) || !$result['success']) {
                // Si falla uno, continuar con los que se puedan generar
                continue;
            }
            
            $title = $result['title'] ?? '';
            if (!empty($title)) {
                $titles[] = $title;
                $total_tokens += $result['tokens'] ?? 0;
            }
        }
        
        if (empty($titles)) {
            return [
                'success' => false,
                'error' => 'No se pudo generar ningún título'
            ];
        }
        
        
        // Generar keywords de imagen para cada título
        $image_keywords_array = [];
        
        foreach ($titles as $index => $title) {
            $result_img = $api->generate_image_keywords(
                $title,
                $campaign['niche'],
                $campaign['company_desc'],
                $campaign['keywords_seo'],
                $campaign['keywords_images']
            );
            
            if ($result_img && isset($result_img['success']) && $result_img['success']) {
                $image_keywords_array[$index] = $result_img['keywords'] ?? $campaign['keywords_images'];
            } else {
                // Si falla, usar las keywords generales de la campaña
                $image_keywords_array[$index] = $campaign['keywords_images'];
            }
        }
        
        
        // Guardar en la cola
        $table_queue = $wpdb->prefix . 'ap_queue';
        $batch_id = 'batch_' . time() . '_' . wp_rand(1000, 9999);
        $inserted_count = 0;
        $errors = [];
        
        foreach ($titles as $index => $title) {
            $image_keywords = $image_keywords_array[$index] ?? $campaign['keywords_images'];
            
            $queue_data = [
                'campaign_id' => $campaign_db_id,  // ID numérico de la campaña
                'batch_id' => $batch_id,
                'title' => $title,
                'keywords_images' => $image_keywords,
                'category_id' => $category_id,
                'status' => 'pending',
                'created_at' => current_time('mysql')
            ];
            
            $inserted = $wpdb->insert($table_queue, $queue_data);
            
            if ($inserted !== false) {
                $inserted_count++;
            } else {
                $errors[] = 'Error al insertar título "' . substr($title, 0, 30) . '...": ' . $wpdb->last_error;
            }
        }
        
        if ($inserted_count === 0) {
            return [
                'success' => false,
                'error' => 'No se pudieron insertar los posts en la cola. Revisa los logs.'
            ];
        }
        
        // Si se insertaron algunos pero no todos, informar
        if ($inserted_count < count($titles)) {
        }
        

        return [
            'success' => true,
            'data' => [
                'message' => $inserted_count . ' posts añadidos a la cola',
                'batch_id' => $batch_id,
                'count' => $inserted_count,
                'campaign_id' => $campaign_db_id
            ]
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Error: ' . $e->getMessage()
        ];
    }
}

/**
 * ==========================================
 * NUEVOS HANDLERS PARA AUTOPILOT-PROCESS.PHP
 * ==========================================
 */

/**
 * Handler: Crear campaña inicial
 * Action: ap_autopilot_create_campaign
 */
add_action('wp_ajax_ap_autopilot_create_campaign', 'ap_autopilot_create_campaign_handler');

function ap_autopilot_create_campaign_handler() {
    check_ajax_referer('ap_autopilot', 'nonce');

    try {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ap_campaigns';

        $name = sanitize_text_field($_POST['name'] ?? '');
        $domain = sanitize_text_field($_POST['domain'] ?? '');
        $niche = sanitize_text_field($_POST['niche'] ?? '');

        if (empty($name) || empty($domain) || empty($niche)) {
            wp_send_json_error('Datos incompletos');
            return;
        }

        // Generar campaign_id único
        $campaign_unique_id = 'campaign_' . time() . '_' . substr(md5(uniqid(rand(), true)), 0, 8);

        // Crear campaña con datos mínimos
        $data = [
            'campaign_id' => $campaign_unique_id,
            'name' => $name,
            'domain' => $domain,
            'niche' => $niche,
            'image_provider' => ap_get_default_image_provider(),
            'post_length' => 'medio',
            'queue_generated' => 0,
            'created_at' => current_time('mysql')
        ];

        $inserted = $wpdb->insert($table_name, $data);

        if ($inserted === false) {
            wp_send_json_error('Error al crear campaña: ' . $wpdb->last_error);
            return;
        }

        $db_id = $wpdb->insert_id;

        wp_send_json_success([
            'campaign_id' => $db_id,
            'campaign_unique_id' => $campaign_unique_id
        ]);

    } catch (Exception $e) {
        wp_send_json_error('Error: ' . $e->getMessage());
    }
}

/**
 * Handler: Generar campo individual
 * Action: ap_autopilot_generate
 */
add_action('wp_ajax_ap_autopilot_generate', 'ap_autopilot_generate_handler');

function ap_autopilot_generate_handler() {
    check_ajax_referer('ap_autopilot', 'nonce');

    try {
        $field = sanitize_text_field($_POST['field'] ?? '');
        $campaign_id = intval($_POST['campaign_id'] ?? 0);
        $sources = json_decode(stripslashes($_POST['sources'] ?? '{}'), true);

        error_log('[AUTOPILOT GENERATE] Field: ' . $field . ' | Campaign ID: ' . $campaign_id);

        if (empty($field) || !$campaign_id) {
            error_log('[AUTOPILOT GENERATE] ERROR: Datos incompletos');
            wp_send_json_error('Datos incompletos');
            return;
        }

        $api = new AP_API_Client();
        $result = null;
        $content = '';

        // Generar batch_id para tracking
        $batch_id = 'autopilot_' . $campaign_id . '_' . time();

        // Obtener campaign_name de la BD
        global $wpdb;
        $table_name = $wpdb->prefix . 'ap_campaigns';
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT name, niche FROM $table_name WHERE id = %d",
            $campaign_id
        ), ARRAY_A);

        $campaign_name = $campaign['name'] ?? 'Campaña';
        $niche = $campaign['niche'] ?? '';

        switch ($field) {
            case 'company_desc':
                $domain = $sources['domain'] ?? '';
                $result = $api->generate_company_description($domain, $campaign_id, $batch_id, $campaign_name);
                if ($result && $result['success']) {
                    $content = $result['description'] ?? '';
                }
                break;

            case 'keywords_seo':
                $company_desc = $sources['company_desc'] ?? '';
                $result = $api->generate_seo_keywords($niche, $company_desc, $campaign_id, $campaign_name, $batch_id);
                if ($result && $result['success']) {
                    $content = $result['keywords'] ?? '';
                }
                break;

            case 'prompt_titles':
                $company_desc = $sources['company_desc'] ?? '';
                $keywords_seo = $sources['keywords_seo'] ?? '';
                $result = $api->generate_title_prompt($niche, $company_desc, $keywords_seo, $campaign_id, $campaign_name, $batch_id);
                if ($result && $result['success']) {
                    $content = $result['prompt'] ?? '';
                }
                break;

            case 'prompt_content':
                $company_desc = $sources['company_desc'] ?? '';
                $keywords_seo = $sources['keywords_seo'] ?? '';
                $result = $api->generate_content_prompt($niche, $company_desc, $keywords_seo, $campaign_id, $campaign_name, $batch_id);
                if ($result && $result['success']) {
                    $content = $result['prompt'] ?? '';
                }
                break;

            case 'keywords_images':
                $company_desc = $sources['company_desc'] ?? '';
                $keywords_seo = $sources['keywords_seo'] ?? '';
                $result = $api->generate_campaign_image_keywords($niche, $company_desc, $campaign_id, $campaign_name, $batch_id, $keywords_seo);
                if ($result && $result['success']) {
                    $content = $result['keywords'] ?? '';
                }
                break;

            default:
                error_log('[AUTOPILOT GENERATE] ERROR: Campo no válido: ' . $field);
                wp_send_json_error('Campo no válido: ' . $field);
                return;
        }

        if (!$result || !$result['success']) {
            error_log('[AUTOPILOT GENERATE] ERROR para ' . $field . ': ' . ($result['error'] ?? 'Unknown error'));
            wp_send_json_error($result['error'] ?? 'Error al generar ' . $field);
            return;
        }

        error_log('[AUTOPILOT GENERATE] SUCCESS para ' . $field . ' | Content length: ' . strlen($content));

        wp_send_json_success([
            'content' => $content
        ]);

    } catch (Exception $e) {
        error_log('[AUTOPILOT GENERATE] EXCEPTION: ' . $e->getMessage());
        wp_send_json_error('Error: ' . $e->getMessage());
    }
}

/**
 * Handler: Actualizar campaña con todos los datos generados
 * Action: ap_autopilot_update_campaign
 */
add_action('wp_ajax_ap_autopilot_update_campaign', 'ap_autopilot_update_campaign_handler');

function ap_autopilot_update_campaign_handler() {
    check_ajax_referer('ap_autopilot', 'nonce');

    try {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ap_campaigns';

        $data = json_decode(stripslashes($_POST['data'] ?? '{}'), true);

        // LOG: Datos recibidos
        error_log('[AUTOPILOT UPDATE] Datos recibidos: ' . print_r($data, true));

        if (empty($data) || !isset($data['campaign_id'])) {
            error_log('[AUTOPILOT UPDATE] ERROR: Datos incompletos');
            wp_send_json_error('Datos incompletos');
            return;
        }

        $campaign_id = intval($data['campaign_id']);

        // Preparar datos para actualizar
        $update_data = [
            'company_desc' => $data['company_desc'] ?? '',
            'keywords_seo' => $data['keywords_seo'] ?? '',
            'prompt_titles' => $data['prompt_titles'] ?? '',
            'prompt_content' => $data['prompt_content'] ?? '',
            'keywords_images' => $data['keywords_images'] ?? '',
            'updated_at' => current_time('mysql')
        ];

        error_log('[AUTOPILOT UPDATE] Actualizando campaña ID: ' . $campaign_id);
        error_log('[AUTOPILOT UPDATE] Datos a actualizar: ' . print_r($update_data, true));

        $updated = $wpdb->update(
            $table_name,
            $update_data,
            ['id' => $campaign_id],
            ['%s', '%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );

        if ($updated === false) {
            error_log('[AUTOPILOT UPDATE] ERROR en update: ' . $wpdb->last_error);
            wp_send_json_error('Error al actualizar campaña: ' . $wpdb->last_error);
            return;
        }

        error_log('[AUTOPILOT UPDATE] Campaña actualizada exitosamente. Rows affected: ' . $updated);

        wp_send_json_success([
            'message' => 'Campaña actualizada correctamente',
            'campaign_id' => $campaign_id,
            'rows_updated' => $updated
        ]);

    } catch (Exception $e) {
        error_log('[AUTOPILOT UPDATE] EXCEPTION: ' . $e->getMessage());
        wp_send_json_error('Error: ' . $e->getMessage());
    }
}
