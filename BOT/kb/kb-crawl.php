<?php
/**
 * PHSBOT – KB Crawl (blocklist, descubrimiento, corpus, árbol, BFS profundidad 2)
 * Archivo: /kb/kb-crawl.php
 */
if ( ! defined('ABSPATH') ) exit;

/* ====================== Blocklist / URLs a ignorar ====================== */
function phsbot_kb_is_blocked_url($url) {
    $p = wp_parse_url($url);
    $path = strtolower(isset($p['path']) ? $p['path'] : '/');
    $qry  = strtolower(isset($p['query']) ? $p['query'] : '');

    // 0) Extensiones de assets/feeds/documentos que NO son HTML útil
    if (preg_match('~\.(?:jpe?g|png|gif|webp|svg|avif|ico|pdf|docx?|xlsx?|pptx?|zip|rar|7z|mp4|mov|avi|wmv|mp3|wav|json|xml|rss|atom|txt|csv|woff2?|ttf|eot|otf|map)$~i', $path)) {
        return true;
    }

    // 1) Directorios/zonas que no aportan contenido de negocio
    $blocked_prefixes = [
        // Core WP/admin/API/Includes
        '/wp-admin/', '/wp-login.php', '/wp-signup.php', '/wp-cron.php', '/wp-json/', '/xmlrpc.php',
        '/wp-includes/',

        // Plantillas/constructores/maquetadores
        '/elementor/', '/elementor-templates/', '/templates/', '/template-parts/', '/components/', '/blocks/', '/patterns/',

        // Áreas de usuario / e-commerce
        '/my-account/', '/mi-cuenta/', '/cuenta/', '/account/', '/profile/', '/perfil/', '/customer/', '/usuario/', '/user/',
        '/cart/', '/carrito/', '/checkout/', '/finalizar-compra/', '/pago/', '/orders/', '/order/', '/pedido/', '/pedidos/',
        '/downloads/', '/download/', '/descargas/', '/wishlist/', '/compare/', '/comparar/', '/comparador/',

        // Listados y feeds
        '/feed/', '/feeds/', '/comments/', '/trackback/',

        // Galerías/álbumes genéricos
        '/galeria/', '/galerias/', '/gallery/', '/galleries/', '/album/', '/albums/',
    ];

    // 2) Archivos de taxonomías y etiquetas (incluye WooCommerce y genéricos)
    $taxonomy_prefixes = [
        '/category/', '/categoria/', '/categories/',
        '/tag/', '/etiqueta/', '/etiquetas/',
        '/product-category/', '/product-tag/', '/product-visibility/', '/product-attributes/',
        '/portfolio_category/', '/portfolio-tag/', '/downloads/category/', '/downloads/tag/',
    ];

    // 3) Paginaciones y variantes inútiles
    $blocked_contains = [
        'add-to-cart=', 'wc-ajax', 'wc-api', 'edd_action=', 'um-',
        'lost-password', 'reset-password', 'login', 'logout',
        'order-received', 'thank-you', 'gracias',
        'replytocom=', 'utm_', 'gclid=', 'fbclid=',
        'orderby=', 'filter_', 'min_price=', 'max_price=',
        'paged=', 'page=',
        '/amp',
    ];

    // 4) Patrones por regex (fecha en URL tipo /YYYY/MM/, /page/N/, /amp/)
    $blocked_regex = [
        '~^/\d{4}/\d{2}/~',   // archivos por fecha de blog
        '~/(?:page|pagina)/\d+/?$~',
        '~/amp/?$~',
    ];

    // Permitir personalización vía filtros
    $blocked_prefixes = apply_filters('phsbot_kb_block_patterns', $blocked_prefixes, 'prefixes');
    $taxonomy_prefixes= apply_filters('phsbot_kb_block_patterns', $taxonomy_prefixes,'taxonomy');
    $blocked_contains = apply_filters('phsbot_kb_block_patterns', $blocked_contains, 'contains');
    $blocked_regex    = apply_filters('phsbot_kb_block_patterns', $blocked_regex,    'regex');

    foreach ((array)$blocked_prefixes as $pre) {
        $pre = rtrim(strtolower($pre), '/').'/';
        if ($pre === '///') continue;
        if (strpos($path, rtrim($pre,'/')) === 0) return true;
    }
    foreach ((array)$taxonomy_prefixes as $pre) {
        $pre = rtrim(strtolower($pre), '/').'/';
        if ($pre === '///') continue;
        if (strpos($path, rtrim($pre,'/')) === 0) return true;
    }

    $haystack = $path . ($qry ? ('?' . $qry) : '');
    foreach ((array)$blocked_contains as $needle) {
        if ($needle && strpos($haystack, strtolower($needle)) !== false) return true;
    }
    foreach ((array)$blocked_regex as $rx) {
        if (@preg_match($rx, $path)) { if (preg_match($rx, $path)) return true; }
    }

    return false;
}

