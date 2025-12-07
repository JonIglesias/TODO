<?php
/**
 * Sistema de Rate Limiting
 *
 * Previene abuso de endpoints AJAX mediante límite de peticiones
 *
 * @package AutoPost
 * @version 7.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AP_Rate_Limiter {

    /**
     * Verificar y aplicar rate limit
     *
     * @param string $action Nombre de la acción AJAX
     * @param int $limit Número máximo de peticiones
     * @param int $window Ventana de tiempo en segundos
     * @return bool True si puede continuar, false si excede límite
     */
    public static function check(string $action, int $limit = 10, int $window = AP_RATE_LIMIT_WINDOW): bool {
        $user_id = get_current_user_id();

        if ($user_id === 0) {
            // Usuario no autenticado - usar IP
            $identifier = self::get_client_ip();
        } else {
            $identifier = 'user_' . $user_id;
        }

        $key = 'ap_rate_' . $action . '_' . md5($identifier);
        $count = get_transient($key);

        if ($count === false) {
            // Primera petición en esta ventana
            set_transient($key, 1, $window);
            return true;
        }

        if ($count >= $limit) {
            // Límite excedido
            return false;
        }

        // Incrementar contador
        set_transient($key, $count + 1, $window);
        return true;
    }

    /**
     * Aplicar rate limit y enviar error si excede
     *
     * @param string $action Nombre de la acción
     * @param int $limit Número máximo de peticiones
     * @param int $window Ventana de tiempo en segundos
     * @return void Envía JSON error y termina si excede
     */
    public static function enforce(string $action, int $limit = 10, int $window = AP_RATE_LIMIT_WINDOW): void {
        if (!self::check($action, $limit, $window)) {
            wp_send_json_error([
                'message' => __('Demasiadas peticiones. Por favor, espera un momento e inténtalo de nuevo.', 'autopost-v2')
            ], 429);
        }
    }

    /**
     * Obtener IP del cliente de manera segura
     *
     * @return string IP del cliente
     */
    private static function get_client_ip(): string {
        $ip_keys = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',  // Proxies
            'HTTP_X_REAL_IP',        // Nginx
            'REMOTE_ADDR'            // Directo
        ];

        foreach ($ip_keys as $key) {
            if (isset($_SERVER[$key])) {
                $ip = $_SERVER[$key];

                // Si es una lista de IPs, tomar la primera
                if (strpos($ip, ',') !== false) {
                    $ip_list = explode(',', $ip);
                    $ip = trim($ip_list[0]);
                }

                // Validar IP
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return 'unknown';
    }

    /**
     * Limpiar rate limits de un usuario/IP específico
     *
     * @param string $action Acción específica o vacío para todas
     * @param int|null $user_id ID de usuario o null para usar actual
     * @return void
     */
    public static function clear(string $action = '', ?int $user_id = null): void {
        global $wpdb;

        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        if ($user_id === 0) {
            $identifier = self::get_client_ip();
        } else {
            $identifier = 'user_' . $user_id;
        }

        if (empty($action)) {
            // Limpiar todos los rate limits de este usuario/IP
            $pattern = '_transient_ap_rate_%_' . md5($identifier);
        } else {
            // Limpiar rate limit específico
            $pattern = '_transient_ap_rate_' . $action . '_' . md5($identifier);
        }

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options}
                 WHERE option_name LIKE %s",
                $pattern
            )
        );
    }

    /**
     * Obtener información de rate limit actual
     *
     * @param string $action Nombre de la acción
     * @return array Información del rate limit
     */
    public static function get_info(string $action): array {
        $user_id = get_current_user_id();

        if ($user_id === 0) {
            $identifier = self::get_client_ip();
        } else {
            $identifier = 'user_' . $user_id;
        }

        $key = 'ap_rate_' . $action . '_' . md5($identifier);
        $count = get_transient($key);

        if ($count === false) {
            return [
                'count' => 0,
                'remaining' => PHP_INT_MAX,
                'reset_in' => 0
            ];
        }

        // WordPress no guarda TTL fácilmente, pero podemos estimarlo
        return [
            'count' => (int) $count,
            'remaining' => max(0, 10 - (int) $count), // Asumiendo límite default de 10
            'reset_in' => AP_RATE_LIMIT_WINDOW // Aproximado
        ];
    }
}
