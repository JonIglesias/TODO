<?php
// File: inject/inject.php
// Módulo INJECT — triggers por texto del USUARIO
// Tipos: HTML | Shortcode (Elementor) | Vídeo YouTube (autoplay)
// Posición: before (antes), after (después), only (sin respuesta del bot)

defined('ABSPATH') || exit;

if (!defined('PHSBOT_INJECT_OPT'))   define('PHSBOT_INJECT_OPT', 'phsbot_inject_rules');
if (!defined('PHSBOT_INJECT_GROUP')) define('PHSBOT_INJECT_GROUP', 'phsbot_inject_group');
if (!defined('PHSBOT_CAP_SETTINGS')) define('PHSBOT_CAP_SETTINGS', 'manage_options');

/* ============================================================================
 * Helpers de opción (acepta array/json/serializado)
 * ========================================================================== */
if (!function_exists('phsbot_inject_decode')) :
function phsbot_inject_decode($value){
    $out = array('items'=>array());
    if (is_array($value)) { $out['items'] = isset($value['items']) && is_array($value['items']) ? $value['items'] : array(); return $out; }
    if (!is_string($value) || $value==='') return $out;
    $maybe = function_exists('maybe_unserialize') ? maybe_unserialize($value) : @unserialize($value);
    if (is_array($maybe)) { $out['items'] = isset($maybe['items']) && is_array($maybe['items']) ? $maybe['items'] : array(); return $out; }
    if (is_string($maybe)) $value = $maybe;
    $arr = json_decode($value, true);
    if (is_array($arr)) $out['items'] = isset($arr['items']) && is_array($arr['items']) ? $arr['items'] : (isset($arr[0])||empty($arr)?$arr:array());
    return $out;
}
endif;

if (!function_exists('phsbot_inject_get')) :
function phsbot_inject_get(){ $raw=get_option(PHSBOT_INJECT_OPT,'[]'); $cfg=phsbot_inject_decode($raw); if(!isset($cfg['items'])||!is_array($cfg['items']))$cfg['items']=array(); return $cfg; }
endif;

/* ============================================================================
 * Render helpers (YouTube + Elementor)
 * ========================================================================== */
if (!function_exists('phsbot_inject_youtube_id')) :
function phsbot_inject_youtube_id($url){
    $url=trim((string)$url); if($url==='')return '';
    if(preg_match('~youtu\.be/([A-Za-z0-9_-]{6,})~i',$url,$m))return $m[1];
    if(preg_match('~youtube\.com/(?:watch\?v=|embed/|shorts/)([A-Za-z0-9_-]{6,})~i',$url,$m))return $m[1];
    if(preg_match('~[?&]v=([A-Za-z0-9_-]{6,})~',$url,$m))return $m[1];
    return '';
}
endif;

if (!function_exists('phsbot_inject_video_embed')) :
function phsbot_inject_video_embed($url,$autoplay=0){
    // enablejsapi=1 + origin => permite pausar/mutar via postMessage
    $id=phsbot_inject_youtube_id($url);
    if(!$id){ $u=esc_url($url); return '<p><a href="'.$u.'" target="_blank" rel="noopener">Ver vídeo</a></p>'; }
    $ap       = $autoplay ? '1' : '0';
    $origin   = preg_replace('~/$~','', home_url());
    $qs       = array(
        'autoplay='.$ap,
        'rel=0',
        'enablejsapi=1',
        'playsinline=1',
        'origin='.rawurlencode($origin)
    );
    $src=esc_url('https://www.youtube.com/embed/'.$id.'?'.implode('&',$qs));
    $wrap='position:relative;padding-bottom:56.25%;height:0;overflow:hidden;margin:10px 0;';
    $ifr='position:absolute;top:0;left:0;width:100%;height:100%;border:0;';
    return '<div class="phs-embed phs-yt" style="'.$wrap.'"><iframe src="'.$src.'" style="'.$ifr.'" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe></div>';
}
endif;

if (!function_exists('phsbot_inject_render_shortcode')) :
function phsbot_inject_render_shortcode($raw){
    $raw=trim((string)$raw); if($raw==='')return '';
    if(preg_match('/^\[(elementor\-template|elementor_templates)\s+id=(["\']?)(\d+)\2[^\]]*\]$/i',$raw,$m)){
        $id=(int)$m[3];
        if($id>0 && did_action('elementor/loaded') && class_exists('\Elementor\Plugin')){
            try{ $html=\Elementor\Plugin::$instance->frontend->get_builder_content_for_display($id,true); if(is_string($html)&&$html!=='')return $html; }catch(\Throwable $e){}
        }
    }
    return do_shortcode($raw);
}
endif;

if (!function_exists('phsbot_inject_prepare_rules_for_front')) :
function phsbot_inject_prepare_rules_for_front(){
    $cfg=phsbot_inject_get(); $out=array(); if(empty($cfg['items']))return $out;
    foreach($cfg['items'] as $it){
        if(empty($it['enabled']))continue;
        $keywords=array_filter(array_map('trim',explode(',',(string)($it['keywords']??'')))); if(!$keywords)continue;
        $type=isset($it['type'])?$it['type']:(isset($it['action'])?$it['action']:'html');
        $match=in_array(($it['match']??'any'),array('any','all'),true)?$it['match']:'any';
        $place=in_array(($it['place']??'before'),array('before','after','only'),true)?$it['place']:'before';
        $autoplay=!empty($it['autoplay'])?1:0;
        if($type==='video')        $content=phsbot_inject_video_embed((string)($it['video']??''),$autoplay);
        elseif($type==='shortcode')$content=phsbot_inject_render_shortcode((string)($it['payload_sc']??$it['payload']??''));
        else                       $content=(string)($it['payload_html']??$it['payload']??$it['content']??'');
        if(trim($content)==='')continue;
        $out[]=array('keywords'=>array_values($keywords),'match'=>$match,'type'=>$type,'html'=>$content,'place'=>$place);
    }
    return $out;
}
endif;

/* ============================================================================
 * Guardado (OPTIONS API) — mantiene compat
 * ========================================================================== */
add_action('admin_init', function(){
    register_setting(
        PHSBOT_INJECT_GROUP, PHSBOT_INJECT_OPT,
        array(
            'type'=>'string',
            'sanitize_callback'=>function($in){
                $input=phsbot_inject_decode($in); $items=array();
                if(isset($input['items'])&&is_array($input['items'])){
                    foreach($input['items'] as $it){
                        $enabled=!empty($it['enabled'])?1:0;
                        $keywords=isset($it['keywords'])?(string)$it['keywords']:'';
                        $type=(isset($it['type'])&&in_array($it['type'],array('html','shortcode','video'),true))?$it['type']:'html';
                        $match=(isset($it['match'])&&in_array($it['match'],array('any','all'),true))?$it['match']:'any';
                        $place=(isset($it['place'])&&in_array($it['place'],array('before','after','only'),true))?$it['place']:'before';
                        $autoplay=!empty($it['autoplay'])?1:0;
                        $payload_html=isset($it['payload_html'])?(string)$it['payload_html']:(isset($it['payload'])?(string)$it['payload']:'');
                        $payload_sc=isset($it['payload_sc'])?(string)$it['payload_sc']:(isset($it['payload'])?(string)$it['payload']:'');
                        $video=isset($it['video'])?(string)$it['video']:'';
                        $keywords=trim(preg_replace('/\s*,\s*/',',',$keywords)," \t\n\r\0\x0B,");
                        if($type==='video'){ $has=($video!==''); $payload=''; }
                        elseif($type==='shortcode'){ $payload=wp_unslash($payload_sc); $has=(trim($payload)!==''); }
                        else{ $payload=wp_unslash($payload_html); $has=(trim($payload)!==''); }
                        if($keywords!=='' && $has){
                            $items[]=array(
                                'enabled'=>$enabled,'keywords'=>$keywords,'type'=>$type,'match'=>$match,'place'=>$place,'autoplay'=>$autoplay,
                                'payload_html'=>($type==='html')?$payload:'','payload_sc'=>($type==='shortcode')?$payload:'',
                                'video'=>($type==='video')?$video:''
                            );
                        }
                    }
                }
                return wp_json_encode(array('items'=>$items));
            },
            'default'=>'[]',
            'show_in_rest'=>false,
        )
    );
});

/* ============================================================================
 * AJAX: guardado de UN trigger (editar en línea)
 * ========================================================================== */
add_action('wp_ajax_phsbot_inject_save_item', function(){
    if (!current_user_can(PHSBOT_CAP_SETTINGS)) wp_send_json_error(array('msg'=>'perm'));
    check_ajax_referer('phsbot_inject_ajax','nonce');

    $id        = isset($_POST['id']) ? intval($_POST['id']) : -1;
    $enabled   = !empty($_POST['enabled']) ? 1 : 0;
    $keywords  = isset($_POST['keywords']) ? (string) wp_unslash($_POST['keywords']) : '';
    $type      = isset($_POST['type']) ? strtolower((string)$_POST['type']) : 'html';
    $autoplay  = !empty($_POST['autoplay']) ? 1 : 0;
    $place     = (isset($_POST['place']) && in_array($_POST['place'], array('before','after','only'), true)) ? $_POST['place'] : 'before';
    $match     = (isset($_POST['match']) && in_array($_POST['match'], array('any','all'), true)) ? $_POST['match'] : 'any';
    $payload_h = isset($_POST['payload_html']) ? (string) wp_unslash($_POST['payload_html']) : '';
    $payload_s = isset($_POST['payload_sc'])   ? (string) wp_unslash($_POST['payload_sc'])   : '';
    $video     = isset($_POST['video'])        ? (string) wp_unslash($_POST['video'])        : '';

    $keywords = trim(preg_replace('/\s*,\s*/', ',', $keywords), " \t\n\r\0\x0B,");
    if ($keywords === '') wp_send_json_error(array('msg'=>'keywords'));

    $has = false; $payload_html = ''; $payload_sc = ''; $video_url = '';
    if ($type === 'video') { $video_url = $video; $has = ($video_url !== ''); }
    elseif ($type === 'shortcode') { $payload_sc = $payload_s; $has = (trim($payload_sc) !== ''); }
    else { $type = 'html'; $payload_html = $payload_h; $has = (trim($payload_html) !== ''); }
    if (!$has) wp_send_json_error(array('msg'=>'content'));

    $cfg   = phsbot_inject_get();
    $items = $cfg['items'];

    $row = array(
        'enabled'=>$enabled,'keywords'=>$keywords,'type'=>$type,'match'=>$match,'place'=>$place,'autoplay'=>$autoplay,
        'payload_html'=>($type==='html')?$payload_html:'','payload_sc'=>($type==='shortcode')?$payload_sc:'',
        'video'=>($type==='video')?$video_url:''
    );

    if ($id >= 0 && isset($items[$id])) { $items[$id] = $row; $new_id = $id; }
    else { $items[] = $row; $new_id = count($items)-1; }

    update_option(PHSBOT_INJECT_OPT, wp_json_encode(array('items'=>$items)));

    $kw_list = array_filter(array_map('trim', explode(',', $keywords)));
    $title   = $kw_list ? $kw_list[0] : ('#'.$new_id);

    // Tipo con icono
    $type_up = strtoupper($type);
    $icon    = ($type==='video') ? 'video-alt3' : (($type==='shortcode') ? 'shortcode' : 'editor-code');
    $type_html = '<span class="dashicons dashicons-'.esc_attr($icon).'" aria-hidden="true"></span> '.esc_html($type_up);

    if ($type === 'video')         $preview = esc_html($video_url);
    elseif ($type === 'shortcode') $preview = esc_html($payload_sc);
    else                           $preview = esc_html(wp_strip_all_tags($payload_html));

    $del_url = wp_nonce_url(admin_url('admin-post.php?action=phsbot_inject_delete&id='.$new_id),'phsbot_inject_delete');

    wp_send_json_success(array(
        'id'           => $new_id,
        'title'        => $title,
        'keywords'     => $keywords,
        'type'         => $type_up,
        'type_html'    => $type_html,
        'enabled'      => $enabled ? 1 : 0,
        'enabled_text' => $enabled ? 'Activo' : 'Inactivo',
        'preview'      => $preview,
        'delete_url'   => $del_url,
    ));
});

/* ============================================================================
 * Borrado masivo / unitario
 * ========================================================================== */
add_action('admin_post_phsbot_inject_bulk_delete', function(){
    if(!current_user_can(PHSBOT_CAP_SETTINGS)) wp_die('Sin permisos',403);
    check_admin_referer('phsbot_inject_bulk_delete');
    $ids=isset($_POST['ids'])?array_map('intval',(array)$_POST['ids']):array();
    $cfg=phsbot_inject_get(); $new=array();
    foreach($cfg['items'] as $i=>$it){ if(!in_array($i,$ids,true)) $new[]=$it; }
    update_option(PHSBOT_INJECT_OPT, wp_json_encode(array('items'=>$new)));
    wp_redirect(add_query_arg(array('page'=>'phsbot-inject','deleted'=>count($ids)), admin_url('admin.php'))); exit;
});
add_action('admin_post_phsbot_inject_delete', function(){
    if(!current_user_can(PHSBOT_CAP_SETTINGS)) wp_die('Sin permisos',403);
    check_admin_referer('phsbot_inject_delete');
    $id=isset($_GET['id'])?intval($_GET['id']):-1;
    $cfg=phsbot_inject_get(); $new=array();
    foreach($cfg['items'] as $i=>$it){ if($i!==$id) $new[]=$it; }
    update_option(PHSBOT_INJECT_OPT, wp_json_encode(array('items'=>$new)));
    wp_redirect(add_query_arg(array('page'=>'phsbot-inject','deleted'=>1), admin_url('admin.php'))); exit;
});

/* ============================================================================
 * Página ADMIN: listado + banco de inputs (oculto)
 * ========================================================================== */
if (!function_exists('phsbot_inject_admin_page')) :
function phsbot_inject_admin_page(){
    if(!current_user_can(PHSBOT_CAP_SETTINGS)) wp_die('No tienes permisos',403);
    $opt=phsbot_inject_get(); $items=$opt['items']; ?>

    <div class="wrap phsbot-inject-admin">
        <h1>PHSBot — Inyecciones (Triggers) <small style="font-weight:normal;color:#888;">(fallback CSS)</small></h1>

        <p class="phs-help"><strong>Posición de la respuesta:</strong> 
            <em>Antes</em> → se inserta justo después del mensaje del usuario. 
            <em>Después</em> → se espera a la siguiente respuesta del bot y se inserta debajo. 
            <em>Sólo trigger</em> → se muestra lo inyectado y se suprime la siguiente respuesta del bot.</p>

        <?php if(!empty($_GET['deleted'])): ?>
            <div class="updated notice is-dismissible"><p><?php echo intval($_GET['deleted']); ?> trigger(s) eliminados.</p></div>
        <?php endif; ?>

        <h2 class="title">Listado</h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="phs-list-form" id="phs-list-form">
            <?php wp_nonce_field('phsbot_inject_bulk_delete'); ?>
            <input type="hidden" name="action" value="phsbot_inject_bulk_delete" />
            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <input type="submit" class="button action" value="Borrar seleccionados" />
                </div>
                <div class="alignleft actions">
                    <a href="#" class="button button-primary" id="phsbot-add-row-top">Añadir regla</a>
                </div>
                <br class="clear" />
            </div>

            <table class="wp-list-table widefat fixed striped table-view-list posts" id="phs-list-table">
                <thead>
                <tr>
                    <td id="cb" class="manage-column column-cb check-column"><input type="checkbox" class="phs-select-all"></td>
                    <th class="manage-column column-title">Palabras clave</th>
                    <th class="manage-column">Tipo</th>
                    <th class="manage-column">Estado</th>
                    <th class="manage-column">Contenido (preview)</th>
                    <th class="manage-column column-actions" style="text-align:right;">Acciones</th>
                </tr>
                </thead>
                <tbody id="the-list">
                <?php if(!empty($items)): foreach($items as $idx=>$r):
                    $type_raw = $r['type'] ?? 'html';
                    $type_up  = strtoupper($type_raw);
                    $icon     = ($type_raw==='video') ? 'video-alt3' : (($type_raw==='shortcode') ? 'shortcode' : 'editor-code');
                    $type_col = '<span class="dashicons dashicons-'.esc_attr($icon).'"></span> '.esc_html($type_up);

                    $enabled=!empty($r['enabled']);
                    $kw_raw=(string)($r['keywords']??'');
                    $kw_list=array_filter(array_map('trim', explode(',', $kw_raw)));
                    $title=$kw_list ? $kw_list[0] : ('#'.$idx);

                    if($type_raw==='video')         $preview=esc_html($r['video']??'');
                    elseif($type_raw==='shortcode') $preview=esc_html($r['payload_sc']??$r['payload']??'');
                    else                             $preview=esc_html(wp_strip_all_tags($r['payload_html']??$r['payload']??''));

                    $del_url = wp_nonce_url(admin_url('admin-post.php?action=phsbot_inject_delete&id='.$idx),'phsbot_inject_delete');
                    ?>
                    <tr data-id="<?php echo (int)$idx; ?>">
                        <th scope="row" class="check-column"><input type="checkbox" name="ids[]" value="<?php echo (int)$idx; ?>"></th>
                        <td class="title column-title page-title">
                            <strong class="row-title"><?php echo esc_html($title); ?></strong>
                            <div class="row-subtitle"><?php echo esc_html($kw_raw); ?></div>
                        </td>
                        <td class="col-type"><?php echo wp_kses_post($type_col); ?></td>
                        <td class="col-state"><?php echo $enabled ? 'Activo' : 'Inactivo'; ?></td>
                        <td class="col-preview" style="max-width:420px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo $preview; ?></td>
                        <td class="column-actions" style="text-align:right;white-space:nowrap;">
                            <a href="#" class="button button-small phs-edit-btn" data-id="<?php echo (int)$idx; ?>">Editar</a>
                            <a href="<?php echo esc_url($del_url); ?>" class="button button-small button-link-delete phs-del-one">Eliminar</a>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="6">No hay triggers aún. Usa “Añadir regla”.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </form>

        <!-- Banco de inputs (oculto) -->
        <form method="post" action="options.php" id="phsbot-editor-form">
            <?php settings_fields(PHSBOT_INJECT_GROUP); ?>
            <table class="widefat fixed striped" id="phsbot-inject-table" style="display:none;">
                <tbody id="phsbot-inject-rows">
                <?php if(!empty($items)): foreach($items as $idx=>$r):
                    $type=$r['type']??'html'; $place=in_array(($r['place']??'before'),array('before','after','only'),true)?$r['place']:'before'; ?>
                    <tr class="phsbot-inject-row" id="phs-edit-<?php echo (int)$idx; ?>">
                        <td><label><input type="checkbox" name="<?php echo esc_attr(PHSBOT_INJECT_OPT); ?>[items][<?php echo (int)$idx; ?>][enabled]" value="1" <?php checked(!empty($r['enabled'])); ?>></label></td>
                        <td><input type="text" class="regular-text" name="<?php echo esc_attr(PHSBOT_INJECT_OPT); ?>[items][<?php echo (int)$idx; ?>][keywords]" value="<?php echo esc_attr($r['keywords'] ?? ''); ?>"></td>
                        <td>
                            <select name="<?php echo esc_attr(PHSBOT_INJECT_OPT); ?>[items][<?php echo (int)$idx; ?>][type]" class="phs-type">
                                <option value="html" <?php selected($type,'html'); ?>>HTML</option>
                                <option value="shortcode" <?php selected($type,'shortcode'); ?>>Shortcode</option>
                                <option value="video" <?php selected($type,'video'); ?>>Vídeo YouTube</option>
                            </select>
                        </td>
                        <td>
                            <div class="phs-field phs-field--html" style="<?php echo ($type==='html'?'':'display:none'); ?>">
                                <textarea name="<?php echo esc_attr(PHSBOT_INJECT_OPT); ?>[items][<?php echo (int)$idx; ?>][payload_html]" rows="3" class="large-text code"><?php echo esc_textarea($r['payload_html'] ?? $r['payload'] ?? ''); ?></textarea>
                            </div>
                            <div class="phs-field phs-field--shortcode" style="<?php echo ($type==='shortcode'?'':'display:none'); ?>">
                                <input type="text" class="regular-text code" name="<?php echo esc_attr(PHSBOT_INJECT_OPT); ?>[items][<?php echo (int)$idx; ?>][payload_sc]" value="<?php echo esc_attr($r['payload_sc'] ?? $r['payload'] ?? ''); ?>">
                            </div>
                            <div class="phs-field phs-field--video" style="<?php echo ($type==='video'?'':'display:none'); ?>">
                                <input type="url" class="regular-text" name="<?php echo esc_attr(PHSBOT_INJECT_OPT); ?>[items][<?php echo (int)$idx; ?>][video]" value="<?php echo esc_attr($r['video'] ?? ''); ?>">
                            </div>
                        </td>
                        <td><label><input type="checkbox" name="<?php echo esc_attr(PHSBOT_INJECT_OPT); ?>[items][<?php echo (int)$idx; ?>][autoplay]" value="1" <?php checked(!empty($r['autoplay'])); ?>></label></td>
                        <td>
                            <select name="<?php echo esc_attr(PHSBOT_INJECT_OPT); ?>[items][<?php echo (int)$idx; ?>][match]">
                                <option value="any" <?php selected(($r['match']??'any'),'any'); ?>>any</option>
                                <option value="all" <?php selected(($r['match']??'any'),'all'); ?>>all</option>
                            </select>
                        </td>
                        <td>
                            <select name="<?php echo esc_attr(PHSBOT_INJECT_OPT); ?>[items][<?php echo (int)$idx; ?>][place]">
                                <option value="before" <?php selected($place,'before'); ?>>antes de la respuesta</option>
                                <option value="after"  <?php selected($place,'after');  ?>>después de la respuesta</option>
                                <option value="only"   <?php selected($place,'only');   ?>>sólo trigger (sin bot)</option>
                            </select>
                        </td>
                        <td><a href="#" class="button button-link-delete phsbot-del-row">Eliminar</a></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>

            <!-- plantilla para nuevas reglas (activas por defecto) -->
            <input type="hidden" id="phsbot-inject-template" value="<?php
                $tpl = '<tr class=&quot;phsbot-inject-row&quot;>'
                     . '<td><label><input type=&quot;checkbox&quot; checked=&quot;checked&quot; name=&quot;'.esc_attr(PHSBOT_INJECT_OPT).'[items][{i}][enabled]&quot; value=&quot;1&quot;></label></td>'
                     . '<td><input type=&quot;text&quot; class=&quot;regular-text&quot; name=&quot;'.esc_attr(PHSBOT_INJECT_OPT).'[items][{i}][keywords]&quot; value=&quot;&quot;></td>'
                     . '<td><select name=&quot;'.esc_attr(PHSBOT_INJECT_OPT).'[items][{i}][type]&quot; class=&quot;phs-type&quot;><option value=&quot;html&quot; selected>HTML</option><option value=&quot;shortcode&quot;>Shortcode</option><option value=&quot;video&quot;>Vídeo YouTube</option></select></td>'
                     . '<td>'
                     . '  <div class=&quot;phs-field phs-field--html&quot;><textarea name=&quot;'.esc_attr(PHSBOT_INJECT_OPT).'[items][{i}][payload_html]&quot; rows=&quot;3&quot; class=&quot;large-text code&quot;></textarea></div>'
                     . '  <div class=&quot;phs-field phs-field--shortcode&quot; style=&quot;display:none&quot;><input type=&quot;text&quot; class=&quot;regular-text code&quot; name=&quot;'.esc_attr(PHSBOT_INJECT_OPT).'[items][{i}][payload_sc]&quot; value=&quot;&quot;></div>'
                     . '  <div class=&quot;phs-field phs-field--video&quot; style=&quot;display:none&quot;><input type=&quot;url&quot; class=&quot;regular-text&quot; name=&quot;'.esc_attr(PHSBOT_INJECT_OPT).'[items][{i}][video]&quot; value=&quot;&quot;></div>'
                     . '</td>'
                     . '<td><label><input type=&quot;checkbox&quot; name=&quot;'.esc_attr(PHSBOT_INJECT_OPT).'[items][{i}][autoplay]&quot; value=&quot;1&quot;></label></td>'
                     . '<td><select name=&quot;'.esc_attr(PHSBOT_INJECT_OPT).'[items][{i}][match]&quot;><option value=&quot;any&quot; selected>any</option><option value=&quot;all&quot;>all</option></select></td>'
                     . '<td><select name=&quot;'.esc_attr(PHSBOT_INJECT_OPT).'[items][{i}][place]&quot;><option value=&quot;before&quot; selected>antes de la respuesta</option><option value=&quot;after&quot;>después de la respuesta</option><option value=&quot;only&quot;>sólo trigger (sin bot)</option></select></td>'
                     . '<td><a href=&quot;#&quot; class=&quot;button button-link-delete phsbot-del-row&quot;>Eliminar</a></td>'
                     . '</tr>';
                echo esc_attr($tpl);
            ?>" />
        </form>
    </div>
<?php }
endif;

/* ============================================================================
 * Menú
 * ========================================================================== */
if (!function_exists('phsbot_inject_menu_exists')) :
function phsbot_inject_menu_exists($parent,$slug){ global $menu,$submenu; if($parent){ if(!isset($submenu[$parent]))return false; foreach($submenu[$parent] as $it){ if(isset($it[2])&&$it[2]===$slug)return true; } return false; } foreach($menu as $it){ if(isset($it[2])&&$it[2]===$slug)return true; } return false; }
endif;

add_action('admin_menu', function(){
    $parent=(defined('PHSBOT_MENU_SLUG')&&PHSBOT_MENU_SLUG)?PHSBOT_MENU_SLUG:null;
    $slug='phsbot-inject';
    if($parent){ if(!phsbot_inject_menu_exists($parent,$slug)) add_submenu_page($parent,'Inyecciones','Inyecciones',PHSBOT_CAP_SETTINGS,$slug,'phsbot_inject_admin_page'); }
    else{ if(!phsbot_inject_menu_exists(null,$slug)) add_menu_page('PHS Inject','PHS Inject',PHSBOT_CAP_SETTINGS,$slug,'phsbot_inject_admin_page','dashicons-admin-generic',60); }
},99);

/* ============================================================================
 * Enqueue
 * ========================================================================== */
add_action('admin_enqueue_scripts', function(){
    $screen=function_exists('get_current_screen')?get_current_screen():null; $is=false;
    if($screen && !empty($screen->id)){ $sid=strtolower($screen->id); if(strpos($sid,'phsbot')!==false && strpos($sid,'inject')!==false)$is=true; }
    if(!$is && isset($_GET['page'])){ $p=strtolower(sanitize_text_field(wp_unslash($_GET['page']))); if($p==='phsbot-inject')$is=true; }
    if(!$is) return;
    $u=plugin_dir_url(__FILE__); $d=plugin_dir_path(__FILE__);
    if(file_exists($d.'inject.css')) wp_enqueue_style('phsbot-inject',$u.'inject.css',array(),@filemtime($d.'inject.css')?:null);
    if(file_exists($d.'inject.js')) {
        wp_enqueue_script('phsbot-inject',$u.'inject.js',array('jquery'),@filemtime($d.'inject.js')?:null,true);
        wp_localize_script('phsbot-inject','phsbotInjectData',array(
            'context'=>'admin',
            'ajax_url'=> admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('phsbot_inject_ajax'),
        ));
    }
});

add_action('wp_enqueue_scripts', function(){
    $rules=phsbot_inject_prepare_rules_for_front(); if(empty($rules))return;
    $u=plugin_dir_url(__FILE__); $d=plugin_dir_path(__FILE__);
    if(file_exists($d.'inject.css')) wp_enqueue_style('phsbot-inject',$u.'inject.css',array(),@filemtime($d.'inject.css')?:null);
    if(file_exists($d.'inject.js')) {
        wp_enqueue_script('phsbot-inject',$u.'inject.js',array(),@filemtime($d.'inject.js')?:null,true);
        wp_localize_script('phsbot-inject','phsbotInjectData',array(
            'context'=>'front','rules'=>$rules,
            'selectors'=>array('root'=>'#phsbot-root','body'=>'.phsbot-body','userRow'=>'.phsbot-msg.user','botRow'=>'.phsbot-msg.bot','bubble'=>'.phsbot-bubble'),
            'debug'=>(defined('WP_DEBUG')&&WP_DEBUG&&current_user_can('manage_options'))?1:0
        ));
    }
});

/* ============================================================================
 * Alias compat
 * ========================================================================== */
if(!function_exists('phsbot_render_inject_admin')){ function phsbot_render_inject_admin(){ phsbot_inject_admin_page(); } }
if(!function_exists('phsbot_render_inject_page')) { function phsbot_render_inject_page(){ phsbot_inject_admin_page(); } }

