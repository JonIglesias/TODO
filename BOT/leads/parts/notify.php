<?php
if (!defined('ABSPATH')) exit;


/* ======== UTIL: ACCESO SEGURO A ARRAYS ======== */
/** Devuelve $arr[$key] o $default si no existe */
if (!function_exists('phsbot_arr_get')) {
    function phsbot_arr_get($arr, $key, $default=null){
        return (is_array($arr) && array_key_exists($key, $arr)) ? $arr[$key] : $default;
    }
}
/* ========FIN UTIL: ACCESO SEGURO A ARRAYS ===== */



/* ======== TÍTULO A MOSTRAR DEL LEAD ======== */
/** Prioriza nombre > teléfono > email, si no hay → 'Lead PHSBOT' */
if (!function_exists('phsbot_leads_display_title')) {
    function phsbot_leads_display_title($lead){
        $name  = trim(phsbot_arr_get($lead,'name',''));
        $phone = trim(phsbot_arr_get($lead,'phone',''));
        $email = trim(phsbot_arr_get($lead,'email',''));
        if ($name)  return $name;
        if ($phone) return $phone;
        if ($email) return $email;
        return 'Lead PHSBOT';
    }
}
/* ========FIN TÍTULO A MOSTRAR DEL LEAD ===== */



/* ======== ENLACE PÚBLICO A LA CONVERSACIÓN ======== */
/** Construye link público si hay token */
if (!function_exists('phsbot_leads_public_link')) {
    function phsbot_leads_public_link($lead){
        $token = phsbot_arr_get($lead, 'public_token', '');
        if (!$token) return '';
        return add_query_arg(array('phslead'=>$token), site_url('/'));
    }
}
/* ========FIN ENLACE PÚBLICO A LA CONVERSACIÓN ===== */



/* ======== TELEGRAM: ENVIAR / EDITAR MENSAJE ======== */
/**
 * Envía (o edita) la notificación a Telegram.
 * - Antes de enviar/editar, reconcilia datos de contacto con IA (si está disponible):
 *   si más adelante aparece prefijo de país o correcciones de nombre/email/teléfono,
 *   se actualiza la ficha del lead y se usa el dato más reciente.
 * - Si existe message_id, intenta editar; si falla, no reenvía (para evitar duplicados).
 */
if (!function_exists('phsbot_leads_notify_telegram')) {
    function phsbot_leads_notify_telegram($lead) {
        // 0) Reconciliar/actualizar contacto según la conversación (nuevo sistema)
        if (function_exists('phsbot_leads_maybe_update_contact_from_conversation')) {
            $changed = phsbot_leads_maybe_update_contact_from_conversation($lead);
            if ($changed && function_exists('phsbot_leads_get')) {
                $lead = phsbot_leads_get(phsbot_arr_get($lead, 'cid', '')) ?: $lead; // refresca desde almacenamiento
            }
        }

        $main = get_option(PHSBOT_MAIN_SETTINGS_OPT, array());
        $tok  = trim(phsbot_arr_get($main, 'telegram_bot_token', ''));
        $chat = trim(phsbot_arr_get($main, 'telegram_chat_id', ''));
        if (!$tok || !$chat) return false;

        $cid    = phsbot_arr_get($lead, 'cid', '');
        $name   = phsbot_leads_display_title($lead);
        $email  = phsbot_arr_get($lead, 'email', '');
        $phone  = phsbot_arr_get($lead, 'phone', '');
        $score  = phsbot_arr_get($lead, 'score', null);
        $page   = phsbot_arr_get($lead, 'page', '');
        $msg_id = phsbot_arr_get($lead, 'telegram_msg_id', '');

        // Resumen (ya universal, puede incluir nombre/email/teléfono si aparecen)
        $summary = function_exists('phsbot_leads_summary_text') ? phsbot_leads_summary_text($lead) : '';
        $summary_md = '';
        if ($summary) {
            $lines = preg_split('/\r\n|\r|\n/', $summary);
            $md = array();
            foreach ($lines as $ln) {
                $ln = trim($ln);
                if ($ln === '') continue;
                $ln = preg_replace('/^[•\-\*]\s*/u', '', $ln);
                $md[] = '• ' . $ln;
            }
            if (!empty($md)) $summary_md = implode("\n", $md);
        }

        $link = phsbot_leads_public_link($lead);

        // Mensaje Markdown simple (no V2) para máxima compatibilidad
        $lines = array();
        $lines[] = "🟢 *{$name}* (CID: `{$cid}`)";
        if ($email) $lines[] = "• Email: `{$email}`";
        if ($phone) $lines[] = "• Tel: `{$phone}`";
        if ($score !== null) $lines[] = "• Score: *{$score}* / 10";
        if ($page)  $lines[] = "• Página: {$page}";
        if ($summary_md){
            $lines[] = "";
            $lines[] = "*Resumen usuario*";
            $lines[] = $summary_md;
        }
        if ($link) {
            $lines[] = "";
            $lines[] = "[Ver conversación completa]({$link})";
        }
        $text = implode("\n", $lines);

        $base = "https://api.telegram.org/bot{$tok}";
        $args = array('timeout'=>12, 'body'=>array(
            'chat_id'=>$chat,
            'text'=>$text,
            'parse_mode'=>'Markdown',
            'disable_web_page_preview'=>true
        ));

        if ($msg_id) {
            // EDIT EXISTENTE
            $args['body']['message_id'] = $msg_id;
            $r = wp_remote_post($base.'/editMessageText', $args);
            if (!is_wp_error($r) && wp_remote_retrieve_response_code($r) === 200) {
                return $msg_id;
            }
            // Si falla la edición, evita enviar otro para no duplicar
            return $msg_id;
        }

        // ENVIAR NUEVO
        unset($args['body']['message_id']);
        $r = wp_remote_post($base.'/sendMessage', $args);
        if (is_wp_error($r) || wp_remote_retrieve_response_code($r) !== 200) return false;
        $data = json_decode(wp_remote_retrieve_body($r), true);

        // message_id robusto
        $mid = '';
        if (isset($data['result']['message_id'])) $mid = (string)$data['result']['message_id'];
        elseif (isset($data['message_id'])) $mid = (string)$data['message_id'];
        return $mid ?: false;
    }
}
/* ========FIN TELEGRAM: ENVIAR / EDITAR MENSAJE ===== */



