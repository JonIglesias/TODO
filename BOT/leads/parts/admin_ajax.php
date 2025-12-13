<?php
if (!defined('ABSPATH')) exit;

/** Helper mínimo si no está declarado en otro part */
if (!function_exists('phsbot_arr_get')) {
    function phsbot_arr_get($arr, $key, $default=null){
        return (is_array($arr) && array_key_exists($key, $arr)) ? $arr[$key] : $default;
    }
}

/** GET detalle de un lead (para persiana) */
add_action('wp_ajax_phsbot_leads_get', function(){
    check_ajax_referer('phsbot_leads','nonce');
    if (!current_user_can('manage_options')) wp_send_json_error();

    $cid  = isset($_POST['cid']) ? sanitize_text_field(wp_unslash($_POST['cid'])) : '';
    $lead = function_exists('phsbot_leads_get') ? phsbot_leads_get($cid) : null;
    if (!$lead) wp_send_json_error();

    $summary_html = function_exists('phsbot_leads_summary_html') ? phsbot_leads_summary_html($lead) : '';

    $payload = array(
        'cid'        => phsbot_arr_get($lead,'cid',''),
        'name'       => phsbot_arr_get($lead,'name',''),
        'email'      => phsbot_arr_get($lead,'email',''),
        'phone'      => phsbot_arr_get($lead,'phone',''),
        'score'      => phsbot_arr_get($lead,'score', null),
        'closed'     => !empty($lead['closed']) ? 1 : 0,
        'page'       => phsbot_arr_get($lead,'page',''),
        'first_ts'   => phsbot_arr_get($lead,'first_ts',0),
        'last_seen'  => phsbot_arr_get($lead,'last_seen',0),
        'messages'   => phsbot_arr_get($lead,'messages', array()),
        'summary'    => $summary_html,
    );
    wp_send_json_success($payload);
});

/** DELETE lead (con fallback a option store si no existe phsbot_leads_del) */
add_action('wp_ajax_phsbot_leads_delete', function(){
    check_ajax_referer('phsbot_leads','nonce');
    if (!current_user_can('manage_options')) wp_send_json_error();

    $cid = isset($_POST['cid']) ? sanitize_text_field(wp_unslash($_POST['cid'])) : '';
    if ($cid === '') wp_send_json_error();

    $ok = false;
    if (function_exists('phsbot_leads_del')) {
        $ok = phsbot_leads_del($cid);
    } else {
        if (!defined('PHSBOT_LEADS_STORE_OPT')) define('PHSBOT_LEADS_STORE_OPT','phsbot_leads_store');
        $map = get_option(PHSBOT_LEADS_STORE_OPT, array());
        if (isset($map[$cid])) { unset($map[$cid]); $ok = update_option(PHSBOT_LEADS_STORE_OPT, $map, false); }
    }

    if ($ok) wp_send_json_success();
    wp_send_json_error();
});

/** CLOSE lead (marcar como cerrado) */
add_action('wp_ajax_phsbot_leads_close', function(){
    check_ajax_referer('phsbot_leads','nonce');
    if (!current_user_can('manage_options')) wp_send_json_error();

    $cid = isset($_POST['cid']) ? sanitize_text_field(wp_unslash($_POST['cid'])) : '';
    if ($cid === '') wp_send_json_error();

    $lead = function_exists('phsbot_leads_get') ? phsbot_leads_get($cid) : null;
    if (!$lead) wp_send_json_error();

    $lead['closed'] = 1;
    $ok = function_exists('phsbot_leads_set') ? phsbot_leads_set($lead) : false;

    if ($ok) wp_send_json_success();
    wp_send_json_error();
});

/** DELETE masivo */
add_action('wp_ajax_phsbot_leads_delete_bulk', function(){
    check_ajax_referer('phsbot_leads','nonce');
    if (!current_user_can('manage_options')) wp_send_json_error();

    $cids = isset($_POST['cids']) && is_array($_POST['cids']) ? array_map('sanitize_text_field', $_POST['cids']) : array();
    if (!$cids) wp_send_json_error();

    $ok_all = true;
    foreach ($cids as $cid) {
        if (function_exists('phsbot_leads_del')) {
            $ok_all = $ok_all && phsbot_leads_del($cid);
        } else {
            if (!defined('PHSBOT_LEADS_STORE_OPT')) define('PHSBOT_LEADS_STORE_OPT','phsbot_leads_store');
            $map = get_option(PHSBOT_LEADS_STORE_OPT, array());
            if (isset($map[$cid])) { unset($map[$cid]); $ok_all = $ok_all && update_option(PHSBOT_LEADS_STORE_OPT, $map, false); }
        }
    }
    if ($ok_all) wp_send_json_success();
    wp_send_json_error();
});

/**
 * Reset memoria de navegador (QA):
 * - Incrementa versión de reset en servidor.
 * - Devuelve URL pública de fallback y un CID sugerido (informativo).
 */
add_action('wp_ajax_phsbot_leads_browser_reset', function(){
    check_ajax_referer('phsbot_leads','nonce');
    if (!current_user_can('manage_options')) wp_send_json_error();

    if (!defined('PHSBOT_CLIENT_RESET_OPT')) define('PHSBOT_CLIENT_RESET_OPT', 'phsbot_client_reset_v');

    $v = (int) get_option(PHSBOT_CLIENT_RESET_OPT, 0);
    $v++;
    update_option(PHSBOT_CLIENT_RESET_OPT, $v, false);

    $reset_url = add_query_arg(array('phsbot_reset'=>1, 'v'=>$v, '_'=>time()), site_url('/'));
    $new_cid   = 'cid_' . wp_generate_password(18, false, false);

    wp_send_json_success(array('v'=>$v, 'reset_url'=>$reset_url, 'new_cid'=>$new_cid));
});