# PHSBOT - Integración con API5 de Tokens

## 📋 Resumen de Implementación

Se ha transformado el chatbot de autónomo (con OpenAI API key directa) a dependiente de API5 con sistema de licencias basado en tokens.

## ✅ Lo que se ha completado

### 1. Arquitectura API5 para Chatbot (`API5/bot/`)

Se creó una estructura aislada que NO modifica el código existente de GeoWriter:

```
API5/bot/
├── config.php                          # Configuración específica del bot
├── services/
│   ├── BotLicenseValidator.php         # Validación de licencias BOT-*
│   ├── BotTokenManager.php             # Gestión de tokens
│   └── BotOpenAIProxy.php              # Proxy a OpenAI con contexto
├── endpoints/
│   ├── chat.php                        # POST /api/bot/v1/chat
│   ├── validate.php                    # GET /api/bot/v1/validate
│   ├── status.php                      # GET /api/bot/v1/status
│   └── usage.php                       # GET /api/bot/v1/usage
├── install-bot-plans.php               # Instalador PHP de planes
└── install-bot-plans.sql               # Instalador SQL de planes
```

### 2. Sistema de Licencias

**Formato de licencia:**
```
BOT-{order_id}-{plan_id}-{year}-{random}
GEO-{order_id}-{plan_id}-{year}-{random}

Ejemplos:
- BOT-1435-20-2025-16570D0B  (Chatbot)
- GEO-1435-20-2025-16570D0B  (GeoWriter)
```

**Diferenciación automática:**
- El `WebhookHandler` detecta automáticamente si es un producto de chatbot
- Busca "bot" o "chat" en el nombre del producto o SKU
- Genera el prefijo correspondiente (BOT o GEO)

### 3. Planes del Chatbot

Se crearon 3 planes iniciales:

| Plan | ID | Tokens/mes | Precio |
|------|-----|------------|--------|
| Starter | `bot_starter` | 50,000 | €29 |
| Pro | `bot_pro` | 150,000 | €79 |
| Enterprise | `bot_enterprise` | 500,000 | €199 |

### 4. Endpoints REST

#### POST /api/bot/v1/chat
Procesa mensajes del usuario y genera respuestas IA

**Request:**
```json
{
  "license_key": "BOT-1435-20-2025-16570D0B",
  "domain": "example.com",
  "message": "¿Qué servicios ofrecéis?",
  "conversation_id": "conv_123",
  "context": {
    "kb_content": "...",
    "history": [...],
    "page_url": "https://example.com/pricing",
    "page_title": "Precios"
  },
  "settings": {
    "model": "gpt-4o",
    "temperature": 0.7,
    "max_tokens": 1000,
    "system_prompt": "..."
  }
}
```

**Response (éxito):**
```json
{
  "success": true,
  "data": {
    "response": "Ofrecemos servicios de...",
    "conversation_id": "conv_123",
    "usage": {
      "prompt_tokens": 150,
      "completion_tokens": 75,
      "total_tokens": 225,
      "tokens_remaining": 49775
    },
    "license": {
      "tokens_used": 225,
      "tokens_limit": 50000,
      "period_ends_at": "2025-02-01 00:00:00"
    }
  }
}
```

**Response (error - tokens agotados):**
```json
{
  "success": false,
  "error": {
    "code": "TOKEN_LIMIT_EXCEEDED",
    "message": "Token limit exceeded. Used: 50,150 / Limit: 50,000",
    "tokens_used": 50150,
    "tokens_limit": 50000,
    "period_ends_at": "2025-02-01",
    "upgrade_url": "https://bocetosmarketing.com/upgrade"
  }
}
```

#### GET /api/bot/v1/validate
Valida licencia y dominio (sin consumir tokens)

**Request:**
```
GET /api/bot/v1/validate?license_key=BOT-xxx&domain=example.com
```

