<?php
if (!defined('ABSPATH')) exit;

class AP_Queue_Generator {
    
    private $campaign_id;
    private $campaign;
    private $max_retries = 3;
    
    public function __construct($campaign_id) {
        global $wpdb;
        $this->campaign_id = $campaign_id;
        $this->campaign = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}ap_campaigns WHERE id = %d", $campaign_id)
        );
    }
    
    public function generate() {
        if (!$this->campaign) {
            return ['success' => false, 'message' => 'Campaña no encontrada'];
        }
        
        // ⭐ VALIDACIÓN: Nombre obligatorio
        if (empty($this->campaign->name) || trim($this->campaign->name) === '') {
            return [
                'success' => false,
                'message' => 'ERROR: Debes dar un NOMBRE a la campaña antes de generar la cola. Por favor, guarda un nombre en el campo "Nombre de campaña".'
            ];
        }
        
        // ⭐ GENERAR batch_id único para esta ejecución de cola
        // Formato: queue_campaignID_timestamp (ej: queue_113_1762517564)
        $batch_id = 'queue_' . $this->campaign_id . '_' . time();
        
        // ⭐ ESTABLECER campaign_id, campaign_name Y batch_id en variables globales
        // ✅ Usar SOLO el ID numérico
        $GLOBALS['ap_current_campaign_id'] = (string)$this->campaign_id;
        $GLOBALS['ap_current_campaign_name'] = $this->campaign->name;
        $GLOBALS['ap_current_batch_id'] = $batch_id;
        
        
        // Validar num_posts
        if (empty($this->campaign->num_posts) || $this->campaign->num_posts < 1) {
            // ⭐ LIMPIAR variables globales antes de salir
            unset($GLOBALS['ap_current_campaign_id'], $GLOBALS['ap_current_campaign_name'], $GLOBALS['ap_current_batch_id']);
            return ['success' => false, 'message' => 'Debes especificar el número de posts antes de generar la cola'];
        }
        
        // Validar campos requeridos
        if (empty($this->campaign->prompt_titles)) {
            // ⭐ LIMPIAR variables globales antes de salir
            unset($GLOBALS['ap_current_campaign_id'], $GLOBALS['ap_current_campaign_name'], $GLOBALS['ap_current_batch_id']);
            return ['success' => false, 'message' => 'Falta prompt_titles en la campaña'];
        }
        
        if (empty($this->campaign->company_desc)) {
            // ⭐ LIMPIAR variables globales antes de salir
            unset($GLOBALS['ap_current_campaign_id'], $GLOBALS['ap_current_campaign_name'], $GLOBALS['ap_current_batch_id']);
            return ['success' => false, 'message' => 'Falta company_desc en la campaña'];
        }
        
        global $wpdb;
        
        // Verificar cuántos posts ya existen en la cola
        $existing_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ap_queue WHERE campaign_id = %d",
            $this->campaign_id
        ));
        
        $total_needed = $this->campaign->num_posts;
        $to_generate = $total_needed - $existing_count;
        
        if ($to_generate <= 0) {
            return [
                'success' => true,
                'message' => "La cola ya está completa con {$existing_count} posts",
                'created' => 0,
                'expected' => $total_needed,
                'missing' => 0,
                'errors' => 0
            ];
        }
        
        
        // TAMAÑO DE LOTE INTELIGENTE
        // Ya que ahora generamos títulos UNO a UNO, los "lotes" son para agrupar en BD
        if ($to_generate <= 5) {
            $batch_size = 2; // Lotes pequeños de 2
        } elseif ($to_generate <= 15) {
            $batch_size = 3; // Lotes medianos de 3
        } else {
            $batch_size = 5; // Lotes grandes de 5
        }
        
        $all_titles = [];
        $created = 0;
        $errors = 0;
        $error_messages = [];
        
        // Obtener títulos existentes para evitar duplicados
        $existing_titles = $wpdb->get_col($wpdb->prepare(
            "SELECT title FROM {$wpdb->prefix}ap_queue WHERE campaign_id = %d",
            $this->campaign_id
        ));
        
        $total_batches = ceil($to_generate / $batch_size);
        
        for ($batch_num = 0; $batch_num < $total_batches; $batch_num++) {
            $posts_in_this_batch = min($batch_size, $to_generate - count($all_titles));
            
            
            // Renovar bloqueo cada lote
            AP_Bloqueo_System::renew('generate', $this->campaign_id);
            
            try {
                // Generar títulos para este lote
                $batch_result = $this->generate_batch_titles(
                    $posts_in_this_batch, 
                    array_merge($existing_titles, $all_titles)
                );
                
                if (!$batch_result['success']) {
                    $errors += $posts_in_this_batch;
                    $error_messages[] = "Lote " . ($batch_num + 1) . ": " . ($batch_result['message'] ?? 'Error desconocido');
                    continue;
                }
                
                $batch_titles = $batch_result['titles'];
                $tokens_titles = $batch_result['tokens'] ?? 0;
                
                // Insertar cada post del lote en la BD inmediatamente
                foreach ($batch_titles as $i => $title) {
                    $global_index = $existing_count + count($all_titles);
                    $result = $this->insert_queue_item($title, $global_index, $tokens_titles / count($batch_titles));
                    
                    if ($result['success']) {
                        $created++;
                        $all_titles[] = $title;
                    } else {
                        $errors++;
                        $error_messages[] = $result['message'];
                    }
                }
                
            } catch (Exception $e) {
                $errors += $posts_in_this_batch;
                $error_messages[] = "Lote " . ($batch_num + 1) . ": " . $e->getMessage();
            }
        }
        
        $total_in_queue = $existing_count + $created;
        $expected = $total_needed;
        $missing = $expected - $total_in_queue;
        
        $message = "Cola generada: {$created} posts nuevos creados";
        if ($existing_count > 0) {
            $message .= " ({$total_in_queue}/{$expected} total)";
        }
        if ($errors > 0) {
            $message .= ", {$errors} con errores";
        }
        
        if ($missing > 0) {
            $message .= ". ATENCIÓN: Aún faltan {$missing} posts. Vuelve a generar para completar.";
        }
        
        
        // ⭐ LIMPIAR variables globales al finalizar
        unset($GLOBALS['ap_current_campaign_id'], $GLOBALS['ap_current_campaign_name'], $GLOBALS['ap_current_batch_id']);
        
        return [
            'success' => $created > 0, 
            'message' => $message,
            'created' => $created,
            'total_in_queue' => $total_in_queue,
            'expected' => $expected,
            'missing' => $missing,
            'errors' => $errors,
            'error_details' => $error_messages,
            'incomplete' => $missing > 0
        ];
    }
    
    /**
     * Generar lote de títulos con contexto para evitar duplicados
     */
    private function generate_batch_titles($count, $existing_titles) {
        $api = new AP_API_Client();
        $prompt = $this->campaign->prompt_titles;
        
        // Añadir contexto de títulos existentes para evitar duplicados
        if (!empty($existing_titles)) {
            $titles_context = implode("\n- ", array_slice($existing_titles, -10)); // Últimos 10
            $prompt .= "\n\n⚠️ IMPORTANTE: Ya generé estos títulos, NO los repitas ni crees similares:\n- " . $titles_context;
        }
        
        // Añadir instrucción de generar múltiples
        $prompt .= "\n\nGenera exactamente {$count} títulos diferentes.";
        
        $titles = [];
        $total_tokens = 0;
        
        // Generar títulos UNO A UNO para tener más control
        for ($i = 0; $i < $count; $i++) {
            
            // Pasar títulos ya generados en esta sesión para evitar duplicados
            $context_prompt = $prompt;
            if (!empty($titles)) {
                $context_prompt .= "\n\nYa generé en esta sesión:\n- " . implode("\n- ", $titles);
            }
            
            $result = $api->generate_post_title(
                $context_prompt,
                $this->campaign->company_desc,
                $this->campaign->keywords_seo ?? '',
                0 // retry_attempt
            );
            
            if (!$result['success']) {

                // ⭐ DETECTAR ERROR DE TOKENS AGOTADOS - No reintentar
                if (isset($result['error_type']) && $result['error_type'] === 'token_limit_exceeded') {
                    return [
                        'success' => false,
                        'titles' => $titles,
                        'tokens' => $total_tokens,
                        'message' => $result['error'],
                        'error_type' => 'token_limit_exceeded'
                    ];
                }

                // Si falla por otra razón, intentar 1 retry
                sleep(2);
                $result = $api->generate_post_title(
                    $context_prompt,
                    $this->campaign->company_desc,
                    $this->campaign->keywords_seo ?? '',
                    1
                );

                if (!$result['success']) {
                    // Si sigue fallando, incluir el mensaje de error real
                    $error_msg = $result['error'] ?? 'Error desconocido';
                    return [
                        'success' => count($titles) > 0,
                        'titles' => $titles,
                        'tokens' => $total_tokens,
                        'message' => "Error al generar títulos: " . $error_msg,
                        'error_type' => $result['error_type'] ?? null
                    ];
                }
            }
            
            $title = trim($result['title'] ?? '');
            if (!empty($title) && strlen($title) > 10) {
                $titles[] = $title;
                $total_tokens += $result['tokens_used'] ?? 0;
                
            }
            
            // Delay entre títulos (1 segundo)
            if ($i < $count - 1) {
                sleep(1);
            }
        }
        
        return [
            'success' => true,
            'titles' => $titles,
            'tokens' => $total_tokens
        ];
    }
    
    /**
     * Insertar un item en la cola
     */
    private function insert_queue_item($title, $index, $estimated_tokens) {
        global $wpdb;
        
        // Generar keywords para imágenes
        $keywords_result = $this->generate_image_keywords($title);
        $image_keywords = '';
        
        if ($keywords_result['success']) {
            $image_keywords = $keywords_result['keywords'] ?? $keywords_result['data'] ?? '';
            
            // Limitar a 15 términos máximo
            $terms = array_map('trim', explode(',', $image_keywords));
            $terms = array_slice($terms, 0, 15);
            $image_keywords = implode(', ', $terms);
        }
        
        if (empty($image_keywords)) {
            $image_keywords = $this->campaign->keywords_seo ?? '';
            $terms = array_map('trim', explode(',', $image_keywords));
            $terms = array_slice($terms, 0, 15);
            $image_keywords = implode(', ', $terms);
        }
        
        $tokens_keywords = $keywords_result['tokens'] ?? 0;
        
        // Buscar imágenes
        $images = [
            'featured' => '',
            'featured_thumb' => '',
            'inner' => '',
            'inner_thumb' => ''
        ];
        
        // Log de debugging
        
        try {
            if (!empty($this->campaign->image_provider) && !empty($image_keywords)) {
                $offset = $index * 2;
                
                $images = AP_Image_Search::search_images($image_keywords, $this->campaign->image_provider, $offset);
                
            } else {
            }
        } catch (Exception $e) {
        }
        
        // Calcular fecha programada
        $publish_days = explode(',', $this->campaign->publish_days);
        $start_date = new DateTime($this->campaign->start_date);
        $publish_time = $this->campaign->publish_time;
        
        $scheduled_date = $this->get_next_publish_date($start_date, $publish_days, $index);
        $scheduled_datetime = $scheduled_date->format('Y-m-d') . ' ' . $publish_time;
        
        // Obtener la siguiente posición
        $next_position = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(MAX(position), 0) + 1 FROM {$wpdb->prefix}ap_queue WHERE campaign_id = %d",
            $this->campaign_id
        ));
        
        // Insertar en cola
        
        $inserted = $wpdb->insert(
            $wpdb->prefix . 'ap_queue',
            [
                'campaign_id' => $this->campaign_id,
                'position' => $next_position,
                'title' => $title,
                'image_keywords' => $image_keywords,
                'featured_image_url' => $images['featured'] ?? '',
                'featured_image_thumb' => $images['featured_thumb'] ?? '',
                'inner_image_url' => $images['inner'] ?? '',
                'inner_image_thumb' => $images['inner_thumb'] ?? '',
                'status' => 'pending',
                'tokens_estimated' => $estimated_tokens + $tokens_keywords + 1500,
                'scheduled_date' => $scheduled_datetime
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s']
        );
        
        if ($inserted) {
            return [
                'success' => true,
                'id' => $wpdb->insert_id
            ];
        } else {
            return [
                'success' => false,
                'message' => "Error insertando en BD: " . $wpdb->last_error
            ];
        }
    }
    
    private function send_progress($data) {
        // Removido - no se usa más
    }
    
    /**
     * Generar todos los títulos de una sola vez (o en lotes si son muchos)
     */
    private function generate_all_titles($num_posts) {
        // Si son más de 5, dividir en lotes para evitar truncamiento
        if ($num_posts > 5) {
            return $this->generate_titles_in_batches($num_posts);
        }
        
        $retries = 0;
        
        while ($retries < $this->max_retries) {
            // Prompt MUY CONCISO para minimizar tokens de entrada
            $prompt_modified = "Genera {$num_posts} títulos únicos para blog, numerados. " . 
                              $this->campaign->prompt_titles;
            
            $result = AP_IA_Helpers::generate_title(
                $prompt_modified,
                $this->campaign->keywords_seo,
                $this->campaign->company_desc,
                $retries
            );
            
            if ($result['success'] ?? false) {
                $raw_titles = $result['data'] ?? '';
                
                
                // Parsear los títulos
                $titles = $this->parse_titles($raw_titles, $num_posts);
                
                if (count($titles) >= $num_posts) {
                    return [
                        'success' => true,
                        'titles' => array_slice($titles, 0, $num_posts),
                        'tokens' => $result['tokens'] ?? 0
                    ];
                }
                
            }
            
            $retries++;
            sleep(2);
        }
        
        return [
            'success' => false,
            'message' => 'No se pudieron generar suficientes títulos después de ' . $this->max_retries . ' intentos'
        ];
    }
    
    /**
     * Generar títulos en lotes (para evitar truncamiento con muchos posts)
     */
    private function generate_titles_in_batches($total_posts) {
        $all_titles = [];
        $batch_size = 5; // Generar de 5 en 5
        $batches = ceil($total_posts / $batch_size);
        
        
        for ($batch = 0; $batch < $batches; $batch++) {
            $remaining = $total_posts - count($all_titles);
            $to_generate = min($batch_size, $remaining);
            
            
            $result = $this->generate_all_titles($to_generate);
            
            if (!$result['success']) {
                return [
                    'success' => false,
                    'message' => "Error en lote " . ($batch + 1) . ": " . ($result['message'] ?? 'Error desconocido')
                ];
            }
            
            $all_titles = array_merge($all_titles, $result['titles']);
            
            // Pausa entre lotes
            if ($batch < $batches - 1) {
                sleep(1);
            }
        }
        
        return [
            'success' => true,
            'titles' => array_slice($all_titles, 0, $total_posts),
            'tokens' => 0 // No podemos sumar tokens de lotes
        ];
    }
    
    /**
     * Parsear títulos desde el texto de respuesta
     * Maneja respuestas truncadas y diferentes formatos
     */
    private function parse_titles($text, $expected_count) {
        $titles = [];
        
        // Dividir por líneas
        $lines = explode("\n", $text);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Saltar líneas vacías o muy cortas
            if (strlen($line) < 10) continue;
            
            // Quitar numeración (1., 2., 1), etc.) y otros prefijos
            $patterns = [
                '/^\d+[\.\)\-\:\#]\s*/',  // 1. o 1) o 1- o 1: o 1#
                '/^[\*\-\•]\s*/',          // * o - o • (bullets)
                '/^Título\s*\d+[\:\.\-]\s*/i'  // "Título 1:" o similar
            ];
            
            $clean = $line;
            foreach ($patterns as $pattern) {
                $clean = preg_replace($pattern, '', $clean);
            }
            
            $clean = trim($clean);
            
            // Validar que sea un título válido
            // - Al menos 15 caracteres
            // - No termina con puntos suspensivos (señal de truncamiento)
            // - No es solo puntuación
            if (strlen($clean) >= 15 && 
                !preg_match('/\.\.\.$/', $clean) &&
                preg_match('/[a-zA-ZáéíóúñÁÉÍÓÚÑ]/', $clean)) {
                $titles[] = $clean;
                
            }
            
            // Si ya tenemos suficientes, parar
            if (count($titles) >= $expected_count) {
                break;
            }
        }
        
        // Log de debug si no conseguimos suficientes
        if (count($titles) < $expected_count) {
        }
        
        return $titles;
    }
    
    private function generate_image_keywords($title) {
        // Pasar las keywords de imagen base de la campaña
        $base_keywords = $this->campaign->keywords_images ?? '';
        
        $result = AP_IA_Helpers::generate_keywords_images(
            $title,
            $this->campaign->niche,
            $this->campaign->company_desc,
            $this->campaign->keywords_seo,
            $base_keywords  // Keywords de imagen de la campaña
        );
        
        return $result;
    }
    
    private function get_next_publish_date($start, $allowed_days, $index) {
        // Traducir días permitidos a formato inglés
        $allowed_days_en = $this->translate_days($allowed_days);
        
        $date = clone $start;
        $found_count = 0;
        
        // Buscar días permitidos hasta llegar al índice solicitado
        while ($found_count <= $index) {
            $current_day = $date->format('l');
            
            if (in_array($current_day, $allowed_days_en)) {
                if ($found_count === $index) {
                    return $date;
                }
                $found_count++;
            }
            
            $date->modify('+1 day');
        }
        
        return $date;
    }
    
    private function translate_days($days) {
        $map = [
            'Lunes' => 'Monday',
            'Martes' => 'Tuesday',
            'Miércoles' => 'Wednesday',
            'Jueves' => 'Thursday',
            'Viernes' => 'Friday',
            'Sábado' => 'Saturday',
            'Domingo' => 'Sunday'
        ];
        
        return array_map(function($day) use ($map) {
            return $map[$day] ?? $day;
        }, $days);
    }
}