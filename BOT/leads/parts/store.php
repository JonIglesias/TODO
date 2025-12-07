<?php
if (!defined('ABSPATH')) exit;

/** Helpers de almacén */
if (!function_exists('phsbot_arr_get')) {
    function phsbot_arr_get($arr, $key, $default=null){
        return (is_array($arr) && array_key_exists($key, $arr)) ? $arr[$key] : $default;
    }
}

if (!function_exists('phsbot_leads_raw_store')) {
    function phsbot_leads_raw_store() {
        return get_option(PHSBOT_LEADS_STORE_OPT, array());
    }
}

if (!function_exists('phsbot_leads_all')) {
    function phsbot_leads_all() {
        $raw = phsbot_leads_raw_store();
        if (is_string($raw)) {
            $try = json_decode($raw, true);
            if (is_array($try)) $raw = $try;
        }
        $out = array();
        if (is_array($raw)) {
            foreach ($raw as $lead) {
                if (!is_array($lead) && !is_object($lead)) continue;
                $lead = (array)$lead;
                $cid  = isset($lead['cid']) ? sanitize_text_field($lead['cid']) : '';
                if (!$cid) { $cid = 'cid_' . substr(md5(maybe_serialize($lead).microtime(true)),0,10); }
                $lead['cid']         = $cid;
                $lead['first_ts']    = isset($lead['first_ts']) ? (int)$lead['first_ts'] : time();
                $lead['last_seen']   = isset($lead['last_seen']) ? (int)$lead['last_seen'] : $lead['first_ts'];
                $lead['messages']    = !empty($lead['messages']) && is_array($lead['messages']) ? $lead['messages'] : array();
                $lead['closed']      = !empty($lead['closed']) ? 1 : 0;
                $lead['last_change_ts'] = isset($lead['last_change_ts']) ? (int)$lead['last_change_ts'] : $lead['last_seen'];
                $out[$cid] = $lead;
            }
        }
        return $out;
    }
}

if (!function_exists('phsbot_leads_save_store')) {
    function phsbot_leads_save_store($map) {
        if (!is_array($map)) $map = array();
        return update_option(PHSBOT_LEADS_STORE_OPT, $map, false);
    }
}

if (!function_exists('phsbot_leads_get')) {
    function phsbot_leads_get($cid) {
        $cid = sanitize_text_field($cid);
        $all = phsbot_leads_all();
        return phsbot_arr_get($all, $cid);
    }
}

if (!function_exists('phsbot_leads_set')) {
    function phsbot_leads_set($lead) {
        if (!is_array($lead)) return false;
        if (empty($lead['cid'])) $lead['cid'] = 'cid_' . substr(md5(wp_json_encode($lead).microtime(true)),0,10);
        $all = phsbot_leads_all();
        $lead['last_change_ts'] = time();
        $all[$lead['cid']] = $lead;
        return phsbot_leads_save_store($all);
    }
}

if (!function_exists('phsbot_leads_delete_by_cid')) {
    function phsbot_leads_delete_by_cid($cid){
        $cid = sanitize_text_field($cid);
        $all = phsbot_leads_all();
        if (isset($all[$cid])) { unset($all[$cid]); return phsbot_leads_save_store($all); }
        return false;
    }
}