/* ====================== Utilidades crawl ====================== */
function phsbot_kb_normalize_url($u){
    $p = wp_parse_url($u);
    if (empty($p['host'])) return rtrim($u,'/').'/';
    $sch = isset($p['scheme']) ? strtolower($p['scheme']) : 'https';
    $host= strtolower($p['host']);
    $path= isset($p['path']) ? $p['path'] : '/';
    $path= ($path==='/'?'/': (substr($path,-1)==='/'?$path:$path.'/'));
    return $sch.'://'.$host.$path;
}
function phsbot_kb_is_likely_post($u, $post_links_set){
    $nu = phsbot_kb_normalize_url($u);
    if (isset($post_links_set[$nu])) return true;
    if (preg_match('~/\d{4}/\d{2}/[^/]+/?$~', $nu)) return true;
    if (preg_match('~/(blog|noticias|news)/[^/]+/?$~i', $nu)) return true;
    return false;
}

/* ====================== Descubrimiento → Corpus ====================== */
function phsbot_kb_build_corpus($base, $extra_specs, $max_urls = 80, $max_pages_main = 50, $max_posts_main = 20) {
    $max_urls       = max(10, min(400, intval($max_urls)));
    $max_pages_main = max(0, min($max_urls, intval($max_pages_main)));
    $max_posts_main = max(0, min($max_urls - $max_pages_main, intval($max_posts_main)));

    $scheme = $base['scheme']; $host = $base['host']; $path = $base['path'];
    $collected = [];

    $has_extra = !empty($extra_specs);
    $quota_main  = $has_extra ? max(10, (int)round($max_urls * 0.80)) : $max_urls;
    $quota_extra = max(0, $max_urls - $quota_main);

    $pages_q = max(0, min($max_pages_main, $quota_main));
    $posts_q = max(0, min($max_posts_main, max(0, $quota_main - $pages_q)));

    $main_urls_typed = phsbot_kb_collect_urls_for_base($scheme, $host, $path, $quota_main, $pages_q, $posts_q);

    foreach ($main_urls_typed as $bucket) {
        $items = phsbot_kb_fetch_texts_for_urls($bucket['urls'], 'main', $host, $bucket['type']);
        $collected = array_merge($collected, $items);
    }

    if ($quota_extra > 0 && $has_extra) {
        $share = max(1, (int)ceil($quota_extra / max(1, count($extra_specs))));
        foreach ($extra_specs as $spec) {
            $sch  = $spec['scheme'] ?? $scheme;
            $h    = $spec['host'];
            $pth  = isset($spec['path']) ? $spec['path'] : '/';
            $urls = phsbot_kb_collect_urls_for_base($sch, $h, $pth, $share);
            $items= phsbot_kb_fetch_texts_for_urls($urls['flat'] ?? $urls, 'extra', $h, 'unknown', 120);
            if (empty($items)) {
                $home = rtrim($sch.'://'.$h.$pth,'/').'/';
                $items = phsbot_kb_fetch_texts_for_urls([$home], 'extra', $h, 'unknown', 80);
            }
            $collected = array_merge($collected, $items);
        }
    }

    if (count($collected) > $max_urls) $collected = array_slice($collected, 0, $max_urls);
    return $collected;
}

