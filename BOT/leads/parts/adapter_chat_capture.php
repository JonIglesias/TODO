<?php
/**
 * PHSBOT – Captura PASIVA del turno del usuario + IA previa (desde 'history')
 *
 * Propósito: Al enviar el usuario, guarda primero la última respuesta de la IA
 * previa (aunque venga en HTML) y después el turno del usuario. No toca el output.
 */
if (!defined('ABSPATH')) exit;


/* ======== HELPERS ======== */
/* ========FIN HELPERS ===== */

/* Devuelve true si la URL parece del front (evita admin y admin-ajax) */
if (!function_exists('phsbot_is_front_url')) {
    function phsbot_is_front_url($url){
        $u = (string) $url;
        if ($u === '') return false;
        if (preg_match('#/(wp-admin|wp-login\.php)#i', $u)) return false;
        if (stripos($u, 'admin-ajax.php') !== false) return false;
        return true;
    }
} /* -- end phsbot_is_front_url -- */


if (!function_exists('phsbot_arr_get')) {
    /** Acceso seguro a array: devuelve $default si no existe clave */
    function phsbot_arr_get($arr, $key, $default=null){
        return (is_array($arr) && array_key_exists($key, $arr)) ? $arr[$key] : $default;
    }
} /* -- end phsbot_arr_get -- */


if (!function_exists('phsbot_html_to_text')) {
    /** Convierte HTML básico a texto conservando saltos */
    function phsbot_html_to_text($html){
        $s = (string)$html;
        if ($s === '') return '';
        // Sustituye <br> y cierres de bloque por saltos:
        $s = preg_replace('#<\s*br\s*/?>#i', "\n", $s);
        $s = preg_replace('#</\s*(p|li|div|h[1-6])\s*>#i', "\n", $s);
        if (function_exists('wp_strip_all_tags')) {
            $s = wp_strip_all_tags($s, false);
        } else {
            $s = strip_tags($s);
        }
        // Normaliza espacios
        $s = str_replace(array("\r\n","\r"), "\n", $s);
        $s = preg_replace('/[ \t\x0B\f]+/u', ' ', $s);
        $s = preg_replace('/\n{3,}/', "\n\n", $s);
        return trim($s);
    }
} /* -- end phsbot_html_to_text -- */


if (!function_exists('phsbot_normalize_text')) {
    /** Normaliza espacios y saltos (para comparar y guardar) */
    function phsbot_normalize_text($s){
        $s = (string) $s;
        if ($s === '') return '';
        // Quita tags por si cuelan html en content
        if (function_exists('wp_strip_all_tags')) {
            $s = wp_strip_all_tags($s, false);
        } else {
            $s = strip_tags($s);
        }
        $s = str_replace(array("\r\n","\r"), "\n", $s);
        $s = preg_replace('/[ \t\x0B\f]+/u', ' ', $s);
        $s = preg_replace('/\n{3,}/', "\n\n", $s);
        return trim($s);
    }
} /* -- end phsbot_normalize_text -- */


if (!function_exists('phsbot_history_item_text')) {
    /**
     * Extrae texto de un item del history:
     * - Prefiere 'html' si existe (lo convierte a texto).
     * - Si no, usa 'content'.
     * - Soporta casos raros tipo {content:{text:"..."}}.
     */
    function phsbot_history_item_text($item){
        if (!is_array($item)) return '';
        // 1) html → texto
        $h = phsbot_arr_get($item, 'html', '');
        if (is_string($h) && trim($h) !== '') {
            return phsbot_html_to_text($h);
        }
        // 2) content plano
        $c = phsbot_arr_get($item, 'content', '');
        if (is_string($c) && trim($c) !== '') {
            return phsbot_normalize_text($c);
        }
        // 3) content anidado
        $cx = phsbot_arr_get($item, 'content', array());
        if (is_array($cx)) {
            $t = phsbot_arr_get($cx, 'text', '');
            if (is_string($t) && trim($t) !== '') {
                return phsbot_normalize_text($t);
            }
        }
        return '';
    }
} /* -- end phsbot_history_item_text -- */



/* ======== ACCIONES SOPORTADAS ======== */
/* ========FIN ACCIONES SOPORTADAS ===== */

if (!function_exists('phsbot_chat_supported_actions')) {
    /** Acciones admin-ajax del chat */
    function phsbot_chat_supported_actions(){
        return array('phsbot_chat','phsbot_chat_send','phsbot_chat_ask','phsbot_chat_message','phs_chat_send');
    }
} /* -- end phsbot_chat_supported_actions -- */



/* ======== RESOLVER CID Y URL ======== */
/* ========FIN RESOLVER CID Y URL ===== */

