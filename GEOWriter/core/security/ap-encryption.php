<?php
/**
 * Sistema de encriptación de claves API
 *
 * Proporciona funciones seguras para encriptar y desencriptar
 * claves API almacenadas en la base de datos.
 *
 * @package AutoPost
 * @version 7.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AP_Encryption {

    private const CIPHER = 'AES-256-CBC';
    private const KEY_LENGTH = 32;
    private const IV_LENGTH = 16;

    /**
     * Obtener clave de encriptación
     * Usa los salts de WordPress para generar una clave única por instalación
     */
    private static function get_encryption_key(): string {
        // Usar salts de WordPress como base
        $key_material = AUTH_KEY . SECURE_AUTH_KEY . LOGGED_IN_KEY . NONCE_KEY;

        // Derivar clave de tamaño correcto
        return substr(hash('sha256', $key_material, true), 0, self::KEY_LENGTH);
    }

    /**
     * Encriptar una cadena
     *
     * @param string $plain_text Texto plano a encriptar
     * @return string|false Texto encriptado en base64, o false si falla
     */
    public static function encrypt(string $plain_text) {
        if (empty($plain_text)) {
            return false;
        }

        try {
            // Generar IV aleatorio
            $iv = openssl_random_pseudo_bytes(self::IV_LENGTH);

            if ($iv === false) {
                return false;
            }

            // Encriptar
            $encrypted = openssl_encrypt(
                $plain_text,
                self::CIPHER,
                self::get_encryption_key(),
                OPENSSL_RAW_DATA,
                $iv
            );

            if ($encrypted === false) {
                return false;
            }

            // Combinar IV + texto encriptado y codificar en base64
            return base64_encode($iv . $encrypted);

        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Desencriptar una cadena
     *
     * @param string $encrypted_text Texto encriptado en base64
     * @return string|false Texto desencriptado, o false si falla
     */
    public static function decrypt(string $encrypted_text) {
        if (empty($encrypted_text)) {
            return false;
        }

        try {
            // Decodificar base64
            $decoded = base64_decode($encrypted_text, true);

            if ($decoded === false) {
                return false;
            }

            // Extraer IV (primeros 16 bytes)
            $iv = substr($decoded, 0, self::IV_LENGTH);

            // Extraer texto encriptado (resto)
            $encrypted = substr($decoded, self::IV_LENGTH);

            if (strlen($iv) !== self::IV_LENGTH) {
                return false;
            }

            // Desencriptar
            $decrypted = openssl_decrypt(
                $encrypted,
                self::CIPHER,
                self::get_encryption_key(),
                OPENSSL_RAW_DATA,
                $iv
            );

            if ($decrypted === false) {
                return false;
            }

            return $decrypted;

        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Obtener opción encriptada de WordPress
     *
     * @param string $option_name Nombre de la opción
     * @param mixed $default Valor por defecto
     * @return string Valor desencriptado o default
     */
    public static function get_encrypted_option(string $option_name, $default = '') {
        $encrypted = get_option($option_name . '_encrypted', '');

        if (empty($encrypted)) {
            return $default;
        }

        $decrypted = self::decrypt($encrypted);

        return $decrypted !== false ? $decrypted : $default;
    }

    /**
     * Guardar opción encriptada en WordPress
     *
     * @param string $option_name Nombre de la opción
     * @param string $value Valor a encriptar y guardar
     * @return bool True si se guardó correctamente
     */
    public static function update_encrypted_option(string $option_name, string $value): bool {
        if (empty($value)) {
            // Si el valor está vacío, eliminar la opción
            delete_option($option_name . '_encrypted');
            return true;
        }

        $encrypted = self::encrypt($value);

        if ($encrypted === false) {
            return false;
        }

        return update_option($option_name . '_encrypted', $encrypted);
    }

    /**
     * Migrar claves existentes de texto plano a encriptadas
     * Se ejecuta automáticamente en la primera carga después de actualizar
     */
    public static function migrate_existing_keys(): void {
        $keys_to_migrate = [
            'ap_license_key',
            'ap_unsplash_key',
            'ap_pixabay_key',
            'ap_pexels_key'
        ];

        $migrated_count = 0;

        foreach ($keys_to_migrate as $key_name) {
            // Verificar si ya está migrada
            $encrypted_exists = get_option($key_name . '_encrypted', false);

            if ($encrypted_exists !== false) {
                continue; // Ya está migrada
            }

            // Obtener clave en texto plano
            $plain_key = get_option($key_name, '');

            if (empty($plain_key)) {
                continue; // No hay nada que migrar
            }

            // Encriptar y guardar
            if (self::update_encrypted_option($key_name, $plain_key)) {
                // NO ELIMINAR la clave en texto plano para mantener compatibilidad
                // con versión anterior del plugin (si se desactiva v7.0)
                // delete_option($key_name);

                // Marcar como migrada
                update_option($key_name . '_migrated_v7', true);
                $migrated_count++;

            }
        }

        if ($migrated_count > 0) {
            // Marcar migración como completada
            update_option('ap_keys_migration_v7', time());

        }
    }
}

// Ejecutar migración en la primera carga
add_action('admin_init', function(): void {
    $migration_done = get_option('ap_keys_migration_v7', false);

    if ($migration_done === false) {
        AP_Encryption::migrate_existing_keys();
    }
}, 5);
