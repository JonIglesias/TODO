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
                        'input' => floatval($price['price_input_per_1m']),
                        'output' => floatval($price['price_output_per_1m'])
                    ];
                }
            }

            // Si no está en BD, obtener todos los precios por defecto
            $prices = $db->query("
                SELECT model_name, price_input_per_1m, price_output_per_1m
                FROM " . DB_PREFIX . "model_prices
                WHERE is_active = 1
            ");

            if (!empty($prices)) {
                $result = [];
                foreach ($prices as $p) {
                    $result[$p['model_name']] = [
                        'input' => floatval($p['price_input_per_1m']),
                        'output' => floatval($p['price_output_per_1m'])
                    ];
                }
                return $model ? self::getFallbackPrices($model) : $result;
            }
        } catch (Exception $e) {
            // Si hay error en BD, usar precios por defecto
        }

        // Fallback: precios hardcodeados (actualizado Dic 2024 - precios por MILLÓN de tokens)
        return $model ? self::getFallbackPrices($model) : self::getAllFallbackPrices();
    }
    
    /**
     * Precios por defecto actualizados manualmente
     *
     * @return array Precios en USD por MILLÓN de tokens (Dic 2024)
     */
    private static function getAllFallbackPrices() {
        return [
            // OpenAI Models
            'gpt-4' => ['input' => 30.00, 'output' => 60.00],
            'gpt-4-turbo' => ['input' => 10.00, 'output' => 30.00],
            'gpt-4o' => ['input' => 2.50, 'output' => 10.00],
            'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
            'gpt-4.1' => ['input' => 2.00, 'output' => 8.00],
            'gpt-3.5-turbo' => ['input' => 0.50, 'output' => 1.50],

            // Anthropic Claude Models
            'claude-3-opus' => ['input' => 15.00, 'output' => 75.00],
            'claude-3-sonnet' => ['input' => 3.00, 'output' => 15.00],
            'claude-3-haiku' => ['input' => 0.25, 'output' => 1.25],
            'claude-3-5-sonnet' => ['input' => 3.00, 'output' => 15.00],
            'claude-3-5-haiku' => ['input' => 0.80, 'output' => 4.00],
        ];
    }
    
    /**
     * Obtener precio de un modelo específico con detección de familia
     */
    private static function getFallbackPrices($model) {
        $prices = self::getAllFallbackPrices();

        // Buscar precio exacto
        if (isset($prices[$model])) {
            return $prices[$model];
        }

        // Detectar familia del modelo
        foreach ($prices as $modelName => $price) {
            if (strpos($model, $modelName) !== false) {
                return $price;
            }
        }

        // Precio por defecto (gpt-4o-mini) - por MILLÓN de tokens
        return ['input' => 0.15, 'output' => 0.60];
    }
    
    /**
     * Actualizar precios en BD
     *
     * @param string $model Nombre del modelo
     * @param float $inputPrice Precio por MILLÓN de tokens de entrada
     * @param float $outputPrice Precio por MILLÓN de tokens de salida
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
