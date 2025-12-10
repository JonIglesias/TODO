<?php
/**
 * Test para verificar que el fix de model matching funciona
 * Test simplificado sin dependencia de BD
 */

// Simular la función de precios
function getAllFallbackPrices() {
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

function getFallbackPrices($model) {
    $prices = getAllFallbackPrices();

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

    // Precio por defecto (gpt-4o-mini) - por MILLÓN de tokens
    return ['input' => 0.15, 'output' => 0.60];
}

echo "=== TEST DE FIX MODEL PRICING ===\n\n";

// Test casos problemáticos
$test_cases = [
    'gpt-4.1-2025-04-14' => ['expected_input' => 2.00, 'expected_output' => 8.00, 'should_match' => 'gpt-4.1'],
    'gpt-4o-mini' => ['expected_input' => 0.15, 'expected_output' => 0.60, 'should_match' => 'gpt-4o-mini'],
    'gpt-4o' => ['expected_input' => 2.50, 'expected_output' => 10.00, 'should_match' => 'gpt-4o'],
    'gpt-4-turbo' => ['expected_input' => 10.00, 'expected_output' => 30.00, 'should_match' => 'gpt-4-turbo'],
    'gpt-4' => ['expected_input' => 30.00, 'expected_output' => 60.00, 'should_match' => 'gpt-4'],
    'claude-3-5-sonnet-20241022' => ['expected_input' => 3.00, 'expected_output' => 15.00, 'should_match' => 'claude-3-5-sonnet'],
    'claude-3-5-haiku-20241022' => ['expected_input' => 0.80, 'expected_output' => 4.00, 'should_match' => 'claude-3-5-haiku'],
];

$all_passed = true;

foreach ($test_cases as $model => $expected) {
    $prices = getFallbackPrices($model);

    echo "Modelo: {$model}\n";
    echo "  Debe detectarse como: {$expected['should_match']}\n";
    echo "  Input:  \${$prices['input']} por millón\n";
    echo "  Output: \${$prices['output']} por millón\n";

    // Verificar
    if ($prices['input'] == $expected['expected_input'] && $prices['output'] == $expected['expected_output']) {
        echo "  ✅ CORRECTO\n";
    } else {
        echo "  ❌ ERROR - Esperaba \${$expected['expected_input']}/\${$expected['expected_output']}\n";
        $all_passed = false;
    }

    echo "\n";
}

echo "===================================\n";
if ($all_passed) {
    echo "✅ TODOS LOS TESTS PASARON\n";
    echo "El fix funciona correctamente.\n";
} else {
    echo "❌ ALGUNOS TESTS FALLARON\n";
}
