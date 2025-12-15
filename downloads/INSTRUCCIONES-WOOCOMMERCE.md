# Configurar Descargas desde GitHub en WooCommerce

## Problema
WooCommerce no puede usar redirecciones directamente como archivos descargables.

## Solución
Usar un plugin personalizado que intercepta las descargas y redirige a GitHub.

---

## Instalación (Método 1 - Plugin)

### 1. Instalar el plugin
Sube el archivo `woocommerce-github-downloads.php` a:
```
/home/bocetosm/public_html/wp-content/plugins/woocommerce-github-downloads.php
```

O vía FTP a:
```
wp-content/plugins/woocommerce-github-downloads.php
```

### 2. Activar el plugin
- Ve a WordPress Admin → Plugins
- Busca "WooCommerce GitHub Downloads"
- Clic en "Activar"

### 3. Configurar productos en WooCommerce

En cada producto digital, configura el archivo descargable con estos nombres:

**Para GEOWriter:**
- Nombre del archivo: `geowriter.zip` (o cualquier cosa que contenga "geowriter")
- URL del archivo: `https://www.bocetosmarketing.com/dummy/geowriter.zip`

**Para Conversa:**
- Nombre del archivo: `conversa.zip` (o cualquier cosa que contenga "conversa")
- URL del archivo: `https://www.bocetosmarketing.com/dummy/conversa.zip`

⚠️ **Importante:** Las URLs pueden ser ficticias (no necesitan existir), el plugin las interceptará.

---

## Instalación (Método 2 - Functions.php)

Si prefieres no usar un plugin adicional, copia el contenido de `woocommerce-github-downloads.php` (sin las primeras líneas del header del plugin) al archivo `functions.php` de tu tema hijo.

---

## Cómo funciona

1. Usuario compra el producto en WooCommerce
2. Hace clic en descargar
3. El plugin intercepta la descarga
4. Detecta si es "geowriter" o "conversa" por el nombre del archivo
5. Consulta la API de GitHub para obtener el último release
6. Redirige automáticamente al ZIP del último release de GitHub
7. El usuario descarga la última versión automáticamente

## Ventajas

✅ Siempre sirve la última versión de GitHub
✅ Compatible con el sistema nativo de WooCommerce
✅ Caché de 1 hora para no saturar la API de GitHub
✅ Fallback automático si GitHub no responde
✅ No requiere archivos físicos en el servidor

## Mantenimiento

- **Cero mantenimiento:** Cada vez que publiques un nuevo release en GitHub, se servirá automáticamente
- **Limpiar caché manual:** Si necesitas forzar una actualización inmediata, desactiva y reactiva el plugin

---

## Alternativa Simple (Sin Plugin)

Si no quieres usar el plugin, otra opción es:

1. Crear un archivo dummy en tu servidor:
   ```
   /home/bocetosm/public_html/dummy/geowriter.zip (archivo vacío de 1KB)
   /home/bocetosm/public_html/dummy/conversa.zip (archivo vacío de 1KB)
   ```

2. Usar los scripts originales (`downloads/geowriter.php` y `downloads/conversa.php`)

3. Configurar `.htaccess` en `/dummy/` para redirigir:
   ```apache
   RewriteEngine On
   RewriteRule ^geowriter\.zip$ /downloads/geowriter.php [L]
   RewriteRule ^conversa\.zip$ /downloads/conversa.php [L]
   ```

Esta opción es más simple pero menos elegante.