/* ——— Descubrimiento para un host/base (menus, sitemap, REST; + BFS profundidad 2) ——— */
function phsbot_kb_collect_urls_for_base($scheme, $host, $path, $limit = 60, $pages_q = null, $posts_q = null) {
    $limit = max(5, min(300, $limit));

    $host_variants = [$host];
    if (stripos($host, 'www.') === 0) $host_variants[] = substr($host, 4);
    else $host_variants[] = 'www.' . $host;

    $scheme_variants = [strtolower($scheme)==='https' ? 'https' : 'http'];
    if (!in_array('http', $scheme_variants, true)) $scheme_variants[] = 'http';
    if (!in_array('https', $scheme_variants, true)) $scheme_variants[] = 'https';

    $base = null; $allowed_prefix = null;
    foreach ($scheme_variants as $sch) {
        foreach ($host_variants as $hv) {
            $candidate = rtrim($sch . '://' . $hv . $path, '/');
            $probe = phsbot_kb_http_get($candidate . '/', 8);
            if (!is_wp_error($probe) && wp_remote_retrieve_response_code($probe) === 200) {
                $base = $candidate;
                $allowed_prefix = $base . '/';
                break 2;
            }
        }
    }
    if (!$base) {
        $base = rtrim($scheme . '://' . $host . $path, '/');
        $allowed_prefix = $base . '/';
    }

    $urls_pages = [];
    $urls_posts = [];
    $urls_unknown = [];
    $flat = [];
    $hasTyped = (is_int($pages_q) || is_int($posts_q));

    // Si posts=0, precalcular set de posts para saltarlos
    $post_exclude_set = [];
    if ($hasTyped && (int)$posts_q === 0) {
        $posts = phsbot_kb_try_wp_rest($base . '/wp-json/wp/v2/posts?per_page=100&_fields=link');
        foreach ($posts as $it) {
            if (!empty($it['link'])) $post_exclude_set[ phsbot_kb_normalize_url($it['link']) ] = true;
        }
    }

    // Páginas y posts vía REST (si están expuestos)
    if ($hasTyped) {
        if ($pages_q > 0) {
            $pages = phsbot_kb_try_wp_rest($base . '/wp-json/wp/v2/pages?per_page=100&_fields=link');
            foreach ($pages as $it) {
                if (!empty($it['link']) && stripos($it['link'], $allowed_prefix) === 0) {
                    if (phsbot_kb_is_blocked_url($it['link'])) continue;
                    $urls_pages[] = $it['link'];
                    if (count($urls_pages) >= $pages_q) break;
                }
            }
        }
        if ($posts_q > 0) {
            $posts = phsbot_kb_try_wp_rest($base . '/wp-json/wp/v2/posts?per_page=100&_fields=link');
            foreach ($posts as $it) {
                if (!empty($it['link']) && stripos($it['link'], $allowed_prefix) === 0) {
                    if (phsbot_kb_is_blocked_url($it['link'])) continue;
                    $urls_posts[] = $it['link'];
                    if (count($urls_posts) >= $posts_q) break;
                }
            }
        }
    }

    // CPTs expuestos via REST → priorizar como "páginas" de negocio
    if ($hasTyped) {
        $pages_room = max(0, $pages_q - count($urls_pages));
        if ($pages_room > 0) {
            $types = phsbot_kb_try_wp_rest($base . '/wp-json/wp/v2/types');
            if (is_array($types)) {
                $exclude = ['page','post','attachment','revision','nav_menu_item','custom_css','customize_changeset','oembed_cache','user_request','wp_block','wp_navigation','wp_template','wp_template_part'];
                foreach ($types as $slug => $meta) {
                    if (in_array($slug, $exclude, true)) continue;
                    $rest_base = is_array($meta) && !empty($meta['rest_base']) ? $meta['rest_base'] : $slug;
                    $list = phsbot_kb_try_wp_rest($base . '/wp-json/wp/v2/' . $rest_base . '?per_page=100&_fields=link');
                    if (!is_array($list)) continue;
                    foreach ($list as $it) {
                        if (!empty($it['link']) && stripos($it['link'], $allowed_prefix) === 0) {
                            if (phsbot_kb_is_blocked_url($it['link'])) continue;
                            if (!in_array($it['link'], $urls_pages, true)) $urls_pages[] = $it['link'];
                            if (count($urls_pages) >= $pages_q) break 2;
                        }
                    }
                }
            }
        }
    }

    /* ====== Navegación inicial: home + comunes ====== */
    $seed = [];
    $homeRes = phsbot_kb_http_get($allowed_prefix, 8);
    if (!is_wp_error($homeRes) && wp_remote_retrieve_response_code($homeRes)===200) {
        $html = wp_remote_retrieve_body($homeRes);
        $navs = phsbot_kb_extract_internal_links($html, $allowed_prefix, $limit);
        foreach ($navs as $u) {
            if (phsbot_kb_is_blocked_url($u)) continue;
            $seed[] = $u;
        }
    }
    $common = ['contacto','contact','contact-us','quienes-somos','about','servicios','services','productos','products','politica-de-privacidad','privacy-policy','aviso-legal','legal-notice','terminos','terms','especies','species'];
    foreach ($common as $slug) {
        $u = $allowed_prefix . $slug . '/';
        if (!phsbot_kb_is_blocked_url($u)) $seed[] = $u;
    }
    $seed = array_values(array_unique($seed));

    // Clasificar semillas en páginas desconocidas
    foreach ($seed as $u) {
        if ($hasTyped) {
            if ($posts_q===0 && phsbot_kb_is_likely_post($u, $post_exclude_set)) continue;
            if (!in_array($u, $urls_pages, true) && !in_array($u, $urls_posts, true) && !in_array($u, $urls_unknown, true)) $urls_unknown[] = $u;
        } else {
            if (!in_array($u, $flat, true)) $flat[] = $u;
        }
    }

    /* ====== Sitemaps ====== */
    $current_total = $hasTyped ? (count($urls_pages) + count($urls_posts) + count($urls_unknown)) : count($flat);
    if ($current_total < $limit) {
        $slot = $limit - $current_total;
        $cands = [$base . '/sitemap.xml', $base . '/wp-sitemap.xml'];
        foreach ($cands as $sm) {
            $found = phsbot_kb_try_parse_sitemap($sm, $slot, $allowed_prefix);
            if (!empty($found)) {
                foreach ($found as $u) {
                    if (phsbot_kb_is_blocked_url($u)) continue;
                    if ($hasTyped) {
                        if ($posts_q===0 && phsbot_kb_is_likely_post($u, $post_exclude_set)) continue;
                        if (!in_array($u, $urls_pages, true) && !in_array($u, $urls_posts, true) && !in_array($u, $urls_unknown, true)) $urls_unknown[] = $u;
                    } else {
                        if (!in_array($u, $flat, true)) $flat[] = $u;
                    }
                    if (($hasTyped ? (count($urls_pages)+count($urls_posts)+count($urls_unknown)) : count($flat)) >= $limit) break 2;
                }
            }
        }
    }

    /* ====== BFS profundidad 2: expandir desde hubs hacia detalle ====== */
    if ($hasTyped) {
        $already = array_flip(array_map('phsbot_kb_normalize_url', array_merge($urls_pages, $urls_posts, $urls_unknown)));
        $queue = array_slice($urls_pages, 0, 10);
        $queue = array_merge($queue, array_slice($urls_unknown, 0, 10));
        $queue = array_values(array_unique($queue));

        $pages_room = max(0, $pages_q - count($urls_pages));
        $unknown_room = max(0, $limit - (count($urls_pages)+count($urls_posts)+count($urls_unknown)));

        $depth = 0;
        while (($pages_room > 0 || $unknown_room > 0) && !empty($queue) && $depth < 2) {
            $next = [];
            foreach ($queue as $parent) {
                $res = phsbot_kb_http_get($parent, 8);
                if (is_wp_error($res) || wp_remote_retrieve_response_code($res)!==200) continue;
                $html = wp_remote_retrieve_body($res);
                $links = phsbot_kb_extract_internal_links($html, $allowed_prefix, 100);
                foreach ($links as $u) {
                    $nu = phsbot_kb_normalize_url($u);
                    if (isset($already[$nu])) continue;
                    if (phsbot_kb_is_blocked_url($u)) continue;
                    if ($posts_q===0 && phsbot_kb_is_likely_post($u, $post_exclude_set)) continue;

                    // Heurística simple de “página de servicio” por slug (neutra)
                    $slug = trim(parse_url($nu, PHP_URL_PATH), '/');
                    $score = 0;
                    if (preg_match('~/(servicios?|services?|productos?|products?|especies?|species?)($|/)~i', $nu)) $score += 2;
                    if (preg_match('~/(ibex|montes|rececho|caza|hunt|grand\-slam|vacaciones|familia|trofeo|trophy)~i', $nu)) $score += 1;

                    if ($pages_room > 0 && $score >= 1) {
                        $urls_pages[] = $u;
                        $pages_room--;
                    } else if ($unknown_room > 0) {
                        $urls_unknown[] = $u;
                        $unknown_room--;
                    }
                    $already[$nu] = true;

                    if ($pages_room <= 0 && $unknown_room <= 0) break;
                }
                if ($pages_room <= 0 && $unknown_room <= 0) break;
                $next = array_merge($next, array_slice($links, 0, 6));
            }
            $queue = array_values(array_unique($next));
            $depth++;
        }
    }

    // Recorte final y empaquetado
    if ($hasTyped) {
        $urls_pages   = array_slice(array_values(array_unique($urls_pages)), 0, max(0,$pages_q));
        $urls_posts   = array_slice(array_values(array_unique($urls_posts)), 0, max(0,$posts_q));
        $remaining    = max(0, $limit - count($urls_pages) - count($urls_posts));
        $urls_unknown = array_slice(array_values(array_unique($urls_unknown)), 0, $remaining);

        return [
            ['type'=>'page',    'urls'=>$urls_pages],
            ['type'=>'post',    'urls'=>$urls_posts],
            ['type'=>'unknown', 'urls'=>$urls_unknown],
        ];
    } else {
        $flat = array_slice(array_values(array_unique($flat)), 0, $limit);
        return ['flat'=>$flat];
    }
}