/* ======== TELEGRAM: ¿DEBEMOS NOTIFICAR AHORA? ======== */
/**
 * Criterio:
 *  - Si ya existe mensaje en TG → true (para editar con nuevos datos).
 *  - Si hay teléfono (posible E.164 tras reconciliar) → true.
 *  - Si hay email y score >= umbral → true.
 *  - Antes de decidir, intenta reconciliar datos (nuevo sistema) por si el prefijo apareció después.
 */
if (!function_exists('phsbot_leads_should_notify_telegram')) {
    function phsbot_leads_should_notify_telegram($lead) {
        // Reconciliar antes de evaluar umbrales (puede completar teléfono con prefijo)
        if (function_exists('phsbot_leads_maybe_update_contact_from_conversation')) {
            $changed = phsbot_leads_maybe_update_contact_from_conversation($lead);
            if ($changed && function_exists('phsbot_leads_get')) {
                $lead = phsbot_leads_get(phsbot_arr_get($lead, 'cid', '')) ?: $lead;
            }
        }

        if (!empty($lead['telegram_msg_id'])) return true; // editar siempre si ya existe
        $s = phsbot_leads_settings();
        $threshold = (float) phsbot_arr_get($s, 'telegram_threshold', 8);

        if (!empty($lead['phone'])) return true;
        if (!empty($lead['email']) && isset($lead['score']) && (float)$lead['score'] >= $threshold) return true;

        return false;
    }
}
/* ========FIN TELEGRAM: ¿DEBEMOS NOTIFICAR AHORA? ===== */



/* ======== EMAIL INSTANTÁNEO TRAS INACTIVIDAD ======== */
/**
 * Envío por email (al finalizar): antes de construir, reconcilia
 * por si hubo correcciones/prefijos aportados más tarde en la conversación.
 */
if (!function_exists('phsbot_leads_send_instant_email')) {
    function phsbot_leads_send_instant_email($lead){
        // Reconciliar contacto (nuevo sistema)
        if (function_exists('phsbot_leads_maybe_update_contact_from_conversation')) {
            $changed = phsbot_leads_maybe_update_contact_from_conversation($lead);
            if ($changed && function_exists('phsbot_leads_get')) {
                $lead = phsbot_leads_get(phsbot_arr_get($lead, 'cid', '')) ?: $lead;
            }
        }

        $s   = phsbot_leads_settings();
        $to  = phsbot_arr_get($s, 'notify_email', '') ?: get_option('admin_email');
        $title = phsbot_leads_display_title($lead);
        $sub = sprintf('[PHSBOT] %s – Lead tras finalizar (score %s)', $title, isset($lead['score']) ? $lead['score'] : '–');

        $summary = function_exists('phsbot_leads_summary_text') ? phsbot_leads_summary_text($lead) : '';
        $link    = function_exists('phsbot_leads_public_link') ? phsbot_leads_public_link($lead) : '';

        $lines = array();
        $lines[] = $title;
        $lines[] = '---------------------';
        $lines[] = 'CID: ' . phsbot_arr_get($lead,'cid','');
        $lines[] = 'Email: ' . phsbot_arr_get($lead,'email','');
        $lines[] = 'Teléfono: ' . phsbot_arr_get($lead,'phone','');
        $lines[] = 'Score: ' . (isset($lead['score']) ? $lead['score'] : '–');
        $lines[] = 'Página: ' . phsbot_arr_get($lead,'page','');
        if ($summary) { $lines[] = ''; $lines[] = 'Resumen:'; $lines[] = $summary; }
        if ($link)    { $lines[] = ''; $lines[] = 'Ver conversación:'; $lines[] = $link; }

        return wp_mail($to, $sub, implode("\n", $lines));
    }
}
/* ========FIN EMAIL INSTANTÁNEO TRAS INACTIVIDAD ===== */
