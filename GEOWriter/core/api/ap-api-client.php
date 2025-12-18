<?php
/**
 * Cliente API - VERSIÓN V11
 * Actualizado para API v11 con soporte de snapshots
 * 
 * CAMBIOS v11:
 * - Usa getActivePlanForLicense() para respetar snapshots
 * - Implementa caché del plan activo
 * - Respeta timing y límites del plan
 * - Timeouts configurables según plan
 */

if (!defined('ABSPATH')) exit;

class AP_API_Client {

    private string $api_url;
    private string $license_key;
    private string $domain;
    private ?array $active_plan_cache = null;

    private const CACHE_KEY = 'ap_active_plan_v11';
    private const CACHE_DURATION = 300; // 5 minutos (reducido de 1 hora para reflejar cambios más rápido)

    public function __construct() {
        $this->api_url = rtrim(get_option('ap_api_url', AP_API_URL_DEFAULT), '/');

        // Usar sistema de encriptación para obtener la clave de licencia
        $this->license_key = AP_Encryption::get_encrypted_option('ap_license_key', '');

        $this->domain = parse_url(get_site_url(), PHP_URL_HOST) ?: '';
    }
    
    /**
     * Petición genérica a la API con timeout configurable
     */
    private function request(string $endpoint, array $data = [], string $method = 'POST', ?int $custom_timeout = null) {
        // Si la URL termina en index.php, usar query string
        $uses_query_string = strpos($this->api_url, 'index.php') !== false;

        if ($uses_query_string) {
            $url = $this->api_url . '?endpoint=' . urlencode($endpoint);
        } else {
            $url = $this->api_url . '/' . ltrim($endpoint, '/');
        }

        // Obtener timeout del plan si no se especifica
        // PERO: Evitar llamar get_active_plan si estamos generando cola para evitar recursión
        if ($custom_timeout === null) {
            $is_generating = isset($_POST['action']) && $_POST['action'] === 'ap_generate_queue';

            if ($is_generating || $endpoint === 'get-active-plan') {
                // Durante generación de cola o al obtener el plan, usar timeout por defecto
                $custom_timeout = 60;
            } else {
                // En otros casos, intentar obtener del plan
                $plan = $this->get_active_plan();
                $custom_timeout = $plan ? ($plan['api_timeout'] ?? 60) : 60;
            }
        }

        // Limitar timeout al máximo permitido
        $custom_timeout = min($custom_timeout, AP_MAX_TIMEOUT);

        
        // Preparar body base
        $body_base = [
            'license_key' => $this->license_key,
            'timestamp' => time()
        ];
        
        // Solo añadir domain si no viene ya en $data
        if (!isset($data['domain'])) {
            $body_base['domain'] = $this->domain;
        }
        
        // ⭐ NUEVO: Si existe campaign_id en el contexto global, añadirlo
        if (isset($GLOBALS['ap_current_campaign_id']) && !isset($data['campaign_id'])) {
            $body_base['campaign_id'] = $GLOBALS['ap_current_campaign_id'];
        }
        
        // ⭐ NUEVO: Si existe campaign_name en el contexto global, añadirlo
        if (isset($GLOBALS['ap_current_campaign_name']) && !isset($data['campaign_name'])) {
            $body_base['campaign_name'] = $GLOBALS['ap_current_campaign_name'];
        }
        
        // ⭐ NUEVO: Si existe batch_id en el contexto global, añadirlo (para colas)
        if (isset($GLOBALS['ap_current_batch_id']) && !isset($data['batch_id'])) {
            $body_base['batch_id'] = $GLOBALS['ap_current_batch_id'];
        }
        
        // Merge: $data tiene prioridad sobre $body_base
        $body = array_merge($body_base, $data);
        
        
        
        // LOG ÚNICO PARA DEBUG - Con ID único
        $unique_id = uniqid('req_');
        
        $args = [
            'method' => $method,
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent' => 'AutoPost/' . AP_VERSION,
                'X-License-Key' => $this->license_key
            ],
            'timeout' => $custom_timeout,
            'sslverify' => false
        ];
        