/* ——— Extraer enlaces internos respetando subcarpeta ——— */
function phsbot_kb_extract_internal_links($html, $allowed_prefix, $slot){
    if ($slot <= 0) return [];
    $links = [];

    $bp = wp_parse_url($allowed_prefix);
    $base_scheme = isset($bp['scheme']) ? $bp['scheme'] : 'https';
    $base_host   = isset($bp['host']) ? $bp['host'] : '';
    $base_path   = isset($bp['path']) ? $bp['path'] : '/';
    if ($base_path === '' ) $base_path = '/';
    if ($base_path !== '/' && substr($base_path,-1) !== '/') $base_path += '/';

    $prefix = rtrim($base_scheme.'://'.$base_host.$base_path,'/').'/';

    if (preg_match_all('~<a\s[^>]*href\s*=\s*(["\'])(.*?)\1[^>]*>~is', $html, $m)) {
        foreach ($m[2] as $href) {
            if (!$href) continue;
            if ($href[0] === '#') continue;

            if (strpos($href, '://') === false) {
                if ($href[0] === '/') {
                    $rel = ltrim($href, '/');
                    if (stripos('/'.$rel, $base_path) === 0) {
                        $href = $base_scheme.'://'.$base_host.'/'.$rel;
                    } else {
                        $href = $prefix . $rel;
                    }
                } else {
                    $href = $prefix . ltrim($href,'/');
                }
            }

            if (stripos($href, $prefix) === 0) {
                $href = strpos($href, '?') !== false ? preg_replace('~\?.*$~','', $href) : $href;
                if (phsbot_kb_is_blocked_url($href)) continue;
                $links[] = $href;
                if (count($links) >= $slot) break;
            }
        }
    }
    return array_values(array_unique($links));
}

