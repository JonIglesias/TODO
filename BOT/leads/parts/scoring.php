<?php
if (!defined('ABSPATH')) exit;

/* ======== AJUSTES GLOBALES (MAIN SETTINGS) ======== */
/** Obtiene los ajustes principales del plugin (API keys, etc.) */
if (!function_exists('phsbot_leads_get_main_settings')) {
    function phsbot_leads_get_main_settings() {
        $s = get_option(PHSBOT_MAIN_SETTINGS_OPT, array());
        return is_array($s) ? $s : array();
    }
}
/* ========FIN AJUSTES GLOBALES (MAIN SETTINGS) ===== */



/* ======== HELPERS DE MODELO (USAR MODELO GLOBAL + GPT-5) ======== */
/** Constante de opción del chat (fallback seguro) */
if (!defined('PHSBOT_CHAT_OPT')) {
    define('PHSBOT_CHAT_OPT', 'phsbot_chat_settings');
}

/** Lee el modelo configurado en la página general (chat/config.php) */
if (!function_exists('phsbot_leads_get_chat_model')) {
    function phsbot_leads_get_chat_model() {
        $chat = get_option(PHSBOT_CHAT_OPT, array());
        if (!is_array($chat)) $chat = array();
        // Usa modelo configurado en BD, si no existe usa valor de BOT_DEFAULT_MODEL de API5
        $model = isset($chat['model']) && $chat['model'] ? (string)$chat['model'] : (defined('BOT_DEFAULT_MODEL') ? BOT_DEFAULT_MODEL : 'gpt-4o-mini');
        return $model;
    }
}

/** ¿El modelo es GPT-5* y debe usar /v1/responses? */
if (!function_exists('phsbot_model_uses_responses_api')) {
    function phsbot_model_uses_responses_api($model) {
        return (bool) preg_match('/^gpt-?5/i', (string)$model);
    }
}

/** Convierte messages[] → input[] para /v1/responses */
if (!function_exists('phsbot_messages_to_responses_input')) {
    function phsbot_messages_to_responses_input($messages){
        $out = array();
        foreach ((array)$messages as $m){
            $role = isset($m['role']) ? (string)$m['role'] : 'user';
            $txt  = isset($m['content']) ? (string)$m['content'] : '';
            $out[] = array(
                'role'    => $role,
                'content' => array(
                    array('type' => 'input_text', 'text' => $txt)
                ),
            );
        }
        return $out;
    }
}
/* ========FIN HELPERS DE MODELO (USAR MODELO GLOBAL + GPT-5) ===== */



/* ======== UTIL: NORMALIZAR ESPACIOS ======== */
/** Normaliza espacios y limpia etiquetas (por si no cargó extractors.php) */
if (!function_exists('phsbot_str_normalize_space')) {
    function phsbot_str_normalize_space($s){
        $s = wp_strip_all_tags((string)$s);
        $s = preg_replace('/\s+/u', ' ', $s);
        return trim($s);
    }
}
/* ========FIN UTIL: NORMALIZAR ESPACIOS ===== */



/* ======== UTIL: SANEADO Y VALIDACIÓN DE TELÉFONO E.164 ======== */
/** Intenta normalizar a E.164 (convierte 00X a +X, quita espacios/guiones/paréntesis) */
if (!function_exists('phsbot_leads_sanitize_e164')) {
    function phsbot_leads_sanitize_e164($raw){
        $p = (string)$raw;
        $p = trim($p);
        $p = preg_replace('/[^\d\+]/', '', $p);              // deja + y dígitos
        if (strpos($p, '00') === 0) $p = '+' . substr($p, 2); // 0034... → +34...
        if ($p !== '' && $p[0] !== '+') $p = $p;              // no inventar prefijo
        // validación E.164 básica: + seguido de 8-15 dígitos
        if (preg_match('/^\+[1-9]\d{7,14}$/', $p)) return $p;
        return $raw; // si no cumple, devolvemos original sin forzar
    }
}
/* ========FIN UTIL: SANEADO Y VALIDACIÓN DE TELÉFONO E.164 ===== */



