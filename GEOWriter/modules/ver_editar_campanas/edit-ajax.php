<?php
if (!defined('ABSPATH')) exit;

/**
 * ⭐ ESTABLECER CONTEXTO DE CAMPAÑA GLOBALMENTE
 */
if (wp_doing_ajax() || (defined('DOING_AJAX') && DOING_AJAX)) {
    $campaign_id = null;
    
    if (isset($_POST['campaign_id']) && !empty($_POST['campaign_id'])) {
        $campaign_id = intval($_POST['campaign_id']);
    } elseif (isset($_GET['campaign_id']) && !empty($_GET['campaign_id'])) {
        $campaign_id = intval($_GET['campaign_id']);
    }
    
    if ($campaign_id) {
        global $wpdb;
        
        if ($wpdb) {
            $campaign = $wpdb->get_row($wpdb->prepare(
                "SELECT id, name FROM {$wpdb->prefix}ap_campaigns WHERE id = %d",
                $campaign_id
            ));
            
            if ($campaign) {
                $GLOBALS['ap_current_campaign_id'] = 'campaign_' . $campaign->id;
                $GLOBALS['ap_current_campaign_name'] = $campaign->name ?? 'Sin nombre';
            }
        }
    }
}

add_action('wp_ajax_ap_generate_ia_field', 'ap_generate_ia_field_ajax');
function ap_generate_ia_field_ajax() {
    // Aplicar rate limiting: máximo 20 generaciones IA cada 5 minutos
    AP_Rate_Limiter::enforce('ap_generate_ia_field', 20, AP_RATE_LIMIT_WINDOW);

    ap_verify_ajax_request();

    // ⭐ ESTABLECER CONTEXTO DE CAMPAÑA AQUÍ (al inicio del handler)
    $campaign_id_from_post = intval($_POST['campaign_id'] ?? 0);
    if ($campaign_id_from_post > 0) {
        global $wpdb;
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT id, name FROM {$wpdb->prefix}ap_campaigns WHERE id = %d",
            $campaign_id_from_post
        ));
        
        if ($campaign) {
            $GLOBALS['ap_current_campaign_id'] = 'campaign_' . $campaign->id;
            $GLOBALS['ap_current_campaign_name'] = $campaign->name ?? 'Sin nombre';
        }
    }

    $request_id = uniqid('ajax_');

    $field = sanitize_text_field($_POST['field'] ?? '');
    $sources = $_POST['sources'] ?? [];
    
    if (!$field) {
        wp_send_json_error(['message' => 'Campo no especificado']);
    }
    
    $result = null;
    
    switch ($field) {
        case 'company_desc':
            $domain = sanitize_text_field($sources['domain'] ?? '');
            $result = AP_IA_Helpers::generate_company_desc($domain);
            break;
            
        case 'prompt_titles':
            $niche = sanitize_text_field($sources['niche'] ?? '');
            $company_desc = sanitize_textarea_field($sources['company_desc'] ?? '');
            $keywords = sanitize_textarea_field($sources['keywords_seo'] ?? '');

            $result = AP_IA_Helpers::generate_prompt_titles($niche, $company_desc, $keywords);
            break;
            
        case 'prompt_content':
            $niche = sanitize_text_field($sources['niche'] ?? '');
            $company_desc = sanitize_textarea_field($sources['company_desc'] ?? '');
            $keywords = sanitize_textarea_field($sources['keywords_seo'] ?? '');

            $result = AP_IA_Helpers::generate_prompt_content($niche, $company_desc, $keywords);
            break;
            
        case 'keywords_seo':
            $niche = sanitize_text_field($sources['niche'] ?? '');
            $company_desc = sanitize_textarea_field($sources['company_desc'] ?? '');
            $result = AP_IA_Helpers::generate_keywords_seo($niche, $company_desc);
            break;
            
        case 'keywords_images':
            // Generar keywords de imágenes a nivel de CAMPAÑA (sin título específico)
            $result = AP_IA_Helpers::generate_keywords_images_campaign(
                sanitize_text_field($sources['niche'] ?? ''),
                sanitize_textarea_field($sources['company_desc'] ?? ''),
                sanitize_text_field($sources['keywords_seo'] ?? '')
            );
            break;
            
        default:
            wp_send_json_error(['message' => 'Campo no válido']);
    }
    
    if ($result && isset($result['success']) && $result['success']) {
        wp_send_json_success([
            'content' => $result['data'] ?? '',
            'tokens' => $result['tokens'] ?? 0
        ]);
    } else {
        wp_send_json_error([
            'message' => $result['message'] ?? 'Error generando contenido'
        ]);
    }
}

// ⭐ ACTUALIZAR NOMBRE DE CAMPAÑA (y sincronizar con API)
add_action('wp_ajax_ap_update_campaign_name', 'ap_update_campaign_name_ajax');
function ap_update_campaign_name_ajax() {
    ap_verify_ajax_request();
    
    $campaign_id = intval($_POST['campaign_id'] ?? 0);
    $name = sanitize_text_field($_POST['name'] ?? '');
    
    if (!$campaign_id || empty($name)) {
        wp_send_json_error(['message' => 'Datos inválidos']);
    }
    
    global $wpdb;
    
    // Actualizar en WordPress
    $updated = $wpdb->update(
        $wpdb->prefix . 'ap_campaigns',
        ['name' => $name],
        ['id' => $campaign_id],
        ['%s'],
        ['%d']
    );
    
    if ($updated !== false) {
        // ⭐ ACTUALIZAR EN LA API
        // La API actualiza automáticamente el nombre cuando recibe campaign_name
        // No necesitamos hacer nada extra aquí, el sistema de contexto lo maneja

        wp_send_json_success(['message' => 'Nombre actualizado']);
    } else {
        wp_send_json_error(['message' => 'Error al actualizar']);
    }
}

