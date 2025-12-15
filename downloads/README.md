# Scripts de Descarga de Plugins

Este directorio contiene scripts PHP que redirigen automáticamente a los últimos releases de GitHub de los plugins.

## Archivos

- `geowriter.php` - Redirige al último release de GEOWriter
- `conversa.php` - Redirige al último release de Conversa

## Uso en WooCommerce

Estos archivos deben subirse al servidor en:
```
/home/bocetosm/public_html/downloads/
```

Luego configurar en WooCommerce como URLs de descarga:
- GEOWriter: `https://www.bocetosmarketing.com/downloads/geowriter.php`
- Conversa: `https://www.bocetosmarketing.com/downloads/conversa.php`

## Cómo funcionan

1. Usuario compra el plugin en WooCommerce
2. Hace clic en descargar
3. El script consulta la API de GitHub para obtener el último release
4. Redirige automáticamente al archivo ZIP del release
5. La descarga comienza automáticamente

## Ventajas

- Siempre sirve la última versión publicada en GitHub
- No requiere actualizar manualmente los archivos en WooCommerce
- Descarga directa sin clics adicionales
- Fallback automático si GitHub no responde
