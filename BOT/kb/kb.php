<?php
/**
 * PHSBOT – KB (Base de Conocimiento) – Orquestación de Admin + AJAX
 * Archivo: /kb/kb.php
 */
if ( ! defined('ABSPATH') ) exit;

/** Cargar núcleos */
require_once __DIR__ . '/kb-core.php';
require_once __DIR__ . '/kb-crawl.php';

/* ============================================================================
   Encolado de assets (CSS/JS) y datos
   ============================================================================ */
add_action('admin_enqueue_scripts', 'phsbot_kb_admin_enqueue');
function phsbot_kb_admin_enqueue($hook){
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    $is_kb = false;
    if (isset($_GET['page'])) {
        $page = sanitize_text_field($_GET['page']);
        if ($page==='phsbot_kb'||$page==='phsbot-kb'||(strpos($page,'phsbot')!==false && strpos($page,'kb')!==false)) $is_kb = true;
    }
    if (!$is_kb && $screen) {
        $id = (string)$screen->id;
        if (strpos($id,'phsbot')!==false && strpos($id,'kb')!==false) $is_kb = true;
    }
    if (!$is_kb) return;

    if (function_exists('wp_enqueue_editor')) wp_enqueue_editor();

    $base_dir  = defined('PHSBOT_DIR') ? rtrim(PHSBOT_DIR, '/\\') : plugin_dir_path(dirname(__FILE__));
    $main_file = defined('PHSBOT_DIR') ? rtrim(PHSBOT_DIR, '/\\') . '/phsbot.php' : plugin_dir_path(dirname(__FILE__)) . 'phsbot.php';
    $ver_js    = file_exists($base_dir . '/kb/kb.js')  ? filemtime($base_dir . '/kb/kb.js')  : '2.6.0';
    $ver_css   = file_exists($base_dir . '/kb/kb.css') ? filemtime($base_dir . '/kb/kb.css') : '2.6.0';

    // CSS unificado (cargar primero)
    wp_enqueue_style(
        'phsbot-modules-unified',
        plugins_url('core/assets/modules-unified.css', $main_file),
        [],
        '1.4'
    );

    wp_enqueue_style ('phsbot-kb-css', plugins_url('kb/kb.css', $main_file), ['phsbot-modules-unified'], $ver_css);
    wp_enqueue_script('phsbot-kb-js',  plugins_url('kb/kb.js',  $main_file), ['jquery'], $ver_js, true);

    $models    = phsbot_kb_get_models(false);
    $sel       = get_option('phsbot_kb_model', '');
    $version   = intval(get_option('phsbot_kb_version', 0));
    $updated   = get_option('phsbot_kb_last_updated', '');
    $detBase   = phsbot_kb_detect_site_base();
    $actBase   = phsbot_kb_get_active_site_base();
    $ovOn      = (bool) get_option('phsbot_kb_site_override_on', false);
    $ovVal     = (string) get_option('phsbot_kb_site_override', '');
    $jobStatus = (string) get_option('phsbot_kb_job_status', 'idle');
    $jobStart  = (string) get_option('phsbot_kb_job_started', '');

    wp_localize_script('phsbot-kb-js', 'phsbotKBData', [
        'ajaxurl'        => admin_url('admin-ajax.php'),
        'nonce'          => wp_create_nonce('phsbot_kb_nonce'),
        'defaultPrompt'  => phsbot_kb_get_default_prompt(),
        'models'         => $models,
        'selectedModel'  => $sel,
        'version'        => $version,
        'updated'        => $updated,
        'detectedBase'   => $detBase,
        'activeBase'     => $actBase,
        'overrideOn'     => $ovOn,
        'overrideValue'  => $ovVal,
        'job'            => ['status'=>$jobStatus,'started'=>$jobStart],
        'i18n' => [
            'generating'   => 'Generación de documento en proceso…',
            'saving'       => 'Guardando cambios…',
            'done'         => 'Documento generado',
            'saved'        => 'Cambios guardados',
            'error'        => 'Error',
            'models_ok'    => 'Lista de modelos actualizada',
            'models_err'   => 'No se pudo actualizar la lista de modelos',
            'override_warn'=> 'Ojo: estás forzando el sitio a escanear. Detectado: %DETECTED%. Usarás: %OVERRIDE%. ¿Seguro?',
            'help_show'    => 'Mostrar ayuda',
            'help_hide'    => 'Ocultar ayuda',
        ],
    ]);
}