/* ——— Sitemaps ——— */
function phsbot_kb_try_parse_sitemap($sitemap_url, $slot, $allowed_prefix = null) {
    if ($slot <= 0) return [];
    if (!$allowed_prefix) {
        $p = wp_parse_url($sitemap_url);
        $sch = isset($p['scheme']) ? $p['scheme'] : 'https';
        $h   = isset($p['host']) ? $p['host'] : '';
        $allowed_prefix = rtrim($sch.'://'.$h,'/').'/';
    }

    $res = phsbot_kb_http_get($sitemap_url, 10);
    if (is_wp_error($res) || wp_remote_retrieve_response_code($res)!==200) return [];
    $xml = wp_remote_retrieve_body($res); if (!trim($xml)) return [];

    $found = [];
    if (strpos($xml, '<sitemapindex') !== false) {
        if (preg_match_all('~<loc>([^<]+)</loc>~i', $xml, $m)) {
            foreach ($m[1] as $sub) {
                $take = $slot - count($found); if ($take <= 0) break;
                $found = array_merge($found, phsbot_kb_try_parse_sitemap($sub, $take, $allowed_prefix));
                if (count($found) >= $slot) break;
            }
        }
    } else {
        if (preg_match_all('~<loc>([^<]+)</loc>~i', $xml, $m)) {
            foreach ($m[1] as $u) {
                if (phsbot_kb_is_blocked_url($u)) continue;
                $found[] = $u;
                if (count($found) >= $slot) break;
            }
        }
    }

    $found = array_values(array_filter($found, function($u) use ($allowed_prefix){
        return stripos($u, $allowed_prefix) === 0;
    }));
    return $found;
}

/* ——— WordPress REST ——— */
function phsbot_kb_try_wp_rest($endpoint) {
    $res = wp_remote_get($endpoint, ['timeout'=>8,'headers'=>['User-Agent'=>'PHSBOT-KB/1.0 (+wordpress)']]);
    if (is_wp_error($res) || wp_remote_retrieve_response_code($res)!==200) return [];
    $data = json_decode(wp_remote_retrieve_body($res), true);
    return is_array($data) ? $data : [];
}

