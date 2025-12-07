<?php
if (!defined('ABSPATH')) exit;

/* ======== DETECCIÓN DE IDIOMA (PHSBOT) ======== */
/*
 * Conjunto de utilidades puras (sin dependencias externas) para:
 * 1) Detectar idioma por flag de plugin multilenguaje (WPML/Polylang/TranslatePress/Weglot) o HTML lang.
 * 2) Inferir idioma por contexto (título + contenido + nombre/slogan del sitio).
 * 3) Optionally: considerar el último mensaje del usuario para el idioma de respuesta.
 *
 * Prioridad solicitada:
 *   1) Flag de plugin de idiomas (o <html lang> fiable).
 *   2) Contexto de la web (título, contenido).
 *   3) Último mensaje del usuario (solo para decidir el idioma de la RESPUESTA).
 */
/* ======== FIN DETECCIÓN DE IDIOMA (PHSBOT) ===== */


/* ======== NORMALIZAR CÓDIGO ISO ======== */
/* Convierte variantes (es_ES, en-GB, ES) a 'es', 'en', etc. */
function phsbot_lang_iso2($code){
    $c = strtolower((string)$code);
    if ($c === '') return '';
    // reemplaza guiones bajos por guiones y extrae los 2 primeros alfa
    $c = preg_replace('/[_]/','-',$c);
    if (preg_match('/^[a-z]{2}/', $c, $m)) return $m[0];
    return '';
} /* ======== FIN NORMALIZAR CÓDIGO ISO ===== */  


/* ======== FLAG DE PLUGINS / ATRIBUTOS HTML ======== */
/* Lee lenguaje desde plugins comunes y <html lang>. Devuelve '' si no hay señal fiable. */
function phsbot_detect_from_plugins(){
    // WPML
    if (defined('ICL_LANGUAGE_CODE') && ICL_LANGUAGE_CODE) {
        return phsbot_lang_iso2(ICL_LANGUAGE_CODE);
    }

    // Polylang
    if (function_exists('pll_current_language')) {
        $pll = pll_current_language('slug'); // 'es', 'en', etc.
        if ($pll) return phsbot_lang_iso2($pll);
    }
    if (!empty($_COOKIE['pll_language'])) {
        return phsbot_lang_iso2($_COOKIE['pll_language']);
    }

    // TranslatePress (cookie o función)
    if (!empty($_COOKIE['trp_language'])) {
        return phsbot_lang_iso2($_COOKIE['trp_language']);
    }
    if (function_exists('trp_get_current_language')) {
        $trp = trp_get_current_language();
        if ($trp) return phsbot_lang_iso2($trp);
    }

    // Weglot
    if (function_exists('weglot_get_current_language')) {
        $wg = weglot_get_current_language(); // suele ser 'es'/'en'
        if ($wg) return phsbot_lang_iso2($wg);
    }
    if (!empty($_COOKIE['weglot_language'])) {
        return phsbot_lang_iso2($_COOKIE['weglot_language']);
    }

    // <html lang="…">
    $attrs = get_language_attributes( 'html' ); // p.ej.: lang="es-ES" dir="ltr"
    if ($attrs && preg_match('/lang="([^"]+)"/i', $attrs, $m)) {
        $h = phsbot_lang_iso2($m[1]);
        if ($h) return $h;
    }

    // Core WP como último indicio “flag”
    $core = get_bloginfo('language'); // p.ej. es-ES
    if ($core) {
        return phsbot_lang_iso2($core);
    }

    return '';
} /* ======== FIN FLAG DE PLUGINS / ATRIBUTOS HTML ===== */  