        // Para POST: body como JSON
        // Para GET: añadir parámetros a URL
        if ($method === 'POST') {
            $args['body'] = json_encode($body);
        } else {
            // Añadir parámetros a la URL para GET
            if (!empty($body)) {
                $separator = (strpos($url, '?') !== false) ? '&' : '?';
                $url .= $separator . http_build_query($body);
            }
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            $error = $response->get_error_message();
            return [
                'success' => false,
                'error' => $error
            ];
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $body_response = wp_remote_retrieve_body($response);
        
        
        if ($http_code !== 200) {
            // Mensaje específico para 404
            if ($http_code === 404) {


                return [
                    'success' => false,
                    'error' => "API no encontrada (404). Verifica que los archivos estén en: {$this->api_url}"
                ];
            }


            // Intentar parsear el error JSON
            $error_data = json_decode($body_response, true);
            $error_msg = isset($error_data['error']) ? $error_data['error'] : "HTTP {$http_code}";

            // ⭐ DETECTAR LÍMITE DE TOKENS EXCEDIDO
            if ($http_code === 401 && stripos($error_msg, 'Token limit exceeded') !== false) {
                // Intentar obtener datos actualizados de uso mediante get_detailed_stats
                $renewal_date = 'fecha no disponible';
                $tokens_used_raw = null;
                $tokens_limit_raw = null;

                // 1. Intentar obtener datos de stats (más actualizado y completo)
                $stats_response = $this->get_detailed_stats(date('Y-m-d', strtotime('-30 days')), date('Y-m-d'));
                if ($stats_response && isset($stats_response['success']) && $stats_response['success']) {
                    $usage_data = $stats_response['data']['usage'] ?? [];
                    $tokens_used_raw = intval($usage_data['tokens_used'] ?? 0);
                    $tokens_limit_raw = intval($usage_data['tokens_limit'] ?? 0);

                    if (isset($usage_data['period_ends_at'])) {
                        $renewal_date = date('d/m/Y', strtotime($usage_data['period_ends_at']));
                    }
                }

                // 2. Fallback: Intentar obtener del error de la API
                if ($tokens_used_raw === null && isset($error_data['tokens_used'])) {
                    $tokens_used_raw = $error_data['tokens_used'];
                }
                if ($tokens_limit_raw === null && isset($error_data['tokens_limit'])) {
                    $tokens_limit_raw = $error_data['tokens_limit'];
                }
                if ($renewal_date === 'fecha no disponible' && isset($error_data['period_ends_at'])) {
                    $renewal_date = date('d/m/Y', strtotime($error_data['period_ends_at']));
                }

                // 3. Último fallback: Intentar obtener del plan cacheado
                if ($tokens_used_raw === null || $tokens_limit_raw === null) {
                    $plan = $this->get_active_plan();
                    if ($plan) {
                        if ($tokens_used_raw === null && isset($plan['usage']['tokens_used'])) {
                            $tokens_used_raw = $plan['usage']['tokens_used'];
                        }
                        if ($tokens_limit_raw === null && isset($plan['limits']['tokens_per_month'])) {
                            $tokens_limit_raw = $plan['limits']['tokens_per_month'];
                        }
                        if ($renewal_date === 'fecha no disponible' && isset($plan['period_ends_at'])) {
                            $renewal_date = date('d/m/Y', strtotime($plan['period_ends_at']));
                        }
                    }
                }

                // Convertir tokens a créditos (1 crédito = 10,000 tokens)
                $credits_used_raw = $tokens_used_raw !== null ? floor($tokens_used_raw / 10000) : null;
                $credits_limit_raw = $tokens_limit_raw !== null ? floor($tokens_limit_raw / 10000) : null;

                // Formatear para mostrar
                $credits_used = $credits_used_raw !== null ? number_format($credits_used_raw, 0, ',', '.') : 'N/A';
                $credits_limit = $credits_limit_raw !== null ? number_format($credits_limit_raw, 0, ',', '.') : 'N/A';

                // Crear mensaje personalizado en español (sin emojis)
                $custom_msg = "LÍMITE DE CRÉDITOS AGOTADO\n\n";
                $custom_msg .= "Uso actual: {$credits_used} / {$credits_limit} créditos\n";
                $custom_msg .= "Fecha de renovación: {$renewal_date}\n\n";
                $custom_msg .= "Opciones disponibles:\n";
                $custom_msg .= "- Esperar hasta la fecha de renovación\n";
                $custom_msg .= "- Actualizar tu plan para obtener más créditos\n";
                $custom_msg .= "- Contactar con soporte para asistencia";

                return [
                    'success' => false,
                    'error' => $custom_msg,
                    'error_type' => 'token_limit_exceeded',
                    'tokens_used' => $tokens_used_raw,
                    'tokens_limit' => $tokens_limit_raw,
                    'renewal_date' => $renewal_date
                ];
            }

            return [
                'success' => false,
                'error' => $error_msg
            ];
        }
        
        $result = json_decode($body_response, true);
        
        if (!is_array($result)) {
            return [
                'success' => false,
                'error' => 'Respuesta inválida de la API'
            ];
        }
        
        
        return $result;
    }
    
