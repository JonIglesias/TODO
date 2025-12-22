<?php
if (!defined('ABSPATH')) exit;

class AP_Image_Search {
    private static $used_images = [];
    
    /**
     * Busca imágenes y devuelve URLs (NO descarga nada)
     * Devuelve URLs de thumbnail para preview y URLs grandes para descarga posterior
     * 
     * @param string $keywords Keywords para buscar (se limitarán a 15 palabras)
     * @param string $provider Proveedor (unsplash, pixabay, pexels)
     * @param int $offset Offset para variar resultados (default: aleatorio)
     */
    public static function search_images($keywords, $provider, $offset = null) {
        // Limitar keywords a 15 palabras máximo
        $words = preg_split('/[,\s]+/', trim($keywords));
        $words = array_filter($words); // Eliminar vacíos
        $words = array_slice($words, 0, 15);
        $keywords = implode(' ', $words);
        
        
        // Si no se especifica offset, usar uno aleatorio para variar resultados
        if ($offset === null) {
            $offset = rand(0, 20);
        }
        
        $result = [
            'featured' => '',
            'featured_thumb' => '',
            'inner' => '',
            'inner_thumb' => ''
        ];
        
        // Para featured: intentar primero con provider preferido
        $featured = self::search_provider($keywords, $provider, $offset);
        
        
        // Si no hay resultado, intentar con otros proveedores
        if (!$featured) {
            $all_providers = [];
            if (get_option('ap_unsplash_key')) $all_providers[] = 'unsplash';
            if (get_option('ap_pixabay_key')) $all_providers[] = 'pixabay';
            if (get_option('ap_pexels_key')) $all_providers[] = 'pexels';
            
            foreach ($all_providers as $alt_provider) {
                if ($alt_provider === $provider) continue;
                $featured = self::search_provider($keywords, $alt_provider, $offset);
                if ($featured) break;
            }
        }
        
        if ($featured) {
            $result['featured'] = $featured['large'] ?? '';
            $result['featured_thumb'] = $featured['thumb'] ?? '';
        } else {
            // Usar imagen dummy
            $dummy_url = self::get_dummy_image();
            $result['featured'] = $dummy_url;
            $result['featured_thumb'] = $dummy_url;
        }
        
        // Para inner: buscar hasta encontrar una diferente
        $inner = null;
        $attempts = 0;
        $max_attempts = 5;
        $inner_offset = $offset + 10;
        
        while ($attempts < $max_attempts) {
            $inner = self::search_provider($keywords, $provider, $inner_offset);
            
            // Verificar que sea diferente a featured
            if ($inner && $featured && $inner['large'] !== $featured['large']) {
                break; // Encontramos una diferente
            }
            
            // Intentar con otro offset
            $inner_offset += 5;
            $attempts++;
        }
        
        // Si no hay inner con provider principal, probar otros
        if (!$inner) {
            $all_providers = [];
            if (get_option('ap_unsplash_key')) $all_providers[] = 'unsplash';
            if (get_option('ap_pixabay_key')) $all_providers[] = 'pixabay';
            if (get_option('ap_pexels_key')) $all_providers[] = 'pexels';
            
            foreach ($all_providers as $alt_provider) {
                if ($alt_provider === $provider) continue;
                $inner = self::search_provider($keywords, $alt_provider, $inner_offset);
                if ($inner && (!$featured || $inner['large'] !== $featured['large'])) {
                    break;
                }
            }
        }
        
        if ($inner) {
            $result['inner'] = $inner['large'] ?? '';
            $result['inner_thumb'] = $inner['thumb'] ?? '';
        } else {
            // Usar imagen dummy
            $dummy_url = self::get_dummy_image();
            $result['inner'] = $dummy_url;
            $result['inner_thumb'] = $dummy_url;
        }
        
        
        return $result;
    }
    
