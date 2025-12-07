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
        $model = isset($chat['model']) && $chat['model'] ? (string)$chat['model'] : 'gpt-4o-mini';
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
        $main    = phsbot_leads_get_main_settings();
        $api_key = trim(phsbot_arr_get($main, 'openai_api_key', ''));
        if (!$api_key) return false;

        // 1) Armar conversación completa (con roles U/A)
        $sum = '';
        if (!empty($lead['messages'])) {
            foreach ($lead['messages'] as $m) {
                $r = ($m['role'] === 'user') ? 'U' : 'A';
                $t = phsbot_str_normalize_space($m['text']);
                $sum .= "{$r}: {$t}\n";
            }
        }
        if ($sum === '') return false;

        // 2) Valores actuales
        $current = array(
            'name'  => (string) phsbot_arr_get($lead, 'name', ''),
            'email' => (string) phsbot_arr_get($lead, 'email', ''),
            'phone' => (string) phsbot_arr_get($lead, 'phone', ''),
        );

        // 3) Prompt estricto (solo JSON, sin inventar)
        $instructions = "Eres un asistente de datos. Lee la conversación completa (mensajes U/A). ".
                        "Tareas:\n".
                        "1) Si el USUARIO aporta o corrige NOMBRE, EMAIL o TELÉFONO en cualquier momento, toma el ÚLTIMO dato confirmado.\n".
                        "2) Si antes había número sin prefijo y más tarde aporta prefijo de país, combínalos y devuelve TELÉFONO en formato E.164 (sin espacios ni guiones), p. ej. +34600111222.\n".
                        "3) NO inventes datos. Si un dato no aparece claro, devuélvelo vacío.\n".
                        "4) Devuelve SOLO un JSON con esta forma:\n".
                        "{\n".
                        "  \"name\": \"\",\n".
                        "  \"email\": \"\",\n".
                        "  \"phone\": \"\",\n".
                        "  \"changed\": {\"name\":true|false, \"email\":true|false, \"phone\":true|false}\n".
                        "}\n".
                        "Reglas: prioriza lo más reciente dicho por el USUARIO. Si duda o se desdice, elige la última versión afirmativa.";

        $model = phsbot_leads_get_chat_model();
        $use_responses = phsbot_model_uses_responses_api($model);

        $headers = array('Content-Type'=>'application/json','Authorization'=>'Bearer '.$api_key);

        if ($use_responses) {
            // GPT-5 → /responses
            $body = array(
                'model'             => $model,
                'input'             => phsbot_messages_to_responses_input(array(
                    array('role'=>'system','content'=>$instructions.
                        "\n\nValores actuales:\n".wp_json_encode($current)."\n\nConversación:\n".$sum),
                )),
                'temperature'       => 0.0,
                'max_output_tokens' => 400,
                'metadata'          => array('cid'=> phsbot_arr_get($lead,'cid',''), 'source'=>'phsbot-leads-contact-reconcile'),
            );
            $args = array('headers'=>$headers,'timeout'=>12,'body'=>wp_json_encode($body));
            $resp = wp_remote_post('https://api.openai.com/v1/responses', $args);
            if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) return false;
            $data = json_decode(wp_remote_retrieve_body($resp), true);

            $txt = '';
            if (isset($data['output']) && is_array($data['output'])) {
                foreach ($data['output'] as $item) {
                    if (!empty($item['content']) && is_array($item['content'])){
                        foreach ($item['content'] as $c){
                            if (isset($c['text']))        $txt .= (string)$c['text'];
                            elseif (isset($c['output_text'])) $txt .= (string)$c['output_text'];
                        }
                    }
                }
            }
        } else {
            // Modelos chat → /chat/completions
            $body = array(
                'model'       => $model,
                'temperature' => 0.0,
                'messages'    => array(
                    array('role'=>'system','content'=>$instructions),
                    array('role'=>'user','content'=>"Valores actuales:\n".wp_json_encode($current)."\n\nConversación:\n".$sum),
                ),
            );
            $args = array('headers'=>$headers,'timeout'=>12,'body'=>wp_json_encode($body));
            $resp = wp_remote_post('https://api.openai.com/v1/chat/completions', $args);
            if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) return false;
            $data = json_decode(wp_remote_retrieve_body($resp), true);
            $txt  = (string) phsbot_arr_get($data['choices'][0]['message'], 'content', '');
        }

        if (!is_string($txt) || $txt === '') return false;

        // 4) Parsear primer JSON del texto
        if (!preg_match('/\{.*\}/s', $txt, $mm)) return false;
        $j = json_decode($mm[0], true);
        if (!is_array($j)) return false;

        $changed = false;

        // Name
        $new_name = isset($j['name']) ? trim((string)$j['name']) : '';
        if (!empty($new_name) && $new_name !== phsbot_arr_get($lead, 'name', '')) {
            $lead['name'] = $new_name;
            $changed = true;
        }

        // Email (validación mínima)
        $new_email = isset($j['email']) ? trim((string)$j['email']) : '';
        if (!empty($new_email) && is_email($new_email) && $new_email !== phsbot_arr_get($lead, 'email', '')) {
            $lead['email'] = $new_email;
            $changed = true;
        }

        // Phone (E.164 si procede)
        $new_phone = isset($j['phone']) ? trim((string)$j['phone']) : '';
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
        $main   = phsbot_leads_get_main_settings();
        $api_key = trim(phsbot_arr_get($main, 'openai_api_key', ''));
        if (!$api_key) return array(null, '');

        $s = phsbot_leads_settings();

        // Conversación completa (U/A) para el scoring
        $sum = '';
        if (!empty($lead['messages'])) {
            foreach ($lead['messages'] as $m) {
                $role = ($m['role'] === 'user') ? 'U' : 'A';
                $sum .= "{$role}: " . phsbot_str_normalize_space($m['text']) . "\n";
            }
        }

        $prompt = (string)$s['scoring_prompt'] . "\n\nConversación:\n{$sum}\n";

        $model = phsbot_leads_get_chat_model();
        $use_responses = phsbot_model_uses_responses_api($model);

        $headers = array(
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer '.$api_key,
        );

        if ($use_responses) {
            // GPT-5 → /responses
            $body = array(
                'model'             => $model,
                'input'             => phsbot_messages_to_responses_input(array(
                    array('role'=>'system','content'=>'Eres un analista comercial objetivo.'),
                    array('role'=>'user','content'=>$prompt),
                )),
                'temperature'       => 0.2,
                'max_output_tokens' => 600,
                'metadata'          => array('cid'=> phsbot_arr_get($lead,'cid',''), 'source'=>'phsbot-leads-score'),
            );
            $args = array('headers'=>$headers,'timeout'=>15,'body'=>wp_json_encode($body));
            $resp = wp_remote_post('https://api.openai.com/v1/responses', $args);
            if (is_wp_error($resp)) return array(null, '');
            $code = wp_remote_retrieve_response_code($resp);
            $data = json_decode(wp_remote_retrieve_body($resp), true);
            if ($code !== 200 || !is_array($data)) return array(null, '');

            // Extraer texto de output[].content[].text / output_text
            $text = '';
            if (isset($data['output']) && is_array($data['output'])) {
                foreach ($data['output'] as $item) {
                    if (!empty($item['content']) && is_array($item['content'])){
                        foreach ($item['content'] as $c){
                            if (isset($c['text']))        $text .= (string)$c['text'];
                            elseif (isset($c['output_text'])) $text .= (string)$c['output_text'];
                        }
                    }
                }
            }
        } else {
            // Modelos chat → /chat/completions
            $body = array(
                'model'       => $model,
                'temperature' => 0.2,
                'messages'    => array(
                    array('role'=>'system','content'=>'Eres un analista comercial objetivo.'),
                    array('role'=>'user','content'=>$prompt),
                ),
            );
            $args = array('headers'=>$headers,'timeout'=>15,'body'=>wp_json_encode($body));
            $resp = wp_remote_post('https://api.openai.com/v1/chat/completions', $args);
            if (is_wp_error($resp)) return array(null, '');
            $code = wp_remote_retrieve_response_code($resp);
            $data = json_decode(wp_remote_retrieve_body($resp), true);
            if ($code !== 200 || !is_array($data)) return array(null, '');
            $content = phsbot_arr_get(phsbot_arr_get($data, 'choices', array()), 0, array());
            $msg     = phsbot_arr_get($content, 'message', array());
            $text    = phsbot_arr_get($msg, 'content', '');
        }

        if (preg_match('/\{.*\}/s', (string)$text, $m)) {
            $j = json_decode($m[0], true);
            if (is_array($j)) {
                return array(
                    isset($j['score']) ? floatval($j['score']) : null,
                    isset($j['rationale']) ? wp_kses_post($j['rationale']) : ''
                );
            }
        }
        return array(null, '');
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

        // 3) OpenAI si está configurado
        $main       = phsbot_leads_get_main_settings();
        $api_key    = trim(phsbot_arr_get($main, 'openai_api_key', ''));
        $summary_txt = '';

        if ($api_key && $corpus !== '') {
            $model = phsbot_leads_get_chat_model();
            $use_responses = phsbot_model_uses_responses_api($model);

            $headers = array('Content-Type'=>'application/json','Authorization'=>'Bearer '.$api_key);

            if ($use_responses) {
                // GPT-5 → /responses
                $body = array(
                    'model'             => $model,
                    'input'             => phsbot_messages_to_responses_input(array(
                        array('role'=>'system','content'=>'Eres un analista de leads. Responde solo con viñetas, sin prefacios.'),
                        array('role'=>'user','content'=> $prompt_effective . "\n\nConversación (solo el usuario):\n".$corpus),
                    )),
                    'temperature'       => 0.2,
                    'max_output_tokens' => 600,
                    'metadata'          => array('cid'=> phsbot_arr_get($lead,'cid',''), 'source'=>'phsbot-leads-summary'),
                );
                $args = array('headers'=>$headers,'timeout'=>15,'body'=>wp_json_encode($body));
                $resp = wp_remote_post('https://api.openai.com/v1/responses', $args);
                if (!is_wp_error($resp) && wp_remote_retrieve_response_code($resp) === 200) {
                    $data = json_decode(wp_remote_retrieve_body($resp), true);
                    $buf  = '';
                    if (isset($data['output']) && is_array($data['output'])) {
                        foreach ($data['output'] as $item) {
                            if (!empty($item['content']) && is_array($item['content'])){
                                foreach ($item['content'] as $c){
                                    if (isset($c['text']))        $buf .= (string)$c['text'];
                                    elseif (isset($c['output_text'])) $buf .= (string)$c['output_text'];
                                }
                            }
                        }
                    }
                    $summary_txt = trim($buf);
                }
            } else {
                // Modelos chat → /chat/completions
                $body = array(
                    'model'       => $model,
                    'temperature' => 0.2,
                    'messages'    => array(
                        array('role'=>'system','content'=>'Eres un analista de leads. Responde solo con viñetas, sin prefacios.'),
                        array('role'=>'user','content'=> $prompt_effective . "\n\nConversación (solo el usuario):\n".$corpus),
                    ),
                );
                $args = array('headers'=>$headers,'timeout'=>15,'body'=>wp_json_encode($body));
                $resp = wp_remote_post('https://api.openai.com/v1/chat/completions', $args);
                if (!is_wp_error($resp) && wp_remote_retrieve_response_code($resp) === 200) {
                    $data = json_decode(wp_remote_retrieve_body($resp), true);
                    $summary_txt = phsbot_arr_get($data['choices'][0]['message'], 'content', '');
                    $summary_txt = trim($summary_txt);
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
