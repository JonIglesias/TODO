<?php
/**
 * Script para limpiar OPcache sin reiniciar PHP-FPM
 * Acceder vía web: https://tu-dominio.com/api_claude_5/clear-opcache.php
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=== LIMPIAR OPCACHE ===\n\n";

// Verificar si OPcache está habilitado
if (!function_exists('opcache_reset')) {
    echo "❌ OPcache no está disponible en esta instalación de PHP\n";
    exit;
}

echo "Estado de OPcache antes de limpiar:\n";
echo str_repeat("-", 60) . "\n";

$status = opcache_get_status();
if ($status) {
    echo "Memoria usada: " . round($status['memory_usage']['used_memory'] / 1024 / 1024, 2) . " MB\n";
    echo "Memoria libre: " . round($status['memory_usage']['free_memory'] / 1024 / 1024, 2) . " MB\n";
    echo "Archivos cacheados: " . $status['opcache_statistics']['num_cached_scripts'] . "\n";
    echo "Hits: " . $status['opcache_statistics']['hits'] . "\n";
    echo "Misses: " . $status['opcache_statistics']['misses'] . "\n";
} else {
    echo "No se pudo obtener el estado de OPcache\n";
}

echo "\n";
echo "Limpiando OPcache...\n";

// Resetear OPcache completamente
$result = opcache_reset();

if ($result) {
    echo "✓ OPcache limpiado exitosamente\n\n";

    // Invalidar archivos específicos críticos
    $criticalFiles = [
        __DIR__ . '/services/OpenAIService.php',
        __DIR__ . '/config.php',
        __DIR__ . '/core/BaseEndpoint.php',
        __DIR__ . '/endpoints/descripcion-empresa.php'
    ];

    echo "Invalidando archivos críticos específicamente:\n";
    echo str_repeat("-", 60) . "\n";

    foreach ($criticalFiles as $file) {
        if (file_exists($file)) {
            opcache_invalidate($file, true);
            echo "✓ " . basename($file) . "\n";
        }
    }

    echo "\n";
    echo "Estado de OPcache después de limpiar:\n";
    echo str_repeat("-", 60) . "\n";

    $statusAfter = opcache_get_status();
    if ($statusAfter) {
        echo "Archivos cacheados: " . $statusAfter['opcache_statistics']['num_cached_scripts'] . "\n";
    }

    echo "\n✓ PROCESO COMPLETADO\n";
    echo "\nAhora prueba el endpoint de descripcion-empresa y revisa el monitor.\n";

} else {
    echo "❌ No se pudo limpiar OPcache\n";
    echo "Puede que necesites reiniciar PHP-FPM manualmente.\n";
}

echo "\n=== FIN ===\n";