    // ================================================================
    // NUEVOS MÉTODOS v11: PLAN ACTIVO
    // ================================================================
    
    /**
     * Obtener plan activo de la licencia (respeta snapshots)
     * Usa caché para evitar llamadas excesivas
     */
    public function get_active_plan(bool $force_refresh = false): ?array {
        // Si ya está en memoria, devolverlo
        if (!$force_refresh && $this->active_plan_cache !== null) {
            return $this->active_plan_cache;
        }
        
        // Intentar caché de WordPress solo si no estamos en AJAX generando cola
        $doing_ajax = defined('DOING_AJAX') && DOING_AJAX;
        $is_generating = isset($_POST['action']) && $_POST['action'] === 'ap_generate_queue';
        
        if (!$force_refresh && !$is_generating) {
            $cached = get_transient(self::CACHE_KEY);
            if ($cached !== false) {
                $this->active_plan_cache = $cached;
                return $cached;
            }
        }
        
        // Llamar a la API
        
        $result = $this->request('get-active-plan', [], 'GET');
        
        
        if (isset($result['success']) && $result['success']) {
            // La API v11 devuelve el plan en data.plan
            $plan = $result['data']['plan'] ?? $result['plan'] ?? null;
            
            if ($plan) {
                // Solo guardar en caché si NO estamos generando cola
                if (!$is_generating) {
                    set_transient(self::CACHE_KEY, $plan, self::CACHE_DURATION);
                }
                $this->active_plan_cache = $plan;
                
                
                return $plan;
            }
        }
        
        
        return null;
    }
    
    /**
     * Obtener estadísticas detalladas de uso
     */
    public function get_detailed_stats(?string $date_from = null, ?string $date_to = null): array {
        if (!$date_from) {
            $date_from = date('Y-m-d', strtotime('-30 days'));
        }
        if (!$date_to) {
            $date_to = date('Y-m-d');
        }
        
        $result = $this->request('get-license-stats', [
            'date_from' => $date_from,
            'date_to' => $date_to
        ]);
        
        if (isset($result['success']) && $result['success']) {
            return [
                'success' => true,
                'data' => $result['data']
            ];
        }
        
        return [
            'success' => false,
            'error' => $result['error'] ?? 'Error obteniendo estadísticas'
        ];
    }
    
    /**
     * Limpiar caché del plan (llamar después de renovar)
     */
    public function clear_plan_cache(): void {
        delete_transient(self::CACHE_KEY);
        $this->active_plan_cache = null;
    }
    
