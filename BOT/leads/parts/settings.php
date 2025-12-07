<?php
if (!defined('ABSPATH')) exit;

/** Ajustes: prompts y umbrales */
if (!function_exists('phsbot_leads_settings_defaults')) {
    function phsbot_leads_settings_defaults(){
        return array(
            'enable_store'             => 1,   // guardar conversaciones
            'telegram_threshold'       => 8.0, // score ≥ ⇒ Telegram
            'immediate_email_threshold'=> 7.0, // score ≥ ⇒ email inmediato al finalizar conversación (inactividad)
            'daily_digest_threshold'   => 5.0, // score ≥ ⇒ incluir en digest diario
            'summary_prompt'           => "Lee exclusivamente lo que escribe el usuario y resume en 3-5 viñetas claras: intereses concretos, preguntas que formula, dudas, señales de intención/urgencia y datos clave (fechas, presupuesto, destino). Sé conciso, español neutro, sin prefacios.",
            'scoring_prompt'           => "Valora de 0 a 10 la intención de compra del lead. Considera contacto (email/teléfono), urgencia (fechas), presupuesto y claridad del interés. Devuelve JSON {\"score\": number, \"rationale\": string} en español.",
            'digest_email'             => '',  // resumen diario
            'notify_email'             => '',  // email inmediato
        );
    }
}

if (!function_exists('phsbot_leads_settings')) {
    function phsbot_leads_settings(){
        $s = get_option(PHSBOT_LEADS_SETTINGS_OPT, array());
        if (!is_array($s)) $s = array();
        $s = array_merge(phsbot_leads_settings_defaults(), $s);

        $s['enable_store']              = !empty($s['enable_store']) ? 1 : 0;
        $s['telegram_threshold']        = (float) $s['telegram_threshold'];
        $s['immediate_email_threshold'] = (float) $s['immediate_email_threshold'];
        $s['daily_digest_threshold']    = (float) $s['daily_digest_threshold'];
        $s['summary_prompt']            = trim((string)$s['summary_prompt']);
        $s['scoring_prompt']            = trim((string)$s['scoring_prompt']);
        $s['digest_email']              = sanitize_email($s['digest_email']);
        $s['notify_email']              = sanitize_email($s['notify_email']);
        return $s;
    }
}

if (!function_exists('phsbot_leads_setting_get')) {
    function phsbot_leads_setting_get($key, $default=null){
        $s = phsbot_leads_settings();
        return array_key_exists($key, $s) ? $s[$key] : $default;
    }
}