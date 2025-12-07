<?php
if (!defined('ABSPATH')) exit;

// Ejecutar migraciones necesarias
function ap_run_migrations() {
    global $wpdb;
    
    $current_version = get_option('ap_db_version', '0');
    
    // Migración v1.1: Añadir columna company_desc
    if (version_compare($current_version, '1.1', '<')) {
        $table = $wpdb->prefix . 'ap_campaigns';
        
        // Verificar si la columna existe
        $columns = $wpdb->get_col("DESCRIBE $table");
        
        if (!in_array('company_desc', $columns)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN company_desc TEXT NULL AFTER domain");
        }
        
        update_option('ap_db_version', '1.1');
    }
    
    // Migración v1.2: Añadir columnas de thumbnail
    if (version_compare($current_version, '1.2', '<')) {
        $table = $wpdb->prefix . 'ap_queue';
        
        $columns = $wpdb->get_col("DESCRIBE $table");
        
        if (!in_array('featured_image_thumb', $columns)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN featured_image_thumb TEXT NULL AFTER featured_image_url");
        }
        
        if (!in_array('inner_image_thumb', $columns)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN inner_image_thumb TEXT NULL AFTER inner_image_url");
        }
        
        update_option('ap_db_version', '1.2');
    }
    
    // Migración v1.3: Añadir columna category_id para asignar categoría a posts
    // ESTA MIGRACIÓN SIEMPRE SE VERIFICA (para instalaciones que ya tenían v1.2)
    $table = $wpdb->prefix . 'ap_campaigns';
    $columns = $wpdb->get_col("DESCRIBE $table");
    
    if (!in_array('category_id', $columns)) {
        $wpdb->query("ALTER TABLE $table ADD COLUMN category_id INT NULL AFTER image_provider");
        update_option('ap_db_version', '1.3');
    } elseif (version_compare($current_version, '1.3', '<')) {
        // La columna ya existe, solo actualizar versión
        update_option('ap_db_version', '1.3');
    }
    
    // Migración v1.4: Añadir columna prompt_content para prompt personalizado de generación
    if (!in_array('prompt_content', $columns)) {
        $wpdb->query("ALTER TABLE $table ADD COLUMN prompt_content TEXT NULL AFTER prompt_titles");
        update_option('ap_db_version', '1.4');
    } elseif (version_compare($current_version, '1.4', '<')) {
        update_option('ap_db_version', '1.4');
    }
    
    // Migración v1.5: Añadir campaign_id único para tracking en API
    $columns = $wpdb->get_col("DESCRIBE $table");
    if (!in_array('campaign_id', $columns)) {
        $wpdb->query("ALTER TABLE $table ADD COLUMN campaign_id VARCHAR(64) NULL AFTER id");
        $wpdb->query("ALTER TABLE $table ADD UNIQUE KEY campaign_id (campaign_id)");

        // Generar campaign_id para campañas existentes
        $existing = $wpdb->get_results("SELECT id FROM $table WHERE campaign_id IS NULL");
        foreach ($existing as $row) {
            $unique_id = 'campaign_' . $row->id;
            $wpdb->update($table, ['campaign_id' => $unique_id], ['id' => $row->id]);
        }

        update_option('ap_db_version', '1.5');
    } elseif (version_compare($current_version, '1.5', '<')) {
        update_option('ap_db_version', '1.5');
    }

    // Migración v1.6: Añadir índices de rendimiento y soft deletes
    // SIEMPRE verificar columnas deleted_at (independiente de versión)
    $campaigns_table = $wpdb->prefix . 'ap_campaigns';
    $queue_table = $wpdb->prefix . 'ap_queue';


    // Verificar columnas existentes
    $campaigns_columns = $wpdb->get_col("DESCRIBE $campaigns_table");
    $queue_columns = $wpdb->get_col("DESCRIBE $queue_table");

    // Añadir columna deleted_at a campaigns
    if (!in_array('deleted_at', $campaigns_columns)) {
        $result = $wpdb->query("ALTER TABLE $campaigns_table ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL");
        if ($result === false) {
        } else {
        }
    } else {
    }

    // Añadir columna deleted_at a queue
    if (!in_array('deleted_at', $queue_columns)) {
        $result = $wpdb->query("ALTER TABLE $queue_table ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL");
        if ($result === false) {
        } else {
        }
    } else {
    }

    // Solo añadir índices si la versión es anterior a 1.6
    if (version_compare($current_version, '1.6', '<')) {

        // Añadir índices a wp_ap_queue para mejorar rendimiento
        $queue_indexes = $wpdb->get_results("SHOW INDEX FROM $queue_table", ARRAY_A);
        $existing_indexes = array_column($queue_indexes, 'Key_name');

        if (!in_array('idx_status', $existing_indexes)) {
            $wpdb->query("ALTER TABLE $queue_table ADD INDEX idx_status (status)");
        }

        if (!in_array('idx_scheduled_date', $existing_indexes)) {
            $wpdb->query("ALTER TABLE $queue_table ADD INDEX idx_scheduled_date (scheduled_date)");
        }

        if (!in_array('idx_campaign_status', $existing_indexes)) {
            $wpdb->query("ALTER TABLE $queue_table ADD INDEX idx_campaign_status (campaign_id, status)");
        }

        // Añadir índice a wp_ap_campaigns
        $campaigns_indexes = $wpdb->get_results("SHOW INDEX FROM $campaigns_table", ARRAY_A);
        $existing_campaign_indexes = array_column($campaigns_indexes, 'Key_name');

        if (!in_array('idx_queue_generated', $existing_campaign_indexes)) {
            $wpdb->query("ALTER TABLE $campaigns_table ADD INDEX idx_queue_generated (queue_generated)");
        }

        update_option('ap_db_version', '1.6');
    } elseif (version_compare($current_version, '1.6', '=')) {
        // Versión 1.6 ya registrada, solo verificar que se completó
    }

    // Migración v1.7: Crear tabla de nichos y poblarla
    if (version_compare($current_version, '1.7', '<')) {

        $nichos_table = $wpdb->prefix . 'ap_nichos';

        // Crear tabla si no existe
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS $nichos_table (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            category VARCHAR(100) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_name (name)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Verificar si la tabla tiene registros
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $nichos_table");

        if ($count == 0) {
            // Poblar con nichos predefinidos (100+ nichos)
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

        update_option('ap_db_version', '1.7');
    }

    // Migración v1.8: Asegurar que la columna position existe en wp_ap_queue
    // Esta migración corrige instalaciones donde dbDelta falló en crear la columna
    $queue_table = $wpdb->prefix . 'ap_queue';
    $queue_columns = $wpdb->get_col("DESCRIBE $queue_table");

    if (!in_array('position', $queue_columns)) {
        // Añadir columna position si no existe
        $wpdb->query("ALTER TABLE $queue_table ADD COLUMN position INT(11) DEFAULT 0 AFTER batch_id");

        // Añadir índice compuesto si no existe
        $queue_indexes = $wpdb->get_results("SHOW INDEX FROM $queue_table", ARRAY_A);
        $existing_indexes = array_column($queue_indexes, 'Key_name');

        if (!in_array('idx_campaign_position', $existing_indexes)) {
            $wpdb->query("ALTER TABLE $queue_table ADD INDEX idx_campaign_position (campaign_id, position)");
        }

        update_option('ap_db_version', '1.8');
    } elseif (version_compare($current_version, '1.8', '<')) {
        // La columna ya existe, solo actualizar versión
        update_option('ap_db_version', '1.8');
    }

    // Migración v1.9: Asegurar que la columna batch_id existe en wp_ap_queue
    // Recargar columnas después de la migración anterior
    $queue_columns = $wpdb->get_col("DESCRIBE $queue_table");

    if (!in_array('batch_id', $queue_columns)) {
        // Añadir columna batch_id si no existe
        $wpdb->query("ALTER TABLE $queue_table ADD COLUMN batch_id VARCHAR(128) DEFAULT NULL AFTER campaign_id");
        update_option('ap_db_version', '1.9');
    } elseif (version_compare($current_version, '1.9', '<')) {
        // La columna ya existe, solo actualizar versión
        update_option('ap_db_version', '1.9');
    }
}

// Ejecutar al activar plugin
add_action('plugins_loaded', 'ap_run_migrations');