    /**
     * Generar imagen dummy SVG
     */
    private static function get_dummy_image() {
        return 'data:image/svg+xml;base64,' . base64_encode('<?xml version="1.0" encoding="UTF-8"?>
<svg width="800" height="600" xmlns="http://www.w3.org/2000/svg">
  <rect width="800" height="600" fill="#f0f0f0"/>
  <text x="400" y="280" font-family="Arial, sans-serif" font-size="24" fill="#666" text-anchor="middle" font-weight="bold">Sin resultados de imagen</text>
  <text x="400" y="320" font-family="Arial, sans-serif" font-size="18" fill="#999" text-anchor="middle">Modifica las Keywords</text>
  <text x="400" y="350" font-family="Arial, sans-serif" font-size="18" fill="#999" text-anchor="middle">de búsqueda de imagen</text>
</svg>');
    }
    
    public static function search_provider($keywords, $provider, $offset = 0) {
        $method = "search_{$provider}";
        if (method_exists(__CLASS__, $method)) {
            return self::$method($keywords, $offset);
        }
        return null;
    }
    
    private static function search_unsplash($keywords, $offset = 0) {
        $key = get_option('ap_unsplash_key');
        if (!$key) return null;
        
        // Calcular página basándose en offset (30 resultados por página)
        $page = floor($offset / 30) + 1;
        $index_in_page = $offset % 30;
        
        // Filtrar por orientación landscape y ordenar por relevancia
        $response = wp_remote_get("https://api.unsplash.com/search/photos?query=" . urlencode($keywords) . "&per_page=30&page={$page}&orientation=landscape&order_by=relevant", [
            'headers' => ['Authorization' => 'Client-ID ' . $key],
            'timeout' => 15
        ]);
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        if ($code === 403) {
            return null;
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        $results = $data['results'] ?? [];
        
        if (empty($results)) {
            return null;
        }
        
        // Tomar la imagen en el índice específico, o una aleatoria si el índice no existe
        if (isset($results[$index_in_page])) {
            $img = $results[$index_in_page];
        } else {
            // Si el offset es mayor que los resultados, tomar una aleatoria
            $img = $results[array_rand($results)];
        }
        
        $large = $img['urls']['regular'] ?? '';
        $thumb = $img['urls']['thumb'] ?? '';
        
        if ($large) {
            // Registrar como usada
            if (!in_array($large, self::$used_images)) {
                self::$used_images[] = $large;
            }
            
            return [
                'large' => $large,
                'thumb' => $thumb
            ];
        }
        
        return null;
    }
    
    private static function search_pixabay($keywords, $offset = 0) {
        $key = get_option('ap_pixabay_key');
        if (!$key) return null;
        
        // Calcular página (20 resultados por página en Pixabay)
        $page = floor($offset / 20) + 1;
        $index_in_page = $offset % 20;
        
        // Ordenar por popularidad (relevancia), filtrar contenido seguro y solo fotos
        $response = wp_remote_get("https://pixabay.com/api/?key={$key}&q=" . urlencode($keywords) . "&per_page=20&page={$page}&order=popular&safesearch=true&image_type=photo", [
            'timeout' => 15
        ]);
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        $hits = $data['hits'] ?? [];
        
        if (empty($hits)) {
            return null;
        }
        
        // Tomar imagen en índice específico o aleatoria
        if (isset($hits[$index_in_page])) {
            $img = $hits[$index_in_page];
        } else {
            $img = $hits[array_rand($hits)];
        }
        
        $large = $img['largeImageURL'] ?? '';
        $thumb = $img['previewURL'] ?? '';
        
        if ($large) {
            if (!in_array($large, self::$used_images)) {
                self::$used_images[] = $large;
            }
            
            return [
                'large' => $large,
                'thumb' => $thumb
            ];
        }
        
        return null;
    }
    
    private static function search_pexels($keywords, $offset = 0) {
        $key = get_option('ap_pexels_key');
        if (!$key) return null;
        
        // Calcular página (30 resultados por página)
        $page = floor($offset / 30) + 1;
        $index_in_page = $offset % 30;
        
        // Filtrar por orientación landscape para coherencia con otros providers
        $response = wp_remote_get("https://api.pexels.com/v1/search?query=" . urlencode($keywords) . "&per_page=30&page={$page}&orientation=landscape", [
            'headers' => ['Authorization' => $key],
            'timeout' => 15
        ]);
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        $photos = $data['photos'] ?? [];
        
        if (empty($photos)) {
            return null;
        }
        
        // Tomar imagen en índice específico o aleatoria
        if (isset($photos[$index_in_page])) {
            $img = $photos[$index_in_page];
        } else {
            $img = $photos[array_rand($photos)];
        }
        
        $large = $img['src']['large'] ?? '';
        $thumb = $img['src']['tiny'] ?? '';
        
        if ($large) {
            if (!in_array($large, self::$used_images)) {
                self::$used_images[] = $large;
            }
            
            return [
                'large' => $large,
                'thumb' => $thumb
            ];
        }
        
        return null;
    }
}