/* ======== HEURÍSTICA DE SCORE (UNIVERSAL) ======== */
/** Calcula un score simple cuando no hay IA disponible (agnóstico de dominio) */
if (!function_exists('phsbot_leads_score_heuristic')) {
    function phsbot_leads_score_heuristic($lead) {
        $score = 0;

        // Señales de contacto directo
        if (!empty($lead['email'])) $score += 5;
        if (!empty($lead['phone'])) $score += 5;

        // Palabras de intención (ES/EN) — genéricas, no de un sector concreto
        $intent_words = array(
            'precio','presupuesto','coste','tarifa','price','budget','cost','quote','quotation',
            'reserva','reservar','comprar','pedido','order','book','booking','purchase','subscribe','suscribir',
            'fecha','fechas','disponibilidad','agenda','cita','horario','date','dates','availability','schedule','appointment',
            'urgente','asap','prisa','plazo','deadline','hoy','mañana','esta semana','este mes',
            'demo','prueba','trial',
            'entrega','envío','shipping','delivery','instalación','installation',
            'soporte','ayuda','support'
        );

        $txt = '';
        if (!empty($lead['messages'])) {
            foreach ($lead['messages'] as $m) {
                if ($m['role'] === 'user' && !empty($m['text'])) {
                    $txt .= ' '.strtolower(wp_strip_all_tags($m['text']));
                }
            }
        }

        $add = 0;
        foreach ($intent_words as $w) {
            if (strpos($txt, $w) !== false) $add++;
        }
        $score += min(5, $add);

        return min(10, $score);
    }
}
/* ========FIN HEURÍSTICA DE SCORE (UNIVERSAL) ===== */



/* ======== RECONCILIACIÓN IA DE DATOS DE CONTACTO ======== */
/**
 * Revisa TODA la conversación y los valores actuales del lead para:
 *  - Detectar correcciones posteriores de nombre/email/teléfono.
 *  - Si aparece un prefijo de país más tarde, combinarlo con el número previo y devolver E.164.
 *  - No inventar; priorizar lo más reciente confirmado por el USUARIO.
 * Actualiza el lead si hay cambios y persiste.
 */
if (!function_exists('phsbot_leads_maybe_update_contact_from_conversation')) {
    function phsbot_leads_maybe_update_contact_from_conversation($lead) {
        // Obtener configuración de la API5
        $bot_license = (string) phsbot_setting('bot_license_key', '');
        $bot_api_url = (string) phsbot_setting('bot_api_url', 'https://bocetosmarketing.com/api_claude_5/index.php');

        if (!$bot_license) return false;

        $domain = parse_url(home_url(), PHP_URL_HOST);

        // 1) Armar conversación completa (con roles y content)
        $conversation = array();
        if (!empty($lead['messages'])) {
            foreach ($lead['messages'] as $m) {
                $conversation[] = array(
                    'role'    => ($m['role'] === 'user') ? 'user' : 'assistant',
                    'content' => phsbot_str_normalize_space($m['text'])
                );
            }
        }
        if (empty($conversation)) return false;

        // 2) Valores actuales
        $current = array(
            'name'  => (string) phsbot_arr_get($lead, 'name', ''),
            'email' => (string) phsbot_arr_get($lead, 'email', ''),
            'phone' => (string) phsbot_arr_get($lead, 'phone', ''),
        );

        // 3) Llamar al endpoint de API5
        $api_endpoint = trailingslashit($bot_api_url) . '?route=bot/extract-contact';

        $api_payload = array(
            'license_key' => $bot_license,
            'domain' => $domain,
            'conversation' => $conversation,
            'current_values' => $current
        );

        $res = wp_remote_post($api_endpoint, array(
            'timeout' => 30,
            'headers' => array('Content-Type' => 'application/json'),
            'body' => wp_json_encode($api_payload),
        ));

        if (is_wp_error($res)) return false;

        $code = wp_remote_retrieve_response_code($res);
        if ($code !== 200) return false;

        $body = json_decode(wp_remote_retrieve_body($res), true);

        if (!is_array($body) || !isset($body['success']) || !$body['success']) {
            return false;
        }

        $extracted = $body['data'] ?? array();

        $changed = false;

        // Name
        $new_name = isset($extracted['name']) ? trim((string)$extracted['name']) : '';
        if (!empty($new_name) && $new_name !== phsbot_arr_get($lead, 'name', '')) {
            $lead['name'] = $new_name;
            $changed = true;
        }

        // Email (validación mínima)
        $new_email = isset($extracted['email']) ? trim((string)$extracted['email']) : '';
        if (!empty($new_email) && is_email($new_email) && $new_email !== phsbot_arr_get($lead, 'email', '')) {
            $lead['email'] = $new_email;
            $changed = true;
        }

        // Phone (E.164 si procede)
        $new_phone = isset($extracted['phone']) ? trim((string)$extracted['phone']) : '';
        if (!empty($new_phone)) {
            $san = phsbot_leads_sanitize_e164($new_phone);
            if ($san !== phsbot_arr_get($lead, 'phone', '')) {
                $lead['phone'] = $san;
                $changed = true;
            }
        }

        if ($changed) {
            $lead['last_change_ts'] = time();
            if (function_exists('phsbot_leads_set')) phsbot_leads_set($lead);
        }

        return $changed;
    }
}
/* ========FIN RECONCILIACIÓN IA DE DATOS DE CONTACTO ===== */



