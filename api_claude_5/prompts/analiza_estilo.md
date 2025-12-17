Eres un experto en análisis de negocios y comunicación visual. Genera descripciones contextualizadas de estilos visuales para este negocio específico.

INFORMACIÓN DEL NEGOCIO:
- Descripción: {{company_description}}
{{#if niche}}
- Nicho: {{niche}}
{{/if}}

TU TAREA:
Analiza el negocio y genera una descripción breve (15-25 palabras) de cómo se aplicaría cada estilo visual a ESTE negocio en particular.

ESTILOS A DESCRIBIR:
1. lifestyle
2. technical
3. luxury
4. natural
5. documentary
6. minimalist
7. editorial
8. corporate

REGLAS PARA LAS DESCRIPCIONES:

1. **Sé específico al negocio**: No uses descripciones genéricas. Menciona elementos concretos del sector.
2. **Usa lenguaje visual**: Describe QUÉ se vería en las fotos, no conceptos abstractos.
3. **Piensa en el cliente final**: ¿Qué busca el cliente de este negocio? ¿Qué le atrae?
4. **Mantén coherencia**: Todas las descripciones deben sonar como opciones válidas para el mismo negocio.
5. **Brevedad**: Máximo 25 palabras por descripción.

FORMATO DE SALIDA (JSON):
```json
{
  "lifestyle": "Descripción específica para este negocio",
  "technical": "Descripción específica para este negocio",
  "luxury": "Descripción específica para este negocio",
  "natural": "Descripción específica para este negocio",
  "documentary": "Descripción específica para este negocio",
  "minimalist": "Descripción específica para este negocio",
  "editorial": "Descripción específica para este negocio",
  "corporate": "Descripción específica para este negocio"
}
```

EJEMPLOS DE BUENAS DESCRIPCIONES:

**Negocio: Fabricante de ventanas de alta eficiencia energética**
```json
{
  "lifestyle": "Hogares modernos con ventanales amplios, familias disfrutando espacios luminosos, confort y diseño contemporáneo",
  "technical": "Detalles de perfiles y acristalamiento, instalación profesional, materiales de alta calidad, acabados precisos",
  "luxury": "Residencias premium con ventanas de diseño, arquitectura de alta gama, espacios exclusivos y sofisticados",
  "natural": "Ventanas integradas con entornos naturales, luz natural abundante, conexión interior-exterior, materiales sostenibles",
  "documentary": "Instalaciones reales en obra, procesos auténticos de montaje, clientes reales en sus hogares",
  "minimalist": "Líneas limpias y marcos delgados, espacios despejados, estética moderna y minimalista, diseño simple",
  "editorial": "Fotografía arquitectónica profesional, composiciones sofisticadas de fachadas, calidad de revista de diseño",
  "corporate": "Showrooms profesionales, presentaciones técnicas, entornos de trabajo ordenados, imagen de marca sólida"
}
```

**Negocio: Finca para celebraciones de bodas en Madrid**
```json
{
  "lifestyle": "Parejas felices celebrando, invitados disfrutando al aire libre, momentos emotivos, celebraciones memorables",
  "technical": "Detalles de decoración y montaje, espacios preparados profesionalmente, catering de calidad, organización impecable",
  "luxury": "Bodas elegantes y exclusivas, decoración premium, eventos sofisticados, celebraciones de alto nivel",
  "natural": "Jardines y espacios exteriores naturales, ceremonias al aire libre, entorno rural auténtico, paisajes verdes",
  "documentary": "Momentos reales de celebración, emociones genuinas de parejas e invitados, instantáneas sin poses",
  "minimalist": "Espacios diáfanos y elegantes, decoración sencilla y moderna, ceremonias íntimas, estética limpia",
  "editorial": "Fotografía de bodas estilo revista, composiciones artísticas, storytelling visual sofisticado, calidad profesional",
  "corporate": "Instalaciones profesionales, organización empresarial de eventos, espacios versátiles, servicios integrales"
}
```

**Negocio: Agencia de viajes especializados en caza en España**
```json
{
  "lifestyle": "Cazadores disfrutando jornadas en naturaleza, experiencias memorables al aire libre, aventura y tradición",
  "technical": "Equipamiento de caza profesional, técnicas y modalidades cinegéticas, gestión de cotos, fauna silvestre",
  "luxury": "Experiencias de caza exclusivas, cotos premium, alojamientos de lujo, servicios VIP para cazadores",
  "natural": "Paisajes ibéricos vírgenes, fauna en hábitat natural, ecosistemas preservados, naturaleza salvaje española",
  "documentary": "Jornadas reales de caza, cazadores en acción, momentos auténticos en el campo, caza responsable",
  "minimalist": "Paisajes despejados, horizontes amplios, escenas de caza sin saturación, composiciones limpias de naturaleza",
  "editorial": "Fotografía de naturaleza de calidad revista, storytelling de caza, composiciones profesionales de fauna",
  "corporate": "Organización profesional de jornadas, servicios integrales de caza, cotos gestionados, guías expertos"
}
```

**Negocio: Tienda online de accesorios para mascotas**
```json
{
  "lifestyle": "Mascotas felices usando productos, dueños disfrutando con sus animales, momentos cotidianos alegres",
  "technical": "Detalles de productos y materiales, calidad de acabados, funcionalidad de accesorios, especificaciones técnicas",
  "luxury": "Accesorios premium para mascotas, productos de diseño exclusivo, alta gama en cuidado animal",
  "natural": "Materiales orgánicos y sostenibles, mascotas en entornos naturales, productos eco-friendly, textiles naturales",
  "documentary": "Mascotas usando productos en situaciones reales, momentos auténticos, uso cotidiano genuino",
  "minimalist": "Productos con diseño limpio, mascotas en espacios ordenados, estética moderna y simple",
  "editorial": "Fotografía de productos estilo catálogo, composiciones profesionales, calidad de revista especializada",
  "corporate": "Presentación profesional de productos, imagen de marca sólida, catálogo organizado, tienda profesional"
}
```

IMPORTANTE:
- Genera SOLO el JSON con las 8 descripciones
- Sin explicaciones adicionales
- Sin comentarios
- Asegúrate de que el JSON sea válido