/* ======== HEURÍSTICA DE TEXTO ======== */
/* Devuelve 'es', 'en' o '' si no hay señal suficiente. */
function phsbot_guess_lang_from_text($text){
    $t = strtolower( wp_strip_all_tags( (string)$text ) );
    $t = preg_replace('/\s+/',' ', $t);
    if ($t === '') return '';

    // Palabras muy frecuentes por idioma (no exhaustivo, suficiente para contenido web)
    $es_words = array(' el ',' la ',' los ',' las ',' de ',' del ',' y ',' con ',' para ',' por ',' que ',' en ',' una ',' un ',' más ',' pero ',' sí ',' no ',' como ',' sobre ',' entre ',' hasta ',' desde ',' donde ',' cuando ',' cuál ',' quién ',' porque ');
    $en_words = array(' the ',' and ',' of ',' to ',' in ',' for ',' on ',' with ',' as ',' is ',' are ',' this ',' that ',' from ',' by ',' at ',' it ',' be ',' or ',' not ',' which ',' who ',' where ',' when ',' why ');

    $score_es = 0; $score_en = 0;

    // Acentos y signos (pistas fuertes de español)
    $accents = preg_match_all('/[áéíóúüñ¿¡]/u', $t);
    $score_es += $accents * 2;

    // Coincidencia de stopwords
    foreach ($es_words as $w) { if (strpos($t, $w) !== false) $score_es++; }
    foreach ($en_words as $w) { if (strpos($t, $w) !== false) $score_en++; }

    // Ponderación por densidad de vocales con tilde (muy típico español)
    $chars = max(1, strlen($t));
    $score_es += min(3, ($accents / $chars) * 1000);

    // Regla de decisión con margen
    if ($score_es >= $score_en + 2) return 'es';
    if ($score_en >= $score_es + 2) return 'en';

    // Regla de desempate: si hay tildes/ñ, favorece es; si no, inglés.
    if ($accents > 0) return 'es';
    return 'en';
} /* ======== FIN HEURÍSTICA DE TEXTO ===== */  


/* ======== TEXTO DE PÁGINA ACTUAL ======== */
/* Construye un bloque de texto con título, contenido, nombre y descripción del sitio. */
function phsbot_collect_page_text(){
    $pieces = array();

    if (function_exists('is_singular') && is_singular()) {
        $post = get_post();
        if ($post) {
            $pieces[] = get_the_title($post);
            $content = apply_filters('the_content', $post->post_content);
            $content = wp_strip_all_tags($content);
            // recorte suave
            if (mb_strlen($content) > 4000) $content = mb_substr($content, 0, 4000);
            $pieces[] = $content;
        }
    }

    // Home / archivos
    $pieces[] = get_bloginfo('name');
    $desc = get_bloginfo('description', 'display');
    if ($desc) $pieces[] = $desc;

    return trim( implode("\n", array_filter($pieces)) );
} /* ======== FIN TEXTO DE PÁGINA ACTUAL ===== */  


/* ======== IDIOMA DEL SITIO (1->2) ======== */
/* Aplica prioridad 1) flags plugins / <html lang> y 2) contexto de página. */
function phsbot_site_language(){
    // 1) Flag plugins / html lang
    $flag = phsbot_detect_from_plugins();
    if ($flag) return $flag;

    // 2) Contexto de página
    $ctx = phsbot_collect_page_text();
    $ctx_lang = phsbot_guess_lang_from_text($ctx);
    if ($ctx_lang) return $ctx_lang;

    // Fallback final
    return 'es';
} /* ======== FIN IDIOMA DEL SITIO (1->2) ===== */  


/* ======== IDIOMA PARA RESPUESTA (3 con prioridad a 1-2) ======== */
/* Devuelve el idioma a usar en la respuesta. Respeta 1-2; si el último mensaje del usuario
 * es muy probablemente en otro idioma, prioriza el del usuario SOLO para la respuesta.
 */
function phsbot_reply_language($user_last_text = ''){
    $site = phsbot_site_language();

    $u = trim((string)$user_last_text);
    if ($u !== '') {
        $u_lang = phsbot_guess_lang_from_text($u);
        if ($u_lang && $u_lang !== $site) {
            // Prioriza el idioma del usuario para esta respuesta concreta
            return $u_lang;
        }
    }
    return $site;
} /* ======== FIN IDIOMA PARA RESPUESTA (3 con prioridad a 1-2) ===== */  


/* ======== ETIQUETA HUMANA ======== */
/* Devuelve nombre legible del idioma (solo 'es'/'en' por ahora). */
function phsbot_lang_human($iso2){
    $iso2 = phsbot_lang_iso2($iso2);
    switch ($iso2){
        case 'en': return 'inglés';
        case 'es': return 'español';
        default:   return $iso2;
    }
} /* ======== FIN ETIQUETA HUMANA ===== */