/* ============================================================================
   Pantalla Admin (UI)
   ============================================================================ */
function phsbot_kb_admin_page() {
    if ( ! current_user_can(PHSBOT_CAP_SETTINGS) ) wp_die('No tienes permisos suficientes.');

    $prompt         = get_option('phsbot_kb_prompt', '');
    $extra_prompt   = get_option('phsbot_kb_extra_prompt', '');
    $extra_domains  = get_option('phsbot_kb_extra_domains', '');
    $max_urls       = intval(get_option('phsbot_kb_max_urls', 80));
    $max_pages_main = intval(get_option('phsbot_kb_max_pages_main', 50));
    $max_posts_main = intval(get_option('phsbot_kb_max_posts_main', 20));
    $document       = get_option('phsbot_kb_document', '');
    $last_run       = get_option('phsbot_kb_last_run', '');
    $last_model     = get_option('phsbot_kb_last_model', '');
    $sel_model      = get_option('phsbot_kb_model', '');
    $version        = intval(get_option('phsbot_kb_version', 0));
    $last_updated   = get_option('phsbot_kb_last_updated', '');
    $ov_on          = (bool) get_option('phsbot_kb_site_override_on', false);
    $ov_val         = (string) get_option('phsbot_kb_site_override', '');

    // Guardado tradicional (opcional)
    if ( isset($_POST['phsbot_kb_save']) && check_admin_referer('phsbot_kb_save_nonce', 'phsbot_kb_save_nonce') ) {
        $prompt         = wp_kses_post(stripslashes($_POST['phsbot_kb_prompt'] ?? ''));
        $extra_prompt   = wp_kses_post(stripslashes($_POST['phsbot_kb_extra_prompt'] ?? ''));
        $extra_domains  = sanitize_textarea_field($_POST['phsbot_kb_extra_domains'] ?? '');
        $max_urls       = max(10, min(400, intval($_POST['phsbot_kb_max_urls'] ?? 80)));
        $max_pages_main = max(0, min(400, intval($_POST['phsbot_kb_max_pages_main'] ?? 50)));
        $max_posts_main = max(0, min(400, intval($_POST['phsbot_kb_max_posts_main'] ?? 20)));
        $sel_model      = sanitize_text_field($_POST['phsbot_kb_model'] ?? '');
        $ov_on          = !empty($_POST['phsbot_kb_site_override_on']);
        $ov_val         = sanitize_text_field($_POST['phsbot_kb_site_override'] ?? '');

        phsbot_kb_update_option_noautoload('phsbot_kb_prompt',            $prompt);
        phsbot_kb_update_option_noautoload('phsbot_kb_extra_prompt',      $extra_prompt);
        phsbot_kb_update_option_noautoload('phsbot_kb_extra_domains',     $extra_domains);
        phsbot_kb_update_option_noautoload('phsbot_kb_max_urls',          $max_urls);
        phsbot_kb_update_option_noautoload('phsbot_kb_max_pages_main',    $max_pages_main);
        phsbot_kb_update_option_noautoload('phsbot_kb_max_posts_main',    $max_posts_main);
        phsbot_kb_update_option_noautoload('phsbot_kb_model',             $sel_model);
        phsbot_kb_update_option_noautoload('phsbot_kb_site_override_on',  $ov_on ? 1 : 0);
        phsbot_kb_update_option_noautoload('phsbot_kb_site_override',     $ov_val);

        echo '<div class="updated"><p>Ajustes guardados.</p></div>';
    }

    $preview_html = phsbot_kb_preview_sanitize($document);
    $models = phsbot_kb_get_models(false);

    $det = phsbot_kb_detect_site_base();
    $act = phsbot_kb_get_active_site_base();
    $det_root = esc_html($det['scheme'].'://'.$det['host'].$det['path']);
    $act_root = esc_html($act['scheme'].'://'.$act['host'].$act['path']);
    ?>
    <div class="wrap phsbot-module-wrap">

        <!-- Header gris estilo GeoWriter -->
        <div class="phsbot-module-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h1 style="margin: 0;">Base de Conocimiento</h1>
            <div>
                <button type="button" class="button button-primary" id="phsbot-kb-save-config-global">Guardar configuración</button>
            </div>
        </div>

        <!-- Barra de progreso bajo el título (visible al generar) -->
        <div class="phsbot-kb-topbar" id="phsbot-kb-topbar" aria-hidden="true">
            <span id="phsbot-kb-topbar-text">Generación de documento en proceso…</span>
            <div class="phsbot-kb-progress mini">
                <div class="phsbot-kb-progress-bar" id="phsbot-kb-topbar-bar"></div>
            </div>
        </div>

        <!-- Barra de error visible -->
        <div class="phsbot-kb-errorbar" id="phsbot-kb-errorbar" style="display:none;"></div>

        <!-- Bloque de ayuda -->
        <div class="phsbot-kb-help" id="phsbot-kb-help">
            <div class="phsbot-kb-help-head">
                <strong>¿Cómo funciona?</strong>
                <button type="button" class="button-link phsbot-kb-help-toggle" id="phsbot-kb-help-toggle" aria-expanded="true">Ocultar ayuda</button>
            </div>
            <ul>
                <li>La IA lee el <strong>sitio principal detectado</strong> (o el override) y construye el <em>Documento de Conocimiento Maestro</em> en HTML.</li>
                <li>En <strong>Dominios adicionales</strong> añade hosts o URLs completas para complementar (sin enlazar fuera).</li>
                <li><strong>Generar</strong> guarda primero la configuración y después lanza la lectura; la barra superior indica progreso.</li>
                <li>El documento es <strong>editable</strong> (WYSIWYG). Usa <strong>Guardar documento</strong> para versionarlo.</li>
                <li>La pestaña <strong>Información</strong> muestra el modelo usado, árbol de secciones, todas las fuentes y <strong>el último error</strong> si lo hubo.</li>
            </ul>
        </div>

        <h2 class="nav-tab-wrapper phsbot-kb-tabs">
            <a href="#" class="nav-tab nav-tab-active" data-tab="main">Generación</a>
            <a href="#" class="nav-tab" data-tab="info">Información</a>
        </h2>

        <div id="phsbot-kb-tab-main" class="phsbot-kb-tab">

            <div class="phsbot-kb-infobar phsbot-kb-infobar-top">
                <div class="chip"><span class="label">Detectado</span><code><?php echo $det_root; ?></code></div>
                <div class="chip"><span class="label">Usando</span><code><?php echo $ov_on ? esc_html($ov_val) : $act_root; ?></code></div>
                <div class="chip"><span class="label">Actualizado</span><span id="phsbot-kb-updated"><?php echo esc_html($last_updated ?: '—'); ?></span></div>
                <?php if ($last_model): ?>
                <div class="chip"><span class="label">Último modelo</span><code><?php echo esc_html($last_model); ?></code></div>
                <?php endif; ?>
                <div class="chip" id="phsbot-kb-sources" style="display:none;"></div>
            </div>

            <div class="phsbot-kb-grid">
                <div class="phsbot-kb-col phsbot-kb-col-left">

                    <form method="post" class="phsbot-kb-form">
                        <?php wp_nonce_field('phsbot_kb_save_nonce', 'phsbot_kb_save_nonce'); ?>

                        <div class="phsbot-kb-section" data-acc="1">
                            <h2 class="title acc-head">0) Override de dominio/carpeta (opcional)</h2>
                            <div class="acc-body">
                                <p class="description">Al activarlo, <strong>se ignora</strong> el sitio detectado y se usa este valor (URL completa, host o solo path).</p>
                                <label><input type="checkbox" name="phsbot_kb_site_override_on" id="phsbot_kb_site_override_on" <?php checked($ov_on, true); ?>> Usar override</label>
                                <input type="text" class="regular-text" name="phsbot_kb_site_override" id="phsbot_kb_site_override" value="<?php echo esc_attr($ov_val); ?>" placeholder="https://dominio.com/subcarpeta/ · dominio.com/subcarpeta · /subcarpeta/">
                                <p class="mini-note"><strong>Detectado:</strong> <code><?php echo $det_root; ?></code> · <strong>Usando:</strong> <code id="phsbot-kb-using-inline"><?php echo $ov_on ? esc_html($ov_val) : $act_root; ?></code></p>
                            </div>
                        </div>

                        <div class="phsbot-kb-section" data-acc="1">
                            <h2 class="title acc-head">1) Modelo de ChatGPT para KB</h2>
                            <div class="acc-body">
                                <p class="description"><strong>ℹ️ El modelo es controlado automáticamente por la API</strong> (actualmente: gpt-4o)</p>
                                <p class="mini-note">El sistema selecciona el mejor modelo según disponibilidad y rendimiento.</p>
                            </div>
                        </div>

                        <div class="phsbot-kb-section" data-acc="1">
                            <h2 class="title acc-head">2) Prompt base</h2>
                            <div class="acc-body">
                                <p><button type="button" class="button" id="phsbot-kb-fill-default">Rellenar prompt por defecto</button></p>
                                <textarea name="phsbot_kb_prompt" id="phsbot_kb_prompt" class="large-text code" rows="10"><?php echo esc_textarea($prompt ?: phsbot_kb_get_default_prompt()); ?></textarea>
                            </div>
                        </div>

                        <div class="phsbot-kb-section" data-acc="1">
                            <h2 class="title acc-head">3) Prompt adicional (opcional)</h2>
                            <div class="acc-body">
                                <textarea name="phsbot_kb_extra_prompt" id="phsbot_kb_extra_prompt" class="large-text code" rows="5"><?php echo esc_textarea($extra_prompt); ?></textarea>
                            </div>
                        </div>

                        <div class="phsbot-kb-section" data-acc="1">
                            <h2 class="title acc-head">4) Dominios adicionales (opcional)</h2>
                            <div class="acc-body">
                                <p class="description">Uno por línea. Hosts (<code>ejemplo.com</code>) o URLs completas (<code>https://ejemplo.com/guia/</code>).</p>
                                <textarea name="phsbot_kb_extra_domains" id="phsbot_kb_extra_domains" class="large-text code" rows="4"><?php echo esc_textarea($extra_domains); ?></textarea>
                            </div>
                        </div>

                        <div class="phsbot-kb-section" data-acc="1">
                            <h2 class="title acc-head">5) Límite de URLs a leer</h2>
                            <div class="acc-body">
                                <p>
                                    <label>Total:&nbsp;<input type="number" min="10" max="400" name="phsbot_kb_max_urls" id="phsbot_kb_max_urls" value="<?php echo esc_attr($max_urls); ?>" /></label>
                                </p>
                                <p style="margin-top:6px;">
                                    <strong>Cupos por tipo (solo dominio principal):</strong><br>
                                    <label>Páginas:&nbsp;<input type="number" min="0" max="400" name="phsbot_kb_max_pages_main" id="phsbot_kb_max_pages_main" value="<?php echo esc_attr($max_pages_main); ?>" /></label>
                                    &nbsp;&nbsp;
                                    <label>Entradas (Blog):&nbsp;<input type="number" min="0" max="400" name="phsbot_kb_max_posts_main" id="phsbot_kb_max_posts_main" value="<?php echo esc_attr($max_posts_main); ?>" /></label>
                                </p>
                            </div>
                        </div>

                        <div class="phsbot-kb-section" data-acc="1">
                            <h2 class="title acc-head">6) Generación</h2>
                            <div class="acc-body">
                                <p>
                                    <button type="button" class="button button-primary" id="phsbot-kb-generate">Generar documento (OpenAI)</button>
                                    <span class="phsbot-kb-badge">v<span id="phsbot-kb-version-badge"><?php echo esc_html($version); ?></span></span>
                                    <span id="phsbot-kb-status" class="phsbot-kb-status"><?php echo $last_run ? 'Última generación: ' . esc_html($last_run) : ''; ?></span>
                                </p>
                                <textarea id="phsbot_kb_document" style="display:none;"><?php echo esc_textarea($document); ?></textarea>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="phsbot-kb-col phsbot-kb-col-right">
                    <div class="phsbot-kb-section phsbot-kb-docbox">
                        <div class="phsbot-kb-right-head">
                            <h2 class="title">Base de Conocimiento</h2>
                            <div class="phsbot-kb-right-actions">
                                <button type="button" class="button button-primary" id="phsbot-kb-save">Guardar documento</button>
                            </div>
                        </div>

                        <div id="phsbot-kb-editor-wrap" class="phsbot-kb-editor-wrap" style="display:block;">
                            <?php
                            $editor_args = [
                                'textarea_name' => 'phsbot_kb_editor',
                                'textarea_rows' => 26,
                                'editor_height' => 650,
                                'media_buttons' => false,
                                'tinymce'       => true,
                                'quicktags'     => true,
                            ];
                            wp_editor($document, 'phsbot_kb_editor', $editor_args);
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="phsbot-kb-tab-info" class="phsbot-kb-tab" style="display:none;">
            <div class="phsbot-kb-section">
                <h2 class="title">Resumen de ejecución</h2>
                <div id="phsbot-kb-debug-summary">Cargando…</div>
            </div>
            <div class="phsbot-kb-section">
                <h2 class="title">Último error</h2>
                <div id="phsbot-kb-last-error">Cargando…</div>
            </div>
            <div class="phsbot-kb-section">
                <h2 class="title">Árbol de secciones (detectado)</h2>
                <div id="phsbot-kb-tree">Cargando…</div>
            </div>
            <div class="phsbot-kb-section">
                <h2 class="title">Fuentes leídas</h2>
                <div id="phsbot-kb-sources-table">Cargando…</div>
            </div>
        </div>
    </div>
    <?php
}