    /**
     * Verificar si se puede ejecutar una acción según límites del plan
     */
    public function can_execute(string $action, int $count = 1): array {
        $plan = $this->get_active_plan();
        
        if (!$plan) {
            return [
                'can_execute' => false,
                'error' => 'No se pudo obtener el plan activo'
            ];
        }
        
        // Por ahora solo validamos que tengamos el plan
        // TODO: Implementar validación de límites cuando la API lo soporte
        
        return ['can_execute' => true];
    }
    
    /**
     * Obtener delay entre posts según el plan
     */
    public function get_post_delay(): int {
        // Durante generación de cola, no usar cache para evitar recursión
        $is_generating = isset($_POST['action']) && $_POST['action'] === 'ap_generate_queue';
        
        if ($is_generating) {
            return 30; // Valor por defecto solo durante GENERACIÓN
        }
        
        $plan = $this->get_active_plan();

        // CORRECCIÓN: La API devuelve los campos directamente, no dentro de 'timing'
        $delay = $plan ? ($plan['post_generation_delay'] ?? 30) : 30;

        // SIEMPRE loguear el delay obtenido


        return $delay;
    }
    
    /**
     * Obtener máximo de reintentos según el plan
     */
    public function get_max_retries(): int {
        // Durante generación de cola, no usar cache para evitar recursión
        $is_generating = isset($_POST['action']) && $_POST['action'] === 'ap_generate_queue';

        if ($is_generating) {
            return AP_MAX_RETRIES; // Valor por defecto durante generación
        }

        $plan = $this->get_active_plan();
        // CORRECCIÓN: La API devuelve los campos directamente, no dentro de 'timing'
        return $plan ? ($plan['max_retries'] ?? AP_MAX_RETRIES) : AP_MAX_RETRIES;
    }

    /**
     * Obtener máximo de posts por campaña según el plan
     *
     * @return int Número máximo de posts por campaña (-1 = ilimitado, 100 por defecto)
     */
    public function get_max_posts_per_campaign(): int {
        // Durante generación de cola, no usar cache para evitar recursión
        $is_generating = isset($_POST['action']) && $_POST['action'] === 'ap_generate_queue';

        if ($is_generating) {
            return 100; // Valor por defecto solo durante GENERACIÓN
        }

        $plan = $this->get_active_plan();

        // CORRECCIÓN: La API devuelve max_posts_per_campaign directamente, no dentro de 'limits'
        $max_posts = $plan ? ($plan['max_posts_per_campaign'] ?? 100) : 100;

        // SIEMPRE loguear el límite obtenido


        return $max_posts;
    }
    
    // ================================================================
    // ENDPOINTS CORREGIDOS
    // ================================================================
    
    /**
     * Verificar licencia
     */
    public function verify_license(): array {
        $result = $this->request('verify', []);
        
        // Si la verificación es exitosa, limpiar caché del plan
        if (isset($result['success']) && $result['success']) {
            $this->clear_plan_cache();
        }
        
        return $result;
    }
    
    /**
     * Obtener información de uso de la licencia
     */
    public function get_usage_info(): array {
        // Primero intentar obtener el plan activo
        $plan = $this->get_active_plan();
        
        $result = $this->request('usage', []);
        
        if (isset($result['success']) && $result['success']) {
            $data = $result['data'] ?? [];
            $usage = $data['usage'] ?? [];
            $limits = $data['limits'] ?? [];
            $plan_data = $data['plan'] ?? [];
            
            // Si tenemos plan activo, usar sus límites
            if ($plan) {
                $limits = $plan['limits'] ?? $limits;
                $plan_data = [
                    'name' => $plan['name'] ?? $plan_data['name'] ?? 'N/A',
                    'price' => $plan['price'] ?? 0
                ];
            }
            
            return [
                'success' => true,
                'data' => [
                    'plan_name' => $plan_data['name'] ?? 'N/A',
                    'plan_price' => $plan_data['price'] ?? 0,
                    'tokens_limit' => $data['tokens_limit'] ?? $limits['tokens_per_month'] ?? 0,
                    'tokens_used' => $data['tokens_used'] ?? $usage['tokens_this_month'] ?? 0,
                    'posts_limit' => $data['posts_limit'] ?? 0,
                    'posts_used' => $data['posts_used'] ?? 0,
                    'is_snapshot' => isset($plan['_is_snapshot'])
                ]
            ];
        }
        
        return [
            'success' => false,
            'error' => $result['error'] ?? 'Error obteniendo información de uso'
        ];
    }
    
