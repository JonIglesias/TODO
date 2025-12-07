<?php
if (!defined('ABSPATH')) exit;

/**
 * Núcleo de guardado de leads: se llama en cada intercambio.
 * - Crea/actualiza lead con mensajes user/assistant.
 * - Reconoce email/teléfono del texto del usuario.
 * - Mantiene page (solo URL front).
 * - Recalcula score, cachea resumen IA, y notifica (Telegram con edición).
 */
if (!function_exists('phsbot_leads_on_exchange')) {
    function phsbot_leads_on_exchange($cid, $user_text = '', $assistant_text = '', $meta = array()) {
        if (!function_exists('phsbot_leads_setting_get') || !phsbot_leads_setting_get('enable_store', 1)) return true;

        $cid  = sanitize_text_field($cid);
        if (!$cid) return true;

        $now  = time();
        $lead = function_exists('phsbot_leads_get') ? phsbot_leads_get($cid) : null;

        if (!$lead) {
            $lead = array(
                'cid' => $cid,
                'first_ts' => $now,
                'last_seen'=> $now,
                'name' => '', 'email'=> '', 'phone'=> '',
                'messages' => array(),
                'score' => null, 'rationale' => '',
                'notified' => array(), 'telegram_msg_id' => '',
                'summary_cache' => array(),
                'closed' => 0,
                'page' => '',
                'public_token' => wp_generate_password(20, false, false),
                'last_change_ts' => $now,
                'emailed_instant' => 0,
            );
        }

        // URL SOLO front si llega
        if (is_array($meta) && !empty($meta['url'])) {
            $u = esc_url_raw($meta['url']);
            if (function_exists('phsbot_is_front_url') && phsbot_is_front_url($u)) {
                $lead['page'] = $u;
            }
        }

        // Mensajes
        if ($user_text !== '') {
            $lead['messages'][] = array('role'=>'user','text'=>$user_text,'ts'=>$now);
            if (empty($lead['email']) && function_exists('phsbot_leads_extract_email')) {
                $e = phsbot_leads_extract_email($user_text); if ($e) $lead['email'] = $e;
            }
            if (empty($lead['phone']) && function_exists('phsbot_leads_extract_phone')) {
                $p = phsbot_leads_extract_phone($user_text); if ($p) $lead['phone'] = $p;
            }
        }
        if ($assistant_text !== '') {
            $lead['messages'][] = array('role'=>'assistant','text'=>$assistant_text,'ts'=>$now);
        }

        $lead['last_seen'] = $now;
        if (function_exists('phsbot_leads_backfill_name')) $lead = phsbot_leads_backfill_name($lead);

        // Guarda y reevalúa
        if (function_exists('phsbot_leads_set')) phsbot_leads_set($lead);
        if (function_exists('phsbot_leads_score_and_update')) phsbot_leads_score_and_update($cid);

        // Recalcula resumen IA (cache)
        if (function_exists('phsbot_leads_summary_text')) {
            $lead = phsbot_leads_get($cid);
            phsbot_leads_summary_text($lead);
            $lead = phsbot_leads_get($cid);
        }

        // Telegram: enviar o EDITAR si ya hay message_id (evita duplicados)
        if (function_exists('phsbot_leads_should_notify_telegram') && phsbot_leads_should_notify_telegram($lead)) {
            if (function_exists('phsbot_leads_notify_telegram')) {
                $mid = phsbot_leads_notify_telegram($lead);
                if ($mid) {
                    $lead['telegram_msg_id'] = $mid;
                    $notified = is_array($lead['notified']) ? $lead['notified'] : array();
                    $notified['telegram'] = 1;
                    $lead['notified'] = $notified;
                    phsbot_leads_set($lead);
                }
            }
        }

        // Log opcional
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[PHSBOT] saved exchange cid='.$cid.' msgs='.(isset($lead['messages'])?count($lead['messages']):0).' score='.(isset($lead['score'])?$lead['score']:'null'));
        }
        return true;
    }
}

/** Hooks compatibles (por si tu chat los dispara directamente) */
add_action('phsbot_chat_exchange',       'phsbot_leads_on_exchange', 10, 4);
add_action('phsbot_lead_exchange',       'phsbot_leads_on_exchange', 10, 4);
add_action('phsbot_on_exchange',         'phsbot_leads_on_exchange', 10, 4);
add_action('phsbot_chat_after_exchange', 'phsbot_leads_on_exchange', 10, 4);

/** Shims por si tu chat llamaba funciones directas antiguas */
if (!function_exists('phsbot_chat_exchange')) {
    function phsbot_chat_exchange($cid, $user_text='', $assistant_text='', $meta=array()){
        return phsbot_leads_on_exchange($cid, $user_text, $assistant_text, $meta);
    }
}
if (!function_exists('phsbot_track')) {
    function phsbot_track($cid, $user_text='', $assistant_text='', $meta=array()){
        return phsbot_leads_on_exchange($cid, $user_text, $assistant_text, $meta);
    }
}