/* Wrappers de compatibilidad (no tocar nombres) */
if (!function_exists('phsbot_render_kb_admin'))   { function phsbot_render_kb_admin()   { phsbot_kb_admin_page(); } }
if (!function_exists('phsbot_render_kb_minimal')) { function phsbot_render_kb_minimal() { phsbot_kb_admin_page(); } }
if (!function_exists('phsbot_render_kb_page'))    { function phsbot_render_kb_page()    { phsbot_kb_admin_page(); } }
if (!function_exists('phsbot_kb_admin'))          { function phsbot_kb_admin()          { phsbot_kb_admin_page(); } }
if (!function_exists('phsbot_kb_page'))           { function phsbot_kb_page()           { phsbot_kb_admin_page(); } }

/* ============================================================================
   AJAX (nombres intactos + endpoints nuevos de error)
   ============================================================================ */
add_action('wp_ajax_phsbot_kb_refresh_models', function(){
    if ( ! current_user_can(PHSBOT_CAP_SETTINGS) ) wp_send_json_error(['message' => 'Permisos insuficientes'], 403);
    check_ajax_referer('phsbot_kb_nonce', 'nonce');
    $list = phsbot_kb_get_models(true);
    if (empty($list)) wp_send_json_error(['message' => 'No hay modelos disponibles. Revisa tu API key.'], 500);

    // Normalizar: convertir arrays a strings
    $normalized = [];
    foreach ($list as $item) {
        if (is_array($item)) {
            $normalized[] = $item['id'] ?? '';
        } else {
            $normalized[] = (string)$item;
        }
    }
    $normalized = array_filter($normalized); // Eliminar vacíos

    wp_send_json_success(['models' => array_values(array_unique($normalized))]);
});

