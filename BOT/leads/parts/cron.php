<?php
if (!defined('ABSPATH')) exit;

/**
 * Cron:
 * - Escaneo periódico para email inmediato tras inactividad.
 * - Digest diario con leads de las últimas 24h.
 */
if (!function_exists('phsbot_leads_cron_schedule')) {
    function phsbot_leads_cron_schedule(){
        if (!wp_next_scheduled('phsbot_leads_scan_inactivity')) {
            wp_schedule_event(time()+300, 'five_minutes', 'phsbot_leads_scan_inactivity');
        }
        if (!wp_next_scheduled('phsbot_leads_digest_daily')) {
            // Programa a las 20:00 hora del sitio
            $ts = strtotime('20:00');
            if ($ts <= time()) $ts = strtotime('+1 day 20:00');
            wp_schedule_event($ts, 'daily', 'phsbot_leads_digest_daily');
        }
    }
    add_action('init', 'phsbot_leads_cron_schedule');
}

/** Intervalo 5 minutos */
add_filter('cron_schedules', function($s){
    if (!isset($s['five_minutes'])) {
        $s['five_minutes'] = array('interval' => 300, 'display' => 'Every 5 Minutes');
    }
    return $s;
});

/** Inactividad ⇒ email inmediato (una sola vez) */
add_action('phsbot_leads_scan_inactivity', function(){
    $s = phsbot_leads_settings();
    $imm_th = (float)$s['immediate_email_threshold'];
    $now = time();
    $map = function_exists('phsbot_leads_all') ? phsbot_leads_all() : array();
    foreach ($map as $cid => $lead) {
        $last = (int) phsbot_arr_get($lead,'last_seen',0);
        $emailed = (int) phsbot_arr_get($lead,'emailed_instant',0);
        $score = (float) phsbot_arr_get($lead,'score',0);
        if ($emailed) continue;
        if ($last <= 0) continue;
        if (($now - $last) < PHSBOT_INACTIVITY_SECS) continue; // aún conversando
        if ($score < $imm_th) continue;

        if (function_exists('phsbot_leads_send_instant_email')) {
            phsbot_leads_send_instant_email($lead);
            $lead['emailed_instant'] = 1;
            phsbot_leads_set($lead);
        }
    }
});

/** Digest diario */
add_action('phsbot_leads_digest_daily', function(){
    $s = phsbot_leads_settings();
    $to = $s['digest_email'];
    if (!$to) return;

    $th = (float)$s['daily_digest_threshold'];
    $since = time() - DAY_IN_SECONDS;
    $map = function_exists('phsbot_leads_all') ? phsbot_leads_all() : array();

    $items = array();
    foreach ($map as $lead) {
        $ts = (int) phsbot_arr_get($lead,'last_seen',0);
        $score = (float) phsbot_arr_get($lead,'score',0);
        if ($ts >= $since && $score >= $th) {
            $summary = function_exists('phsbot_leads_summary_text') ? phsbot_leads_summary_text($lead) : '';
            $link    = function_exists('phsbot_leads_public_link') ? phsbot_leads_public_link($lead) : '';
            $items[] = array(
                'cid' => phsbot_arr_get($lead,'cid',''),
                'name'=> phsbot_arr_get($lead,'name',''),
                'email'=> phsbot_arr_get($lead,'email',''),
                'phone'=> phsbot_arr_get($lead,'phone',''),
                'score'=> $score,
                'summary'=>$summary,
                'link'=>$link,
            );
        }
    }
    if (empty($items)) return;

    $lines = array();
    $lines[] = 'Digest diario PHSBOT';
    $lines[] = '====================';
    foreach ($items as $it) {
        $lines[] = '';
        $lines[] = 'CID: '.$it['cid'].' | Score: '.$it['score'];
        if ($it['name'])  $lines[] = 'Nombre: '.$it['name'];
        if ($it['email']) $lines[] = 'Email: '.$it['email'];
        if ($it['phone']) $lines[] = 'Teléfono: '.$it['phone'];
        if ($it['link'])  $lines[] = 'Ver: '.$it['link'];
        if ($it['summary']) { $lines[] = 'Resumen:'; $lines[] = $it['summary']; }
    }

    wp_mail($to, '[PHSBOT] Digest diario de leads', implode("\n", $lines));
});