    /**
     * 1. Generar descripción de empresa
     * CORREGIDO: usa generate-meta con type=company_description
     */
    public function generate_company_description(string $domain, ?string $campaign_id = null, ?string $batch_id = null, ?string $campaign_name = null): array {
        $data = [
            'type' => 'company_description',
            'domain' => $domain
        ];
        
        // Añadir tracking data si está disponible
        if ($campaign_id) {
            $data['campaign_id'] = $campaign_id;
        }
        if ($batch_id) {
            $data['batch_id'] = $batch_id;
        }
        if ($campaign_name) {
            $data['campaign_name'] = $campaign_name;
        }
        
        $result = $this->request('generate-meta', $data);
        
        if (isset($result['success']) && $result['success']) {
            $data = $result['data'] ?? [];
            return [
                'success' => true,
                'description' => $data['description'] ?? $data['content'] ?? '',
                'tokens_used' => $data['usage']['total_tokens'] ?? 0
            ];
        }
        
        return [
            'success' => false,
            'error' => $result['error'] ?? 'Error generando descripción'
        ];
    }
    
    /**
     * 2. Generar prompt de títulos
     * CORREGIDO: usa generate-meta con type=title_prompt
     */
    public function generate_title_prompt(string $niche, string $description, $keywords = [], ?string $campaign_id = null, ?string $campaign_name = null, ?string $batch_id = null): array {
        $data = [
            'type' => 'title_prompt',
            'niche' => $niche,
            'company_description' => $description,
            'keywords_seo' => $keywords
        ];
        
        if ($campaign_id) $data['campaign_id'] = $campaign_id;
        if ($campaign_name) $data['campaign_name'] = $campaign_name;
        if ($batch_id) $data['batch_id'] = $batch_id;
        
        $result = $this->request('generate-meta', $data);
        
        if (isset($result['success']) && $result['success']) {
            $data = $result['data'] ?? [];
            return [
                'success' => true,
                'prompt' => $data['prompt'] ?? $data['content'] ?? '',
                'tokens_used' => $data['usage']['total_tokens'] ?? 0
            ];
        }
        
        return [
            'success' => false,
            'error' => $result['error'] ?? 'Error generando prompt'
        ];
    }
    
    /**
     * 4b. Generar prompt personalizado para contenido
     */
    public function generate_content_prompt($niche, $description, $keywords = [], $campaign_id = null, $campaign_name = null, $batch_id = null) {
        $data = [
            'niche' => $niche,
            'company_desc' => $description,
            'keywords_seo' => $keywords
        ];
        
        if ($campaign_id) $data['campaign_id'] = $campaign_id;
        if ($campaign_name) $data['campaign_name'] = $campaign_name;
        if ($batch_id) $data['batch_id'] = $batch_id;
        
        $result = $this->request('generate-content-prompt', $data);
        
        if (isset($result['success']) && $result['success']) {
            $data = $result['data'] ?? [];
            return [
                'success' => true,
                'prompt' => $data['prompt'] ?? $data['content'] ?? '',
                'tokens_used' => $data['usage']['total_tokens'] ?? 0
            ];
        }
        
        return [
            'success' => false,
            'error' => $result['error'] ?? 'Error generando prompt de contenido'
        ];
    }
    
