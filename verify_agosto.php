<?php
/**
 * Verificar costes de campaña AGOSTO
 */

// ID 3696 como ejemplo:
// Model: gpt-4.1-2025-04-14
// tokens_input: 1265, tokens_output: 148
// cost_input: 0.03795000, cost_output: 0.00888000

echo "=== VERIFICACIÓN COSTES AGOSTO ===\n\n";

// Precios correctos de gpt-4.1
$gpt41_input = 2.00;  // por millón
$gpt41_output = 8.00; // por millón

// Cálculo correcto
$tokens_input = 1265;
$tokens_output = 148;

$cost_correcto_input = ($tokens_input / 1000000) * $gpt41_input;
$cost_correcto_output = ($tokens_output / 1000000) * $gpt41_output;
$cost_correcto_total = $cost_correcto_input + $cost_correcto_output;

echo "CÁLCULO CORRECTO (gpt-4.1 @ \$2/\$8 por millón):\n";
echo "  Input:  ({$tokens_input} / 1M) × \$2.00 = \$" . number_format($cost_correcto_input, 8) . "\n";
echo "  Output: ({$tokens_output} / 1M) × \$8.00 = \$" . number_format($cost_correcto_output, 8) . "\n";
echo "  Total: \$" . number_format($cost_correcto_total, 8) . "\n\n";

// Lo que está registrado
$cost_registrado_input = 0.03795000;
$cost_registrado_output = 0.00888000;
$cost_registrado_total = $cost_registrado_input + $cost_registrado_output;

echo "CÁLCULO REGISTRADO (INCORRECTO):\n";
echo "  Input:  \$" . number_format($cost_registrado_input, 8) . "\n";
echo "  Output: \$" . number_format($cost_registrado_output, 8) . "\n";
echo "  Total: \$" . number_format($cost_registrado_total, 8) . "\n\n";

// Calcular qué precio se está usando
$precio_usado_input = $cost_registrado_input / ($tokens_input / 1000000);
$precio_usado_output = $cost_registrado_output / ($tokens_output / 1000000);

echo "PRECIO QUE SE ESTÁ USANDO (DETECTADO):\n";
echo "  Input:  \$" . number_format($precio_usado_input, 2) . " por millón\n";
echo "  Output: \$" . number_format($precio_usado_output, 2) . " por millón\n";
echo "  ⚠️  ¡Está usando precios de gpt-4, NO de gpt-4.1!\n\n";

echo "DIFERENCIA:\n";
echo "  Cobrado de más: \$" . number_format($cost_registrado_total - $cost_correcto_total, 8) . "\n";
echo "  Factor: " . number_format($cost_registrado_total / $cost_correcto_total, 1) . "x más caro\n\n";

// Todos los registros de AGOSTO
$registros_agosto = [
    ['id' => 3695, 'endpoint' => 'descripcion-empresa', 'model' => 'gpt-4o-mini', 'ti' => 2774, 'to' => 366, 'ci' => 0.00041610, 'co' => 0.00021960],
    ['id' => 3696, 'endpoint' => 'keywords-seo', 'model' => 'gpt-4.1-2025-04-14', 'ti' => 1265, 'to' => 148, 'ci' => 0.03795000, 'co' => 0.00888000],
    ['id' => 3697, 'endpoint' => 'prompt-titulos', 'model' => 'gpt-4.1-2025-04-14', 'ti' => 569, 'to' => 207, 'ci' => 0.01707000, 'co' => 0.01242000],
    ['id' => 3698, 'endpoint' => 'prompt-contenido', 'model' => 'gpt-4.1-2025-04-14', 'ti' => 752, 'to' => 204, 'ci' => 0.02256000, 'co' => 0.01224000],
    ['id' => 3699, 'endpoint' => 'keywords-campana', 'model' => 'gpt-4.1-2025-04-14', 'ti' => 1103, 'to' => 77, 'ci' => 0.03309000, 'co' => 0.00462000],
];

// Calcular totales para SETUP de AGOSTO
$total_incorrecto = 0;
$total_correcto = 0;

foreach ($registros_agosto as $reg) {
    $total_incorrecto += $reg['ci'] + $reg['co'];

    if ($reg['model'] === 'gpt-4.1-2025-04-14') {
        $correcto = ($reg['ti'] / 1000000 * 2.00) + ($reg['to'] / 1000000 * 8.00);
        $total_correcto += $correcto;
    } else {
        // gpt-4o-mini
        $correcto = ($reg['ti'] / 1000000 * 0.15) + ($reg['to'] / 1000000 * 0.60);
        $total_correcto += $correcto;
    }
}

echo "TOTALES SOLO SETUP (5 operaciones):\n";
echo "  Coste registrado (incorrecto): \$" . number_format($total_incorrecto, 6) . "\n";
echo "  Coste correcto: \$" . number_format($total_correcto, 6) . "\n";
echo "  Sobrecargo: \$" . number_format($total_incorrecto - $total_correcto, 6) . "\n\n";

echo "===================================\n";
echo "PROBLEMA IDENTIFICADO:\n";
echo "El modelo 'gpt-4.1-2025-04-14' está siendo detectado\n";
echo "como 'gpt-4' por el método strpos() en getFallbackPrices().\n";
echo "Solución: Ordenar precios de más específico a menos específico,\n";
echo "o usar preg_match para match exacto.\n";
