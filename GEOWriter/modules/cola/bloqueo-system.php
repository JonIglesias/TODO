<?php
/**
 * SISTEMA DE BLOQUEO V2 - CON BASE DE DATOS
 * 
 * Mejoras sobre V1:
 * - Usa BD en vez de transients → Atomicidad garantizada
 * - Bloqueos por campaign_id → Múltiples campañas pueden generarse en paralelo
 * - Cleanup automático de bloqueos huérfanos
 * - Heartbeat tracking
 * 
 * @package AutopostIA
 * @version 2.0
 */

if (!defined('ABSPATH')) exit;

class AP_Bloqueo_System {
    
    const LOCK_TTL = 300; // 5 minutos - por si el proceso muere
    const HEARTBEAT_TTL = 30; // Heartbeat cada 30s
    
    /**
     * Crear tabla de bloqueos si no existe
     */
    public static function init_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'ap_locks';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table (
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
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Intentar adquirir bloqueo
     * 
     * @param string $type 'generate' o 'execute'
     * @param int $campaign_id ID de la campaña
     * @return bool true si se adquirió, false si ya está bloqueado
     */
    public static function acquire($type, $campaign_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'ap_locks';
        
        // Cleanup de bloqueos expirados primero
        self::cleanup_expired();
        
        $process_id = self::generate_process_id();
        $user_id = get_current_user_id();
        $now = current_time('mysql');
        
        // Intentar insertar (si existe falla por UNIQUE constraint)
        $result = $wpdb->query($wpdb->prepare(
            "INSERT INTO $table 
            (lock_type, campaign_id, process_id, user_id, acquired_at, last_heartbeat, data)
            VALUES (%s, %d, %s, %d, %s, %s, %s)
            ON DUPLICATE KEY UPDATE id = id", // Si existe, no hace nada
            $type,
            $campaign_id,
            $process_id,
            $user_id,
            $now,
            $now,
            json_encode(['started_at' => time()])
        ));
        
        if ($result === false) {
            return false;
        }
        
        // Verificar que realmente se insertó (no fue duplicado)
        $inserted_id = $wpdb->insert_id;
        
        if ($inserted_id > 0) {
            
            // Guardar en sesión para heartbeat
            $_SESSION['ap_lock_' . $type . '_' . $campaign_id] = [
                'id' => $inserted_id,
                'process_id' => $process_id
            ];
            
            return true;
        }
        
        
        return false;
    }
    
    /**
     * Renovar heartbeat del bloqueo
     */
    public static function renew($type, $campaign_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'ap_locks';
        
        $lock_data = $_SESSION['ap_lock_' . $type . '_' . $campaign_id] ?? null;
        
        if (!$lock_data) {
            return false;
        }
        
        $result = $wpdb->update(
            $table,
            ['last_heartbeat' => current_time('mysql')],
            [
                'id' => $lock_data['id'],
                'process_id' => $lock_data['process_id']
            ],
            ['%s'],
            ['%d', '%s']
        );
        
        return $result !== false;
    }
    
    /**
     * Liberar bloqueo
     */
    public static function release($type, $campaign_id = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'ap_locks';
        
        if ($campaign_id === null) {
            // Liberar todos los bloqueos de este tipo
            $result = $wpdb->delete($table, ['lock_type' => $type], ['%s']);
        } else {
            // Liberar bloqueo específico
            $lock_data = $_SESSION['ap_lock_' . $type . '_' . $campaign_id] ?? null;
            
            if ($lock_data) {
                $result = $wpdb->delete(
                    $table,
                    [
                        'id' => $lock_data['id'],
                        'process_id' => $lock_data['process_id']
                    ],
                    ['%d', '%s']
                );
                
                unset($_SESSION['ap_lock_' . $type . '_' . $campaign_id]);
            } else {
                // Fallback: liberar por tipo y campaña
                $result = $wpdb->delete(
                    $table,
                    [
                        'lock_type' => $type,
                        'campaign_id' => $campaign_id
                    ],
                    ['%s', '%d']
                );
            }
        }
        
        
        return $result !== false;
    }
    
    /**
     * Verificar si hay bloqueo activo
     */
    public static function is_locked($type, $campaign_id = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'ap_locks';
        
        // Cleanup primero
        self::cleanup_expired();
        
        if ($campaign_id === null) {
            // Verificar si hay algún bloqueo de este tipo
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE lock_type = %s",
                $type
            ));
        } else {
            // Verificar bloqueo específico de campaña
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE lock_type = %s AND campaign_id = %d",
                $type,
                $campaign_id
            ));
        }
        
        return intval($count) > 0;
    }
    
    /**
     * Obtener info del bloqueo actual
     */
    public static function get_lock_info($type, $campaign_id = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'ap_locks';
        
        self::cleanup_expired();
        
        if ($campaign_id === null) {
            $lock = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE lock_type = %s ORDER BY acquired_at DESC LIMIT 1",
                $type
            ), ARRAY_A);
        } else {
            $lock = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE lock_type = %s AND campaign_id = %d",
                $type,
                $campaign_id
            ), ARRAY_A);
        }
        
        if ($lock) {
            $lock['data'] = json_decode($lock['data'], true);
            $lock['age_seconds'] = time() - strtotime($lock['acquired_at']);
        }
        
        return $lock;
    }
    
    /**
     * Limpiar bloqueos expirados (sin heartbeat en X minutos)
     */
    public static function cleanup_expired() {
        global $wpdb;
        $table = $wpdb->prefix . 'ap_locks';
        
        $expired_time = date('Y-m-d H:i:s', time() - self::LOCK_TTL);
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE last_heartbeat < %s",
            $expired_time
        ));
        
        if ($deleted > 0) {
        }
        
        return $deleted;
    }
    
    /**
     * Forzar liberación de TODOS los bloqueos (emergencia)
     */
    public static function release_all() {
        global $wpdb;
        $table = $wpdb->prefix . 'ap_locks';
        
        $result = $wpdb->query("TRUNCATE TABLE $table");
        
        
        return $result !== false;
    }
    
    /**
     * Verificar estado de la cola
     */
    public static function check_queue_state($campaign_id) {
        global $wpdb;
        
        $states = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as errors
            FROM {$wpdb->prefix}ap_queue 
            WHERE campaign_id = %d",
            $campaign_id
        ), ARRAY_A);
        
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT num_posts, queue_generated FROM {$wpdb->prefix}ap_campaigns WHERE id = %d",
            $campaign_id
        ), ARRAY_A);
        
        $expected = $campaign ? intval($campaign['num_posts']) : 0;
        $total_in_queue = intval($states['total'] ?? 0);
        $pending = intval($states['pending'] ?? 0);
        $processing = intval($states['processing'] ?? 0);
        
        return [
            'campaign_id' => $campaign_id,
            'expected' => $expected,
            'total_in_queue' => $total_in_queue,
            'pending' => $pending,
            'processing' => $processing,
            'completed' => intval($states['completed'] ?? 0),
            'errors' => intval($states['errors'] ?? 0),
            'missing' => max(0, $expected - $total_in_queue),
            'is_complete' => $total_in_queue >= $expected && $expected > 0,
            'is_empty' => $total_in_queue === 0,
            'has_processing' => $processing > 0,
            'queue_generated' => $campaign['queue_generated'] ?? 0
        ];
    }
    
    /**
     * Verificar si se puede generar cola
     */
    public static function can_generate($campaign_id, $force = false) {
        // 1. Verificar bloqueo de generación en ESTA campaña
        if (self::is_locked('generate', $campaign_id)) {
            $lock_info = self::get_lock_info('generate', $campaign_id);
            return [
                'can' => false,
                'reason' => 'generate_locked',
                'message' => 'Ya se está generando esta cola. Espera a que termine.',
                'lock_info' => $lock_info
            ];
        }
        
        // 2. Verificar bloqueo de ejecución en ESTA campaña
        if (self::is_locked('execute', $campaign_id)) {
            return [
                'can' => false,
                'reason' => 'execute_locked',
                'message' => 'Esta cola se está ejecutando. Espera a que termine.'
            ];
        }
        
        // 3. Verificar estado de la cola
        $state = self::check_queue_state($campaign_id);
        
        // Si hay posts procesándose, no permitir
        if ($state['has_processing']) {
            return [
                'can' => false,
                'reason' => 'posts_processing',
                'message' => 'Hay posts ejecutándose. Espera a que terminen.'
            ];
        }
        
        // Si la cola está completa y no se fuerza, no permitir
        if ($state['is_complete'] && !$force) {
            return [
                'can' => false,
                'reason' => 'queue_complete',
                'message' => "Ya existe una cola completa con {$state['total_in_queue']} posts.",
                'allow_view' => true
            ];
        }
        
        // Si hay posts pendientes pero incompletos y no se fuerza, preguntar
        if ($state['pending'] > 0 && !$state['is_complete'] && !$force) {
            return [
                'can' => false,
                'reason' => 'queue_incomplete',
                'message' => "Cola incompleta: {$state['pending']} posts de {$state['expected']}. Faltan {$state['missing']}.",
                'allow_complete' => true,
                'state' => $state
            ];
        }
        
        return [
            'can' => true,
            'state' => $state
        ];
    }
    
    /**
     * Verificar si se puede ejecutar cola
     */
    public static function can_execute($campaign_id) {
        // 1. Verificar bloqueo de ejecución en ESTA campaña
        if (self::is_locked('execute', $campaign_id)) {
            return [
                'can' => false,
                'reason' => 'execute_locked',
                'message' => 'Esta cola ya se está ejecutando. Espera a que termine.'
            ];
        }
        
        // 2. Verificar bloqueo de generación en ESTA campaña
        if (self::is_locked('generate', $campaign_id)) {
            return [
                'can' => false,
                'reason' => 'generate_locked',
                'message' => 'Se está generando esta cola. Espera a que termine.'
            ];
        }
        
        // 3. Verificar que hay posts pendientes
        $state = self::check_queue_state($campaign_id);
        
        if ($state['pending'] === 0) {
            return [
                'can' => false,
                'reason' => 'no_pending',
                'message' => 'No hay posts pendientes para ejecutar.'
            ];
        }
        
        return [
            'can' => true,
            'state' => $state
        ];
    }
    
    /**
     * Generar ID único de proceso
     */
    private static function generate_process_id() {
        return sprintf(
            '%s_%s_%d',
            php_uname('n'), // hostname
            getmypid(),     // process ID
            time()
        );
    }
    
    /**
     * Obtener todos los bloqueos activos (para debug)
     */
    public static function get_all_locks() {
        global $wpdb;
        $table = $wpdb->prefix . 'ap_locks';
        
        self::cleanup_expired();
        
        $locks = $wpdb->get_results("SELECT * FROM $table ORDER BY acquired_at DESC", ARRAY_A);
        
        foreach ($locks as &$lock) {
            $lock['data'] = json_decode($lock['data'], true);
            $lock['age_seconds'] = time() - strtotime($lock['acquired_at']);
        }
        
        return $locks;
    }
}