    /**
     * 3. Generar keywords SEO
     * CORREGIDO: usa generate-keywords con type=seo
     */
    public function generate_seo_keywords(string $niche, string $description, ?string $campaign_id = null, ?string $campaign_name = null, ?string $batch_id = null): array {
        $data = [
            'type' => 'seo',
            'niche' => $niche,
            'company_description' => $description
        ];
        
        if ($campaign_id) $data['campaign_id'] = $campaign_id;
        if ($campaign_name) $data['campaign_name'] = $campaign_name;
        if ($batch_id) $data['batch_id'] = $batch_id;
        
        $result = $this->request('generate-keywords', $data);
        
        if (isset($result['success']) && $result['success']) {
            $data = $result['data'] ?? [];
            return [
                'success' => true,
                'keywords' => $data['keywords'] ?? '',
                'tokens_used' => $data['usage']['total_tokens'] ?? 0
            ];
        }
        
        return [
            'success' => false,
            'error' => $result['error'] ?? 'Error generando keywords'
        ];
    }
    
    /**
     * 4. Generar título individual
     * CORRECTO: generate-title ya existe en la API
     */
    public function generate_post_title($prompt, $description, $keywords, $retry_attempt = 0) {
        $result = $this->request('generate-title', [
            'prompt' => $prompt,
            'company_description' => $description,
            'keywords_seo' => $keywords,
            'retry' => $retry_attempt
        ]);
        
        if (isset($result['success']) && $result['success']) {
            $data = $result['data'] ?? [];
            return [
                'success' => true,
                'title' => $data['title'] ?? $result['title'] ?? '',
                'tokens_used' => $data['usage']['total_tokens'] ?? 0
            ];
        }
        
        return [
            'success' => false,
            'error' => $result['error'] ?? 'Error generando título'
        ];
    }
    
    /**
     * 5. Generar keywords para imágenes
     * NUEVO: usa generate/keywords-imagenes con dynamic_prompt
     */
    public function generate_image_keywords($title, $dynamic_prompt = '') {
        // Si no hay dynamic_prompt, no podemos generar keywords
        if (empty($dynamic_prompt)) {
            return [
                'success' => false,
                'error' => 'dynamic_prompt es requerido'
            ];
        }

        $result = $this->request('generate/keywords-imagenes', [
            'title' => $title,
            'dynamic_prompt' => $dynamic_prompt
        ]);

        if (isset($result['success']) && $result['success']) {
            return [
                'success' => true,
                'keywords' => $result['keywords'] ?? '',
                'tokens_used' => $result['tokens_used'] ?? 0
            ];
        }

        return [
            'success' => false,
            'error' => $result['error'] ?? 'Error generando keywords de imágenes'
        ];
    }
    
    /**
     * 5b. Generar keywords para imágenes A NIVEL DE CAMPAÑA
     * NUEVO: usa generate-image-keys-campaign (sin título, genérico)
     */
    public function generate_campaign_image_keywords($niche, $description, $campaign_id = null, $campaign_name = null, $batch_id = null, $seo_keywords = '') {
        $data = [
            'niche' => $niche,
            'company_description' => $description,
            'keywords_seo' => $seo_keywords
        ];
        
        if ($campaign_id) $data['campaign_id'] = $campaign_id;
        if ($campaign_name) $data['campaign_name'] = $campaign_name;
        if ($batch_id) $data['batch_id'] = $batch_id;
        
        $result = $this->request('generate-image-keys-campaign', $data);
        
        if (isset($result['success']) && $result['success']) {
            $data = $result['data'] ?? [];
            return [
                'success' => true,
                'keywords' => $data['keywords'] ?? '',
                'tokens_used' => $data['usage']['total_tokens'] ?? 0
            ];
        }
        
        return [
            'success' => false,
            'error' => $result['error'] ?? 'Error generando keywords de imágenes de campaña'
        ];
    }
    
