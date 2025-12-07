<?php
if (!defined('ABSPATH')) exit;

/**
 * Helper para obtener nichos desde la base de datos
 */

/**
 * Obtiene todos los nichos desde la base de datos
 *
 * @return array Array de nombres de nichos
 */
function ap_get_nichos(): array {
    global $wpdb;
    $table = $wpdb->prefix . 'ap_nichos';

    // Verificar si la tabla existe
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;

    if (!$table_exists) {
        // Fallback a lista por defecto si la tabla no existe
        return ap_get_nichos_default();
    }

    $results = $wpdb->get_col("SELECT name FROM $table ORDER BY category, name");

    if (empty($results)) {
        // Fallback a lista por defecto si no hay datos
        return ap_get_nichos_default();
    }

    return $results;
}

/**
 * Obtiene los nichos agrupados por categoría
 *
 * @return array Array asociativo [categoria => [nichos]]
 */
function ap_get_nichos_by_category(): array {
    global $wpdb;
    $table = $wpdb->prefix . 'ap_nichos';

    // Verificar si la tabla existe
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;

    if (!$table_exists) {
        return [];
    }

    $results = $wpdb->get_results("SELECT name, category FROM $table ORDER BY category, name");

    $grouped = [];
    foreach ($results as $row) {
        $category = $row->category ?: 'Otros';
        if (!isset($grouped[$category])) {
            $grouped[$category] = [];
        }
        $grouped[$category][] = $row->name;
    }

    return $grouped;
}

/**
 * Lista de nichos por defecto (fallback)
 *
 * @return array
 */
function ap_get_nichos_default(): array {
    return [
        // Salud y Bienestar
        'Medicina General', 'Nutrición y Dietética', 'Fitness y Gimnasio', 'Yoga', 'Pilates', 'Salud Mental',
        'Psicología', 'Fisioterapia', 'Pediatría', 'Geriatría', 'Odontología', 'Oftalmología', 'Dermatología',
        'Medicina Estética', 'Spa y Belleza', 'Quiropráctica', 'Acupuntura', 'Naturopatía',

        // Tecnología
        'Inteligencia Artificial', 'Blockchain', 'Ciberseguridad', 'Desarrollo Web', 'Desarrollo Móvil',
        'Apps iOS', 'Apps Android', 'Gaming', 'E-sports', 'Cloud Computing', 'DevOps', 'Data Science',
        'Machine Learning', 'IoT', 'Realidad Virtual', 'Realidad Aumentada', 'Software as a Service',
        'Hardware', 'Semiconductores', 'Robótica', 'Automatización',

        // Negocios y Finanzas
        'Marketing Digital', 'SEO', 'SEM', 'Redes Sociales', 'Email Marketing', 'Content Marketing',
        'E-commerce', 'Dropshipping', 'Amazon FBA', 'Shopify', 'Recursos Humanos', 'Contabilidad',
        'Auditoría', 'Consultoría Empresarial', 'Startups', 'Venture Capital', 'Finanzas Personales',
        'Inversión en Bolsa', 'Criptomonedas', 'Trading', 'Forex', 'Bienes Raíces', 'Crowdfunding',
        'Seguros', 'Banca', 'Planificación Financiera',

        // Legal
        'Abogacía', 'Derecho Penal', 'Derecho Civil', 'Derecho Laboral', 'Derecho Mercantil',
        'Propiedad Intelectual', 'Derecho Fiscal', 'Derecho Inmobiliario', 'Notaría',

        // Educación
        'Online Learning', 'E-learning', 'Formación Profesional', 'Universidad', 'MBA',
        'Idiomas', 'Inglés', 'Español', 'Francés', 'Alemán', 'Chino', 'Tutorías', 'Coaching',
        'Mentoring', 'Desarrollo Personal', 'Liderazgo',

        // Creatividad y Diseño
        'Diseño Gráfico', 'Diseño Web', 'UX/UI Design', 'Ilustración', 'Animación', 'Motion Graphics',
        'Fotografía', 'Fotografía de Boda', 'Fotografía de Producto', 'Video', 'Edición de Video',
        'Producción Audiovisual', 'Música', 'Producción Musical', 'DJ', 'Arte', 'Arte Digital',
        'Escritura Creativa', 'Copywriting', 'Guión', 'Publicidad',

        // Estilo de Vida
        'Viajes', 'Turismo', 'Hoteles', 'Gastronomía', 'Restaurantes', 'Chef', 'Cocina',
        'Repostería', 'Moda', 'Moda Masculina', 'Moda Femenina', 'Moda Infantil', 'Belleza',
        'Peluquería', 'Barbería', 'Manicura', 'Cosmética', 'Decoración', 'Interiorismo',
        'Arquitectura', 'Jardinería', 'Paisajismo', 'Mascotas', 'Veterinaria', 'Adiestramiento Canino',

        // Deportes
        'Fútbol', 'Baloncesto', 'Tenis', 'Pádel', 'Golf', 'Ciclismo', 'Running', 'Natación',
        'Artes Marciales', 'Boxeo', 'CrossFit', 'Montañismo', 'Surf', 'Esquí', 'Snowboard',

        // Automoción
        'Coches', 'Motos', 'Automoción Eléctrica', 'Mecánica', 'Tuning', 'Concesionarios',
        'Rent a Car', 'Carsharing',

        // Construcción e Industria
        'Construcción', 'Reformas', 'Fontanería', 'Electricidad', 'Carpintería', 'Pintura',
        'Climatización', 'Energías Renovables', 'Energía Solar', 'Ingeniería', 'Manufactura',
        'Logística', 'Transporte', 'Mudanzas',

        // Servicios
        'Limpieza', 'Seguridad', 'Mantenimiento', 'Reparaciones', 'Mensajería', 'Catering',
        'Organización de Eventos', 'Bodas', 'Agencia de Viajes', 'Inmobiliaria', 'Alquiler',

        // Ocio y Entretenimiento
        'Cine', 'Teatro', 'Conciertos', 'Parques Temáticos', 'Casinos', 'Juegos de Mesa',
        'Libros', 'Cómics', 'Podcasts', 'Streaming',

        // Agricultura y Alimentación
        'Agricultura', 'Agricultura Ecológica', 'Ganadería', 'Pesca', 'Acuicultura',
        'Producción de Alimentos', 'Alimentos Orgánicos', 'Vegano', 'Vegetariano',

        // Otros
        'ONG', 'Sostenibilidad', 'Medio Ambiente', 'Reciclaje', 'Religión', 'Espiritualidad',
        'Astrología', 'Tarot', 'Genealogía', 'Coleccionismo', 'Antigüedades'
    ];
}
