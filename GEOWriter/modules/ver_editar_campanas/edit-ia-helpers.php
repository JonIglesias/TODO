<?php
if (!defined('ABSPATH')) exit;

class AP_IA_Helpers {
    
    /**
     * ⭐ ESTABLECER CONTEXTO DE CAMPAÑA - MÉTODO CENTRALIZADO
     * Se ejecuta ANTES de cada llamada API
     * 
     * @return array ['success' => bool, 'message' => string]
     */
    private static function set_campaign_context() {
        $campaign_id = null;
        
        // ⭐ PRIMERO: Si ya está establecido en globals, usarlo directamente
        if (isset($GLOBALS['ap_current_campaign_id']) && isset($GLOBALS['ap_current_campaign_name'])) {
            return ['success' => true];
        }
        
        // ⭐ NUEVO ENFOQUE: Obtener campaign_id desde múltiples fuentes
        // 1. Desde $_POST (AJAX)
        if (isset($_POST['campaign_id']) && $_POST['campaign_id'] > 0) {
            $campaign_id = intval($_POST['campaign_id']);
        }
        // 2. Desde $_GET (URL)
        elseif (isset($_GET['id']) && $_GET['id'] > 0) {
            $campaign_id = intval($_GET['id']);
        }
        // 3. Desde Referer (última opción)
        elseif (isset($_SERVER['HTTP_REFERER'])) {
            // Extraer ID de la URL: ...&id=123
            if (preg_match('/[?&]id=(\d+)/', $_SERVER['HTTP_REFERER'], $matches)) {
                $campaign_id = intval($matches[1]);
            }
        }

        // Si no hay campaign_id, ERROR
        if (!$campaign_id) {
            return [
                'success' => false,
                'message' => '❌ Error: No se pudo detectar el ID de la campaña. Recarga la página e intenta de nuevo.'
            ];
        }
        
        global $wpdb;
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT id, name FROM {$wpdb->prefix}ap_campaigns WHERE id = %d",
            $campaign_id
        ));
        
        // Si no existe la campaña, ERROR
        if (!$campaign) {
            return [
                'success' => false,
                'message' => '❌ Error: La campaña #' . $campaign_id . ' no existe.'
            ];
        }
        
        // ⭐ VALIDACIÓN: Nombre obligatorio
        if (empty($campaign->name) || trim($campaign->name) === '') {
            return [
                'success' => false,
                'message' => '❌ Error: Debes dar un NOMBRE a la campaña antes de usar la IA. Por favor, guarda un nombre en el campo "Nombre de campaña".'
            ];
        }
        
        // Todo OK - Establecer contexto global
        // ✅ Usar SOLO el ID numérico
        $GLOBALS['ap_current_campaign_id'] = (string)$campaign->id;
        $GLOBALS['ap_current_campaign_name'] = $campaign->name;

        return ['success' => true];
    }
    
    private static function get_api_client() {
        return new AP_API_Client();
    }
    
    public static function generate_company_desc($domain) {
        // ⭐ ESTABLECER Y VALIDAR CONTEXTO
        $context = self::set_campaign_context();
        if (!$context['success']) {
            return $context; // Retornar error si falla validación
        }
        
        $api = self::get_api_client();
        $result = $api->generate_company_description($domain);
        
        if ($result && isset($result['success']) && $result['success']) {
            return [
                'success' => true,
                'data' => $result['description'],
                'tokens' => $result['tokens_used'] ?? 0
            ];
        }
        
        return [
            'success' => false,
            'message' => $result['error'] ?? 'Error generando descripción'
        ];
    }
    
    public static function generate_prompt_titles($niche, $company_desc, $keywords) {
        // ⭐ ESTABLECER Y VALIDAR CONTEXTO
        $context = self::set_campaign_context();
        if (!$context['success']) {
            return $context;
        }
        
        $api = self::get_api_client();
        $result = $api->generate_title_prompt($niche, $company_desc, $keywords);
        
        if ($result && isset($result['success']) && $result['success']) {
            return [
                'success' => true,
                'data' => $result['prompt'],
                'tokens' => $result['tokens_used'] ?? 0
            ];
        }
        
        return [
            'success' => false,
            'message' => $result['error'] ?? 'Error generando prompt'
        ];
    }
    
    public static function generate_prompt_content($niche, $company_desc, $keywords) {
        // ⭐ ESTABLECER Y VALIDAR CONTEXTO
        $context = self::set_campaign_context();
        if (!$context['success']) {
            return $context;
        }
        
        $api = self::get_api_client();
        $result = $api->generate_content_prompt($niche, $company_desc, $keywords);
        
        if ($result && isset($result['success']) && $result['success']) {
            return [
                'success' => true,
                'data' => $result['prompt'],
                'tokens' => $result['tokens_used'] ?? 0
            ];
        }
        
        return [
            'success' => false,
            'message' => $result['error'] ?? 'Error generando prompt de contenido'
        ];
    }
    
    public static function generate_keywords_seo($niche, $company_desc) {
        // ⭐ ESTABLECER Y VALIDAR CONTEXTO
        $context = self::set_campaign_context();
        if (!$context['success']) {
            return $context;
        }
        
        $api = self::get_api_client();
        $result = $api->generate_seo_keywords($niche, $company_desc);
        
        if ($result && isset($result['success']) && $result['success']) {
            return [
                'success' => true,
                'data' => $result['keywords'],
                'tokens' => $result['tokens_used'] ?? 0
            ];
        }
        
        return [
            'success' => false,
            'message' => $result['error'] ?? 'Error generando keywords SEO'
        ];
    }
    
    public static function generate_keywords_images($title, $dynamic_prompt = '') {
        // ⭐ ESTABLECER Y VALIDAR CONTEXTO
        $context = self::set_campaign_context();
        if (!$context['success']) {
            return $context;
        }

        $api = self::get_api_client();
        $result = $api->generate_image_keywords($title, $dynamic_prompt);

        if ($result && isset($result['success']) && $result['success']) {
            return [
                'success' => true,
                'data' => $result['keywords'] ?? '',
                'keywords' => $result['keywords'] ?? '',
                'tokens' => $result['tokens_used'] ?? 0
            ];
        }

        return [
            'success' => false,
            'message' => $result['error'] ?? 'Error generando keywords de imagen'
        ];
    }
    
    public static function generate_keywords_images_campaign($niche, $company_desc, $keywords_seo) {
        // ⭐ ESTABLECER Y VALIDAR CONTEXTO
        $context = self::set_campaign_context();
        if (!$context['success']) {
            return $context;
        }
        
        $api = self::get_api_client();
        $result = $api->generate_campaign_image_keywords($niche, $company_desc, $keywords_seo);
        
        if ($result && isset($result['success']) && $result['success']) {
            return [
                'success' => true,
                'data' => $result['keywords'] ?? '',
                'keywords' => $result['keywords'] ?? '',
                'tokens' => $result['tokens_used'] ?? 0
            ];
        }
        
        return [
            'success' => false,
            'message' => $result['error'] ?? 'Error generando keywords de imagen de campaña'
        ];
    }
    
    public static function generate_title($prompt_titles, $keywords_seo, $company_desc, $retry = 0) {
        // ⭐ ESTABLECER Y VALIDAR CONTEXTO
        $context = self::set_campaign_context();
        if (!$context['success']) {
            return $context;
        }
        
        $api = self::get_api_client();
        $result = $api->generate_post_title($prompt_titles, $company_desc, $keywords_seo, $retry);
        
        if ($result && isset($result['success']) && $result['success']) {
            return [
                'success' => true,
                'data' => $result['title'],
                'tokens' => $result['tokens_used'] ?? 0
            ];
        }
        
        return [
            'success' => false,
            'message' => $result['error'] ?? 'Error generando título'
        ];
    }
    
    public static function generate_content($title, $keywords_seo, $company_desc, $length, $custom_prompt = '') {
        // ⭐ ESTABLECER Y VALIDAR CONTEXTO
        $context = self::set_campaign_context();
        if (!$context['success']) {
            return $context;
        }

        $api = self::get_api_client();
        $result = $api->generate_post_content($title, $keywords_seo, $company_desc, $length, $custom_prompt);

        if ($result && isset($result['success']) && $result['success']) {
            return [
                'success' => true,
                'data' => $result['content'],
                'tokens' => $result['tokens_used'] ?? 0
            ];
        }

        return [
            'success' => false,
            'message' => $result['error'] ?? 'Error generando contenido'
        ];
    }

    /**
     * Decide estilo visual - Genera descripciones contextualizadas de estilos
     *
     * @param string $niche
     * @param string $company_desc
     * @return array ['success' => bool, 'data' => ['styles' => array]]
     */
    public static function decide_estilo($niche, $company_desc) {
        // ⭐ ESTABLECER Y VALIDAR CONTEXTO
        $context = self::set_campaign_context();
        if (!$context['success']) {
            return $context;
        }

        $api = self::get_api_client();
        $result = $api->decide_estilo($niche, $company_desc);

        if ($result && isset($result['success']) && $result['success']) {
            return [
                'success' => true,
                'data' => [
                    'styles' => $result['styles'] ?? []
                ],
                'tokens' => $result['tokens_used'] ?? 0
            ];
        }

        return [
            'success' => false,
            'message' => $result['error'] ?? 'Error analizando estilos visuales'
        ];
    }

    /**
     * Genera prompt dinámico para keywords de imagen
     *
     * @param string $company_desc
     * @param string $niche
     * @param string $image_style_selected
     * @return array ['success' => bool, 'data' => string (dynamic_prompt)]
     */
    public static function generate_image_prompt($company_desc, $niche, $image_style_selected) {
        // ⭐ ESTABLECER Y VALIDAR CONTEXTO
        $context = self::set_campaign_context();
        if (!$context['success']) {
            return $context;
        }

        $api = self::get_api_client();
        $result = $api->generate_image_prompt($company_desc, $niche, $image_style_selected);

        if ($result && isset($result['success']) && $result['success']) {
            return [
                'success' => true,
                'data' => $result['dynamic_prompt'] ?? '',
                'tokens' => $result['tokens_used'] ?? 0
            ];
        }

        return [
            'success' => false,
            'message' => $result['error'] ?? 'Error generando prompt dinámico de imagen'
        ];
    }
}