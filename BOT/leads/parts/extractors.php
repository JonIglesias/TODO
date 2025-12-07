<?php
if (!defined('ABSPATH')) exit;

if (!function_exists('phsbot_str_normalize_space')) {
    function phsbot_str_normalize_space($s){
        $s = wp_strip_all_tags((string)$s);
        $s = preg_replace('/\s+/u', ' ', $s);
        return trim($s);
    }
}

if (!function_exists('phsbot_leads_extract_email')) {
    function phsbot_leads_extract_email($text) {
        $text = phsbot_str_normalize_space($text);
        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $m)) {
            return sanitize_email($m[0]);
        }
        return '';
    }
}

if (!function_exists('phsbot_leads_extract_phone')) {
    function phsbot_leads_extract_phone($text) {
        $text = preg_replace('/[^\d+]/', '', (string)$text);
        if (strpos($text, '00') === 0) $text = '+' . substr($text, 2);
        if (preg_match('/^\+?\d{6,15}$/', $text)) return $text;
        return '';
    }
}

if (!function_exists('phsbot_leads_backfill_name')) {
    function phsbot_leads_backfill_name($lead) {
        if (!empty($lead['name'])) return $lead;
        if (!empty($lead['messages']) && is_array($lead['messages'])) {
            foreach ($lead['messages'] as $m) {
                if (isset($m['role']) && $m['role'] === 'user') {
                    $t = strtolower(wp_strip_all_tags(phsbot_arr_get($m, 'text', '')));
                    if (preg_match('/(soy|me llamo|mi nombre es)\s+([a-záéíóúñ ]{2,})/u', $t, $mm)) {
                        $name = trim($mm[2]); if ($name) { $lead['name'] = ucwords($name); break; }
                    }
                }
            }
        }
        return $lead;
    }
}