add_action('wp_ajax_phsbot_kb_default_prompt_live', function(){
    if ( ! current_user_can(PHSBOT_CAP_SETTINGS) ) wp_send_json_error(['message' => 'Permisos insuficientes'], 403);
    check_ajax_referer('phsbot_kb_nonce', 'nonce');

    $ov_on = !empty($_POST['ov_on']);
    $ov_val= isset($_POST['ov_val']) ? trim((string)$_POST['ov_val']) : '';

    $det = phsbot_kb_detect_site_base();
    if ($ov_on && $ov_val !== '') {
        $base = phsbot_kb_normalize_override_for_prompt($det, $ov_val);
    } else {
        $base = $det;
    }

    $root = rtrim($base['scheme'] . '://' . $base['host'] . $base['path'], '/');
    $prompt = phsbot_kb_get_default_prompt();
    $prompt = preg_replace('/"https?:\/\/[^"]+"/', '"'.$root.'"', $prompt, 1);

    wp_send_json_success(['prompt' => $prompt, 'root' => $root]);
});

add_action('wp_ajax_phsbot_kb_job_state', function(){
    if ( ! current_user_can(PHSBOT_CAP_SETTINGS) ) wp_send_json_error(['message' => 'Permisos insuficientes'], 403);
    $status  = (string) get_option('phsbot_kb_job_status', 'idle');
    $started = (string) get_option('phsbot_kb_job_started', '');
    $version = intval(get_option('phsbot_kb_version', 0));
    $updated = (string) get_option('phsbot_kb_last_updated', '');
    wp_send_json_success(compact('status','started','version','updated'));
});