/* ======== SCORE CON OPENAI (USA MODELO GLOBAL Y COMPAT GPT-5) ======== */
/** Calcula el score con OpenAI. Devuelve [score|null, rationale_html] */
if (!function_exists('phsbot_leads_score_openai')) {
    function phsbot_leads_score_openai($lead) {
        // Obtener configuración de la API5
        $bot_license = (string) phsbot_setting('bot_license_key', '');
        $bot_api_url = (string) phsbot_setting('bot_api_url', 'https://bocetosmarketing.com/api_claude_5/index.php');

        if (!$bot_license) return array(null, '');

        $domain = parse_url(home_url(), PHP_URL_HOST);

        $s = phsbot_leads_settings();

        // Conversación completa (con roles y content)
        $conversation = array();
        if (!empty($lead['messages'])) {
            foreach ($lead['messages'] as $m) {
                $conversation[] = array(
                    'role'    => ($m['role'] === 'user') ? 'user' : 'assistant',
                    'content' => phsbot_str_normalize_space($m['text'])
                );
            }
        }

        if (empty($conversation)) return array(null, '');

        // Prompt de scoring
        $scoring_prompt = (string)$s['scoring_prompt'];
        if (trim($scoring_prompt) === '') return array(null, '');

        // Llamar al endpoint de API5
        $api_endpoint = trailingslashit($bot_api_url) . '?route=bot/score-lead';

        $api_payload = array(
            'license_key' => $bot_license,
            'domain' => $domain,
            'conversation' => $conversation,
            'scoring_prompt' => $scoring_prompt
        );

        $res = wp_remote_post($api_endpoint, array(
            'timeout' => 30,
            'headers' => array('Content-Type' => 'application/json'),
            'body' => wp_json_encode($api_payload),
        ));

        if (is_wp_error($res)) return array(null, '');

        $code = wp_remote_retrieve_response_code($res);
        if ($code !== 200) return array(null, '');

        $body = json_decode(wp_remote_retrieve_body($res), true);

        if (!is_array($body) || !isset($body['success']) || !$body['success']) {
            return array(null, '');
        }

        $result = $body['data'] ?? array();
        $score = isset($result['score']) ? floatval($result['score']) : null;
        $rationale = isset($result['rationale']) ? wp_kses_post($result['rationale']) : '';

        return array($score, $rationale);
    }
}
/* ========FIN SCORE CON OPENAI (USA MODELO GLOBAL Y COMPAT GPT-5) ===== */



/* ======== RESUMEN IA (UNIVERSAL, SOLO MENSAJES DEL USUARIO) ======== */
/**
 * Genera RESUMEN IA (texto plano) leyendo SOLO mensajes del USUARIO.
 * - Usa OpenAI si hay API key; si no, heurística universal (sin citas literales).
 * - Cachea por PHSBOT_SUMMARY_TTL en lead['summary_cache'].
 * - Addendum: si detecta nombre/email/teléfono, pedir incluirlos; si hay prefijo+núm., E.164.
 */