if (!function_exists('phsbot_resolve_cid_and_url')) {
    /** Resuelve CID y URL con control de reset por versión */
    function phsbot_resolve_cid_and_url(){
        $server_v = (int) get_option(PHSBOT_CLIENT_RESET_OPT, 0);
        $client_v = isset($_COOKIE['phsbot_reset_v']) ? (int) $_COOKIE['phsbot_reset_v'] : 0;
        $must_force_new = ($client_v < $server_v);

        $cookie_key = 'phsbot_cid';
        $cookie_cid = isset($_COOKIE[$cookie_key]) ? sanitize_text_field(wp_unslash($_COOKIE[$cookie_key])) : '';
        $post_cid   = isset($_POST['cid']) ? sanitize_text_field(wp_unslash($_POST['cid'])) : '';
        $bad        = ($post_cid === '' || strtolower($post_cid) === 'cid-float' || strtolower($post_cid) === 'cid-fallback');

        if ($must_force_new || $bad && $cookie_cid === '') {
            $new_cid = 'cid_' . wp_generate_password(18, false, false);
            setcookie($cookie_key, $new_cid, time() + YEAR_IN_SECONDS, COOKIEPATH?COOKIEPATH:'/', COOKIE_DOMAIN, is_ssl(), false);
            setcookie('phsbot_reset_v', (string) $server_v, time() + YEAR_IN_SECONDS, COOKIEPATH?COOKIEPATH:'/', COOKIE_DOMAIN, is_ssl(), false);
            $_POST['cid'] = $post_cid = $cookie_cid = $new_cid;
        } elseif ($bad) {
            $_POST['cid'] = $post_cid = $cookie_cid;
        }

        $page_url = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';
        if (!phsbot_is_front_url($page_url)) $page_url = '';

        return array($post_cid, $page_url);
    }
} /* -- end phsbot_resolve_cid_and_url -- */



/* ======== CAPTURA PASIVA ======== */
/* ========FIN CAPTURA PASIVA ===== */

add_action('init', function () {
    /* Hook en admin-ajax: guarda IA previa (html o texto) y el turno del usuario */
    if (!defined('DOING_AJAX') || !DOING_AJAX) return;

    $action = isset($_REQUEST['action']) ? sanitize_key($_REQUEST['action']) : '';
    if (!$action || !in_array($action, phsbot_chat_supported_actions(), true)) return;

    if (!function_exists('phsbot_leads_on_exchange')) return;
    if (function_exists('phsbot_leads_setting_get') && !phsbot_leads_setting_get('enable_store', 1)) return;

    list($cid, $page_url) = phsbot_resolve_cid_and_url();

    // 0) Texto actual del USUARIO (tu chat usa 'q'; 'message' por compat)
    $user_text = '';
    if (isset($_POST['q']))           { $user_text = (string) wp_unslash($_POST['q']); }
    elseif (isset($_POST['message'])) { $user_text = (string) wp_unslash($_POST['message']); }
    $user_text = phsbot_normalize_text($user_text);
    if ($user_text === '') return; // sin turno de usuario, no guardamos nada

    // 1) IA inmediatamente anterior (desde 'history'), soportando {html} o {content}
    $last_assistant = '';
    if (!empty($_POST['history'])) {
        $hist_raw = wp_unslash($_POST['history']);
        $hist     = json_decode($hist_raw, true);

        if (is_array($hist) && !empty($hist)) {
            // Recorremos hacia atrás y cogemos el primer 'assistant' con texto
            for ($i = count($hist) - 1; $i >= 0; $i--) {
                $item = $hist[$i];
                $role = is_array($item) ? phsbot_arr_get($item, 'role', '') : '';
                if ($role !== 'assistant') continue;
                $txt = phsbot_history_item_text($item);
                if ($txt !== '') { $last_assistant = $txt; break; }
            }
        }
    }

    // 1.1) Guarda IA previa primero si hay y no es duplicada
    if ($last_assistant !== '') {
        $lead = function_exists('phsbot_leads_get') ? phsbot_leads_get($cid) : null;

        $dup = false;
        if (is_array($lead) && !empty($lead['messages'])) {
            for ($i = count($lead['messages']) - 1; $i >= 0; $i--) {
                $m = $lead['messages'][$i];
                if (phsbot_arr_get($m, 'role', '') === 'assistant') {
                    $prev = phsbot_normalize_text(phsbot_arr_get($m, 'text', ''));
                    if ($prev !== '' && $prev === $last_assistant) $dup = true;
                    break;
                }
            }
        }

        if (!$dup) {
            phsbot_leads_on_exchange($cid, '', $last_assistant, array('url' => $page_url));
        }
    }

    // 2) Guarda el USUARIO (actual)
    phsbot_leads_on_exchange($cid, $user_text, '', array('url' => $page_url));

}, 0); /* -- end admin-ajax capture -- */
