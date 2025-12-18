<?php
if (!defined('ABSPATH')) exit;

add_action('admin_init', 'ap_campaign_save_handler');
function ap_campaign_save_handler() {
    if (!isset($_POST['ap_campaign_nonce'])) return;
    
    if (!wp_verify_nonce($_POST['ap_campaign_nonce'], 'ap_save_campaign')) {
        wp_die('Nonce inválido');
    }
    
    if (!current_user_can('manage_options')) {
        wp_die('Permisos insuficientes');
    }
    
    global $wpdb;
    
    $campaign_id = intval($_POST['campaign_id'] ?? 0);
    
    $niche = !empty($_POST['niche_custom'])
        ? sanitize_text_field($_POST['niche_custom'] ?? '')
        : sanitize_text_field($_POST['niche'] ?? '');

    $publish_days = isset($_POST['publish_days']) && is_array($_POST['publish_days'])
        ? implode(',', array_map('sanitize_text_field', $_POST['publish_days']))
        : '';

    // ========================================
    // VALIDACIÓN CRÍTICA: NOMBRE OBLIGATORIO (mínimo 3 caracteres)
    // ========================================
    $campaign_name = sanitize_text_field($_POST['name'] ?? '');
    if (empty($campaign_name) || trim($campaign_name) === '' || strlen(trim($campaign_name)) < 3) {
        wp_die('❌ ERROR: El nombre de campaña debe tener al menos 3 caracteres.', 'Error de Validación', ['back_link' => true]);
        return;
    }

    // ========================================
    // DATOS DEL FORMULARIO (SOLO CAMPOS EXISTENTES EN BD)
    // ========================================
    $data = [
        'name' => $campaign_name,
        'domain' => sanitize_text_field($_POST['domain'] ?? ''),
        'company_desc' => sanitize_textarea_field($_POST['company_desc'] ?? ''),
        'niche' => $niche,
        'prompt_titles' => sanitize_textarea_field($_POST['prompt_titles'] ?? ''),
        'prompt_content' => sanitize_textarea_field($_POST['prompt_content'] ?? ''),
        'keywords_seo' => sanitize_textarea_field($_POST['keywords_seo'] ?? ''),
        'keywords_images' => sanitize_textarea_field($_POST['keywords_images'] ?? ''),
        'publish_days' => $publish_days,
        'start_date' => sanitize_text_field($_POST['start_date'] ?? ''),
        'publish_time' => sanitize_text_field($_POST['publish_time'] ?? '09:00'),
        'num_posts' => intval($_POST['num_posts'] ?? 0),
        'post_length' => sanitize_text_field($_POST['post_length'] ?? 'medio'),
        'image_provider' => sanitize_text_field($_POST['image_provider'] ?? 'pexels'),
        'category_id' => intval($_POST['category_id'] ?? 0)
    ];

    // ========================================
    // GENERAR PROMPT DINÁMICO DE IMAGEN
    // ========================================
    // Si tenemos company_desc, niche y un estilo seleccionado, generar el prompt dinámico
    $image_dynamic_prompt = '';
    $company_desc = $data['company_desc'];
    $niche_value = $data['niche'];
    $keywords_images_json = $data['keywords_images'];

    if (!empty($company_desc) && !empty($keywords_images_json)) {
        // Parsear el JSON de keywords_images para obtener el estilo seleccionado
        $keywords_data = json_decode($keywords_images_json, true);
        $selected_style = $keywords_data['selected'] ?? '';

        if (!empty($selected_style)) {
            // Establecer contexto de campaña para el helper
            if ($campaign_id) {
                $GLOBALS['ap_current_campaign_id'] = (string)$campaign_id;
                $GLOBALS['ap_current_campaign_name'] = $campaign_name;
            }

            // Llamar al helper para generar el prompt dinámico
            $result = AP_IA_Helpers::generate_image_prompt($company_desc, $niche_value, $selected_style);

            if ($result && isset($result['success']) && $result['success']) {
                $image_dynamic_prompt = $result['data'];
            }
        }
    }

    // Añadir el prompt dinámico a los datos a guardar
    $data['image_dynamic_prompt'] = $image_dynamic_prompt;
    
    // ========================================
    // ACTUALIZAR CAMPAÑA EXISTENTE
    // ========================================
    if ($campaign_id) {
        // Actualizar timestamp
        $data['updated_at'] = current_time('mysql');

        // Actualizar en BD
        $wpdb->update(
            $wpdb->prefix . 'ap_campaigns',
            $data,
            ['id' => $campaign_id],
            array_fill(0, count($data), '%s'),
            ['%d']
        );

        $message = 'Campaña actualizada correctamente';
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
            "SELECT COUNT(*) FROM {$wpdb->prefix}ap_campaigns WHERE name = %s AND deleted_at IS NULL FOR UPDATE",
            $campaign_name
        ));

        if ($duplicate > 0) {
            $wpdb->query('ROLLBACK');
            wp_die('⚠️ ERROR: Ya existe una campaña con ese nombre. Por favor, usa un nombre diferente.', 'Error de Validación', ['back_link' => true]);
            return;
        }

        // Generar campaign_id único (mismo formato que autosave)
        $unique_campaign_id = 'campaign_' . time() . '_' . substr(md5(uniqid(rand(), true)), 0, 8);
        $data['campaign_id'] = $unique_campaign_id;

        // Establecer timestamps
        $data['created_at'] = current_time('mysql');
        $data['updated_at'] = current_time('mysql');

        // Insertar nueva campaña
        $inserted = $wpdb->insert(
            $wpdb->prefix . 'ap_campaigns',
            $data,
            array_fill(0, count($data), '%s')
        );

        if ($inserted) {
            $campaign_id = $wpdb->insert_id;
            $wpdb->query('COMMIT');

            $message = 'Campaña creada correctamente';
        } else {
            $wpdb->query('ROLLBACK');
            wp_die('❌ ERROR: No se pudo crear la campaña. ' . $wpdb->last_error, 'Error de Base de Datos', ['back_link' => true]);
            return;
        }
    }
    
    wp_redirect(admin_url('admin.php?page=autopost-campaign-edit&id=' . $campaign_id . '&updated=1'));
    exit;
}

add_action('admin_notices', 'ap_campaign_save_notices');
function ap_campaign_save_notices() {
    if (isset($_GET['page']) && $_GET['page'] === 'autopost-campaign-edit' && isset($_GET['updated'])) {
        echo '<div class="notice notice-success is-dismissible"><p>Campaña guardada correctamente</p></div>';
    }
}