if (!function_exists('phsbot_leads_summary_text')) {
    function phsbot_leads_summary_text($lead) {
        $cache = phsbot_arr_get($lead, 'summary_cache', array());
        $ts    = (int)phsbot_arr_get($cache, 'ts', 0);
        if ($ts && (time() - $ts) < PHSBOT_SUMMARY_TTL) {
            $txt = phsbot_arr_get($cache, 'txt', '');
            if ($txt !== '') return $txt;
        }

        // 1) Construir corpus SOLO con el usuario
        $user_lines = array();
        if (!empty($lead['messages'])) {
            foreach ($lead['messages'] as $m) {
                if ($m['role'] === 'user' && !empty($m['text'])) {
                    $user_lines[] = phsbot_str_normalize_space($m['text']);
                }
            }
        }
        $corpus = implode("\nU: ", $user_lines);
        $corpus = $corpus ? "U: ".$corpus : '';

        // 2) Prompt (neutral/universal)
        $s = phsbot_leads_settings();
        $default_prompt = "Lee exclusivamente lo que escribe el usuario y resume en 3-5 viñetas claras: qué solicita, datos relevantes (fechas, cantidades, referencias), dudas y señales de intención/urgencia. Sé conciso, español neutro, sin adornos ni conclusiones.";
        $prompt = trim((string)phsbot_arr_get($s, 'summary_prompt', $default_prompt));
        if ($prompt === '') $prompt = $default_prompt;

        $addendum = "IMPORTANTE (no inventes): si aparecen nombre, email o teléfono, inclúyelos explícitamente.\n".
                    "Si hay prefijo internacional y número, devuelve el teléfono en formato E.164 (sin espacios ni guiones), p. ej. +34600111222.\n".
                    "Incluye estos datos solo si aparecen; no asumas ni completes faltantes.";
        $prompt_effective = $prompt . "\n\n" . $addendum;

        // 3) OpenAI a través de API5
        $bot_license = (string) phsbot_setting('bot_license_key', '');
        $bot_api_url = (string) phsbot_setting('bot_api_url', 'https://bocetosmarketing.com/api_claude_5/index.php');
        $summary_txt = '';

        if ($bot_license && $corpus !== '') {
            $domain = parse_url(home_url(), PHP_URL_HOST);

            // Llamar al endpoint de API5
            $api_endpoint = trailingslashit($bot_api_url) . '?route=bot/summarize-conversation';

            $api_payload = array(
                'license_key' => $bot_license,
                'domain' => $domain,
                'user_messages' => $user_lines,
                'summary_prompt' => $prompt
            );

            $res = wp_remote_post($api_endpoint, array(
                'timeout' => 30,
                'headers' => array('Content-Type' => 'application/json'),
                'body' => wp_json_encode($api_payload),
            ));

            if (!is_wp_error($res) && wp_remote_retrieve_response_code($res) === 200) {
                $body = json_decode(wp_remote_retrieve_body($res), true);
                if (is_array($body) && isset($body['success']) && $body['success']) {
                    $summary_txt = trim($body['data']['summary'] ?? '');
                }
            }
        }

        // 4) Fallback heurístico universal si no hubo IA o corpus vacío
        if ($summary_txt === '') {
            $bullets = array();
            $joined  = strtolower(' '.implode(' ', $user_lines));
            $add = function($s) use (&$bullets){ if ($s && count($bullets) < 5) $bullets[] = '• '.$s; };

            // Precio / presupuesto / coste (ES/EN)
            if (preg_match('/\b(precio|presupuesto|coste|tarifa|price|budget|cost|quote|quotation)\b/u', $joined)) {
                $add('Consulta de precio/presupuesto.');
            }

            // Fechas / disponibilidad / agenda / cita (ES/EN)
            if (preg_match('/\b(fecha|fechas|disponibil|agenda|cita|horario|date|dates|availability|schedule|appointment)\b/u', $joined)) {
                $add('Consulta por fechas/agenda/disponibilidad.');
            }

            // Intención de compra / reserva / pedido (ES/EN)
            if (preg_match('/\b(reserva|reservar|comprar|pedido|order|book|booking|purchase|subscribe|suscrib)\b/u', $joined)) {
                $add('Intención de compra/reserva/pedido.');
            }

            // Ubicación / zona / país / ciudad / dirección (ES/EN)
            if (preg_match('/\b(ubicaci[oó]n|localizaci[oó]n|zona|regi[oó]n|pa[ií]s|ciudad|direcci[oó]n|location|region|country|city|address)\b/u', $joined)) {
                $add('Mención de ubicación/área geográfica.');
            }

            // Cantidad / unidades / personas (ES/EN)
            if (preg_match('/\b(cantidad|unidades|n[uú]mero|personas|plazas|qty|units|people|guests)\b/u', $joined)) {
                $add('Indica cantidades/personas/unidades.');
            }

            // Especificaciones / requisitos / compatibilidad / características (ES/EN)
            if (preg_match('/\b(requisit|caracter[ií]stic|tama[nñ]o|medidas|modelo|versi[oó]n|compatib|especificaci[oó]n|spec|feature)\b/u', $joined)) {
                $add('Pide especificaciones/requisitos del producto/servicio.');
            }

            // Urgencia / plazos (ES/EN)
            if (preg_match('/\b(urgente|asap|prisa|plazo|deadline|hoy|ma[ñn]ana|esta semana|este mes|lo antes posible)\b/u', $joined)) {
                $add('Muestra urgencia o límite temporal.');
            }

            // Datos de contacto mencionados (ES/EN)
            if (preg_match('/\b(tel[eé]fono|m[oó]vil|whatsapp|email|correo|mail|phone|mobile|e-?mail)\b/u', $joined)) {
                $add('Menciona datos de contacto (teléfono/email).');
            }

            if (empty($bullets) && $joined !== '') {
                $add('Interés general; solicita información.');
            }

            $summary_txt = implode("\n", $bullets);
        }

        // 5) Guardar en cache del lead y persisitir
        $html = '';
        if ($summary_txt !== '') {
            $lines = preg_split('/\r\n|\r|\n/', $summary_txt);
            $html = '<div class="summary">';
            foreach ($lines as $ln) {
                $ln = trim($ln);
                if ($ln === '') continue;
                $ln = preg_replace('/^[•\-\*]\s*/u', '', $ln);
                $html .= '<div>• '. esc_html($ln) .'</div>';
            }
            $html .= '</div>';
        }

        $lead['summary_cache'] = array('txt'=>$summary_txt, 'html'=>$html, 'ts'=>time(), 'prompt'=>$prompt);
        if (function_exists('phsbot_leads_set')) phsbot_leads_set($lead);

        return $summary_txt;
    }
}
/* ========FIN RESUMEN IA (UNIVERSAL, SOLO MENSAJES DEL USUARIO) ===== */



