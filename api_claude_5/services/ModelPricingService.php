<?php
/**
 * Servicio para gestión de precios de modelos de IA
 * 
 * Los precios se pueden:
 * 1. Guardar en BD (tabla api_model_prices)
 * 2. Actualizar manualmente
 * 3. Consultar desde una API externa (futuro)
 */

defined('API_ACCESS') or die('Direct access not permitted');

class ModelPricingService {
    
    /**
     * Obtener precios actuales de modelos
     * Primero intenta desde BD, luego usa precios por defecto
     *
     * @return array Precios en USD por MILLÓN de tokens
     */
    public static function getPrices($model = null) {
        $db = Database::getInstance();

        try {
            // Intentar obtener de BD
            if ($model) {
                $price = $db->fetchOne("
                    SELECT * FROM " . DB_PREFIX . "model_prices
                    WHERE model_name = ? AND is_active = 1
                    ORDER BY updated_at DESC LIMIT 1
                ", [$model]);

                if ($price) {
                    return [
                        'input' => floatval($price['price_input_per_1k'] ?? 0),
                        'output' => floatval($price['price_output_per_1k'] ?? 0)
                    ];
                }
            }

            // Si no está en BD, obtener todos los precios por defecto
            $prices = $db->query("
                SELECT model_name, price_input_per_1k, price_output_per_1k
                FROM " . DB_PREFIX . "model_prices
                WHERE is_active = 1
            ");

            if (!empty($prices)) {
                $result = [];
                foreach ($prices as $p) {
                    $result[$p['model_name']] = [
                        'input' => floatval($p['price_input_per_1k'] ?? 0),
                        'output' => floatval($p['price_output_per_1k'] ?? 0)
                    ];
                }
                return $model ? self::getFallbackPrices($model) : $result;
            }
        } catch (Exception $e) {
            // Si hay error en BD, usar precios por defecto
        }

        // Fallback: precios hardcodeados (actualizado Dic 2024 - precios por 1.000 tokens)
        return $model ? self::getFallbackPrices($model) : self::getAllFallbackPrices();
    }
    
    /**
     * Precios por defecto actualizados manualmente
     *
     * @return array Precios en USD por 1.000 tokens (Dic 2025)
     */
    private static function getAllFallbackPrices() {
        return [
            // OpenAI Models (precios por 1K tokens)
            // GPT-4.1 Series (April 2025) - MUST be BEFORE gpt-4
            'gpt-4.1' => ['input' => 0.002, 'output' => 0.008],
            'gpt-4.1-mini' => ['input' => 0.0004, 'output' => 0.0016],
            'gpt-4.1-nano' => ['input' => 0.0002, 'output' => 0.0008],

            // GPT-4o & GPT-4 Turbo
            'gpt-4o' => ['input' => 0.0025, 'output' => 0.01],
            'gpt-4o-mini' => ['input' => 0.00015, 'output' => 0.0006],
            'gpt-4-turbo' => ['input' => 0.01, 'output' => 0.03],
            'gpt-4' => ['input' => 0.03, 'output' => 0.06],
            'gpt-3.5-turbo' => ['input' => 0.0005, 'output' => 0.0015],

            // Anthropic Claude Models (precios por 1K tokens)
            'claude-3-opus' => ['input' => 0.015, 'output' => 0.075],
            'claude-3-sonnet' => ['input' => 0.003, 'output' => 0.015],
            'claude-3-haiku' => ['input' => 0.00025, 'output' => 0.00125],
            'claude-3-5-sonnet' => ['input' => 0.003, 'output' => 0.015],
            'claude-3-5-haiku' => ['input' => 0.0008, 'output' => 0.004],
        ];
    }
    
    /**
     * Obtener precio de un modelo específico con detección de familia
     * IMPORTANTE: Ordena modelos por longitud (más específicos primero) para evitar
     * que 'gpt-4' haga match con 'gpt-4.1-2025-04-14'
     */
    private static function getFallbackPrices($model) {
        $prices = self::getAllFallbackPrices();

        // Buscar precio exacto
        if (isset($prices[$model])) {
            return $prices[$model];
        }

        // Ordenar modelos por longitud (más largos primero) para detectar más específicos
        // Esto asegura que 'gpt-4.1' se compruebe antes que 'gpt-4'
        uksort($prices, function($a, $b) {
            return strlen($b) - strlen($a);
        });

        // Detectar familia del modelo
        foreach ($prices as $modelName => $price) {
            if (strpos($model, $modelName) !== false) {
                return $price;
            }
        }

        // Precio por defecto (gpt-4o-mini) - por 1.000 tokens
        return ['input' => 0.00015, 'output' => 0.0006];
    }
    
    /**
     * Actualizar precios en BD
     *
     * @param string $model Nombre del modelo
     * @param float $inputPrice Precio por 1.000 tokens de entrada
     * @param float $outputPrice Precio por 1.000 tokens de salida
     * @param string $source Fuente de los precios (manual, api, etc.)
     */
    public static function updatePrices($model, $inputPrice, $outputPrice, $source = 'manual') {
        $db = Database::getInstance();

        // Desactivar precios anteriores de este modelo
        $db->query("
            UPDATE " . DB_PREFIX . "model_prices
            SET is_active = 0
            WHERE model_name = ?
        ", [$model]);

        // Insertar nuevo precio
        return $db->insert('model_prices', [
            'model_name' => $model,
            'price_input_per_1m' => $inputPrice,
            'price_output_per_1m' => $outputPrice,
            'source' => $source,
            'is_active' => 1,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Obtener historial de cambios de precios
     */
    public static function getPriceHistory($model, $limit = 10) {
        $db = Database::getInstance();
        
        return $db->query("
            SELECT * FROM " . DB_PREFIX . "model_prices 
            WHERE model_name = ?
            ORDER BY updated_at DESC
            LIMIT ?
        ", [$model, $limit]);
    }
}
