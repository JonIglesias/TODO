<?php
if (!defined('ABSPATH')) exit;

function ap_create_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();

    // NOTA IMPORTANTE: Usamos SQL directo en lugar de dbDelta para asegurar
    // que todas las columnas se creen correctamente. dbDelta tiene problemas
    // conocidos con el formato exacto del SQL y puede fallar silenciosamente.

    // Tabla campañas
    $campaigns_table = $wpdb->prefix . 'ap_campaigns';
    $sql_campaigns = "CREATE TABLE IF NOT EXISTS $campaigns_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        campaign_id varchar(64) DEFAULT NULL,
        name varchar(255) NOT NULL,
        domain varchar(255) NOT NULL,
        company_desc text,
        niche varchar(100),
        prompt_titles text,
        prompt_content text,
        keywords_seo text,
        keywords_images text,
        publish_days varchar(100),
        start_date datetime,
        publish_time time,
        num_posts int(11) NOT NULL DEFAULT 0,
        post_length varchar(20) DEFAULT 'medio',
        image_provider varchar(50),
        category_id int(11) DEFAULT NULL,
        queue_generated tinyint(1) DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at datetime DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY campaign_id (campaign_id),
        KEY idx_queue_generated (queue_generated)
    ) $charset";
    $wpdb->query($sql_campaigns);

    // Tabla cola
    $queue_table = $wpdb->prefix . 'ap_queue';
    $sql_queue = "CREATE TABLE IF NOT EXISTS $queue_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        campaign_id bigint(20) NOT NULL,
        batch_id varchar(128) DEFAULT NULL,
        position int(11) DEFAULT 0,
        title varchar(500),
        image_keywords text,
        featured_image_url text,
        featured_image_thumb text,
        inner_image_url text,
        inner_image_thumb text,
        status varchar(20) DEFAULT 'pending',
        post_id bigint(20) DEFAULT NULL,
        tokens_estimated int(11) DEFAULT 0,
        tokens_used int(11) DEFAULT 0,
        scheduled_date datetime,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at datetime DEFAULT NULL,
        PRIMARY KEY (id),
        KEY campaign_id (campaign_id),
        KEY idx_campaign_position (campaign_id, position),
        KEY idx_status (status),
        KEY idx_scheduled_date (scheduled_date),
        KEY idx_campaign_status (campaign_id, status)
    ) $charset";
    $wpdb->query($sql_queue);

    // Tabla de bloqueos (Sistema V2)
    $locks_table = $wpdb->prefix . 'ap_locks';
    $sql_locks = "CREATE TABLE IF NOT EXISTS $locks_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        lock_type varchar(20) NOT NULL,
        campaign_id bigint(20) NOT NULL,
        process_id varchar(50) NOT NULL,
        user_id bigint(20) NOT NULL,
        acquired_at datetime NOT NULL,
        last_heartbeat datetime NOT NULL,
        data text,
        PRIMARY KEY (id),
        UNIQUE KEY lock_unique (lock_type, campaign_id),
        KEY campaign_id (campaign_id),
        KEY last_heartbeat (last_heartbeat)
    ) $charset";
    $wpdb->query($sql_locks);

    // Tabla de nichos - FORZAR CREACIÓN CON SQL DIRECTO
    $nichos_table = $wpdb->prefix . 'ap_nichos';

    // Eliminar tabla si existe para recrearla limpia
    $wpdb->query("DROP TABLE IF EXISTS $nichos_table");

    // Crear tabla con SQL directo (más confiable que dbDelta)
    $sql_nichos_direct = "CREATE TABLE $nichos_table (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        category VARCHAR(100) NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_name (name)
    ) $charset";

    $wpdb->query($sql_nichos_direct);

    // Poblar tabla de nichos INMEDIATAMENTE después de crearla
    ap_populate_nichos_table();
}

