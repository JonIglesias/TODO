<?php
if (!defined('ABSPATH')) exit;

// Cargar helper de IA necesario para generar contenido
require_once dirname(__DIR__) . '/ver_editar_campanas/edit-ia-helpers.php';

class AP_Queue_Executor {
    
    public static function process_queue_item($queue_id) {
        global $wpdb;

        $item = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}ap_queue WHERE id = %d", $queue_id)
        );

        if (!$item) {
            return false;
        }

        if ($item->status !== 'pending') {
            return false;
        }

        // Marcar como procesando
        $wpdb->update(
            $wpdb->prefix . 'ap_queue',
            ['status' => 'processing'],
            ['id' => $queue_id],
            ['%s'],
            ['%d']
        );
        
        // Obtener campaña
        $campaign = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}ap_campaigns WHERE id = %d", $item->campaign_id)
        );

        if (!$campaign) {
            $wpdb->update(
                $wpdb->prefix . 'ap_queue',
                ['status' => 'pending'],
                ['id' => $queue_id],
                ['%s'],
                ['%d']
            );
            return false;
        }

        // Generar contenido con IA
        $content_result = AP_IA_Helpers::generate_content(
            $item->title,
            $campaign->keywords_seo,
            $campaign->company_desc,
            $campaign->post_length,
            $campaign->prompt_content ?? '' // Prompt personalizado
        );

        if (!($content_result['success'] ?? false)) {
            $wpdb->update(
                $wpdb->prefix . 'ap_queue',
                ['status' => 'pending'],
                ['id' => $queue_id],
                ['%s'],
                ['%d']
            );
            return false;
        }
        
        $content = $content_result['data'] ?? '';
        $tokens_used = $content_result['tokens'] ?? 0;
        
        // LIMPIAR CONTENIDO DE LA IA
        $content = self::clean_ai_content($content, $item->title);

        // Descargar imágenes con logs
        $featured_id = self::download_image($item->featured_image_url, [
            'keywords' => $campaign->keywords_seo,
            'title' => $item->title,
            'type' => 'featured'
        ]);

        $inner_id = self::download_image($item->inner_image_url, [
            'keywords' => $campaign->keywords_seo,
            'title' => $item->title,
            'type' => 'inner'
        ]);

        // Insertar imagen interior en el contenido HTML (antes de convertir a bloques)
        if ($inner_id) {
            $content = self::insert_inner_image_html($content, $inner_id);
        }
        
        // CONVERTIR HTML A BLOQUES DE GUTENBERG CORRECTAMENTE
        // WordPress 5.0+ usa una estructura de bloques - debemos convertir el HTML
        
        // 1. Limpiar y preparar el contenido HTML
        $content = wpautop($content); // Asegurar que hay <p> tags
        
        // 2. Convertir a bloques manualmente (WordPress no tiene función automática para esto)
        // Cada <p> se convierte en un bloque de párrafo
        // Cada <h2> se convierte en un bloque de heading
        // Las imágenes ya están como bloques (<!-- wp:image -->)

        $blocks_content = self::convert_html_to_blocks($content);

        // Crear post programado con optimización SEO
        $post_data = [
            'post_title' => $item->title,
            'post_content' => $blocks_content,
            'post_status' => 'future',
            'post_date' => $item->scheduled_date,
            'post_type' => 'post',
            'post_name' => self::generate_seo_slug($item->title, $campaign->keywords_seo), // Slug SEO
            'meta_input' => [
                // Forzar que use editor de bloques
                '_wp_block_editor_enabled' => true,
                // Indicar que NO es contenido clásico
                'classic-editor-remember' => 'block-editor'
            ]
        ];
        
        // Asignar categoría si está configurada
        if (!empty($campaign->category_id)) {
            $post_data['post_category'] = [(int)$campaign->category_id];
        }

        $post_id = wp_insert_post($post_data);


        if (is_wp_error($post_id)) {
            $wpdb->update(
                $wpdb->prefix . 'ap_queue',
                ['status' => 'pending'],
                ['id' => $queue_id],
                ['%s'],
                ['%d']
            );
            return false;
        }
        
        // OPTIMIZAR SEO DEL POST
        self::optimize_post_seo($post_id, [
            'title' => $item->title,
            'keywords' => $campaign->keywords_seo,
            'content' => $content // Contenido sin formato para excerpt
        ]);
        
        // Asignar imagen destacada
        if ($featured_id) {
            set_post_thumbnail($post_id, $featured_id);
        }

        // Actualizar cola
        $wpdb->update(
            $wpdb->prefix . 'ap_queue',
            [
                'status' => 'completed',
                'post_id' => $post_id,
                'tokens_used' => $tokens_used
            ],
            ['id' => $queue_id],
            ['%s', '%d', '%d'],
            ['%d']
        );

        return true;
    }
    
    /**
     * Insertar imagen interior en HTML
     * REGLAS:
     * 1. Hacia la MITAD del contenido
     * 2. ANTES de un H2 si existe después de la mitad
     * 3. Si no hay H2 después de mitad, ANTES de un párrafo en la mitad
     * 4. NUNCA en medio de texto o listas
     * 5. Vertical: alineada derecha, max 25% ancho
     * 6. Horizontal: 100% ancho, ratio 16:9
     */
    private static function insert_inner_image_html($content, $attachment_id) {
        if (!$attachment_id) {
            return $content;
        }

        // Obtener dimensiones
        $metadata = wp_get_attachment_metadata($attachment_id);
        $width = $metadata['width'] ?? 1200;
        $height = $metadata['height'] ?? 800;
        $is_vertical = $height > $width;
        
        $image_url = wp_get_attachment_image_url($attachment_id, 'full');
        $image_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true) ?: 'Imagen relacionada';

        // Generar HTML de imagen según orientación
        if ($is_vertical) {
            // VERTICAL: Alineada derecha, max 25% ancho (300px aprox)
            $img_html = sprintf(
                '<!-- wp:image {"id":%d,"sizeSlug":"full","linkDestination":"none","align":"right","className":"is-style-default"} -->
<figure class="wp-block-image alignright size-full is-style-default"><img src="%s" alt="%s" class="wp-image-%d" style="max-width:25%%;height:auto;"/></figure>
<!-- /wp:image -->',
                $attachment_id,
                esc_url($image_url),
                esc_attr($image_alt),
                $attachment_id
            );
        } else {
            // HORIZONTAL: 100%% ancho, recorte 16:9
            $img_html = sprintf(
                '<!-- wp:image {"id":%d,"sizeSlug":"full","linkDestination":"none","className":"is-style-default"} -->
<figure class="wp-block-image size-full is-style-default"><img src="%s" alt="%s" class="wp-image-%d" style="width:100%%;aspect-ratio:16/9;object-fit:cover;"/></figure>
<!-- /wp:image -->',
                $attachment_id,
                esc_url($image_url),
                esc_attr($image_alt),
                $attachment_id
            );
        }
        
        // ESTRATEGIA DE INSERCIÓN
        // NUNCA insertar en el último 20% del contenido
        
        // 1. Buscar todos los H2
        preg_match_all('/<h2[^>]*>.*?<\/h2>/is', $content, $h2_matches, PREG_OFFSET_CAPTURE);
        
        $content_length = strlen($content);
        $middle = $content_length / 2;
        $max_position = $content_length * 0.80; // No más allá del 80%
        
        if (!empty($h2_matches[0])) {
            // Hay H2s - buscar el primero DESPUÉS de la mitad pero ANTES del 80%
            $valid_h2s = array_filter($h2_matches[0], function($match) use ($middle, $max_position) {
                return $match[1] >= $middle && $match[1] <= $max_position;
            });
            
            if (!empty($valid_h2s)) {
                // Insertar ANTES del primer H2 válido
                $first_h2 = array_values($valid_h2s)[0];
                $h2_pos = $first_h2[1];

                $content = substr_replace($content, $img_html . "\n\n", $h2_pos, 0);

                return $content;
            }
        }
        
        // 2. No hay H2 válidos - buscar párrafos en zona válida
        preg_match_all('/<p[^>]*>.*?<\/p>/is', $content, $p_matches, PREG_OFFSET_CAPTURE);
        
        if (!empty($p_matches[0])) {
            // Buscar párrafo más cercano al 50% pero dentro de zona válida (40%-70%)
            $target = $content_length * 0.5;
            $min_position = $content_length * 0.4;
            $max_position_p = $content_length * 0.7;
            
            $best_p = null;
            $best_distance = PHP_INT_MAX;
            
            foreach ($p_matches[0] as $p_match) {
                $p_pos = $p_match[1];
                
                // Solo considerar párrafos en zona válida
                if ($p_pos >= $min_position && $p_pos <= $max_position_p) {
                    $distance = abs($p_pos - $target);
                    
                    if ($distance < $best_distance) {
                        $best_distance = $distance;
                        $best_p = $p_match;
                    }
                }
            }


            if ($best_p) {
                $content = substr_replace($content, $img_html . "\n\n", $best_p[1], 0);

                return $content;
            }
        }
        
        // 3. Fallback: insertar al 45% del contenido (nunca al final)
        $safe_position = (int)($content_length * 0.45);
        $content = substr_replace($content, "\n\n" . $img_html . "\n\n", $safe_position, 0);

        return $content;
    }
    
    /**
     * Limpiar contenido generado por IA
     * - Eliminar bloques de código (```html, etc)
     * - Eliminar títulos duplicados (H1, H2 con el título del post)
     * - Eliminar secciones de "Conclusión" genéricas
     */
    private static function clean_ai_content($content, $title) {
        // 1. ELIMINAR BLOQUES DE CÓDIGO (```html, ```css, etc)
        $content = preg_replace('/```[a-z]*\n?/i', '', $content);
        
        // 2. ELIMINAR H1 CON EL TÍTULO (exacto o muy similar)
        $title_clean = strtolower(trim($title));
        $content = preg_replace_callback(
            '/<h1[^>]*>(.*?)<\/h1>/is',
            function($matches) use ($title_clean) {
                $h1_text = strtolower(trim(strip_tags($matches[1])));
                // Si el H1 es igual o muy similar al título, eliminarlo
                similar_text($title_clean, $h1_text, $percent);
                if ($percent > 85) {
                    return ''; // Eliminar
                }
                return $matches[0]; // Mantener
            },
            $content
        );
        
        // 3. ELIMINAR PRIMER H2 SI ES IGUAL AL TÍTULO
        $first_h2_removed = false;
        $content = preg_replace_callback(
            '/<h2[^>]*>(.*?)<\/h2>/is',
            function($matches) use ($title_clean, &$first_h2_removed) {
                if ($first_h2_removed) {
                    return $matches[0]; // Ya eliminamos el primero, mantener los demás
                }
                
                $h2_text = strtolower(trim(strip_tags($matches[1])));
                similar_text($title_clean, $h2_text, $percent);

                if ($percent > 85) {
                    $first_h2_removed = true;
                    return ''; // Eliminar primer H2 duplicado
                }
                return $matches[0];
            },
            $content,
            1 // Solo verificar el primero
        );
        
        // 4. ELIMINAR SECCIÓN "CONCLUSIÓN" GENÉRICA AL FINAL
        // Buscar H2 o H3 con "Conclusión" seguido de párrafo genérico
        $content = preg_replace(
            '/<h[23][^>]*>\s*Conclusi[oó]n\s*<\/h[23]>\s*<p>.*?En (resumen|conclusión|definitiva).*?<\/p>/is',
            '',
            $content
        );
        
        // También eliminar párrafos finales muy genéricos tipo "En conclusión..."
        $content = preg_replace(
            '/<p>\s*En (conclusión|resumen|definitiva),.*?<\/p>\s*$/is',
            '',
            $content
        );
        
        // 5. LIMPIAR ESPACIOS Y SALTOS EXCESIVOS
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
        $content = trim($content);

        return $content;
    }
    
    /**
     * Convertir contenido HTML a bloques de Gutenberg
     * Método SIMPLE que respeta el HTML original
     */
    private static function convert_html_to_blocks($html) {
        $html = trim($html);
        
        // Si ya todo está en bloques, retornar
        $wp_blocks_count = preg_match_all('/<!-- wp:[a-z\/]+ -->/', $html);
        $html_tags_count = preg_match_all('/<(p|h2|h3|h4|ul|ol)[^>]*>/', $html);

        if ($wp_blocks_count > 0 && $html_tags_count === 0) {
            return $html;
        }

        // Separar HTML de bloques WP existentes (imágenes)
        $parts = preg_split('/(<!-- wp:image.*?<!-- \/wp:image -->)/s', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        
        $result = [];
        
        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) continue;
            
            // Si es un bloque WP (imagen), conservarlo tal cual
            if (preg_match('/^<!-- wp:image/', $part)) {
                $result[] = $part;
                continue;
            }
            
            // Es HTML puro - convertir elemento por elemento
            // Separar por tags principales manteniendo el HTML original
            $converted = self::wrap_html_in_blocks($part);
            $result[] = $converted;
        }

        $final = implode("\n\n", $result);

        return $final;
    }
    
    /**
     * Envolver HTML en comentarios de bloques manteniendo HTML original
     */
    private static function wrap_html_in_blocks($html) {
        $html = trim($html);
        if (empty($html)) return '';
        
        $output = '';
        
        // Procesar línea por línea para no destruir el HTML
        $lines = explode("\n", $html);
        $buffer = '';
        $in_list = false;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // IGNORAR líneas completamente vacías
            if (empty($line)) continue;


            // IGNORAR párrafos vacíos o con solo espacios
            if (preg_match('/^<p[^>]*>\s*<\/p>$/i', $line)) {
                continue;
            }

            // Detectar inicio de lista
            if (preg_match('/^<(ul|ol)/', $line)) {
                $in_list = true;
                $buffer = $line . "\n";
                continue;
            }
            
            // Detectar fin de lista
            if (preg_match('/<\/(ul|ol)>/', $line)) {
                $buffer .= $line;
                
                // Determinar si es UL u OL
                $is_ordered = strpos($buffer, '<ol') !== false;
                $class = 'wp-block-list';
                
                // Agregar clase si no existe
                if (strpos($buffer, $class) === false) {
                    $buffer = preg_replace('/<(ul|ol)/', '<$1 class="' . $class . '"', $buffer);
                }
                
                if ($is_ordered) {
                    $output .= "<!-- wp:list {\"ordered\":true} -->\n";
                    $output .= $buffer . "\n";
                    $output .= "<!-- /wp:list -->\n\n";
                } else {
                    $output .= "<!-- wp:list -->\n";
                    $output .= $buffer . "\n";
                    $output .= "<!-- /wp:list -->\n\n";
                }
                
                $buffer = '';
                $in_list = false;
                continue;
            }
            
            // Si estamos en una lista, agregar al buffer
            if ($in_list) {
                $buffer .= $line . "\n";
                continue;
            }
            
            // H2
            if (preg_match('/^<h2([^>]*)>(.*?)<\/h2>$/i', $line, $matches)) {
                $content = trim($matches[2]);
                // Solo crear bloque si hay contenido
                if (!empty($content)) {
                    $output .= "<!-- wp:heading -->\n";
                    $output .= "<h2 class=\"wp-block-heading\">" . $content . "</h2>\n";
                    $output .= "<!-- /wp:heading -->\n\n";
                }
                continue;
            }
            
            // H3
            if (preg_match('/^<h3([^>]*)>(.*?)<\/h3>$/i', $line, $matches)) {
                $content = trim($matches[2]);
                if (!empty($content)) {
                    $output .= "<!-- wp:heading {\"level\":3} -->\n";
                    $output .= "<h3 class=\"wp-block-heading\">" . $content . "</h3>\n";
                    $output .= "<!-- /wp:heading -->\n\n";
                }
                continue;
            }
            
            // H4
            if (preg_match('/^<h4([^>]*)>(.*?)<\/h4>$/i', $line, $matches)) {
                $content = trim($matches[2]);
                if (!empty($content)) {
                    $output .= "<!-- wp:heading {\"level\":4} -->\n";
                    $output .= "<h4 class=\"wp-block-heading\">" . $content . "</h4>\n";
                    $output .= "<!-- /wp:heading -->\n\n";
                }
                continue;
            }
            
            // Párrafo
            if (preg_match('/^<p([^>]*)>(.*?)<\/p>$/is', $line, $matches)) {
                $content = trim($matches[2]);
                // Solo crear bloque si hay contenido real (no solo espacios/&nbsp;)
                $text_content = trim(strip_tags($content));
                $text_content = str_replace(['&nbsp;', ' '], '', $text_content);
                
                if (!empty($text_content)) {
                    $output .= "<!-- wp:paragraph -->\n";
                    $output .= "<p>" . $content . "</p>\n";
                    $output .= "<!-- /wp:paragraph -->\n\n";
                }
                continue;
            }
            
            // Si no matchea nada pero tiene contenido, tratarlo como párrafo
            $text_content = trim(strip_tags($line));
            if (!empty($text_content)) {
                $output .= "<!-- wp:paragraph -->\n";
                $output .= "<p>" . $line . "</p>\n";
                $output .= "<!-- /wp:paragraph -->\n\n";
            }
        }
        
        return rtrim($output);
    }

    
    /**
     * Convertir contenido HTML a bloques de Gutenberg (método alternativo simplificado)
     * YA NO SE USA - WordPress lo hace automáticamente
     */
    
    /**
     * Descargar imagen y optimizarla para SEO
     * @param string $url URL de la imagen
     * @param array $seo_data Datos SEO: ['keywords' => '', 'title' => '', 'type' => 'featured|inner']
     * @return int|false ID del attachment o false si falla
     */
    private static function download_image($url, $seo_data = []) {
        if (empty($url)) {
            return false;
        }

        // Cargar funciones necesarias de WordPress
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Descargar imagen a archivo temporal
        $tmp = download_url($url, 300);

        if (is_wp_error($tmp)) {
            return false;
        }

        // GENERAR NOMBRE SEO-FRIENDLY
        $filename = self::generate_seo_filename($seo_data);
        
        // Preparar array de archivo
        $file_array = [
            'name' => $filename,
            'tmp_name' => $tmp
        ];

        // Subir a mediateca
        $attachment_id = media_handle_sideload($file_array, 0);

        if (is_wp_error($attachment_id)) {
            @unlink($file_array['tmp_name']);
            return false;
        }

        // OPTIMIZAR SEO DE LA IMAGEN
        self::optimize_image_seo($attachment_id, $seo_data);

        return $attachment_id;
    }
    
    /**
     * Generar nombre de archivo SEO-friendly basado en keywords
     */
    private static function generate_seo_filename($seo_data) {
        $keywords = $seo_data['keywords'] ?? '';
        $title = $seo_data['title'] ?? '';
        $type = $seo_data['type'] ?? 'image';
        
        $filename = '';
        
        // Estrategia: keyword más relevante (primera) + parte del título
        if (!empty($keywords) && !empty($title)) {
            $keywords_array = array_map('trim', explode(',', $keywords));
            
            // Tomar la primera keyword (la más importante)
            $main_keyword = sanitize_title($keywords_array[0]);
            
            // Tomar las primeras 3-4 palabras del título
            $title_words = explode(' ', $title);
            $title_slug = sanitize_title(implode(' ', array_slice($title_words, 0, 4)));
            
            // Combinar: keyword-titulo
            $filename = $main_keyword . '-' . $title_slug;
            
        } elseif (!empty($title)) {
            // Solo título (primeras 6 palabras)
            $title_words = explode(' ', $title);
            $filename = sanitize_title(implode(' ', array_slice($title_words, 0, 6)));
            
        } elseif (!empty($keywords)) {
            // Solo keywords (primera)
            $keywords_array = array_map('trim', explode(',', $keywords));
            $filename = sanitize_title($keywords_array[0]);
        }
        
        if (empty($filename)) {
            $filename = 'imagen-post-' . time();
        }
        
        // Limitar longitud total
        if (strlen($filename) > 50) {
            $filename = substr($filename, 0, 50);
        }
        
        // Añadir extensión
        $filename .= '.jpg';

        return $filename;
    }
    
    /**
     * Optimizar metadatos SEO de la imagen
     */
    private static function optimize_image_seo($attachment_id, $seo_data) {
        $keywords = $seo_data['keywords'] ?? '';
        $title = $seo_data['title'] ?? '';
        
        if (empty($keywords) && empty($title)) {
            return;
        }
        
        // Generar ALT text optimizado (máx 125 caracteres)
        $alt_text = self::generate_alt_text($keywords, $title);
        
        // Generar title para la imagen
        $image_title = self::generate_image_title($keywords, $title);
        
        // Generar descripción
        $description = self::generate_image_description($keywords, $title);
        
        // Actualizar post de attachment
        wp_update_post([
            'ID' => $attachment_id,
            'post_title' => $image_title,
            'post_content' => $description,
            'post_excerpt' => substr($description, 0, 200) // Caption
        ]);
        
        // Actualizar ALT text
        update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);
    }

    /**
     * Generar ALT text optimizado (máx 125 caracteres)
     * Buenas prácticas SEO: usar el título del artículo o descripción natural
     */
    private static function generate_alt_text($keywords, $title) {
        if (!empty($title)) {
            // BUENA PRÁCTICA: usar el título del post (más natural)
            $alt = $title;
            
            // Limitar a 125 caracteres
            if (strlen($alt) > 125) {
                $alt = substr($alt, 0, 122) . '...';
            }
            
            return $alt;
        }
        
        if (!empty($keywords)) {
            // Fallback: usar keywords principales
            $keywords_array = array_map('trim', explode(',', $keywords));
            $main_keywords = array_slice($keywords_array, 0, 3);
            $alt = implode(', ', $main_keywords);
            
            if (strlen($alt) > 125) {
                $alt = substr($alt, 0, 122) . '...';
            }
            
            return $alt;
        }
        
        return 'Imagen relacionada con el artículo';
    }
    
    /**
     * Generar título para la imagen
     */
    private static function generate_image_title($keywords, $title) {
        if (!empty($keywords)) {
            $keywords_array = array_map('trim', explode(',', $keywords));
            return ucfirst($keywords_array[0]);
        }
        
        if (!empty($title)) {
            return substr($title, 0, 60);
        }
        
        return 'Imagen del artículo';
    }
    
    /**
     * Generar descripción para la imagen
     */
    private static function generate_image_description($keywords, $title) {
        if (!empty($keywords) && !empty($title)) {
            $keywords_array = array_map('trim', explode(',', $keywords));
            $main_keywords = array_slice($keywords_array, 0, 3);
            
            return sprintf(
                'Imagen sobre %s relacionada con %s',
                $title,
                implode(', ', $main_keywords)
            );
        }
        
        if (!empty($title)) {
            return 'Imagen relacionada con: ' . $title;
        }
        
        return 'Imagen del contenido';
    }
    
    /**
     * Generar slug SEO-friendly para el post
     */
    private static function generate_seo_slug($title, $keywords) {
        // Empezar con el título
        $slug = sanitize_title($title);
        
        // Si hay keywords, intentar incluir la principal al inicio
        if (!empty($keywords)) {
            $keywords_array = array_map('trim', explode(',', $keywords));
            $main_keyword = sanitize_title($keywords_array[0]);
            
            // Si la keyword principal no está en el slug, añadirla
            if (strpos($slug, $main_keyword) === false && strlen($main_keyword) > 3) {
                $slug = $main_keyword . '-' . $slug;
            }
        }
        
        // Limitar longitud del slug (máx 60 caracteres)
        if (strlen($slug) > 60) {
            $slug = substr($slug, 0, 57) . '...';
        }
        
        return $slug;
    }
    
    /**
     * Optimizar SEO completo del post
     */
    private static function optimize_post_seo($post_id, $data) {
        $title = $data['title'] ?? '';
        $keywords = $data['keywords'] ?? '';
        $content = $data['content'] ?? '';
        
        // 1. META DESCRIPTION (compatible con Yoast, RankMath, etc.)
        $meta_description = self::generate_meta_description($title, $keywords, $content);
        update_post_meta($post_id, '_yoast_wpseo_metadesc', $meta_description);
        update_post_meta($post_id, 'rank_math_description', $meta_description);
        update_post_meta($post_id, '_aioseop_description', $meta_description);
        
        // 2. FOCUS KEYWORD (para plugins SEO)
        if (!empty($keywords)) {
            $keywords_array = array_map('trim', explode(',', $keywords));
            $focus_keyword = $keywords_array[0];
            
            update_post_meta($post_id, '_yoast_wpseo_focuskw', $focus_keyword);
            update_post_meta($post_id, 'rank_math_focus_keyword', $focus_keyword);
        }
        
        // 3. EXCERPT optimizado
        $excerpt = self::generate_seo_excerpt($content, $keywords);
        wp_update_post([
            'ID' => $post_id,
            'post_excerpt' => $excerpt
        ]);
        
        // 4. TAGS automáticos basados en keywords
        if (!empty($keywords)) {
            self::add_seo_tags($post_id, $keywords);
        }
        
        // 5. SCHEMA.ORG JSON-LD
        $schema = self::generate_schema_markup($post_id, $title, $meta_description);
        update_post_meta($post_id, '_autopost_schema_markup', $schema);
    }

    /**
     * Generar meta description optimizada (150-160 caracteres)
     */
    private static function generate_meta_description($title, $keywords, $content) {
        // Extraer primer párrafo del contenido
        $clean_content = wp_strip_all_tags($content);
        $clean_content = preg_replace('/\s+/', ' ', $clean_content);
        
        // Si hay keywords, incluirlas naturalmente
        if (!empty($keywords)) {
            $keywords_array = array_map('trim', explode(',', $keywords));
            $main_keywords = array_slice($keywords_array, 0, 2);
            
            // Tomar primeras 100 palabras del contenido
            $words = str_word_count($clean_content, 1, 'áéíóúñü');
            $snippet = implode(' ', array_slice($words, 0, 20));
            
            // Construir description
            $description = sprintf(
                'Descubre todo sobre %s. %s',
                implode(' y ', $main_keywords),
                $snippet
            );
        } else {
            // Sin keywords, usar inicio del contenido
            $words = str_word_count($clean_content, 1, 'áéíóúñü');
            $description = implode(' ', array_slice($words, 0, 25));
        }
        
        // Limitar a 160 caracteres
        if (strlen($description) > 160) {
            $description = substr($description, 0, 157) . '...';
        }
        
        return $description;
    }
    
    /**
     * Generar excerpt SEO optimizado
     */
    private static function generate_seo_excerpt($content, $keywords) {
        $clean_content = wp_strip_all_tags($content);
        $clean_content = preg_replace('/\s+/', ' ', $clean_content);
        
        // Tomar primeras 30 palabras
        $words = str_word_count($clean_content, 1, 'áéíóúñü');
        $excerpt = implode(' ', array_slice($words, 0, 30));
        
        // Si hay keywords, mencionarlas al final
        if (!empty($keywords) && strlen($excerpt) < 100) {
            $keywords_array = array_map('trim', explode(',', $keywords));
            $main_keyword = $keywords_array[0];
            
            if (stripos($excerpt, $main_keyword) === false) {
                $excerpt .= ' Más sobre ' . $main_keyword . '.';
            }
        }
        
        return $excerpt;
    }
    
    /**
     * Añadir tags basados en keywords SEO
     */
    private static function add_seo_tags($post_id, $keywords) {
        if (empty($keywords)) return;
        
        $keywords_array = array_map('trim', explode(',', $keywords));
        
        // Tomar máximo 8 tags (no saturar)
        $tags = array_slice($keywords_array, 0, 8);
        
        // Limpiar y normalizar tags
        $tags = array_map(function($tag) {
            // Eliminar palabras muy cortas
            if (strlen($tag) < 3) return null;
            
            // Capitalizar primera letra
            return ucfirst(strtolower($tag));
        }, $tags);
        
        $tags = array_filter($tags); // Eliminar nulls
        
        if (!empty($tags)) {
            wp_set_post_tags($post_id, $tags, false);
        }
    }

    /**
     * Generar Schema.org JSON-LD para el artículo
     */
    private static function generate_schema_markup($post_id, $title, $description) {
        $post_url = get_permalink($post_id);
        $post_date = get_the_date('c', $post_id);
        $post_modified = get_the_modified_date('c', $post_id);
        
        // Obtener imagen destacada
        $image_url = get_the_post_thumbnail_url($post_id, 'full');
        if (!$image_url) {
            $image_url = get_site_icon_url();
        }
        
        // Obtener autor
        $author_id = get_post_field('post_author', $post_id);
        $author_name = get_the_author_meta('display_name', $author_id);
        
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $title,
            'description' => $description,
            'image' => $image_url,
            'datePublished' => $post_date,
            'dateModified' => $post_modified,
            'author' => [
                '@type' => 'Person',
                'name' => $author_name
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => get_bloginfo('name'),
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => get_site_icon_url()
                ]
            ],
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $post_url
            ]
        ];
        
        return wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