/* ——— Lectura y compactación (solo HTML real) ——— */
function phsbot_kb_fetch_texts_for_urls($urls, $origin = 'main', $src_host = '', $ctype = 'unknown', $minChars = 200) {
    if (isset($urls['flat'])) $urls = $urls['flat'];
    $out = [];
    foreach ($urls as $u) {
        if (phsbot_kb_is_blocked_url($u)) continue;

        $res = wp_remote_get($u, ['timeout'=>10,'headers'=>['User-Agent'=>'PHSBOT-KB/1.0 (+wordpress)']]);
        if (is_wp_error($res) || wp_remote_retrieve_response_code($res)!==200) continue;

        // Solo páginas HTML
        $ct = wp_remote_retrieve_header($res, 'content-type');
        if (!$ct || stripos($ct, 'text/html') === false) continue;

        $html = wp_remote_retrieve_body($res); if (!trim($html)) continue;

        $text = phsbot_kb_html_to_text($html); if (strlen($text) < $minChars) continue;
        $out[] = [
            'url'      => $u,
            'text'     => phsbot_kb_compact_text($text, 20000),
            'domain'   => $origin==='main'?'main':'extra',
            'src_host' => $src_host,
            'type'     => $ctype
        ];
    }
    return $out;
}
function phsbot_kb_html_to_text($html) {
    $html = preg_replace('~<script\b[^>]*>.*?</script>~is', '', $html);
    $html = preg_replace('~<style\b[^>]*>.*?</style>~is', '', $html);
    $html = preg_replace('~</(h1|h2|h3|h4|p|li|section|article|div)>~i', "$0\n", $html);
    $text = wp_strip_all_tags($html, true);
    $text = preg_replace('~[ \t]+~', ' ', $text);
    $text = preg_replace('~\n{3,}~', "\n\n", $text);
    return trim($text);
}
function phsbot_kb_compact_text($text, $max = 20000) {
    $len = strlen($text); if ($len <= $max) return $text;
    $cut = substr($text, 0, $max); $pos = strrpos($cut, "\n");
    if ($pos !== false && $pos > $max - 400) $cut = substr($cut, 0, $pos);
    return rtrim($cut) . "\n[…truncado…]";
}

/* ——— Árbol de secciones ——— */
function phsbot_kb_build_site_tree($urls, $base){
    $rootPrefix = rtrim($base['scheme'].'://'.$base['host'].$base['path'],'/').'/';
    $tree = [];
    foreach ($urls as $u) {
        if (stripos($u,$rootPrefix)!==0) continue;
        $rel = substr($u, strlen($rootPrefix));
        $rel = trim($rel,'/');
        $parts = $rel === '' ? [] : explode('/', $rel);
        $node = &$tree;
        foreach ($parts as $p) {
            if (!isset($node[$p])) $node[$p] = ['__children'=>[],'__count'=>0];
            $node[$p]['__count']++;
            $node = &$node[$p]['__children'];
        }
        if (!isset($node['__leafs'])) $node['__leafs'] = 1;
        else $node['__leafs']++;
        unset($node);
    }
    return $tree;
}

/* ——— Construcción de prompt con corpus ——— */
function phsbot_kb_build_prompt($base_prompt, $extra_prompt, $corpus, $main_host, $extra_hosts) {
    $base = phsbot_kb_get_active_site_base();
    $root = rtrim($base['scheme'].'://'.$base['host'].$base['path'], '/');

    $intro = $base_prompt . "\n\n" .
             ( $extra_prompt ? "Instrucciones adicionales del usuario:\n" . $extra_prompt . "\n\n" : '' ) .
             "Dominios:\n- Principal: {$root}\n" .
             ( !empty($extra_hosts) ? "- Adicionales: " . implode(', ', $extra_hosts) . "\n" : "- Adicionales: (ninguno)\n" ) .
             "Reglas de citas: cita solo URLs del dominio principal; integra los adicionales sin enlazarlos ni nombrar marcas externas.\n\n";

    $blocks = [];
    $i = 0;
    foreach ($corpus as $item) {
        $i++;
        $src = $item['domain'] === 'main' ? 'PRINCIPAL' : 'EXTRA';
        $blocks[] = "### Fuente {$i} ({$src})\nURL: {$item['url']}\n----\n" . $item['text'];
    }

    return $intro .
        "======== CORPUS (LECTURA) ========\n" .
        implode("\n\n", $blocks) .
        "\n======== FIN CORPUS ========\n\n" .
        "Genera ahora el **Documento de Conocimiento Maestro** siguiendo estrictamente las reglas y formatos indicados.";
}