    /**
     * 6. Generar contenido del post
     * CORREGIDO: usa generate-post (no generate-content)
     */
    public function generate_post_content($title, $niche, $description, $keywords, $custom_prompt = '') {
        $result = $this->request('generate-post', [
            'title' => $title,
            'niche' => $niche,
            'company_description' => $description,
            'keywords_seo' => $keywords,
            'custom_prompt' => $custom_prompt
        ]);
        
        if (isset($result['success']) && $result['success']) {
            $data = $result['data'] ?? [];
            return [
                'success' => true,
                'content' => $data['content'] ?? $result['content'] ?? '',
                'tokens_used' => $data['usage']['total_tokens'] ?? 0
            ];
        }
        
        return [
            'success' => false,
            'error' => $result['error'] ?? 'Error generando contenido'
        ];
    }
    
    /**
     * 7. Generar post completo (título + contenido)
     * NUEVO: usa generate-complete
     */
    public function generate_complete_post($niche, $description, $keywords, $config = []) {
        $result = $this->request('generate-complete', [
            'niche' => $niche,
            'company_description' => $description,
            'keywords_seo' => $keywords,
            'config' => $config
        ]);
        
        if (isset($result['success']) && $result['success']) {
            $data = $result['data'] ?? [];
            return [
                'success' => true,
                'title' => $data['title'] ?? $result['title'] ?? '',
                'content' => $data['content'] ?? $result['content'] ?? '',
                'tokens_used' => $data['usage']['total_tokens'] ?? 0
            ];
        }
        
        return [
            'success' => false,
            'error' => $result['error'] ?? 'Error generando post completo'
        ];
    }
    
    /**
     * Obtener configuración de la API
     */
    public function get_api_settings(): array {
        $result = $this->request('get-settings', []);

        if (isset($result['success']) && $result['success']) {
            return [
                'success' => true,
                'data' => $result['data'] ?? []
            ];
        }

        return [
            'success' => false,
            'error' => $result['error'] ?? 'Error obteniendo configuración'
        ];
    }

    /**
     * Decide estilo visual - Analiza el negocio y genera descripciones contextualizadas de estilos
     *
     * @param string $niche
     * @param string $company_desc
     * @return array ['success' => bool, 'styles' => array, 'tokens_used' => int]
     */
    public function decide_estilo(string $niche, string $company_desc): array {
        $result = $this->request('generate/decide-estilo', [
            'niche' => $niche,
            'company_description' => $company_desc
        ]);

        if (isset($result['success']) && $result['success']) {
            return [
                'success' => true,
                'styles' => $result['styles'] ?? [],
                'tokens_used' => $result['tokens_used'] ?? 0
            ];
        }

        return [
            'success' => false,
            'error' => $result['error'] ?? 'Error analizando estilos visuales'
        ];
    }

    /**
     * Genera prompt dinámico para keywords de imagen basado en negocio y estilo seleccionado
     *
     * @param string $company_desc
     * @param string $niche
     * @param string $image_style_selected
     * @return array ['success' => bool, 'dynamic_prompt' => string, 'tokens_used' => int]
     */
    public function generate_image_prompt(string $company_desc, string $niche, string $image_style_selected): array {
        $result = $this->request('generate/image-prompt', [
            'company_description' => $company_desc,
            'niche' => $niche,
            'image_style_selected' => $image_style_selected,
            // title se añadirá como placeholder {{title}} en el prompt
            'title' => '{{title}}'
        ]);

        if (isset($result['success']) && $result['success']) {
            return [
                'success' => true,
                'dynamic_prompt' => $result['dynamic_prompt'] ?? '',
                'tokens_used' => $result['tokens_used'] ?? 0
            ];
        }

        return [
            'success' => false,
            'error' => $result['error'] ?? 'Error generando prompt dinámico de imagen'
        ];
    }
}