add_action('wp_ajax_phsbot_kb_save_doc', function(){
    if ( ! current_user_can(PHSBOT_CAP_SETTINGS) ) wp_send_json_error(['message' => 'Permisos insuficientes'], 403);
    check_ajax_referer('phsbot_kb_nonce', 'nonce');

    $html = isset($_POST['html']) ? stripslashes((string)$_POST['html']) : '';
    $raw  = phsbot_kb_strip_fences($html);

    phsbot_kb_update_option_noautoload('phsbot_kb_document',   $raw);
    phsbot_kb_update_option_noautoload('phsbot_kb_last_updated', current_time('mysql'));
    $ver = intval(get_option('phsbot_kb_version', 0)) + 1;
    phsbot_kb_update_option_noautoload('phsbot_kb_version', $ver);

    wp_send_json_success([
        'document' => $raw,
        'preview'  => phsbot_kb_preview_sanitize($raw),
        'version'  => $ver,
        'updated'  => get_option('phsbot_kb_last_updated'),
        'message'  => 'Guardado',
    ]);
});

add_action('wp_ajax_phsbot_kb_save_settings', function(){
    if ( ! current_user_can(PHSBOT_CAP_SETTINGS) ) wp_send_json_error(['message' => 'Permisos insuficientes'], 403);
    check_ajax_referer('phsbot_kb_nonce', 'nonce');

    $prompt         = wp_kses_post(stripslashes($_POST['prompt'] ?? ''));
    $extra_prompt   = wp_kses_post(stripslashes($_POST['extra_prompt'] ?? ''));
    $extra_domains  = sanitize_textarea_field($_POST['extra_domains'] ?? '');
    $max_urls       = max(10, min(400, intval($_POST['max_urls'] ?? 80)));
    $max_pages_main = max(0, min(400, intval($_POST['max_pages_main'] ?? 50)));
    $max_posts_main = max(0, min(400, intval($_POST['max_posts_main'] ?? 20)));
    $sel_model      = sanitize_text_field($_POST['model'] ?? '');
    $ov_on          = !empty($_POST['ov_on']);
    $ov_val         = sanitize_text_field($_POST['ov_val'] ?? '');

    phsbot_kb_update_option_noautoload('phsbot_kb_prompt',            $prompt);
    phsbot_kb_update_option_noautoload('phsbot_kb_extra_prompt',      $extra_prompt);
    phsbot_kb_update_option_noautoload('phsbot_kb_extra_domains',     $extra_domains);
    phsbot_kb_update_option_noautoload('phsbot_kb_max_urls',          $max_urls);
    phsbot_kb_update_option_noautoload('phsbot_kb_max_pages_main',    $max_pages_main);
    phsbot_kb_update_option_noautoload('phsbot_kb_max_posts_main',    $max_posts_main);
    phsbot_kb_update_option_noautoload('phsbot_kb_model',             $sel_model);
    phsbot_kb_update_option_noautoload('phsbot_kb_site_override_on',  $ov_on ? 1 : 0);
    phsbot_kb_update_option_noautoload('phsbot_kb_site_override',     $ov_val);

    wp_send_json_success(['message' => 'Guardado']);
});

add_action('wp_ajax_phsbot_kb_debug_get', function(){
    if ( ! current_user_can(PHSBOT_CAP_SETTINGS) ) wp_send_json_error(['message' => 'Permisos insuficientes'], 403);
    $dbg = get_option('phsbot_kb_last_debug', []);
    if (!$dbg) $dbg = [];
    // Añadir último error al payload de debug
    $err = get_option('phsbot_kb_last_error', []);
    $dbg['error'] = $err;
    wp_send_json_success($dbg);
});

/* ==== NUEVO: endpoints para leer/limpiar el último error (para la barra visible) ==== */
add_action('wp_ajax_phsbot_kb_error_get', function(){
    if ( ! current_user_can(PHSBOT_CAP_SETTINGS) ) wp_send_json_error(['message' => 'Permisos insuficientes'], 403);
    $err = get_option('phsbot_kb_last_error', []);
    wp_send_json_success(['error' => $err]);
});
add_action('wp_ajax_phsbot_kb_error_clear', function(){
    if ( ! current_user_can(PHSBOT_CAP_SETTINGS) ) wp_send_json_error(['message' => 'Permisos insuficientes'], 403);
    delete_option('phsbot_kb_last_error');
    wp_send_json_success(['ok'=>true]);
});

add_action('wp_ajax_phsbot_kb_generate', 'phsbot_kb_ajax_generate');
function phsbot_kb_ajax_generate() {
    if ( ! current_user_can(PHSBOT_CAP_SETTINGS) ) wp_send_json_error(['message' => 'Permisos insuficientes'], 403);
    check_ajax_referer('phsbot_kb_nonce', 'nonce');

    phsbot_kb_job_set_running();

    $base          = phsbot_kb_get_active_site_base();
    $base_host     = $base['host'];

    $base_prompt   = get_option('phsbot_kb_prompt', phsbot_kb_get_default_prompt());
    $extra_prompt  = get_option('phsbot_kb_extra_prompt', '');
    $max_urls      = intval(get_option('phsbot_kb_max_urls', 80));
    $max_pages_main= intval(get_option('phsbot_kb_max_pages_main', 50));
    $max_posts_main= intval(get_option('phsbot_kb_max_posts_main', 20));
    $extra_domains = get_option('phsbot_kb_extra_domains', '');
    $selected_model= sanitize_text_field($_POST['model'] ?? get_option('phsbot_kb_model', ''));
    $available     = phsbot_kb_get_models(false);

    // Parse dominios adicionales
    $extra_specs = [];
    foreach (preg_split('~\r\n|\r|\n~', (string)$extra_domains) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $pu = wp_parse_url($line);
        if (!empty($pu['host'])) {
            $spec = [
                'scheme' => isset($pu['scheme']) ? $pu['scheme'] : $base['scheme'],
                'host'   => $pu['host'],
                'path'   => isset($pu['path']) ? (substr($pu['path'],-1) === '/' ? $pu['path'] : $pu['path'].'/') : '/',
            ];
            $extra_specs[] = $spec; continue;
        }
        $host = preg_replace('~^https?://~i', '', $line);
        $host = trim($host, '/');
        if ($host !== '') $extra_specs[] = ['scheme'=>$base['scheme'],'host'=>$host,'path'=>'/'];
    }

    // Construir corpus
    $corpus = phsbot_kb_build_corpus($base, $extra_specs, $max_urls, $max_pages_main, $max_posts_main);

    // Estadísticas y árbol
    $main_count = 0; $extra_count = 0; $extra_hosts_used = [];
    $main_pages = 0; $main_posts = 0;
    $sources_debug = [];
    $main_urls_for_tree = [];
    foreach ($corpus as $it) {
        if ($it['domain']==='main') {
            $main_count++;
            $main_urls_for_tree[] = $it['url'];
            if ($it['type']==='page') $main_pages++;
            elseif ($it['type']==='post') $main_posts++;
        } else {
            $extra_count++;
            if (!empty($it['src_host'])) $extra_hosts_used[$it['src_host']] = true;
        }
        $sources_debug[] = [
            'url'      => $it['url'],
            'domain'   => $it['domain'],
            'src_host' => $it['src_host'] ?? '',
            'type'     => $it['type'] ?? 'unknown',
            'chars'    => strlen($it['text']),
        ];
    }
    $site_tree = phsbot_kb_build_site_tree($main_urls_for_tree, $base);

    // Prompt consolidado
    $full_prompt = phsbot_kb_build_prompt($base_prompt, $extra_prompt, $corpus, $base_host, array_keys($extra_hosts_used) ?: array_map(function($s){return $s['host'];}, $extra_specs));

    // Selección de modelo (se envía a la API, no se usa localmente)
    if (!$selected_model) { list($selected_model, $_) = phsbot_kb_choose_model_with_fallback('gpt-4o-mini', $available); }
    list($model_to_use, $fallback_note) = phsbot_kb_choose_model_with_fallback($selected_model, $available);

    $used_model = $model_to_use;
    $error_note = null;
    $raw_html   = '';
    $ok         = false;
    $usage      = null;
    $http_code  = null;

    // Llamar a API (ya no usa api_key local, se maneja en la API)
    $response = phsbot_kb_openai_chat(null, $used_model, $full_prompt, 12000, 0.2);
    if (is_wp_error($response)) {
        $error_note = $response->get_error_message();
        $http_code  = null;
    } else {
        $http_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $txt  = $body['choices'][0]['message']['content'] ?? '';
        $usage= $body['usage'] ?? null;
        if ($http_code === 200 && $txt) {
            $raw_html = phsbot_kb_strip_fences($txt);
            $raw_html = phsbot_kb_remove_external_links($raw_html, $base_host);
            $ok = true;
        } else {
            $error_note = $body['error']['message'] ?? ('HTTP ' . $http_code);
            // Fallback en cascada (intentar modelos alternativos)
            $alts = ['gpt-4o','gpt-4o-mini','gpt-4-turbo','gpt-4','gpt-3.5-turbo'];
            foreach ($alts as $alt) {
                $match = null; foreach ($available as $av) { if (strcasecmp($av,$alt)===0 || stripos($av,$alt)!==false) { $match = $av; break; } }
                if (!$match) continue;
                $used_model = $match;
                $r2 = phsbot_kb_openai_chat(null, $used_model, $full_prompt, 12000, 0.2);
                if (!is_wp_error($r2) && wp_remote_retrieve_response_code($r2) === 200) {
                    $b2 = json_decode(wp_remote_retrieve_body($r2), true);
                    $t2 = $b2['choices'][0]['message']['content'] ?? '';
                    $usage= $b2['usage'] ?? $usage;
                    if ($t2) { $raw_html = phsbot_kb_strip_fences($t2); $raw_html = phsbot_kb_remove_external_links($raw_html, $base_host); $ok = true; $fallback_note = 'Se aplicó fallback automático.'; break; }
                } else {
                    // Mantener el último código de error para diagnóstico
                    $http_code = is_wp_error($r2) ? null : wp_remote_retrieve_response_code($r2);
                }
            }
        }
    }

    if (!$ok) {
        // Registrar error con detalles
        $err_kind = 'unknown';
        if (is_wp_error($response)) {
            $code = $response->get_error_code();
            if (stripos($code,'timedout')!==false || stripos($error_note,'timed out')!==false || stripos($error_note,'cURL error 28')!==false) $err_kind = 'timeout';
            else $err_kind = 'network';
        } else {
            if ($http_code === 401) $err_kind = 'auth';
            elseif ($http_code === 402) $err_kind = 'billing';
            elseif ($http_code === 429) $err_kind = 'rate_limit';
            elseif ($http_code >= 500) $err_kind = 'server';
            elseif ($http_code && $http_code !== 200) $err_kind = 'http_'.$http_code;
            elseif (empty($raw_html)) $err_kind = 'empty_response';
        }

        phsbot_kb_record_error('No se pudo generar el documento.', [
            'kind'           => $err_kind,
            'selected_model' => $selected_model,
            'tried_model'    => $model_to_use,
            'used_model'     => $used_model,
            'http_code'      => $http_code,
            'message'        => $error_note ?: 'Error desconocido',
        ]);

        phsbot_kb_job_set_idle();

        wp_send_json_error([
            'message'       => $error_note ?: 'No se pudo generar el documento.',
            'selected_model'=> $selected_model,
            'tried_model'   => $model_to_use,
            'used_model'    => $used_model,
            'http_code'     => $http_code,
        ], 500);
    }

    // ÉXITO → limpiar error previo
    delete_option('phsbot_kb_last_error');

    phsbot_kb_update_option_noautoload('phsbot_kb_document',   $raw_html);
    phsbot_kb_update_option_noautoload('phsbot_kb_last_run',   current_time('mysql'));
    phsbot_kb_update_option_noautoload('phsbot_kb_last_model', $used_model);
    phsbot_kb_update_option_noautoload('phsbot_kb_last_updated', current_time('mysql'));
    $ver = intval(get_option('phsbot_kb_version', 0)) + 1;
    phsbot_kb_update_option_noautoload('phsbot_kb_version', $ver);

    $debug = [
        'selected_model' => $selected_model,
        'used_model'     => $used_model,
        'fallback_note'  => $fallback_note,
        'started'        => get_option('phsbot_kb_job_started', ''),
        'finished'       => get_option('phsbot_kb_last_updated', current_time('mysql')),
        'version'        => $ver,
        'stats'          => [
            'main_urls'   => $main_count,
            'extra_urls'  => $extra_count,
            'main_pages'  => $main_pages,
            'main_posts'  => $main_posts,
            'extra_hosts' => array_keys($extra_hosts_used),
        ],
        'usage'          => $usage,
        'site_tree'      => $site_tree,
        'sources'        => $sources_debug,
        'error'          => [], // vacío en éxito
    ];
    phsbot_kb_update_option_noautoload('phsbot_kb_last_debug', $debug);

    phsbot_kb_job_set_idle();

    wp_send_json_success([
        'document'       => $raw_html,
        'preview'        => phsbot_kb_preview_sanitize($raw_html),
        'last_run'       => get_option('phsbot_kb_last_run'),
        'selected_model' => $selected_model,
        'used_model'     => $used_model,
        'note'           => $fallback_note,
        'version'        => $ver,
        'updated'        => get_option('phsbot_kb_last_updated'),
        'stats'          => $debug['stats'],
    ]);
}