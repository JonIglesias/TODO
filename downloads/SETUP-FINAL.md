# Configuración Final: Descargas de Plugins desde GitHub en WooCommerce

Esta es la solución definitiva que **SÍ funciona** con WooCommerce.

## Cómo funciona

1. Los releases de GitHub se descargan automáticamente al servidor
2. WooCommerce sirve los archivos físicos normalmente
3. Siempre se mantiene actualizado vía webhook o manualmente

---

## Instalación Inicial

### Paso 1: Subir archivos al servidor

Copia estos archivos a tu servidor:

```
/home/bocetosm/public_html/downloads/
├── github-webhook.php      (receptor de webhook de GitHub)
├── manual-download.php     (script para descargar manualmente)
```

### Paso 2: Crear directorio de descargas

```bash
mkdir -p /home/bocetosm/public_html/wp-content/uploads/woocommerce_downloads/
chmod 755 /home/bocetosm/public_html/wp-content/uploads/woocommerce_downloads/
```

### Paso 3: Descargar releases iniciales

Ejecuta vía SSH o navegador:

**Vía SSH:**
```bash
cd /home/bocetosm/public_html/downloads/
php manual-download.php geowriter
php manual-download.php conversa
```

**Vía navegador:**
```
https://www.bocetosmarketing.com/downloads/manual-download.php?plugin=geowriter
https://www.bocetosmarketing.com/downloads/manual-download.php?plugin=conversa
```

Esto descargará:
- `wp-content/uploads/woocommerce_downloads/geowriter-latest.zip`
- `wp-content/uploads/woocommerce_downloads/conversa-latest.zip`

### Paso 4: Configurar productos en WooCommerce

En cada producto digital:

**GEOWriter:**
- Nombre del archivo: `geowriter-latest.zip`
- URL del archivo: `https://www.bocetosmarketing.com/wp-content/uploads/woocommerce_downloads/geowriter-latest.zip`

**Conversa:**
- Nombre del archivo: `conversa-latest.zip`
- URL del archivo: `https://www.bocetosmarketing.com/wp-content/uploads/woocommerce_downloads/conversa-latest.zip`

---

## Configurar Webhook de GitHub (Automático)

Para que se actualice automáticamente cuando publiques un release:

### 1. Ir a GitHub

**Para GEOWriter:**
- Ve a: https://github.com/bocetosmarketing/geowriter/settings/hooks
- Clic en "Add webhook"

**Para Conversa:**
- Ve a: https://github.com/bocetosmarketing/conversa-bot/settings/hooks
- Clic en "Add webhook"

### 2. Configurar webhook

- **Payload URL:** `https://www.bocetosmarketing.com/downloads/github-webhook.php`
- **Content type:** `application/json`
- **Secret:** (opcional, dejar vacío)
- **Which events:** Seleccionar "Let me select individual events" → marcar solo **"Releases"**
- **Active:** ✓ Marcado
- Clic en "Add webhook"

### 3. Probar webhook

Publica un release de prueba en GitHub y verifica que se descargue automáticamente:

```bash
# Ver log del webhook
cat /home/bocetosm/public_html/wp-content/uploads/woocommerce_downloads/webhook.log
```

---

## Actualización Manual (Si webhook falla)

Si el webhook no funciona o quieres actualizar manualmente:

```bash
cd /home/bocetosm/public_html/downloads/
php manual-download.php geowriter
php manual-download.php conversa
```

O desde navegador:
```
https://www.bocetosmarketing.com/downloads/manual-download.php?plugin=geowriter
https://www.bocetosmarketing.com/downloads/manual-download.php?plugin=conversa
```

---

## Ventajas de esta solución

✅ **Compatible al 100% con WooCommerce** - Usa archivos físicos reales
✅ **Automático con webhook** - Se actualiza solo cuando publicas release
✅ **Manual como fallback** - Puedes actualizar manualmente si necesitas
✅ **Mantiene historial** - Guarda últimas 3 versiones
✅ **Logs completos** - Puedes ver qué pasó en webhook.log
✅ **Sin redirecciones** - WooCommerce sirve el archivo directamente

---

## Archivos que se crean

```
wp-content/uploads/woocommerce_downloads/
├── geowriter-latest.zip         (última versión, lo usa WooCommerce)
├── geowriter-v7.0.90.zip        (versión específica)
├── geowriter-v7.0.89.zip        (versión anterior)
├── conversa-latest.zip          (última versión, lo usa WooCommerce)
├── conversa-v1.4.1.zip          (versión específica)
├── conversa-v1.4.zip            (versión anterior)
└── webhook.log                  (log de actualizaciones)
```

---

## Troubleshooting

### "No se descarga el archivo"
- Verifica que el directorio `woocommerce_downloads` tenga permisos 755
- Verifica que los archivos `-latest.zip` existan
- Revisa el log: `webhook.log`

### "Webhook no funciona"
- Verifica que la URL sea accesible públicamente
- Revisa los logs de GitHub: Settings → Webhooks → Recent Deliveries
- Ejecuta manual-download.php como fallback

### "Archivo corrupto"
- GitHub a veces tarda unos segundos en generar el zipball
- Vuelve a ejecutar manual-download.php
- Verifica el tamaño del archivo (debe ser varios MB)

---

## Workflow futuro

1. Desarrollas en `/home/user/TODO/`
2. Me dices: "Sube GEOWriter v7.X.X"
3. Yo creo el release en GitHub
4. **Automáticamente:**
   - GitHub envía webhook
   - Se descarga el ZIP al servidor
   - WooCommerce lo tiene disponible
5. Los clientes descargan la última versión
6. Los plugins instalados reciben notificación de actualización ✅

**Todo automático, cero mantenimiento.**
