-- ============================================
-- Script SQL para recalcular costes históricos
-- IMPORTANTE: Hacer BACKUP antes de ejecutar
-- ============================================

-- Crear tabla temporal con precios correctos (por MILLÓN de tokens)
CREATE TEMPORARY TABLE temp_model_prices (
    model_pattern VARCHAR(50) PRIMARY KEY,
    price_input DECIMAL(10,2),
    price_output DECIMAL(10,2),
    priority INT -- Orden de comprobación (más específico primero)
);

-- Insertar precios ordenados por especificidad (más específicos primero)
INSERT INTO temp_model_prices VALUES
-- OpenAI (orden: más específico primero)
('gpt-4o-mini', 0.15, 0.60, 1),
('gpt-4-turbo', 10.00, 30.00, 2),
('gpt-4.1', 2.00, 8.00, 3),
('gpt-4o', 2.50, 10.00, 4),
('gpt-4', 30.00, 60.00, 5),
('gpt-3.5-turbo', 0.50, 1.50, 6),
-- Anthropic (orden: más específico primero)
('claude-3-5-sonnet', 3.00, 15.00, 7),
('claude-3-5-haiku', 0.80, 4.00, 8),
('claude-3-opus', 15.00, 75.00, 9),
('claude-3-sonnet', 3.00, 15.00, 10),
('claude-3-haiku', 0.25, 1.25, 11);

-- ============================================
-- OPCIÓN 1: Ver qué registros se actualizarían (SIN cambiar datos)
-- ============================================
SELECT
    t.id,
    t.campaign_id,
    t.batch_id,
    t.model,
    t.tokens_input,
    t.tokens_output,
    t.cost_total as costo_actual,
    ROUND(
        ((t.tokens_input / 1000000) * p.price_input) +
        ((t.tokens_output / 1000000) * p.price_output),
        8
    ) as costo_correcto,
    ROUND(
        t.cost_total - (
            ((t.tokens_input / 1000000) * p.price_input) +
            ((t.tokens_output / 1000000) * p.price_output)
        ),
        8
    ) as diferencia
FROM api_usage_tracking t
LEFT JOIN temp_model_prices p ON t.model LIKE CONCAT('%', p.model_pattern, '%')
WHERE t.model IS NOT NULL
  AND t.model != ''
  AND (t.tokens_input > 0 OR t.tokens_output > 0)
  AND p.model_pattern IS NOT NULL
  -- Filtrar solo registros con diferencia significativa
  AND ABS(
        t.cost_total - (
            ((t.tokens_input / 1000000) * p.price_input) +
            ((t.tokens_output / 1000000) * p.price_output)
        )
    ) > 0.0001
ORDER BY diferencia DESC
LIMIT 100;

-- ============================================
-- OPCIÓN 2: UPDATE para recalcular costes
-- ⚠️ EJECUTAR CON CUIDADO - MODIFICA DATOS
-- ============================================

/*
-- DESCOMENTAR PARA EJECUTAR:

UPDATE api_usage_tracking t
INNER JOIN (
    -- Subconsulta para obtener el precio correcto por registro
    -- usando el patrón más específico que haga match
    SELECT
        t.id,
        MIN(p.priority) as best_priority
    FROM api_usage_tracking t
    INNER JOIN temp_model_prices p ON t.model LIKE CONCAT('%', p.model_pattern, '%')
    WHERE t.model IS NOT NULL
      AND t.model != ''
      AND (t.tokens_input > 0 OR t.tokens_output > 0)
    GROUP BY t.id
) as best_match ON t.id = best_match.id
INNER JOIN temp_model_prices p ON t.model LIKE CONCAT('%', p.model_pattern, '%')
    AND p.priority = best_match.best_priority
SET
    t.cost_input = ROUND((t.tokens_input / 1000000) * p.price_input, 8),
    t.cost_output = ROUND((t.tokens_output / 1000000) * p.price_output, 8),
    t.cost_total = ROUND(
        ((t.tokens_input / 1000000) * p.price_input) +
        ((t.tokens_output / 1000000) * p.price_output),
        8
    )
WHERE ABS(
    t.cost_total - (
        ((t.tokens_input / 1000000) * p.price_input) +
        ((t.tokens_output / 1000000) * p.price_output)
    )
) > 0.0001;

-- Ver cuántos registros se actualizaron
SELECT ROW_COUNT() as registros_actualizados;

*/

-- ============================================
-- OPCIÓN 3: Ver resumen por campaña
-- ============================================
SELECT
    t.campaign_id,
    COUNT(*) as operaciones,
    SUM(t.tokens_input + t.tokens_output) as tokens_totales,
    ROUND(SUM(t.cost_total), 4) as costo_actual,
    ROUND(SUM(
        ((t.tokens_input / 1000000) * p.price_input) +
        ((t.tokens_output / 1000000) * p.price_output)
    ), 4) as costo_correcto,
    ROUND(SUM(
        t.cost_total - (
            ((t.tokens_input / 1000000) * p.price_input) +
            ((t.tokens_output / 1000000) * p.price_output)
        )
    ), 4) as sobrecargo
FROM api_usage_tracking t
LEFT JOIN temp_model_prices p ON t.model LIKE CONCAT('%', p.model_pattern, '%')
WHERE t.model IS NOT NULL
  AND t.model != ''
  AND (t.tokens_input > 0 OR t.tokens_output > 0)
GROUP BY t.campaign_id
HAVING ABS(sobrecargo) > 0.0001
ORDER BY sobrecargo DESC;

-- Limpiar tabla temporal
DROP TEMPORARY TABLE IF EXISTS temp_model_prices;