// Handler AJAX para ejecutar cola completa con SSE
add_action('wp_ajax_ap_execute_queue', 'ap_execute_queue_ajax');
function ap_execute_queue_ajax() {
    // ⭐ ESTABLECER CONTEXTO DE CAMPAÑA
    $campaign_id = intval($_GET['campaign_id'] ?? 0);
    
    if ($campaign_id > 0) {
        global $wpdb;
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT id, name FROM {$wpdb->prefix}ap_campaigns WHERE id = %d",
            $campaign_id
        ));
        
        if ($campaign) {
            // ✅ Usar SOLO el ID numérico, sin prefijo 'campaign_'
            $GLOBALS['ap_current_campaign_id'] = (string)$campaign->id;
            $GLOBALS['ap_current_campaign_name'] = $campaign->name ?? 'Sin nombre';
            // ✅ Generar batch_id para operaciones de content
            $GLOBALS['ap_current_batch_id'] = 'content_' . $campaign->id . '_' . time();
        }
    }

    // Obtener timeout del plan
    $api = new AP_API_Client();
    $plan = $api->get_active_plan();
    $timeout = 600; // Fallback por defecto
    
    if ($plan && isset($plan['timing']['api_timeout'])) {
        // Para ejecución usamos más tiempo: timeout del plan x 5 (para múltiples posts)
        $timeout = max(600, (int)$plan['timing']['api_timeout'] * 5);
    }
    
    @set_time_limit($timeout);
    @ini_set('max_execution_time', $timeout);
    
    if (!current_user_can('manage_options')) {
        wp_die('Permisos insuficientes');
    }
    
    if (!wp_verify_nonce($_GET['nonce'] ?? '', 'ap_nonce')) {
        wp_die('Nonce inválido');
    }
    
    // Ya obtuvimos campaign_id arriba
    if (!$campaign_id) {
        echo "data: " . json_encode(['status' => 'error', 'message' => 'ID inválido']) . "\n\n";
        flush();
        exit;
    }
    
    // VERIFICAR SI SE PUEDE EJECUTAR
    $check = AP_Bloqueo_System::can_execute($campaign_id);
    
    if (!$check['can']) {
        echo "data: " . json_encode([
            'status' => 'error',
            'message' => $check['message']
        ]) . "\n\n";
        flush();
        exit;
    }
    
    // ADQUIRIR BLOQUEO
    if (!AP_Bloqueo_System::acquire('execute', $campaign_id)) {
        echo "data: " . json_encode([
            'status' => 'error',
            'message' => 'No se pudo adquirir bloqueo. Reintenta.'
        ]) . "\n\n";
        flush();
        exit;
    }
    
    // Configurar SSE con headers más robustos
    if (function_exists('apache_setenv')) {
        @apache_setenv('no-gzip', 1);
    }
    @ini_set('zlib.output_compression', 0);
    @ini_set('implicit_flush', 1);
    
    // Limpiar cualquier buffer previo
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    header('Connection: keep-alive');
    
    // Enviar algo inmediatamente para establecer conexión
    echo "data: " . json_encode(['status' => 'connected']) . "\n\n";
    flush();
    
    global $wpdb;
    
    $pending_items = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ap_queue WHERE campaign_id = %d AND status = 'pending' ORDER BY scheduled_date ASC",
        $campaign_id
    ));
    
    $total = count($pending_items);
    $processed = 0;
    $errors = 0;
    
    if ($total === 0) {
        AP_Bloqueo_System::release('execute');
        echo "data: " . json_encode([
            'status' => 'done',
            'message' => 'No hay posts pendientes'
        ]) . "\n\n";
        flush();
        exit;
    }
    
    // Obtener delay desde el plan activo (v11)
    $api_client = new AP_API_Client();
    $delay = $api_client->get_post_delay(); // Obtiene delay del plan activo

    try {
        foreach ($pending_items as $index => $item) {
            $current = $index + 1;
            
            // Notificar inicio de procesamiento de este item
            echo "data: " . json_encode([
                'queue_id' => $item->id,
                'item_status' => 'processing',
                'current' => $current,
                'total' => $total,
                'spinner_text' => 'Generando contenido con IA...'
            ]) . "\n\n";
            flush();
            
            try {
                $result = AP_Queue_Executor::process_queue_item($item->id);
                
                if ($result) {
                    $processed++;
                    // Notificar completado
                    echo "data: " . json_encode([
                        'queue_id' => $item->id,
                        'item_status' => 'completed'
                    ]) . "\n\n";
                } else {
                    $errors++;
                    // Notificar error
                    echo "data: " . json_encode([
                        'queue_id' => $item->id,
                        'item_status' => 'error'
                    ]) . "\n\n";
                }
            } catch (Exception $e) {
                $errors++;

                echo "data: " . json_encode([
                    'queue_id' => $item->id,
                    'item_status' => 'error',
                    'error_message' => $e->getMessage()
                ]) . "\n\n";
            }
            
            flush();
            
            // Esperar entre posts según configuración de la API
            if ($current < $total) {
                sleep($delay);
            }
        }
    } catch (Exception $e) {
        // Error fatal en el loop
        AP_Bloqueo_System::release('execute');
        
        echo "data: " . json_encode([
            'status' => 'error',
            'message' => 'Error fatal: ' . $e->getMessage(),
            'processed' => $processed,
            'errors' => $errors,
            'remaining' => $total - $processed - $errors
        ]) . "\n\n";
        flush();
        exit;
    }
    
    // LIBERAR BLOQUEO
    AP_Bloqueo_System::release('execute');
    
    $remaining = $total - $processed - $errors;
    $message = "Completado: {$processed} posts creados";
    
    if ($errors > 0) {
        $message .= ", {$errors} con errores";
    }
    
    if ($remaining > 0) {
        $message .= ". ⚠️ ATENCIÓN: {$remaining} posts quedaron pendientes. Vuelve a ejecutar para completarlos.";
    }
    
    echo "data: " . json_encode([
        'status' => 'done',
        'processed' => $processed,
        'errors' => $errors,
        'remaining' => $remaining,
        'total' => $total,
        'message' => $message
    ]) . "\n\n";
    flush();
    exit;
}

// Handler AJAX para obtener estados de la cola (polling)
add_action('wp_ajax_ap_get_queue_status', 'ap_get_queue_status_ajax');
function ap_get_queue_status_ajax() {
    check_ajax_referer('ap_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permisos insuficientes']);
    }
    
    $campaign_id = intval($_POST['campaign_id'] ?? 0);
    
    if (!$campaign_id) {
        wp_send_json_error(['message' => 'ID inválido']);
    }
    
    global $wpdb;
    $items = $wpdb->get_results($wpdb->prepare(
        "SELECT id, status FROM {$wpdb->prefix}ap_queue WHERE campaign_id = %d ORDER BY scheduled_date ASC",
        $campaign_id
    ));
    
    wp_send_json_success(['items' => $items]);
}
