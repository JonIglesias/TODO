<?php
/**
 * Acciones para campañas
 *
 * @package AutoPost
 * @version 7.0.0
 */

if (!defined('ABSPATH')) exit;

class AP_Campaign_Actions {

    /**
     * Eliminar campaña (eliminación permanente por defecto)
     *
     * @param int $campaign_id ID de la campaña
     * @param bool $force_delete Si true, elimina permanentemente. Si false, soft delete
     * @return bool True si se eliminó correctamente
     */
    public static function delete_campaign(int $campaign_id, bool $force_delete = true): bool {
        global $wpdb;

        if ($force_delete) {
            // Eliminación permanente
            // Eliminar cola asociada
            $wpdb->delete(
                $wpdb->prefix . 'ap_queue',
                ['campaign_id' => $campaign_id],
                ['%d']
            );

            // Eliminar campaña
            $deleted = $wpdb->delete(
                $wpdb->prefix . 'ap_campaigns',
                ['id' => $campaign_id],
                ['%d']
            );

            if ($deleted) {
                return true;
            }
        } else {
            // Soft delete: marcar deleted_at
            $updated = $wpdb->update(
                $wpdb->prefix . 'ap_campaigns',
                ['deleted_at' => current_time('mysql')],
                ['id' => $campaign_id],
                ['%s'],
                ['%d']
            );

            // También marcar cola asociada como eliminada
            $wpdb->update(
                $wpdb->prefix . 'ap_queue',
                ['deleted_at' => current_time('mysql')],
                ['campaign_id' => $campaign_id],
                ['%s'],
                ['%d']
            );

            if ($updated !== false) {
                return true;
            }
        }

        return false;
    }
    
    /**
     * Eliminar múltiples campañas
     *
     * @param array $campaign_ids Array de IDs de campañas
     * @param bool $force_delete Si true, elimina permanentemente
     * @return int Número de campañas eliminadas
     */
    public static function delete_multiple_campaigns(array $campaign_ids, bool $force_delete = true): int {
        $deleted = 0;
        foreach ($campaign_ids as $id) {
            if (self::delete_campaign((int) $id, $force_delete)) {
                $deleted++;
            }
        }
        return $deleted;
    }
    
    /**
     * Clonar campaña
     *
     * @param int $campaign_id ID de la campaña a clonar
     * @return int|false ID de la nueva campaña o false si falla
     */
    public static function clone_campaign(int $campaign_id) {
        global $wpdb;

        $campaign = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}ap_campaigns WHERE id = %d", $campaign_id),
            ARRAY_A
        );

        if (!$campaign) return false;

        // VALIDAR que la campaña original tiene nombre - NO CLONAR campañas sin nombre
        if (empty($campaign['name']) || trim($campaign['name']) === '') {
            return false;
        }

        // Remover campos que no se deben copiar
        unset($campaign['id']);
        unset($campaign['created_at']);
        unset($campaign['updated_at']);
        unset($campaign['campaign_id']);  // ✅ También eliminar campaign_id - WordPress asignará nuevo ID

        // Cambiar nombre
        $campaign['name'] = $campaign['name'] . ' (Copia)';
        $campaign['queue_generated'] = 0;
        
        // Definir formatos correctos para cada campo según su tipo
        $formats = [];
        foreach ($campaign as $key => $value) {
            switch ($key) {
                case 'num_posts':
                case 'queue_generated':
                case 'category_id':
                    $formats[] = '%d'; // Enteros
                    break;
                case 'start_date':
                case 'publish_time':
                    $formats[] = '%s'; // Datetime/time como string
                    break;
                default:
                    $formats[] = '%s'; // Strings por defecto
            }
        }
        
        $inserted = $wpdb->insert(
            $wpdb->prefix . 'ap_campaigns',
            $campaign,
            $formats
        );

        if ($inserted) {
            $new_id = $wpdb->insert_id;
            return $new_id;
        }

        return false;
    }
}