// ⭐ AUTO-GUARDAR CAMPAÑA - SISTEMA UNIFICADO V2
add_action('wp_ajax_ap_autosave_campaign', 'ap_autosave_campaign_ajax');
function ap_autosave_campaign_ajax() {
    // Aplicar rate limiting: máximo 30 autoguardados cada 5 minutos
    AP_Rate_Limiter::enforce('ap_autosave_campaign', 30, AP_RATE_LIMIT_WINDOW);

    ap_verify_ajax_request();

    global $wpdb;
    $table = $wpdb->prefix . 'ap_campaigns';

    $campaign_id = intval($_POST['campaign_id'] ?? 0);

    // ========================================
    // VALIDACIÓN CRÍTICA: NOMBRE OBLIGATORIO
    // ========================================
    $campaign_name = sanitize_text_field($_POST['name'] ?? '');

    // Validar que el nombre existe y tiene al menos 3 caracteres
    if (empty($campaign_name) || trim($campaign_name) === '' || strlen(trim($campaign_name)) < 3) {
        wp_send_json_error([
            'message' => '❌ El nombre de campaña debe tener al menos 3 caracteres.'
        ]);
        return;
    }

    // ========================================
    // PROCESAR NICHE (select + custom)
    // ========================================
    $niche = !empty($_POST['niche_custom'])
        ? sanitize_text_field($_POST['niche_custom'])
        : sanitize_text_field($_POST['niche'] ?? '');

    // ========================================
    // PROCESAR DÍAS DE PUBLICACIÓN
    // ========================================
    $publish_days = '';
    if (isset($_POST['weekdays']) && !empty($_POST['weekdays'])) {
        // Viene del nuevo sistema unificado
        $publish_days = sanitize_text_field($_POST['weekdays']);
    } elseif (isset($_POST['publish_days']) && is_array($_POST['publish_days'])) {
        // Viene del formulario (array de checkboxes)
        $publish_days = implode(',', array_map('sanitize_text_field', $_POST['publish_days']));
    }

    // ========================================
    // DATOS DEL FORMULARIO (SOLO CAMPOS EXISTENTES EN BD)
    // ========================================
    $data = [
        'name' => $campaign_name,
        'domain' => sanitize_text_field($_POST['domain'] ?? ''),
        'company_desc' => sanitize_textarea_field($_POST['company_desc'] ?? ''),
        'niche' => $niche,
        'num_posts' => intval($_POST['num_posts'] ?? 0),
        'post_length' => sanitize_text_field($_POST['post_length'] ?? 'medio'),
        'keywords_seo' => sanitize_textarea_field($_POST['keywords_seo'] ?? ''),
        'prompt_titles' => sanitize_textarea_field($_POST['prompt_titles'] ?? ''),
        'prompt_content' => sanitize_textarea_field($_POST['prompt_content'] ?? ''),
        'keywords_images' => sanitize_textarea_field($_POST['keywords_images'] ?? ''),
        'publish_days' => $publish_days,
        'start_date' => sanitize_text_field($_POST['start_date'] ?? ''),
        'publish_time' => sanitize_text_field($_POST['post_time'] ?? '09:00'),
        'category_id' => intval($_POST['category_id'] ?? 0),
        'image_provider' => sanitize_text_field($_POST['image_provider'] ?? 'pexels')
    ];

    // ========================================
    // ACTUALIZAR CAMPAÑA EXISTENTE
    // ========================================
    if ($campaign_id > 0) {
        // Verificar que la campaña existe
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE id = %d",
            $campaign_id
        ));

        if (!$exists) {
            wp_send_json_error(['message' => 'La campaña no existe']);
            return;
        }

        // Actualizar timestamp
        $data['updated_at'] = current_time('mysql');

        // Actualizar en BD
        $updated = $wpdb->update(
            $table,
            $data,
            ['id' => $campaign_id],
            array_fill(0, count($data), '%s'),
            ['%d']
        );

        if ($updated !== false) {
            wp_send_json_success(['campaign_id' => $campaign_id]);
        } else {
            wp_send_json_error(['message' => 'Error al actualizar: ' . $wpdb->last_error]);
        }
    }
    // ========================================
    // CREAR NUEVA CAMPAÑA
    // ========================================
    else {
        // ⭐ PROTECCIÓN CONTRA DUPLICADOS MEJORADA
        // Solo verificar campañas activas (deleted_at IS NULL)
        // Usar FOR UPDATE para bloquear la fila durante la transacción
        $wpdb->query('START TRANSACTION');

        $duplicate = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE name = %s AND deleted_at IS NULL FOR UPDATE",
            $campaign_name
        ));

        if ($duplicate > 0) {
            $wpdb->query('ROLLBACK');
            wp_send_json_error([
                'message' => '⚠️ Ya existe una campaña con ese nombre. Por favor, usa un nombre diferente.'
            ]);
            return;
        }

        // Generar campaign_id único
        $unique_campaign_id = 'campaign_' . time() . '_' . substr(md5(uniqid(rand(), true)), 0, 8);
        $data['campaign_id'] = $unique_campaign_id;

        // Establecer timestamps
        $data['created_at'] = current_time('mysql');
        $data['updated_at'] = current_time('mysql');

        // Insertar en BD
        $inserted = $wpdb->insert(
            $table,
            $data,
            array_fill(0, count($data), '%s')
        );

        if ($inserted) {
            $new_id = $wpdb->insert_id;
            $wpdb->query('COMMIT');

            wp_send_json_success(['campaign_id' => $new_id]);
        } else {
            $wpdb->query('ROLLBACK');
            wp_send_json_error(['message' => 'Error al guardar: ' . $wpdb->last_error]);
        }
    }
}