**Response:**
```json
{
  "success": true,
  "data": {
    "valid": true,
    "license": {
      "key": "BOT-xxx",
      "status": "active",
      "plan_name": "Chatbot Starter",
      "tokens_available": 45000,
      "tokens_limit": 50000,
      "expires_at": "2025-02-01 00:00:00"
    }
  }
}
```

#### GET /api/bot/v1/status
Obtiene estado detallado de la licencia

#### GET /api/bot/v1/usage
Obtiene estadísticas de uso (últimos N días)

### 5. Modificaciones al Plugin

**archivo: `config/config.php`**

Se añadieron dos nuevos campos en la pestaña "Conexiones":

1. **Bot License Key** - Para introducir la licencia BOT-xxx
2. **Bot API URL** - URL de la API (default: https://bocetosmarketing.com/api_claude_5/index.php)

El campo "Token OpenAI" ahora es opcional y se mantiene por compatibilidad.

## 🔧 Próximos Pasos (Para completar)

### Paso 1: Instalar Planes en la Base de Datos

Ejecuta el SQL en tu base de datos:

```bash
# Opción A: Desde línea de comandos
mysql -u bocetosm_APAPI -p bocetosm_api_claude4 < API5/bot/install-bot-plans.sql

# Opción B: Desde phpMyAdmin
# 1. Abre phpMyAdmin
# 2. Selecciona la base de datos bocetosm_api_claude4
# 3. Ve a SQL
# 4. Copia y pega el contenido de API5/bot/install-bot-plans.sql
# 5. Ejecuta
```

### Paso 2: Crear Productos en WooCommerce

1. **Ir a WooCommerce → Productos → Añadir nuevo**

2. **Crear producto "Chatbot Starter":**
   - Nombre: "Chatbot Starter - 50,000 tokens/mes"
   - SKU: `bot-starter-monthly`
   - Precio: €29
   - **Importante:** El nombre o SKU debe contener "bot" o "chatbot"

3. **Repetir para los otros planes:**
   - Chatbot Pro (150k tokens, €79)
   - Chatbot Enterprise (500k tokens, €199)

4. **Asociar productos con planes:**
   ```sql
   UPDATE api_plans SET woo_product_id = {PRODUCT_ID} WHERE id = 'bot_starter';
   UPDATE api_plans SET woo_product_id = {PRODUCT_ID} WHERE id = 'bot_pro';
   UPDATE api_plans SET woo_product_id = {PRODUCT_ID} WHERE id = 'bot_enterprise';
   ```

   *Reemplaza {PRODUCT_ID} con los IDs reales de WooCommerce*

### Paso 3: Modificar chat-core.php ✅ COMPLETADO

La función `phsbot_ajax_chat` en `chat/chat-core.php` ha sido completamente modificada para:

1. **Obtenga la license key y API URL:**
```php
$bot_license = phsbot_setting('bot_license_key', '');
$bot_api_url = phsbot_setting('bot_api_url', 'https://bocetosmarketing.com/api_claude_5/index.php');
$domain = parse_url(home_url(), PHP_URL_HOST);
```

2. **Construya el payload para API5:**
```php
$api_payload = array(
    'license_key' => $bot_license,
    'domain' => $domain,
    'message' => $q,
    'conversation_id' => $cid,
    'context' => array(
        'kb_content' => $kb,
        'history' => $hist,
        'page_url' => $ctx_url,
        'page_title' => $ctx_title,
        // ... más contexto
    ),
    'settings' => array(
        'model' => $model,
        'temperature' => $temp,
        'max_tokens' => $max_t,
        'system_prompt' => $system
    )
);
```

3. **Llame a la API5:**
```php
$api_endpoint = trailingslashit($bot_api_url) . '?route=bot/chat';

$res = wp_remote_post($api_endpoint, array(
    'timeout' => 30,
    'headers' => array('Content-Type' => 'application/json'),
    'body' => wp_json_encode($api_payload),
));
```

4. **Maneje la respuesta:**
```php
$body = json_decode(wp_remote_retrieve_body($res), true);

if (!$body['success']) {
    $error_code = $body['error']['code'] ?? 'UNKNOWN';
    $error_msg = $body['error']['message'] ?? 'Error desconocido';

    wp_send_json(array(
        'ok' => false,
        'error' => $error_msg,
        'code' => $error_code
    ));
}

$txt = $body['data']['response'];
// ... resto del procesamiento
```

### Paso 4: Probar la Integración

#### Test 1: Validar Licencia

```bash
curl "https://bocetosmarketing.com/api_claude_5/index.php?route=bot/validate&license_key=BOT-xxx&domain=tudominio.com"
```

Debería retornar un JSON indicando si la licencia es válida.

#### Test 2: Enviar Mensaje de Chat

```bash
curl -X POST "https://bocetosmarketing.com/api_claude_5/index.php?route=bot/chat" \
  -H "Content-Type: application/json" \
  -d '{
    "license_key": "BOT-xxx",
    "domain": "tudominio.com",
    "message": "Hola",
    "settings": {"model": "gpt-4o-mini"}
  }'
```

Debería retornar la respuesta de la IA y el consumo de tokens.

### Paso 5: Configurar el Plugin en WordPress ✅ COMPLETADO

1. **Ir a PHSBOT → Configuración → Conexiones**
2. **Introducir:**
   - Bot License Key: `BOT-xxx` (tu licencia de prueba)
   - Bot API URL: `https://bocetosmarketing.com/api_claude_5/index.php`
3. **Hacer clic en "Validar Licencia"** para verificar que la licencia es válida
4. **Guardar configuración**

**Nuevo en esta versión:**
- ✅ Campo "Token OpenAI" eliminado (ya no es necesario)
- ✅ Botón "Validar Licencia" con validación en tiempo real
- ✅ Muestra información de la licencia: plan, tokens disponibles, fecha de expiración
- ✅ Mensajes de error claros si la licencia no es válida

### Paso 6: Probar en Frontend

1. Visita tu sitio web
2. Abre el chatbot
3. Envía un mensaje
4. Verifica que:
   - La respuesta llega correctamente
   - No hay errores en la consola del navegador
   - El tracking de tokens funciona (revisa `api_usage_tracking` en la BD)

## 📊 Verificación de Funcionamiento

### Base de Datos

**Verificar planes instalados:**
```sql
SELECT id, name, tokens_per_month, price
FROM api_plans
WHERE id LIKE 'bot%';
```

**Verificar licencia de prueba:**
```sql
SELECT license_key, status, domain, tokens_used_this_period, tokens_limit, period_ends_at
FROM api_licenses
WHERE license_key LIKE 'BOT-%';
```

**Ver tracking de uso:**
```sql
SELECT created_at, tokens_input, tokens_output, tokens_total, model, endpoint
FROM api_usage_tracking
WHERE operation_type = 'bot_chat'
ORDER BY created_at DESC
LIMIT 10;
```

## 🔍 Troubleshooting

### Error: "License key not found"
- Verifica que la licencia existe en `api_licenses`
- Verifica que empiece con `BOT-`

### Error: "Domain mismatch"
- El dominio ya está registrado en otra licencia
- Verifica el campo `domain` en `api_licenses`

### Error: "Token limit exceeded"
- Los tokens del periodo están agotados
- Verifica `tokens_used_this_period` vs `tokens_limit`
- Espera al siguiente ciclo o actualiza el plan

### Error: "OpenAI API Key is not configured"
- La API key de OpenAI no está configurada en la API5
- Configúrala en el panel de admin de API5

## 📝 Notas Importantes

1. **No se modificó el código de GeoWriter** - Todo está aislado en `API5/bot/`

2. **Retrocompatibilidad** - El campo OpenAI API Key se mantiene por si se necesita en el futuro

3. **Tracking detallado** - Cada request queda registrado con tokens de entrada y salida por separado

4. **Auto-sync** - El webhook y el cron automático gestionan la sincronización con WooCommerce

5. **Formato de licencia** - Las licencias BOT se distinguen de las GEO por su prefijo

6. **Dominio auto-captura** - En la primera petición se captura automáticamente el dominio

## 🚀 Siguientes Funcionalidades (Futuras)

- Dashboard de uso en el plugin (gráficas de consumo)
- Alertas cuando quedan pocos tokens
- Botón de compra directa desde el plugin
- Múltiples dominios por licencia (upgrade)
- Histórico de conversaciones (analytics)

## 📝 Changelog - Versión Final

### ✅ Cambios Críticos Completados (2025-01-04)

#### `chat/chat-core.php`
- ✅ **Eliminada dependencia de OpenAI API key directa**
- ✅ **Requiere ahora bot_license_key obligatoria**
- ✅ **Llama a API5 en lugar de OpenAI directamente**
- ✅ **Validación de licencia antes de procesar cada mensaje**
- ✅ **Auto-detección del dominio desde home_url()**
- ✅ **Manejo de errores mejorado con mensajes en español**
- ✅ **Mapeo de códigos de error de API a mensajes user-friendly**

**Códigos de error soportados:**
- `TOKEN_LIMIT_EXCEEDED` → "Has alcanzado el límite de tokens..."
- `DOMAIN_MISMATCH` → "Esta licencia está registrada para otro dominio..."
- `LICENSE_EXPIRED` → "Tu licencia ha expirado..."
- `LICENSE_NOT_FOUND` → "Licencia no válida..."

#### `config/config.php`
- ✅ **Eliminado campo "Token OpenAI" del panel** (ya no es necesario)
- ✅ **Añadidos IDs a campos bot_license_key y bot_api_url**
- ✅ **Añadido botón "Validar Licencia"**
- ✅ **Añadido div #phsbot-license-status para mostrar resultados**

#### `config/config.js`
- ✅ **Añadido handler AJAX para validación de licencia**
- ✅ **Validación en tiempo real al hacer clic**
- ✅ **Muestra información completa de la licencia:**
  - Plan contratado
  - Estado (active/suspended/expired)
  - Dominio asignado
  - Tokens disponibles / límite
  - Porcentaje de uso
  - Fecha de expiración
- ✅ **Manejo de errores con mensajes claros**
- ✅ **Estados visuales: loading, success, error**

### 🎯 Flujo de Funcionamiento Actual

1. **Usuario abre el chatbot** → Frontend carga
2. **Usuario envía mensaje** → AJAX a `phsbot_ajax_chat`
3. **Plugin valida licencia** → Comprueba que existe `bot_license_key`
4. **Plugin construye payload** → Incluye licencia, dominio, mensaje, contexto
5. **Plugin llama a API5** → `POST /api/bot/v1/chat`
6. **API5 valida licencia** → BotLicenseValidator
7. **API5 valida dominio** → Auto-captura en primera petición
8. **API5 verifica tokens** → Comprueba límite vs usado
9. **API5 llama a OpenAI** → BotOpenAIProxy
10. **API5 registra consumo** → BotTokenManager
11. **API5 retorna respuesta** → Con tokens consumidos
12. **Plugin muestra respuesta** → O error si falla

### 🔒 Seguridad Implementada

- ✅ **No se puede usar el chatbot sin licencia válida**
- ✅ **No se puede usar el chatbot sin dominio autorizado**
- ✅ **No se puede usar el chatbot si se agotaron los tokens**
- ✅ **No se puede usar el chatbot si la licencia expiró**
- ✅ **Cada petición valida la licencia en tiempo real**
- ✅ **Tracking completo de consumo por licencia**

## 📞 Soporte

Si tienes dudas sobre la implementación o encuentras errores:
1. Revisa los logs de errores de PHP
2. Revisa los logs de API5 (`API5/logs/`)
3. Verifica la configuración de la base de datos
4. Contacta con el desarrollador

---

**Implementado por:** Claude AI
**Fecha:** 2025-01-04
**Versión:** 1.0
