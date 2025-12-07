<?php
/**
 * Vista pública por token: IA truncada + botón "ver respuesta de la IA completa"
 * URL: https://tuweb.com/?phslead=TOKEN
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('phsbot_arr_get')) {
    function phsbot_arr_get($arr, $key, $default=null){
        return (is_array($arr) && array_key_exists($key, $arr)) ? $arr[$key] : $default;
    }
}
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

add_action('template_redirect', function(){
    if (empty($_GET['phslead'])) return;

    $token = sanitize_text_field(wp_unslash($_GET['phslead']));
    if ($token === '') return;
    if (!function_exists('phsbot_leads_all')) return;

    $map = phsbot_leads_all();
    $lead = null;
    foreach ($map as $l) {
        if (!empty($l['public_token']) && hash_equals($l['public_token'], $token)) { $lead = $l; break; }
    }
    if (!$lead) {
        status_header(404);
        wp_die('<h1>Lead no encontrado</h1>', 404);
    }

    nocache_headers();
    header('Content-Type: text/html; charset=utf-8');

    $title_id = phsbot_leads_display_title($lead);
    $score = esc_html(phsbot_arr_get($lead,'score','–'));
    $page  = esc_html(phsbot_arr_get($lead,'page',''));

    $truncate = function($text, $max){
        $text = trim(wp_strip_all_tags((string)$text));
        if ($text === '') return '';
        $parts = preg_split('/\s+/u', $text);
        if (count($parts) <= $max) return esc_html($text);
        return esc_html(implode(' ', array_slice($parts, 0, $max)) . '…');
    };

    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>'.esc_html($title_id).' – Conversación</title>';
    echo '<style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,"Fira Sans","Droid Sans","Helvetica Neue",Arial,sans-serif;margin:0;background:#0d1117;color:#f0f6fc}
    .wrap{max-width:800px;margin:20px auto;padding:16px}
    .card{background:#0f141b;border:1px solid #30363d;border-radius:10px;padding:16px}
    .bubble{padding:10px 12px;border-radius:10px;margin:8px 0;max-width:90%}
    .u{background:#273b64;margin-left:auto}
    .a{background:#2b3137;margin-right:auto}
    .meta{font-size:12px;opacity:.8;margin:4px 2px}
    h1{margin:0 0 10px}
    .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px 16px;margin-bottom:10px}
    @media(max-width:600px){.grid{grid-template-columns:1fr}}
    .link{background:none;border:0;padding:0;margin-left:8px;text-decoration:underline;cursor:pointer;font-size:11px;opacity:.9;color:#9ecbff}
    .link:hover{text-decoration:none;opacity:1}
    </style></head><body><div class="wrap"><div class="card">';
    echo '<h1>'.esc_html($title_id).'</h1>';
    echo '<div class="grid">';
    echo '<div><strong>Score:</strong> '.$score.'</div>';
    echo '<div><strong>Página:</strong> '.$page.'</div>';
    echo '</div>';

    $msgs = phsbot_arr_get($lead,'messages',array());
    foreach ($msgs as $m) {
        $isUser = ($m['role'] === 'user');
        $who  = $isUser ? 'Usuario' : 'IA';
        $full = (string)phsbot_arr_get($m,'text','');
        echo '<div class="bubble '.($isUser?'u':'a').'"><div class="meta"><strong>'.$who.'</strong></div>';
        if ($isUser) {
            echo '<div class="text">'.wp_kses_post($full).'</div>';
        } else {
            $tr = $truncate($full, 4);
            $full_attr = esc_attr($full);
            echo '<div class="text"><span class="msg-trunc">'.$tr.'</span>';
            if (trim($tr,'…') !== trim(wp_strip_all_tags($full))) {
                echo ' <button class="link phs-more" data-full="'.$full_attr.'">ver respuesta de la IA completa</button>';
            }
            echo '</div>';
        }
        echo '</div>';
    }

    echo '<script>
    document.addEventListener("click",function(e){
      if(e.target && e.target.classList.contains("phs-more")){
        var t=e.target; var wrap=t.closest(".text"); if(!wrap) return;
        var full=t.getAttribute("data-full")||""; 
        var span=wrap.querySelector(".msg-trunc");
        if(span){ span.textContent = full; }
        t.remove();
      }
    });
    </script>';

    echo '</div></div></body></html>';
    exit;
});