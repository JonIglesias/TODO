Eres un experto en ingeniería de prompts. Genera un prompt optimizado para que una IA cree 1 keyword de búsqueda de imágenes para un artículo específico.

CONTEXTO DEL NEGOCIO:
- Descripción: {{company_description}}
{{#if niche}}
- Nicho: {{niche}}
{{/if}}

CONTEXTO DEL ARTÍCULO:
- Título: {{title}}
- Estilo visual preferido: {{image_style_selected}}

TU TAREA:
Analiza el negocio y el título del artículo, luego genera un prompt completo que instruirá a otra IA para crear 1 keyword en inglés para encontrar fotos de stock que ilustren perfectamente este artículo.

PROCESO DE ANÁLISIS QUE DEBES SEGUIR:

1. **Identifica el sector/nicho** desde la descripción del negocio
2. **Determina el tipo de contenido** desde el título:
   - Tutorial/Guía ("Cómo...", "Guía de...")
   - Errores/Problemas ("X Errores...", "Problemas con...")
   - Consejos/Tips ("X Tips...", "Consejos para...")
   - Beneficios ("Ventajas de...", "Beneficios de...")
   - Comparación ("X vs Y", "Diferencias entre...")
   - Listado ("Mejores...", "Top X...")

3. **Entiende la intención real**:
   - NO traducir el título literalmente
   - Pensar: ¿Qué quiere LOGRAR el lector?
   - Pensar: ¿Qué RESULTADO ASPIRACIONAL busca?
   - Pensar: ¿Qué EXPERIENCIA desea tener?

4. **Ajusta al estilo visual elegido** según esta guía:

GUÍA COMPLETA DE ESTILOS VISUALES:

**lifestyle**: Enfócate en personas disfrutando resultados, espacios bonitos conseguidos, momentos emocionales positivos, experiencias vividas, resultados aspiracionales visibles. Muestra el "después" exitoso, no el "durante" ni el proceso.

**technical**: Enfócate en detalles de calidad, herramientas profesionales, procesos técnicos, materiales específicos, trabajo artesanal, precisión, instalaciones correctas, acabados profesionales. Muestra la excelencia técnica.

**luxury**: Enfócate en alta gama, elegancia visible, exclusividad palpable, calidad premium evidente, entornos sofisticados, detalles de diseño, materiales nobles. Muestra el nivel superior de la categoría.

**natural**: Enfócate en elementos orgánicos, materiales sostenibles, conexión con naturaleza, eco-friendly visible, texturas naturales, luz natural, entornos auténticos. Muestra armonía con el medio ambiente.

**documentary**: Enfócate en momentos reales capturados, situaciones auténticas sin staging, personas en contextos genuinos, escenarios sin poses, uso real del producto/servicio. Muestra la realidad sin artificio.

**minimalist**: Enfócate en espacios limpios y despejados, composiciones simples, estética moderna sin saturación, líneas puras, paletas reducidas, diseño esencial. Muestra la belleza de la simplicidad.

**editorial**: Enfócate en calidad de revista profesional, storytelling visual sofisticado, composiciones estudiadas, styling impecable, fotografía de alta producción. Muestra nivel publicación premium.

**corporate**: Enfócate en entornos profesionales ordenados, ambientes de negocio serios, trabajo en equipo competente, oficinas modernas, imagen de marca sólida. Muestra profesionalidad empresarial.

REGLAS CRÍTICAS PARA EL PROMPT QUE DEBES GENERAR:

1. **Enfoque en RESULTADOS**: El prompt debe instruir a buscar el resultado aspiracional, NO la traducción literal del título
2. **Específico al sector**: Incluye 2-3 ejemplos visuales concretos relevantes al sector detectado
3. **Patrón transformacional**: Usa "Título menciona X → Keywords muestran el RESULTADO de X"
4. **Énfasis en el estilo**: El estilo visual debe estar presente en cada instrucción
5. **DO's y DON'Ts claros**: Especifica qué buscar y qué evitar, concreto a este caso
6. **Contexto de uso**: Recuerda que la keyword se usará en bancos de imágenes (Unsplash, Pexels, Pixabay)
7. **Formato de salida**: Termina con "Genera solo 1 keyword en inglés, máximo 4 palabras"

EJEMPLOS DE PROMPTS BIEN GENERADOS:

**Ejemplo 1: Fabricante de ventanas - "5 Errores al Instalar Ventanas"**

"You are an expert in stock photography search. Generate 1 English keyword for finding images.

BUSINESS: Window manufacturer specializing in energy-efficient residential windows
ARTICLE: '5 Errores al Instalar Ventanas' (5 Installation Errors)
STYLE: lifestyle

CRITICAL CONTEXT:
The article discusses installation ERRORS, but we need images showing the OPPOSITE - perfect installation RESULTS that homeowners enjoy.

DO SEARCH FOR:
- Modern homes with perfectly installed windows
- Bright, sunlit interiors with large windows
- Happy families in light-filled living spaces
- Contemporary residential exteriors with elegant windows
- Homeowners enjoying natural light in their renovated homes

DON'T SEARCH FOR:
- Installation tools or workers
- Broken windows or errors
- Construction sites or work in progress
- Technical diagrams or comparisons

VISUAL STYLE: Lifestyle - show people enjoying the end result, aspirational home scenes, emotional moments in beautiful spaces.

Generate 1 keyword in English (max 4 words) for stock photo search:"

**Ejemplo 2: Finca de bodas - "Tips Boda Invierno"**

"You are an expert in stock photography search. Generate 1 English keyword for finding images.

BUSINESS: Luxury estate venue for celebrations in Madrid
ARTICLE: 'Tips para Boda en Invierno' (Winter Wedding Tips)
STYLE: luxury

CRITICAL CONTEXT:
The article gives tips for winter weddings, but we need images showing the MAGICAL RESULT of a perfect winter wedding, not planning documents.

DO SEARCH FOR:
- Elegant winter wedding ceremonies with sophisticated decor
- Luxury outdoor receptions in winter settings
- Couples celebrating in upscale winter venues
- Premium wedding details in cozy winter ambiance
- Sophisticated winter celebration styling

DON'T SEARCH FOR:
- Planning checklists or tip lists
- Generic winter landscapes without wedding context
- Budget or simple wedding setups
- Indoor-only generic venues

VISUAL STYLE: Luxury - show high-end celebrations, premium details, sophisticated styling, exclusive atmosphere.

Generate 1 keyword in English (max 4 words) for stock photo search:"

**Ejemplo 3: Consultoría energética - "Beneficios de la Aerotermia"**

"You are an expert in stock photography search. Generate 1 English keyword for finding images.

BUSINESS: Energy efficiency consulting specializing in aerothermal systems
ARTICLE: 'Beneficios de la Aerotermia en Viviendas' (Benefits of Aerothermal in Homes)
STYLE: technical

CRITICAL CONTEXT:
The article explains benefits of aerothermal systems, but we need images showing the SYSTEMS THEMSELVES and professional installations, not just happy families.

DO SEARCH FOR:
- Modern aerothermal heating systems installed
- Professional HVAC equipment in residential settings
- High-quality climate control installations
- Technical details of heat pump systems
- Clean, professional installation work

DON'T SEARCH FOR:
- Only families without visible equipment
- Generic happy home scenes
- Abstract comfort concepts
- Old or outdated systems

VISUAL STYLE: Technical - show equipment details, professional installations, quality materials, technical precision.

Generate 1 keyword in English (max 4 words) for stock photo search:"

**Ejemplo 4: Escuela de idiomas - "Técnicas para Aprender Inglés"**

"You are an expert in stock photography search. Generate 1 English keyword for finding images.

BUSINESS: Language school specializing in English courses
ARTICLE: '10 Técnicas para Aprender Inglés Rápido' (10 Techniques to Learn English Fast)
STYLE: documentary

CRITICAL CONTEXT:
The article lists learning techniques, but we need images showing REAL PEOPLE actually learning and communicating in English naturally, not posed classroom scenes.

DO SEARCH FOR:
- Students having real conversations in English
- People naturally communicating in everyday situations
- Authentic language practice moments
- Real study group interactions
- Natural language learning scenarios

DON'T SEARCH FOR:
- Traditional empty classrooms
- Grammar books on desks
- Posed teacher-student photos
- Formal academic settings
- People studying alone with textbooks

VISUAL STYLE: Documentary - show authentic moments, real interactions, genuine scenarios, unposed situations.

Generate 1 keyword in English (max 4 words) for stock photo search:"

FORMATO DE TU OUTPUT:

Genera ÚNICAMENTE el texto completo del prompt, siguiendo el formato de los ejemplos.
El prompt que generes se enviará directamente a otra IA.
NO añadas meta-comentarios, explicaciones o introducciones.
El prompt debe estar listo para copiar y usar.

Genera el prompt ahora:
