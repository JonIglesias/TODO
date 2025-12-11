# Manual de Usuario - PHSBot

**Versión del Plugin:** 1.4
**Fecha:** Diciembre 2024

---

## Índice

1. [Introducción](#introducción)
2. [Instalación y Activación](#instalación-y-activación)
3. [Configuración Inicial](#configuración-inicial)
4. [Módulos del Plugin](#módulos-del-plugin)
5. [Casos de Uso Prácticos](#casos-de-uso-prácticos)
6. [Solución de Problemas](#solución-de-problemas)
7. [Glosario de Términos](#glosario-de-términos)
8. [Soporte Técnico](#soporte-técnico)

---

## Introducción

### ¿Qué es PHSBot?

PHSBot es un plugin de WordPress que integra un chatbot de inteligencia artificial en su sitio web. El chatbot puede:

- **Responder preguntas** de sus visitantes en tiempo real
- **Aprender automáticamente** del contenido de su sitio web
- **Capturar leads** y datos de contacto de clientes potenciales
- **Integrarse con Telegram** para notificaciones instantáneas
- **Personalizar completamente** su apariencia y comportamiento

### ¿Cómo funciona?

PHSBot utiliza tecnología de inteligencia artificial avanzada para comprender las preguntas de sus visitantes y proporcionar respuestas precisas basadas en el contenido de su sitio web.

**El sistema funciona con créditos:**
- Cada conversación consume créditos según su complejidad
- Los créditos se renuevan mensualmente según su plan de suscripción
- Puede monitorear su consumo en tiempo real desde el panel de Estadísticas

---

## Instalación y Activación

### Paso 1: Instalación del Plugin

1. Descargue el archivo `phsbot.zip` que recibió tras su compra
2. Acceda al panel de WordPress → **Plugins → Añadir nuevo**
3. Haga clic en **Subir plugin** y seleccione el archivo ZIP
4. Haga clic en **Instalar ahora**
5. Una vez instalado, haga clic en **Activar**

### Paso 2: Obtener su Licencia

Su licencia se genera automáticamente tras la compra y está disponible en:

- **Email de confirmación** de compra
- **Mi Cuenta → Suscripciones** en el sitio de Bocetos Marketing

La licencia tiene el formato: `BOT-XXXX-XX-XXXX-XXXXXXXX`

### Paso 3: Activar la Licencia

1. Vaya a **PHSBot → Configuración → Conexiones**
2. Pegue su clave de licencia en el campo **Licencia PHSBot**
3. Haga clic en **Validar Licencia**
4. Si es correcta, verá un mensaje de confirmación en verde

**IMPORTANTE:** La licencia se vincula automáticamente al dominio de su sitio web en el primer uso.

---

## Configuración Inicial

### 1. Configuración Básica (Pestaña Conexiones)

#### Licencia PHSBot
- **Campo:** Licencia BOT
- **Formato:** BOT-XXXX-XX-XXXX-XXXXXXXX
- **Acción:** Introduzca la licencia y haga clic en "Validar Licencia"

#### Telegram (Opcional)
Si desea recibir notificaciones de leads de alta calidad:

- **Token del Bot:** Consulte la [Guía de Telegram](GUIA_TELEGRAM.md)
- **ID del Chat:** ID del chat, usuario o canal donde recibirá notificaciones

#### WhatsApp (Opcional)
- **Teléfono:** Número en formato internacional (ej: +34612345678)
- **Uso:** Botón de enlace para que los visitantes le contacten directamente

### 2. Configuración del Chat (Pestaña Chat IA)

#### Modelo de IA
- **Recomendado:** Dejar en automático
- **Descripción:** El sistema selecciona el mejor modelo disponible

#### Temperatura (0.0 - 2.0)
- **Valor por defecto:** 0.5
- **Baja (0.0-0.5):** Respuestas más precisas y conservadoras
- **Alta (1.0-2.0):** Respuestas más creativas y variadas

#### Mensaje de Bienvenida
- **Ejemplo:** "¡Hola! Soy el asistente virtual de [Su Empresa]. ¿En qué puedo ayudarte?"
- **Consejo:** Personalícelo con el tono de su marca

#### Prompt del Sistema (Avanzado)
Instrucciones que definen cómo debe comportarse el chatbot. Ejemplo:

```
Eres el asistente virtual de [Nombre Empresa], especializada en [sector].
Tu objetivo es ayudar a los usuarios a encontrar información sobre nuestros
productos/servicios y resolver sus dudas de forma amable y profesional.
```

#### Opciones de Contenido
- **Permitir HTML en respuestas:** ✓ (Recomendado)
- **Permitir shortcodes Elementor:** ✓ (Si usa Elementor)
- **Traer contenido de página actual:** ✓ (Mejora precisión)

#### Límites
- **Máx. mensajes en historial:** 10 (Recomendado)
- **Máx. tokens por respuesta:** 1400 (Recomendado)

### 3. Configuración de Aspecto

#### Posición del Chat
- **Abajo derecha** (Recomendado para la mayoría de sitios)
- **Abajo izquierda**

#### Dimensiones
- **Ancho:** 360px (Valor por defecto)
- **Alto:** 520px (Valor por defecto)

#### Colores

**Paletas Predefinidas:**
1. **Manual:** Configure cada color individualmente
2. **PHS Dark:** Tonos oscuros vino/arena
3. **PHS Light:** Tonos claros vino/arena
4. **Forest:** Tonos verdes
5. **Desert:** Tonos ocres

**Colores Personalizables:**
- **Color Primario (Cabecera):** Color de la barra superior del chat
- **Color Secundario (Hovers):** Color al pasar el ratón sobre botones
- **Fondo del Chat:** Color de fondo del área de conversación
- **Texto General:** Color del texto
- **Burbuja del Bot:** Color de fondo de mensajes del bot
- **Burbuja del Usuario:** Color de fondo de mensajes del usuario
- **Color Footer:** Color del pie del chat

**Tamaños:**
- **Tamaño de fuente (burbujas):** 12-22 px (Recomendado: 15px)

---

## Módulos del Plugin

### 1. Base de Conocimiento (KB)

#### ¿Qué es?

La Base de Conocimiento es el "cerebro" de su chatbot. El sistema analiza automáticamente su sitio web y genera un documento maestro con la información más relevante que el chatbot utilizará para responder.

#### ¿Cómo funciona?

1. El sistema **rastrea automáticamente** las páginas de su sitio
2. **Extrae el contenido** más relevante (productos, servicios, precios, etc.)
3. **Procesa la información** con IA para crear un documento estructurado
4. El chatbot **utiliza este documento** como fuente de conocimiento

#### Generar la Base de Conocimiento

1. Vaya a **PHSBot → Base de Conocimiento**
2. Haga clic en **Generar documento**
3. **Espere 2-3 minutos** (el sistema le mostrará el progreso)
4. Una vez completado, podrá **ver y editar** el documento generado

**IMPORTANTE:**
- El proceso puede tardar varios minutos dependiendo del tamaño de su sitio
- No cierre la ventana mientras se genera
- Si el proceso falla, solicite asistencia técnica

#### Editar la Base de Conocimiento

El documento generado es **totalmente editable**:

1. **Revise** el contenido generado automáticamente
2. **Añada información** que no esté en el sitio:
   - Precios actualizados
   - Promociones vigentes
   - Políticas comerciales
   - Horarios de atención
   - Datos de contacto
3. **Elimine** información irrelevante o desactualizada
4. Haga clic en **Guardar documento**

#### Configuración Avanzada (Modo Admin)

Para acceder al modo admin, añada `/?admin` al final de la URL:
```
https://su-sitio.com/wp-admin/admin.php?page=phsbot-kb&admin
```

**Opciones disponibles:**
- **Override de dominio:** Rastrear un subdominio específico
- **Modelo de IA:** Seleccionar modelo específico
- **Prompt personalizado:** Cambiar instrucciones de generación
- **Dominios adicionales:** Incluir contenido de otros sitios
- **Límites de rastreo:**
  - Máximo de URLs (default: 80)
  - Máximo de páginas (default: 50)
  - Máximo de posts (default: 20)

#### URLs Excluidas Automáticamente

El sistema NO rastrea:
- Páginas de administración (wp-admin, wp-login)
- Carrito y checkout
- Mi cuenta y páginas de usuario
- Categorías y etiquetas
- Archivos adjuntos (PDFs, imágenes, videos)
- URLs con parámetros UTM
- Páginas paginadas duplicadas

#### Nota Técnica

**Para sitios con gran volumen de contenido:**

Si su sitio tiene más de 100 páginas, el proceso de análisis puede exceder el tiempo límite del servidor. En ese caso:

1. Divida el proceso en varias generaciones
2. Use la opción "Dominios adicionales" estratégicamente
3. O solicite asistencia técnica a través de nuestro sistema de tickets

---

### 2. Leads & Scoring

#### ¿Qué son los Leads?

Los leads son visitantes que han mostrado **interés activo** en sus productos o servicios a través del chat. El sistema captura y analiza automáticamente estas interacciones.

#### Sistema de Puntuación (Scoring)

Cada lead recibe una **puntuación de 0 a 10** basada en:

| Criterio | Puntos |
|----------|--------|
| **Detección de teléfono** | Automático (envía notificación) |
| **Intención de compra detectada** | +5 puntos |
| **Email proporcionado** | +2 puntos |
| **Nombre proporcionado** | +1 punto |
| **Preguntas sobre precio** | +1 punto |
| **Urgencia en el mensaje** | +1 punto |

#### Información Capturada

Para cada lead, el sistema guarda:

- **Nombre** (si se proporciona)
- **Email** (extraído automáticamente)
- **Teléfono** (extraído automáticamente)
- **Puntuación** (0-10)
- **Página** donde se contactó
- **Historial completo** de mensajes
- **Fecha y hora** del primer contacto
- **Última actividad**

#### Visualizar Leads

1. Vaya a **PHSBot → Leads & Scoring**
2. Verá la lista de todos los leads capturados
3. Use los filtros para:
   - Ver solo leads de alta puntuación
   - Buscar por email o teléfono
   - Ordenar por fecha o puntuación

#### Notificaciones de Telegram

**Configuración:**

1. Configure su bot de Telegram (ver [Guía de Telegram](GUIA_TELEGRAM.md))
2. Vaya a **Leads & Scoring → Configuración**
3. Configure el **Umbral de Telegram** (recomendado: 7-8)
4. Cuando un lead alcance ese umbral, recibirá notificación instantánea

**Contenido de la notificación:**
```
🔔 Nuevo Lead de Alta Calidad

👤 Nombre: Juan Pérez
📧 Email: juan@ejemplo.com
📱 Teléfono: +34 612 345 678
⭐ Puntuación: 8/10
📄 Página: /productos/servicio-premium
```

#### Gestión de Leads

**Acciones disponibles:**
- **Ver detalles:** Click en el lead para ver historial completo
- **Marcar como cerrado:** Lead ya contactado
- **Eliminar:** Borrar lead del sistema
- **Exportar:** (Próximamente) Exportar a CSV

---

### 3. Estadísticas

#### Panel de Estadísticas

Acceda a **PHSBot → Estadísticas** para ver métricas detalladas de uso.

#### Métricas Disponibles

**Panel Principal (3 Cards):**

1. **Información del Plan**
   - Nombre del plan actual
   - Límite mensual de créditos
   - Fecha de renovación
   - Días restantes

2. **Gráfico de Evolución**
   - Mensajes por día
   - Créditos consumidos por día
   - Período seleccionable (7, 30, 60, 90 días)

3. **Créditos Disponibles**
   - Visualización con animación de líquido
   - Muestra créditos restantes en tiempo real
   - Cambia de color según disponibilidad

#### Períodos de Consulta

- **Período actual de facturación** (Recomendado)
- Últimos 7 días
- Últimos 30 días
- Últimos 60 días
- Últimos 90 días

#### Detalle de Operaciones

Tabla con desglose por tipo:

| Operación | Descripción | Créditos Típicos |
|-----------|-------------|------------------|
| 💬 Chat de Usuario | Conversaciones normales | Variable |
| 🌐 Traducción de Bienvenida | Traducción automática | Bajo |
| 📚 Generación de KB | Creación base conocimiento | Alto |
| 📋 Listado de Modelos | Actualización de modelos | Muy bajo |

#### Nota sobre Créditos

**¿Qué es un crédito?**
- 1 crédito = 10,000 tokens de procesamiento
- El consumo varía según la complejidad de la pregunta y la respuesta
- Conversaciones simples consumen menos créditos
- Consultas complejas con contexto extenso consumen más

---

### 4. Inyecciones (Triggers)

#### ¿Qué son las Inyecciones?

Las inyecciones permiten **mostrar contenido automático** cuando el usuario escribe determinadas palabras clave en el chat.

#### Casos de Uso

**Ejemplos prácticos:**

1. **Formulario de Contacto**
   - Palabra clave: "contacto, presupuesto"
   - Acción: Mostrar formulario de Elementor

2. **Video Explicativo**
   - Palabra clave: "tutorial, como funciona"
   - Acción: Insertar video de YouTube

3. **Tabla de Precios**
   - Palabra clave: "precio, tarifa, coste"
   - Acción: Mostrar HTML con tabla de precios

4. **Promoción Activa**
   - Palabra clave: "oferta, descuento, promoción"
   - Acción: Mostrar shortcode con banner promocional

#### Crear una Inyección

1. Vaya a **PHSBot → Inyecciones**
2. Haga clic en **Añadir regla**
3. Configure los campos:

**Campos de la regla:**

- **Activado:** ✓ (marcar para activar)
- **Palabras clave:** `precio,tarifa,coste` (separadas por comas)
- **Coincidencia:**
  - **Any (Cualquiera):** Si escribe UNA de las palabras
  - **All (Todas):** Solo si escribe TODAS las palabras
- **Tipo de contenido:**
  - **HTML:** Código HTML personalizado
  - **Shortcode:** Shortcode de WordPress/Elementor
  - **Video YouTube:** URL de YouTube
- **Posición:**
  - **Antes:** Muestra ANTES de la respuesta del bot
  - **Después:** Muestra DESPUÉS de la respuesta del bot
  - **Solo trigger:** SOLO muestra el contenido (sin respuesta del bot)

**Ejemplo de HTML:**
```html
<div style="padding: 15px; background: #f0f0f0; border-radius: 8px;">
  <h3>Nuestras Tarifas</h3>
  <ul>
    <li>Plan Básico: 29€/mes</li>
    <li>Plan Pro: 79€/mes</li>
    <li>Plan Enterprise: 199€/mes</li>
  </ul>
  <p><a href="/contacto">Solicitar presupuesto personalizado</a></p>
</div>
```

**Ejemplo de Shortcode:**
```
[elementor-template id="123"]
```

**Ejemplo de Video:**
```
https://www.youtube.com/watch?v=ABC123XYZ
```

4. Haga clic en **Guardar**

#### Gestionar Inyecciones

- **Editar:** Click en "Editar" en la fila de la inyección
- **Desactivar:** Desmarque "Activado" para pausar sin borrar
- **Eliminar:** Click en "Eliminar" y confirme
- **Borrar múltiples:** Seleccione varias y click en "Borrar seleccionados"

---

### 5. Chat & Widget (Configuración Avanzada)

#### Personalización Visual

La configuración visual del chat se realiza en **PHSBot → Configuración → Aspecto**.

Todas las opciones están explicadas en la sección [Configuración de Aspecto](#3-configuración-de-aspecto).

#### Shortcode para Embeber

Si desea embeber el chat en una página específica (no flotante):

```
[phsbot]
```

**Parámetros opcionales:**
```
[phsbot position="inline" width="100%" height="600px"]
```

#### Activar/Desactivar el Chat

Para desactivar temporalmente el chat en el sitio:

1. Vaya a **PHSBot → Configuración → Conexiones**
2. Desmarque "Activar chatbot en el sitio"
3. Guarde cambios

---

## Casos de Uso Prácticos

### Caso 1: E-commerce con Captura de Leads

**Configuración:**
1. **Base de Conocimiento:** Genere KB con productos y precios
2. **Inyecciones:** Cree trigger para "precio" que muestre tabla de precios
3. **Leads:** Configure Telegram con umbral 8
4. **Prompt del sistema:**
```
Eres el asistente de [Tu Tienda]. Ayuda a los clientes a encontrar productos,
explica características y anima a contactar para compras personalizadas.
```

**Resultado:**
- Chatbot responde preguntas sobre productos
- Captura emails y teléfonos automáticamente
- Notifica vía Telegram cuando hay alta intención de compra

### Caso 2: Servicios Profesionales

**Configuración:**
1. **Base de Conocimiento:** Genere KB con servicios y casos de éxito
2. **Inyecciones:**
   - "presupuesto" → Formulario de contacto
   - "portfolio" → Galería de trabajos (shortcode)
3. **Aspecto:** Colores corporativos de la marca
4. **Prompt del sistema:**
```
Eres el asistente de [Tu Empresa], especializada en [servicios].
Explica nuestros servicios, comparte casos de éxito y facilita el contacto
para presupuestos personalizados.
```

**Resultado:**
- Chatbot presenta servicios profesionalmente
- Muestra portfolio cuando se pregunta
- Facilita solicitud de presupuesto

### Caso 3: Blog o Sitio de Contenido

**Configuración:**
1. **Base de Conocimiento:** Rastree artículos del blog
2. **Inyecciones:** "suscribir" → Formulario de newsletter
3. **Aspecto:** Diseño minimalista acorde al blog
4. **Prompt del sistema:**
```
Eres el asistente del blog [Nombre]. Ayuda a los lectores a encontrar
artículos relevantes según sus intereses y recomienda contenido relacionado.
```

**Resultado:**
- Chatbot recomienda artículos relevantes
- Capta suscriptores para newsletter
- Mejora el tiempo de permanencia en el sitio

---

## Solución de Problemas

### El chat no aparece en mi sitio

**Verificaciones:**

1. **¿El plugin está activado?**
   - WordPress → Plugins → Busque "PHSBot" → Debe estar activado

2. **¿El chat está activado en configuración?**
   - PHSBot → Configuración → Conexiones
   - Verifique que "Activar chatbot en el sitio" está marcado

3. **¿La licencia es válida?**
   - PHSBot → Configuración → Conexiones
   - Haga clic en "Validar Licencia"
   - Debe mostrar mensaje verde de confirmación

4. **¿Hay conflictos con el tema o plugins?**
   - Desactive temporalmente otros plugins
   - Cambie a un tema predeterminado (Twenty Twenty-Three)
   - Verifique si el chat aparece

5. **¿El caché está limpio?**
   - Limpie caché de WordPress
   - Limpie caché del navegador (Ctrl+F5)

### El chatbot no responde correctamente

**Verificaciones:**

1. **¿Tiene créditos disponibles?**
   - PHSBot → Estadísticas
   - Verifique que tiene créditos en el widget de líquido

2. **¿La Base de Conocimiento está generada?**
   - PHSBot → Base de Conocimiento
   - Debe haber contenido en el editor
   - Si no, haga clic en "Generar documento"

3. **¿El contenido del KB es relevante?**
   - Revise y edite el documento de KB
   - Añada información específica que falta
   - Guarde los cambios

4. **¿El prompt del sistema es claro?**
   - PHSBot → Configuración → Chat (IA)
   - Revise las instrucciones del prompt
   - Sea específico sobre el comportamiento deseado

### No se generan leads

**Verificaciones:**

1. **¿Los visitantes proporcionan datos?**
   - El sistema solo captura lo que los usuarios escriben
   - Anime a los visitantes a compartir email/teléfono

2. **¿El scoring funciona?**
   - PHSBot → Leads & Scoring
   - Verifique que aparecen leads aunque sea con puntuación baja

3. **¿El threshold de Telegram es muy alto?**
   - Si está en 9-10, pocas conversaciones lo alcanzarán
   - Pruebe reducirlo temporalmente a 7

### No recibo notificaciones de Telegram

**Verificaciones:**

1. **¿El token del bot es correcto?**
   - Vaya a BotFather en Telegram
   - Verifique que el token es exactamente el mismo

2. **¿El chat ID es correcto?**
   - Use el bot @userinfobot para obtener su ID
   - Pegue el número exacto (puede ser negativo)

3. **¿El bot tiene permisos?**
   - Si es un canal/grupo, agregue el bot como administrador
   - Envíe un mensaje al bot primero (`/start`)

4. **¿El umbral es alcanzable?**
   - Pruebe con umbral bajo (5-6) temporalmente
   - Verifique que llegan notificaciones

### Error al generar Base de Conocimiento

**Posibles causas:**

1. **Timeout del servidor**
   - Sitios muy grandes pueden exceder el límite de tiempo
   - Solicite asistencia técnica para solución personalizada

2. **Sin créditos disponibles**
   - Generar KB consume créditos
   - Verifique saldo en Estadísticas

3. **Problema de conectividad**
   - Verifique conexión a internet del servidor
   - Pruebe nuevamente en unos minutos

**Solución:**
- Si el error persiste, abra un ticket de soporte con:
  - Captura de pantalla del error
  - URL de su sitio
  - Número de páginas aproximado

### El chat se ve mal en móvil

**Verificaciones:**

1. **¿El módulo mobile_patch está activo?**
   - Este módulo optimiza automáticamente para móviles
   - Está activado por defecto

2. **¿Las dimensiones son apropiadas?**
   - En móvil, el chat se adapta automáticamente
   - No use width/height fijas muy grandes

3. **¿Hay conflicto con CSS del tema?**
   - Inspeccione con herramientas de desarrollo
   - Puede necesitar CSS personalizado

---

## Glosario de Términos

### Crédito
Unidad de consumo del servicio. 1 crédito = 10,000 tokens de procesamiento. Los créditos se consumen en cada conversación según su complejidad.

### Token
Unidad de medida de texto procesado por la IA. Aproximadamente 4 caracteres = 1 token. Un mensaje típico de 100 palabras ≈ 130 tokens.

### Base de Conocimiento (KB)
Documento maestro que contiene la información de su sitio web procesada y estructurada para que el chatbot la utilice al responder.

### Lead
Visitante que ha mostrado interés activo en sus productos/servicios y ha proporcionado información de contacto (email, teléfono, nombre).

### Scoring
Sistema de puntuación automática de leads de 0 a 10 basado en la intención de compra y datos proporcionados.

### Inyección (Trigger)
Contenido que se muestra automáticamente cuando el usuario escribe determinadas palabras clave.

### Shortcode
Código corto de WordPress que se reemplaza por contenido dinámico. Formato: `[nombre-shortcode parámetros]`

### Temperatura
Parámetro que controla la creatividad de las respuestas. Valores bajos = respuestas más precisas. Valores altos = respuestas más creativas.

### Prompt del Sistema
Instrucciones que definen la personalidad y comportamiento del chatbot.

### Threshold (Umbral)
Puntuación mínima que debe alcanzar un lead para disparar una notificación automática.

### Crawler
Sistema automatizado que rastrea las páginas de su sitio web para extraer contenido.

### Widget
Elemento visual flotante o embebido que muestra el chat en su sitio.

### Elementor
Constructor visual de páginas para WordPress muy popular. PHSBot es compatible con sus shortcodes.

---

## Soporte Técnico

### ¿Necesita Ayuda?

Si tiene problemas que no se resuelven con este manual:

1. **Revise primero** la sección [Solución de Problemas](#solución-de-problemas)
2. **Consulte** el glosario de términos si no entiende algún concepto
3. **Abra un ticket** de soporte técnico

### Sistema de Tickets

**URL:** https://bocetosmarketing.com/enviar-ticket/

**Al abrir un ticket, incluya:**
- **Descripción clara** del problema
- **Pasos** que ha realizado
- **Capturas de pantalla** del error (si hay)
- **URL de su sitio web**
- **Número de licencia** BOT-XXXX

**Tiempo de respuesta:**
- Tickets urgentes: 24-48 horas
- Tickets normales: 48-72 horas
- Consultas generales: 3-5 días laborables

### Recursos Adicionales

- **Guía de Telegram:** [GUIA_TELEGRAM.md](GUIA_TELEGRAM.md)
- **Actualizaciones:** Las actualizaciones se instalan automáticamente desde WordPress

---

## Información Legal

### Privacidad y Datos

- Los datos de conversaciones se almacenan en su servidor WordPress
- Los mensajes se envían a la API de procesamiento para generar respuestas
- Los leads capturados son almacenados localmente en su base de datos
- Cumplimiento GDPR: Responsabilidad del propietario del sitio

### Licencia y Uso

- La licencia es personal e intransferible
- Válida para un único dominio
- Renovación automática mensual según plan contratado
- Cancelación desde Mi Cuenta → Suscripciones

---

**© 2024 Bocetos Marketing - Todos los derechos reservados**

*Este documento es propiedad de Bocetos Marketing. Prohibida su reproducción sin autorización.*

---

**Versión del Manual:** 1.0
**Última actualización:** Diciembre 2024
**Plugin:** PHSBot v1.4
