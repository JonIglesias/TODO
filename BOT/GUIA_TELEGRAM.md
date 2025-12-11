# Guía de Configuración - Bot de Telegram para PHSBot

**Versión:** 1.0
**Última actualización:** Diciembre 2025

---

## Índice

1. [Introducción](#introducción)
2. [Requisitos Previos](#requisitos-previos)
3. [Paso 1: Crear el Bot en Telegram](#paso-1-crear-el-bot-en-telegram)
4. [Paso 2: Obtener el Token del Bot](#paso-2-obtener-el-token-del-bot)
5. [Paso 3: Obtener el Chat ID](#paso-3-obtener-el-chat-id)
6. [Paso 4: Configurar PHSBot](#paso-4-configurar-phsbot)
7. [Paso 5: Probar la Integración](#paso-5-probar-la-integración)
8. [Solución de Problemas](#solución-de-problemas)
9. [Preguntas Frecuentes](#preguntas-frecuentes)
10. [Seguridad y Mejores Prácticas](#seguridad-y-mejores-prácticas)

---

## Introducción

Esta guía le ayudará a configurar un bot de Telegram para recibir notificaciones automáticas cuando PHSBot identifique leads de alta calidad en su sitio web.

**¿Qué conseguirá con esta integración?**

- Notificaciones instantáneas en Telegram cuando un visitante alcance un scoring alto
- Información detallada del lead (nombre, email, teléfono, puntuación)
- Historial de conversaciones del lead con el chatbot
- Respuesta rápida a oportunidades de negocio

**Tiempo estimado:** 10-15 minutos

---

## Requisitos Previos

Antes de comenzar, asegúrese de tener:

1. **Cuenta de Telegram activa** (en dispositivo móvil o versión de escritorio)
2. **Acceso al plugin PHSBot** instalado en WordPress
3. **Permisos de administrador** en WordPress
4. **Conexión a internet** estable

---

## Paso 1: Crear el Bot en Telegram

### 1.1 Acceder a BotFather

1. Abra la aplicación de Telegram en su dispositivo
2. En el buscador, escriba: **@BotFather**
3. Seleccione el bot oficial verificado (tiene una marca de verificación azul)
4. Inicie la conversación presionando el botón **"Iniciar"** o **"Start"**

### 1.2 Crear un Nuevo Bot

1. Envíe el comando: `/newbot`

2. BotFather le solicitará un **nombre para su bot**. Este es el nombre público que verán los usuarios.

   **Ejemplo:**
   ```
   PHSBot Notificaciones
   ```
   o
   ```
   Mi Empresa - Leads
   ```

3. Luego le pedirá un **nombre de usuario único** (username). Este debe:
   - Terminar en "bot" (ejemplo: `miempresa_leads_bot`)
   - Ser único en toda la plataforma Telegram
   - No contener espacios ni caracteres especiales (solo letras, números y guiones bajos)

   **Ejemplo:**
   ```
   phsbot_notificaciones_bot
   ```
   o
   ```
   miempresa_leads_bot
   ```

4. Si el nombre está disponible, BotFather confirmará la creación del bot.

---

## Paso 2: Obtener el Token del Bot

### 2.1 Localizar el Token

Inmediatamente después de crear el bot, BotFather le proporcionará un **token de acceso HTTP API**.

**Ejemplo de token:**
```
1234567890:ABCdefGHIjklMNOpqrsTUVwxyz123456789
```

### 2.2 Copiar el Token de Forma Segura

1. Mantenga presionado sobre el token en la aplicación de Telegram
2. Seleccione **"Copiar"**
3. **IMPORTANTE:** Guarde este token en un lugar seguro temporalmente (bloc de notas, gestor de contraseñas)
4. **NUNCA comparta este token públicamente** ni lo suba a repositorios de código

### 2.3 Recuperar el Token (Si lo Perdió)

Si cerró la conversación sin copiar el token:

1. Vuelva a la conversación con @BotFather
2. Envíe el comando: `/mybots`
3. Seleccione su bot de la lista
4. Presione **"API Token"**
5. BotFather le mostrará nuevamente el token

---

## Paso 3: Obtener el Chat ID

El **Chat ID** identifica el destino donde se enviarán las notificaciones. Puede ser:

- **Su usuario personal** (para recibir notificaciones privadas)
- **Un grupo** (para que todo un equipo reciba las notificaciones)
- **Un canal** (menos común para este caso de uso)

### Opción A: Chat ID Personal (Recomendado para Iniciar)

#### Método 1: Usando el Bot @userinfobot

1. En Telegram, busque: **@userinfobot**
2. Inicie la conversación presionando **"Start"**
3. El bot le responderá automáticamente con su información
4. Copie el número que aparece en **"Id:"**

   **Ejemplo de respuesta:**
   ```
   Id: 123456789
   First name: Juan
   Username: @juanperez
   ```

5. Su Chat ID es: `123456789`

#### Método 2: Usando el Bot @getidsbot

1. Busque: **@getidsbot**
2. Inicie la conversación
3. El bot le mostrará su Chat ID inmediatamente

### Opción B: Chat ID de un Grupo

Si desea que las notificaciones lleguen a un grupo de trabajo:

#### 3.1 Crear o Seleccionar un Grupo

1. Cree un nuevo grupo en Telegram o use uno existente
2. Agregue los miembros del equipo que deben recibir notificaciones

#### 3.2 Agregar el Bot al Grupo

1. Abra el grupo
2. Toque el nombre del grupo en la parte superior
3. Seleccione **"Agregar miembro"** o **"Add member"**
4. Busque el nombre de usuario de su bot (ejemplo: `@phsbot_notificaciones_bot`)
5. Agregue el bot al grupo

#### 3.3 Otorgar Permisos al Bot

1. En la configuración del grupo, toque **"Administradores"**
2. Seleccione **"Agregar administrador"**
3. Seleccione su bot
4. Active al menos el permiso: **"Enviar mensajes"**

#### 3.4 Obtener el Chat ID del Grupo

**Método 1: Usando @getidsbot en el grupo**

1. Agregue **@getidsbot** al grupo (igual que agregó su bot)
2. El bot enviará automáticamente un mensaje con el Chat ID del grupo
3. Copie el número (los grupos tienen IDs negativos)

   **Ejemplo:**
   ```
   Chat ID: -987654321
   ```

**Método 2: Usando una API de Telegram**

1. Envíe un mensaje cualquiera en el grupo
2. Abra su navegador web
3. Vaya a esta URL (reemplace `YOUR_BOT_TOKEN` por su token real):
   ```
   https://api.telegram.org/botYOUR_BOT_TOKEN/getUpdates
   ```

   **Ejemplo real:**
   ```
   https://api.telegram.org/bot1234567890:ABCdefGHIjklMNOpqrsTUVwxyz123456789/getUpdates
   ```

4. Busque en la respuesta JSON el campo `"chat":{"id":`
5. El número que aparece después es su Chat ID (será negativo para grupos)

---

## Paso 4: Configurar PHSBot

### 4.1 Acceder a la Configuración

1. Inicie sesión en el panel de administración de WordPress
2. En el menú lateral, localice **"PHSBot"**
3. Haga clic en **"Configuración"**
4. Navegue a la pestaña **"Conexiones"**

### 4.2 Ingresar las Credenciales de Telegram

En la sección **"Telegram Notifications"**:

1. **Token del Bot:** Pegue el token que obtuvo de BotFather
   ```
   1234567890:ABCdefGHIjklMNOpqrsTUVwxyz123456789
   ```

2. **Chat ID:** Pegue el Chat ID que obtuvo en el Paso 3
   - Si es personal: `123456789`
   - Si es de grupo: `-987654321` (con el signo menos)

3. Haga clic en el botón **"Guardar cambios"**

### 4.3 Configurar el Umbral de Notificación

1. Navegue a la pestaña **"Leads & Scoring"**
2. En la sección **"Notificaciones de Telegram"**
3. Configure el **umbral de scoring** mínimo para enviar notificaciones

   **Recomendaciones:**
   - **Scoring 70-80:** Para recibir notificaciones de leads calientes
   - **Scoring 50-60:** Si desea más notificaciones incluyendo leads tibios
   - **Scoring 90+:** Solo para leads extremadamente calificados

4. Guarde los cambios

---

## Paso 5: Probar la Integración

### 5.1 Prueba Manual desde WordPress

Algunos plugins incluyen un botón de prueba en la configuración:

1. En **PHSBot > Configuración > Conexiones**
2. Si existe un botón **"Probar conexión"** o **"Test"**, présiónelo
3. Verifique que llegue un mensaje de prueba a su Telegram

### 5.2 Prueba Real con el Chatbot

La forma más confiable de probar:

1. Abra su sitio web en una ventana de incógnito (para simular un visitante nuevo)
2. Interactúe con el chatbot proporcionando:
   - Nombre completo
   - Email válido
   - Teléfono
   - Realice varias preguntas para aumentar el scoring

3. Cuando el scoring alcance el umbral configurado, debería recibir una notificación en Telegram

**Ejemplo de notificación:**
```
🔥 Nuevo Lead de Alta Calidad

📊 Puntuación: 85/100

👤 Nombre: Juan Pérez
📧 Email: juan@example.com
📱 Teléfono: +34 600 123 456

💬 Conversación:
- ¿Cuáles son sus precios?
- ¿Ofrecen envío a domicilio?
- Necesito información sobre productos premium

🌐 Sitio: https://miempresa.com
⏰ Fecha: 11/12/2025 14:35
```

---

## Solución de Problemas

### Problema 1: No Llegan las Notificaciones

**Causas posibles:**

1. **Token incorrecto**
   - Verifique que copió el token completo sin espacios adicionales
   - Compruebe que no haya caracteres invisibles al inicio o final
   - El token debe contener un ":" en el medio

2. **Chat ID incorrecto**
   - Para usuarios personales: solo números positivos
   - Para grupos: debe incluir el signo menos (-)
   - Verifique que no haya espacios antes o después del número

3. **El bot no está en el grupo**
   - Si usa un grupo, verifique que el bot esté agregado como miembro
   - Asegúrese de que el bot tenga permisos para enviar mensajes

4. **El scoring no alcanza el umbral**
   - Revise la configuración del umbral mínimo en Leads & Scoring
   - Haga una prueba más exhaustiva con el chatbot

**Soluciones:**

1. Vuelva a copiar el token directamente desde BotFather
2. Regenere el Chat ID usando @userinfobot
3. Verifique los registros de error en WordPress (si tiene acceso)
4. Contacte con soporte técnico proporcionando:
   - Captura de pantalla de la configuración (ocultando el token completo)
   - Descripción detallada del problema

### Problema 2: "Chat not found" o "Bot was blocked by the user"

**Causa:** El bot no puede enviar mensajes al chat especificado.

**Solución:**

1. Si es un Chat ID personal:
   - Busque su bot en Telegram por el nombre de usuario (ejemplo: `@phsbot_notificaciones_bot`)
   - Inicie la conversación presionando **"Start"**
   - Intente nuevamente

2. Si es un grupo:
   - Verifique que el bot esté en el grupo
   - Asegúrese de que el bot es administrador con permiso de enviar mensajes

### Problema 3: El Token Ha Expirado o Es Inválido

**Causa:** El token fue regenerado o el bot fue eliminado.

**Solución:**

1. Vaya a @BotFather en Telegram
2. Envíe `/mybots`
3. Seleccione su bot
4. Presione **"API Token"**
5. Si aparece un token diferente, cópielo y actualícelo en PHSBot
6. Si el bot no aparece en la lista, deberá crear uno nuevo desde el Paso 1

### Problema 4: Recibo Demasiadas Notificaciones

**Causa:** El umbral de scoring está muy bajo.

**Solución:**

1. Vaya a **PHSBot > Leads & Scoring**
2. Aumente el umbral de notificación (ejemplo: de 50 a 75)
3. Guarde los cambios

### Problema 5: Formato de Notificación Incorrecto

**Causa:** Incompatibilidad con el formato Markdown de Telegram.

**Solución:**

Este problema normalmente se resuelve automáticamente por el plugin. Si persiste:

1. Contacte con soporte técnico
2. Proporcione un ejemplo del mensaje que genera error
3. El equipo técnico ajustará el formato de las plantillas de notificación

---

## Preguntas Frecuentes

### ¿Puedo usar el mismo bot para varios sitios web?

**No es recomendable.** Aunque técnicamente es posible, recibirá notificaciones mezcladas de diferentes sitios sin poder diferenciarlas fácilmente.

**Mejor práctica:** Cree un bot diferente para cada sitio web y use grupos separados si tiene múltiples sitios.

### ¿Cuántos administradores pueden recibir notificaciones?

Si usa un **grupo de Telegram**, todos los miembros del grupo recibirán las notificaciones. No hay límite en el número de miembros.

Si usa **Chat ID personal**, solo esa persona recibirá las notificaciones.

**Recomendación:** Use un grupo para equipos de ventas o atención al cliente.

### ¿Las notificaciones tienen costo?

No. Las notificaciones de Telegram son completamente gratuitas. No hay límite en el número de mensajes que su bot puede enviar.

**Nota:** Los créditos de PHSBot se consumen por el uso del chatbot, no por las notificaciones de Telegram.

### ¿Puedo personalizar el mensaje de notificación?

Actualmente, el formato de las notificaciones está predefinido por el plugin. Si necesita personalización avanzada, contacte con el equipo de desarrollo para solicitudes especiales.

### ¿Qué pasa si cambio el Chat ID?

Si cambia el Chat ID en la configuración de PHSBot:

1. Las notificaciones dejarán de llegar al destino anterior
2. Comenzarán a llegar al nuevo destino inmediatamente
3. No afecta a los leads ya registrados en la base de datos

### ¿Puedo enviar notificaciones a múltiples destinos?

La configuración estándar permite un solo Chat ID. Si necesita enviar a múltiples destinos:

- **Opción 1:** Use un grupo que incluya a todas las personas
- **Opción 2:** Contacte con soporte para configuraciones avanzadas

### ¿El bot puede responder mensajes?

Por defecto, el bot solo **envía** notificaciones. No está configurado para recibir ni responder mensajes.

El objetivo del bot es notificar al equipo, quien luego contactará al lead por los medios tradicionales (teléfono, email).

---

## Seguridad y Mejores Prácticas

### Protección del Token

1. **Nunca comparta su token públicamente**
   - No lo suba a repositorios de código (GitHub, GitLab, etc.)
   - No lo publique en foros o redes sociales
   - No lo incluya en capturas de pantalla públicas

2. **Regenerar el token si se compromete:**
   - Vaya a @BotFather
   - Seleccione `/mybots` > su bot > **"Revoke current token"**
   - Se generará un nuevo token
   - Actualice inmediatamente la configuración en PHSBot

3. **Acceso al panel de WordPress:**
   - Solo usuarios administradores confiables deben tener acceso
   - Use contraseñas seguras para WordPress
   - Active autenticación de dos factores si es posible

### Privacidad de los Datos

1. **Datos sensibles en notificaciones:**
   - Las notificaciones pueden contener emails y teléfonos de clientes
   - Asegúrese de que todos los miembros del grupo cumplan con políticas de privacidad
   - Si usa grupos, informe a los miembros sobre la confidencialidad de la información

2. **Cumplimiento RGPD/GDPR:**
   - Informe a los visitantes en su sitio web que sus datos pueden ser procesados para contacto comercial
   - Incluya esta información en su política de privacidad
   - Respete los derechos de los usuarios a eliminar sus datos

3. **Retención de datos:**
   - Telegram almacena los mensajes según la configuración de cada chat
   - En grupos, considere eliminar mensajes antiguos periódicamente
   - Los datos en WordPress se gestionan desde el módulo Leads & Scoring

### Monitoreo y Mantenimiento

1. **Revise periódicamente:**
   - Que las notificaciones estén llegando correctamente
   - El umbral de scoring sigue siendo apropiado
   - Los miembros del grupo (si aplica) son los correctos

2. **Actualizaciones:**
   - Mantenga PHSBot actualizado a la última versión
   - Las actualizaciones pueden incluir mejoras en las notificaciones

3. **Documentación:**
   - Guarde esta guía en un lugar accesible para su equipo
   - Documente cualquier configuración personalizada que aplique

---

## Recursos Adicionales

### Enlaces Útiles

- **Documentación oficial de Telegram Bots:** https://core.telegram.org/bots
- **BotFather (crear bots):** https://t.me/botfather
- **Obtener Chat ID:** https://t.me/userinfobot
- **Soporte PHSBot:** https://bocetosmarketing.com/enviar-ticket/

### Comandos Útiles de BotFather

- `/newbot` - Crear un nuevo bot
- `/mybots` - Ver tus bots existentes
- `/setname` - Cambiar el nombre del bot
- `/setdescription` - Cambiar la descripción
- `/setuserpic` - Cambiar la foto del bot
- `/deletebot` - Eliminar un bot (irreversible)

### Soporte Técnico

Si después de seguir esta guía continúa teniendo problemas:

1. **Revise la sección "Solución de Problemas"** de este documento
2. **Consulte el Manual de Usuario Principal** para configuración general de PHSBot
3. **Envíe un ticket de soporte** con:
   - Descripción detallada del problema
   - Capturas de pantalla (ocultando datos sensibles)
   - Versión de PHSBot que está usando
   - Pasos que ya intentó para resolver el problema

**Sistema de tickets:** https://bocetosmarketing.com/enviar-ticket/

---

## Historial de Cambios

| Versión | Fecha | Cambios |
|---------|-------|---------|
| 1.0 | Diciembre 2025 | Versión inicial de la guía |

---

**© 2025 Bocetos Marketing - Todos los derechos reservados**

Esta guía es parte de la documentación oficial de PHSBot y puede ser actualizada sin previo aviso. Visite nuestro sitio web para obtener la versión más reciente.