function ap_populate_nichos_table() {
    global $wpdb;
    $nichos_table = $wpdb->prefix . 'ap_nichos';

    // Verificar que la tabla existe primero
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$nichos_table'") === $nichos_table;

    if (!$table_exists) {
        return;
    }

    // Verificar si ya tiene registros (NO poblar si ya tiene datos)
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $nichos_table");

    if ($count > 0) {
        return;
    }

    // Poblar con 213 nichos predefinidos
    $nichos_data = [
        // Salud y Bienestar (18)
        ['Medicina General', 'Salud y Bienestar'],
        ['Nutrición y Dietética', 'Salud y Bienestar'],
        ['Fitness y Gimnasio', 'Salud y Bienestar'],
        ['Yoga', 'Salud y Bienestar'],
        ['Pilates', 'Salud y Bienestar'],
        ['Salud Mental', 'Salud y Bienestar'],
        ['Psicología', 'Salud y Bienestar'],
        ['Fisioterapia', 'Salud y Bienestar'],
        ['Pediatría', 'Salud y Bienestar'],
        ['Geriatría', 'Salud y Bienestar'],
        ['Odontología', 'Salud y Bienestar'],
        ['Oftalmología', 'Salud y Bienestar'],
        ['Dermatología', 'Salud y Bienestar'],
        ['Medicina Estética', 'Salud y Bienestar'],
        ['Spa y Belleza', 'Salud y Bienestar'],
        ['Quiropráctica', 'Salud y Bienestar'],
        ['Acupuntura', 'Salud y Bienestar'],
        ['Naturopatía', 'Salud y Bienestar'],

        // Tecnología (21)
        ['Inteligencia Artificial', 'Tecnología'],
        ['Blockchain', 'Tecnología'],
        ['Ciberseguridad', 'Tecnología'],
        ['Desarrollo Web', 'Tecnología'],
        ['Desarrollo Móvil', 'Tecnología'],
        ['Apps iOS', 'Tecnología'],
        ['Apps Android', 'Tecnología'],
        ['Gaming', 'Tecnología'],
        ['E-sports', 'Tecnología'],
        ['Cloud Computing', 'Tecnología'],
        ['DevOps', 'Tecnología'],
        ['Data Science', 'Tecnología'],
        ['Machine Learning', 'Tecnología'],
        ['IoT', 'Tecnología'],
        ['Realidad Virtual', 'Tecnología'],
        ['Realidad Aumentada', 'Tecnología'],
        ['Software as a Service', 'Tecnología'],
        ['Hardware', 'Tecnología'],
        ['Semiconductores', 'Tecnología'],
        ['Robótica', 'Tecnología'],
        ['Automatización', 'Tecnología'],

        // Negocios y Finanzas (26)
        ['Marketing Digital', 'Negocios y Finanzas'],
        ['SEO', 'Negocios y Finanzas'],
        ['SEM', 'Negocios y Finanzas'],
        ['Redes Sociales', 'Negocios y Finanzas'],
        ['Email Marketing', 'Negocios y Finanzas'],
        ['Content Marketing', 'Negocios y Finanzas'],
        ['E-commerce', 'Negocios y Finanzas'],
        ['Dropshipping', 'Negocios y Finanzas'],
        ['Amazon FBA', 'Negocios y Finanzas'],
        ['Shopify', 'Negocios y Finanzas'],
        ['Recursos Humanos', 'Negocios y Finanzas'],
        ['Contabilidad', 'Negocios y Finanzas'],
        ['Auditoría', 'Negocios y Finanzas'],
        ['Consultoría Empresarial', 'Negocios y Finanzas'],
        ['Startups', 'Negocios y Finanzas'],
        ['Venture Capital', 'Negocios y Finanzas'],
        ['Finanzas Personales', 'Negocios y Finanzas'],
        ['Inversión en Bolsa', 'Negocios y Finanzas'],
        ['Criptomonedas', 'Negocios y Finanzas'],
        ['Trading', 'Negocios y Finanzas'],
        ['Forex', 'Negocios y Finanzas'],
        ['Bienes Raíces', 'Negocios y Finanzas'],
        ['Crowdfunding', 'Negocios y Finanzas'],
        ['Seguros', 'Negocios y Finanzas'],
        ['Banca', 'Negocios y Finanzas'],
        ['Planificación Financiera', 'Negocios y Finanzas'],

        // Legal (9)
        ['Abogacía', 'Legal'],
        ['Derecho Penal', 'Legal'],
        ['Derecho Civil', 'Legal'],
        ['Derecho Laboral', 'Legal'],
        ['Derecho Mercantil', 'Legal'],
        ['Propiedad Intelectual', 'Legal'],
        ['Derecho Fiscal', 'Legal'],
        ['Derecho Inmobiliario', 'Legal'],
        ['Notaría', 'Legal'],

        // Educación (16)
        ['Online Learning', 'Educación'],
        ['E-learning', 'Educación'],
        ['Formación Profesional', 'Educación'],
        ['Universidad', 'Educación'],
        ['MBA', 'Educación'],
        ['Idiomas', 'Educación'],
        ['Inglés', 'Educación'],
        ['Español', 'Educación'],
        ['Francés', 'Educación'],
        ['Alemán', 'Educación'],
        ['Chino', 'Educación'],
        ['Tutorías', 'Educación'],
        ['Coaching', 'Educación'],
        ['Mentoring', 'Educación'],
        ['Desarrollo Personal', 'Educación'],
        ['Liderazgo', 'Educación'],

        // Creatividad y Diseño (20)
        ['Diseño Gráfico', 'Creatividad y Diseño'],
        ['Diseño Web', 'Creatividad y Diseño'],
        ['UX/UI Design', 'Creatividad y Diseño'],
        ['Ilustración', 'Creatividad y Diseño'],
        ['Animación', 'Creatividad y Diseño'],
        ['Motion Graphics', 'Creatividad y Diseño'],
        ['Fotografía', 'Creatividad y Diseño'],
        ['Fotografía de Boda', 'Creatividad y Diseño'],
        ['Fotografía de Producto', 'Creatividad y Diseño'],
        ['Video', 'Creatividad y Diseño'],
        ['Edición de Video', 'Creatividad y Diseño'],
        ['Producción Audiovisual', 'Creatividad y Diseño'],
        ['Música', 'Creatividad y Diseño'],
        ['Producción Musical', 'Creatividad y Diseño'],
        ['DJ', 'Creatividad y Diseño'],
        ['Arte', 'Creatividad y Diseño'],
        ['Arte Digital', 'Creatividad y Diseño'],
        ['Escritura Creativa', 'Creatividad y Diseño'],
        ['Copywriting', 'Creatividad y Diseño'],
        ['Publicidad', 'Creatividad y Diseño'],

        // Estilo de Vida y Gastronomía (26)
        ['Viajes', 'Estilo de Vida'],
        ['Turismo', 'Estilo de Vida'],
        ['Hoteles', 'Estilo de Vida'],
        ['Gastronomía', 'Estilo de Vida'],
        ['Restaurantes', 'Gastronomía'],
        ['Chef', 'Gastronomía'],
        ['Cocina', 'Gastronomía'],
        ['Repostería', 'Gastronomía'],
        ['Moda', 'Estilo de Vida'],
        ['Moda Masculina', 'Estilo de Vida'],
        ['Moda Femenina', 'Estilo de Vida'],
        ['Moda Infantil', 'Estilo de Vida'],
        ['Belleza', 'Estilo de Vida'],
        ['Peluquería', 'Estilo de Vida'],
        ['Barbería', 'Estilo de Vida'],
        ['Manicura', 'Estilo de Vida'],
        ['Cosmética', 'Estilo de Vida'],
        ['Decoración', 'Estilo de Vida'],
        ['Interiorismo', 'Estilo de Vida'],
        ['Arquitectura', 'Construcción e Industria'],
        ['Jardinería', 'Estilo de Vida'],
        ['Paisajismo', 'Estilo de Vida'],
        ['Mascotas', 'Estilo de Vida'],
        ['Veterinaria', 'Estilo de Vida'],
        ['Adiestramiento Canino', 'Estilo de Vida'],
        ['Tiendas de Mascotas', 'Estilo de Vida'],

        // Deportes (15)
        ['Deportes', 'Deportes'],
        ['Fútbol', 'Deportes'],
        ['Baloncesto', 'Deportes'],
        ['Tenis', 'Deportes'],
        ['Pádel', 'Deportes'],
        ['Golf', 'Deportes'],
        ['Ciclismo', 'Deportes'],
        ['Running', 'Deportes'],
        ['Natación', 'Deportes'],
        ['Artes Marciales', 'Deportes'],
        ['Boxeo', 'Deportes'],
        ['CrossFit', 'Deportes'],
        ['Montañismo', 'Deportes'],
        ['Surf', 'Deportes'],
        ['Esquí', 'Deportes'],

        // Automoción (8)
        ['Coches', 'Automoción'],
        ['Motos', 'Automoción'],
        ['Automoción Eléctrica', 'Automoción'],
        ['Mecánica', 'Automoción'],
        ['Tuning', 'Automoción'],
        ['Concesionarios', 'Automoción'],
        ['Rent a Car', 'Automoción'],
        ['Carsharing', 'Automoción'],

        // Construcción e Industria (14)
        ['Construcción', 'Construcción e Industria'],
        ['Reformas', 'Construcción e Industria'],
        ['Fontanería', 'Construcción e Industria'],
        ['Electricidad', 'Construcción e Industria'],
        ['Carpintería', 'Construcción e Industria'],
        ['Pintura', 'Construcción e Industria'],
        ['Climatización', 'Construcción e Industria'],
        ['Energías Renovables', 'Construcción e Industria'],
        ['Energía Solar', 'Construcción e Industria'],
        ['Ingeniería', 'Construcción e Industria'],
        ['Manufactura', 'Construcción e Industria'],
        ['Logística', 'Construcción e Industria'],
        ['Transporte', 'Construcción e Industria'],
        ['Mudanzas', 'Construcción e Industria'],

        // Servicios (11)
        ['Limpieza', 'Servicios'],
        ['Seguridad', 'Servicios'],
        ['Mantenimiento', 'Servicios'],
        ['Reparaciones', 'Servicios'],
        ['Mensajería', 'Servicios'],
        ['Catering', 'Servicios'],
        ['Organización de Eventos', 'Servicios'],
        ['Bodas', 'Servicios'],
        ['Agencia de Viajes', 'Servicios'],
        ['Inmobiliaria', 'Servicios'],
        ['Alquiler', 'Servicios'],

        // Ocio y Entretenimiento (10)
        ['Cine', 'Ocio y Entretenimiento'],
        ['Teatro', 'Ocio y Entretenimiento'],
        ['Conciertos', 'Ocio y Entretenimiento'],
        ['Parques Temáticos', 'Ocio y Entretenimiento'],
        ['Casinos', 'Ocio y Entretenimiento'],
        ['Juegos de Mesa', 'Ocio y Entretenimiento'],
        ['Libros', 'Ocio y Entretenimiento'],
        ['Cómics', 'Ocio y Entretenimiento'],
        ['Podcasts', 'Ocio y Entretenimiento'],
        ['Streaming', 'Ocio y Entretenimiento'],

        // Agricultura y Alimentación (9)
        ['Agricultura', 'Agricultura y Alimentación'],
        ['Agricultura Ecológica', 'Agricultura y Alimentación'],
        ['Ganadería', 'Agricultura y Alimentación'],
        ['Pesca', 'Agricultura y Alimentación'],
        ['Acuicultura', 'Agricultura y Alimentación'],
        ['Producción de Alimentos', 'Agricultura y Alimentación'],
        ['Alimentos Orgánicos', 'Agricultura y Alimentación'],
        ['Vegano', 'Agricultura y Alimentación'],
        ['Vegetariano', 'Agricultura y Alimentación'],

        // Otros (10)
        ['ONG', 'Otros'],
        ['Sostenibilidad', 'Otros'],
        ['Medio Ambiente', 'Otros'],
        ['Reciclaje', 'Otros'],
        ['Religión', 'Otros'],
        ['Espiritualidad', 'Otros'],
        ['Astrología', 'Otros'],
        ['Tarot', 'Otros'],
        ['Genealogía', 'Otros'],
        ['Coleccionismo', 'Otros']
    ];

    foreach ($nichos_data as $nicho) {
        $wpdb->insert(
            $nichos_table,
            [
                'name' => $nicho[0],
                'category' => $nicho[1]
            ],
            ['%s', '%s']
        );
    }
}

function ap_set_default_options() {
    $defaults = [
        'ap_api_url' => AP_API_URL_DEFAULT,
        'ap_license_key' => '',
        'ap_unsplash_key' => '',
        'ap_pixabay_key' => '',
        'ap_pexels_key' => ''
    ];
    
    foreach ($defaults as $key => $value) {
        if (get_option($key) === false) {
            add_option($key, $value);
        }
    }
}