/* ======== RESUMEN → HTML (PERSIANA) ======== */
/** Devuelve HTML del resumen (usa cache; si falta, lo recalcula) */
if (!function_exists('phsbot_leads_summary_html')) {
    function phsbot_leads_summary_html($lead) {
        $cache = phsbot_arr_get($lead, 'summary_cache', array());
        $ts    = (int)phsbot_arr_get($cache, 'ts', 0);
        $html_cached = phsbot_arr_get($cache, 'html', '');
        if ($ts && (time() - $ts) < PHSBOT_SUMMARY_TTL && $html_cached !== '') {
            return $html_cached;
        }

        // Genera (y persiste) si no hay cache válida
        phsbot_leads_summary_text($lead);
        $lead2 = function_exists('phsbot_leads_get') ? phsbot_leads_get($lead['cid']) : $lead;
        $cache2 = phsbot_arr_get($lead2, 'summary_cache', array());
        return phsbot_arr_get($cache2, 'html', '');
    }
}
/* ========FIN RESUMEN → HTML (PERSIANA) ===== */



/* ======== RECALCULAR SCORE Y ACTUALIZAR LEAD ======== */
/** Reconciliar contacto (IA) → recalcular score (IA→heurística si falla) → persistir */
if (!function_exists('phsbot_leads_score_and_update')) {
    function phsbot_leads_score_and_update($cid) {
        $lead = phsbot_leads_get($cid);
        if (!$lead) return false;

        // 1) Antes de nada, reconciliar/actualizar contacto desde la conversación
        phsbot_leads_maybe_update_contact_from_conversation($lead);

        // 2) Scoring
        list($score_ia, $rat_ia) = phsbot_leads_score_openai($lead);
        if ($score_ia === null) {
            $lead['score'] = phsbot_leads_score_heuristic($lead);
            if (empty($lead['rationale'])) {
                $lead['rationale'] = __('Heurística: señales de contacto e intención por palabras clave.', 'phsbot');
            }
        } else {
            $lead['score']     = max(0, min(10, (float)$score_ia));
            $lead['rationale'] = $rat_ia;
        }

        phsbot_leads_set($lead);
        return true;
    }
}
/* ========FIN RECALCULAR SCORE Y ACTUALIZAR LEAD ===== */
