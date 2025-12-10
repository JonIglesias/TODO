-- Migración: Renombrar columnas de precios de per_1k a per_1m
-- Y actualizar valores existentes multiplicando por 1000 (conversión de $/1K a $/1M tokens)

-- Paso 1: Agregar nuevas columnas con denominación per_1m
ALTER TABLE `api_model_prices`
  ADD COLUMN `price_input_per_1m` DECIMAL(10,2) NULL COMMENT 'Precio por millón de tokens de entrada (USD)' AFTER `model_name`,
  ADD COLUMN `price_output_per_1m` DECIMAL(10,2) NULL COMMENT 'Precio por millón de tokens de salida (USD)' AFTER `price_input_per_1m`;

-- Paso 2: Copiar y convertir datos existentes (multiplicar por 1000 para convertir $/1K a $/1M)
UPDATE `api_model_prices`
SET
  `price_input_per_1m` = `price_input_per_1k` * 1000,
  `price_output_per_1m` = `price_output_per_1k` * 1000
WHERE `price_input_per_1k` IS NOT NULL OR `price_output_per_1k` IS NOT NULL;

-- Paso 3: Eliminar columnas antiguas per_1k
ALTER TABLE `api_model_prices`
  DROP COLUMN `price_input_per_1k`,
  DROP COLUMN `price_output_per_1k`;

-- Paso 4: Desactivar precios antiguos (marcar como histórico)
UPDATE `api_model_prices` SET `is_active` = 0;

-- Paso 5: Insertar precios actualizados de Diciembre 2024 (por MILLÓN de tokens)
-- OpenAI Models
INSERT INTO `api_model_prices` (`model_name`, `price_input_per_1m`, `price_output_per_1m`, `source`, `is_active`, `updated_at`, `notes`)
VALUES
  ('gpt-4', 30.00, 60.00, 'openai_pricing_dec2024', 1, NOW(), 'Precio actualizado Dic 2024 - por MILLÓN de tokens'),
  ('gpt-4-turbo', 10.00, 30.00, 'openai_pricing_dec2024', 1, NOW(), 'Precio actualizado Dic 2024 - por MILLÓN de tokens'),
  ('gpt-4o', 2.50, 10.00, 'openai_pricing_dec2024', 1, NOW(), 'Precio actualizado Dic 2024 - por MILLÓN de tokens'),
  ('gpt-4o-mini', 0.15, 0.60, 'openai_pricing_dec2024', 1, NOW(), 'Precio actualizado Dic 2024 - por MILLÓN de tokens'),
  ('gpt-4.1', 2.00, 8.00, 'openai_pricing_dec2024', 1, NOW(), 'Nuevo modelo GPT-4.1 - Dic 2024'),
  ('gpt-3.5-turbo', 0.50, 1.50, 'openai_pricing_dec2024', 1, NOW(), 'Precio actualizado Dic 2024 - por MILLÓN de tokens');

-- Anthropic Claude Models
INSERT INTO `api_model_prices` (`model_name`, `price_input_per_1m`, `price_output_per_1m`, `source`, `is_active`, `updated_at`, `notes`)
VALUES
  ('claude-3-opus', 15.00, 75.00, 'anthropic_pricing_dec2024', 1, NOW(), 'Precio actualizado Dic 2024 - por MILLÓN de tokens'),
  ('claude-3-sonnet', 3.00, 15.00, 'anthropic_pricing_dec2024', 1, NOW(), 'Precio actualizado Dic 2024 - por MILLÓN de tokens'),
  ('claude-3-haiku', 0.25, 1.25, 'anthropic_pricing_dec2024', 1, NOW(), 'Precio actualizado Dic 2024 - por MILLÓN de tokens'),
  ('claude-3-5-sonnet', 3.00, 15.00, 'anthropic_pricing_dec2024', 1, NOW(), 'Precio actualizado Dic 2024 - por MILLÓN de tokens'),
  ('claude-3-5-haiku', 0.80, 4.00, 'anthropic_pricing_dec2024', 1, NOW(), 'Nuevo modelo Claude 3.5 Haiku - Dic 2024');
