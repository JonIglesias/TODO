<?php
// File: logs/logs.php — Panel de Logs PHSBOT (tabs + filtros + controles)
if (!defined('ABSPATH')) exit;

// Carga helpers/constantes comunes desde la raíz del plugin
$common = dirname(__DIR__) . '/common.php';
if (file_exists($common)) require_once $common;

/* ==================== UTILIDADES DE FICHEROS ==================== */
if (!function_exists('phsbot_tail_file')) {
    function phsbot_tail_file($filepath, $lines = 800) {
        $result = array('exists' => false, 'content' => array(), 'filesize' => 0);
        if (!file_exists($filepath)) return $result;
        $result['exists'] = true;
        $result['filesize'] = @filesize($filepath) ?: 0;

        $f = @fopen($filepath, "rb");
        if (!$f) return $result;

        $buffer = '';
        $chunkSize = 4096;
        $pos = -1;
        $lineCount = 0;

        fseek($f, 0, SEEK_END);
        $filesize = ftell($f);

        while ($lineCount < $lines && -$pos < $filesize) {
            $step = min($chunkSize, $filesize + $pos);
            $pos -= $step;
            fseek($f, $pos, SEEK_END);
            $chunk = fread($f, $step);
            $buffer = $chunk . $buffer;
            $lineCount = substr_count($buffer, "\n");
        }
        fclose($f);

        $arr = explode("\n", $buffer);
        $arr = array_slice($arr, -$lines);
        foreach ($arr as $i => $ln) $arr[$i] = rtrim($ln) . "\n";

        $result['content'] = $arr;
        return $result;
    }
}

if (!function_exists('phsbot_truncate_file_tail')) {
    function phsbot_truncate_file_tail($filepath, $keep_bytes = 1048576) {
        if (!file_exists($filepath) || !is_writable($filepath)) return false;
        $size = @filesize($filepath) ?: 0;
        if ($size <= $keep_bytes) return false;

        $f = @fopen($filepath, 'rb'); if (!$f) return false;
        $offset = max(0, $size - $keep_bytes);
        fseek($f, $offset, SEEK_SET);
        $tail = stream_get_contents($f);
        fclose($f);

        $w = @fopen($filepath, 'wb'); if (!$w) return false;
        fwrite($w, $tail);
        fclose($w);
        return true;
    }
}

/* ==================== EDICIÓN DE WP-CONFIG (OPCIONAL) ==================== */
if (!function_exists('phsbot_update_wp_config')) {
    /**
     * Inserta/actualiza defines y (opcional) ini_set() en wp-config.php de manera conservadora.
     * - Hace copia de seguridad wp-config.php.phsbot.Ymd-His.bak
     * - Inserta antes del marcador "That's all, stop editing".
     * - Reemplaza si ya existen líneas previas de esos defines.
     *
     * @param array $opts = [
     *   'WP_DEBUG'=> true|false|null,
     *   'WP_DEBUG_LOG'=> true|false|string|null, // true/false o ruta
     *   'WP_DEBUG_DISPLAY'=> true|false|null,
     *   'ini_log_errors'=> '1'|'0'|null,
     *   'ini_display_errors'=> '1'|'0'|null,
     *   'ini_error_reporting'=> int|null
     * ]
     * @return array ['ok'=>bool,'msg'=>string]
     */
    function phsbot_update_wp_config($opts) {
        $config = ABSPATH . 'wp-config.php';
        if (!file_exists($config) || !is_readable($config)) {
            return array('ok'=>false,'msg'=>'No se puede leer wp-config.php');
        }
        if (!is_writable($config)) {
            return array('ok'=>false,'msg'=>'wp-config.php no es escribible');
        }
        $src = file_get_contents($config);
        if ($src === false) {
            return array('ok'=>false,'msg'=>'No se pudo leer el contenido de wp-config.php');
        }

        $backup = ABSPATH . 'wp-config.php.phsbot.' . date('Ymd-His') . '.bak';
        @copy($config, $backup);

        // Función para set/reemplazar define()
        $set_define = function(&$code, $name, $value) {
            if ($value === null) return;
            $re = '/^\s*define\s*\(\s*[\'"]'.preg_quote($name,'/').'[\'"]\s*,\s*.+?\)\s*;\s*$/mi';
            $line = "define('{$name}', " . (is_bool($value) ? ($value?'true':'false') : (is_int($value)?$value:("'".addslashes($value)."'"))) . ");";
            if (preg_match($re, $code)) {
                $code = preg_replace($re, $line, $code);
            } else {
                $code .= "\n{$line}\n";
            }
        };

        // Insertaremos nuestro bloque justo antes del marcador
        $marker = "/* That's all, stop editing! Happy publishing. */";
        $head = $src; $tail = '';
        $pos = strpos($src, $marker);
        if ($pos !== false) {
            $head = substr($src, 0, $pos);
            $tail = substr($src, $pos);
        }

        // Limpiamos defines previos de nuestras claves para evitar duplicados
        $keys = array('WP_DEBUG','WP_DEBUG_LOG','WP_DEBUG_DISPLAY');
        foreach ($keys as $k) {
            $re = '/^\s*define\s*\(\s*[\'"]'.preg_quote($k,'/').'[\'"]\s*,\s*.+?\)\s*;\s*$/mi';
            $head = preg_replace($re, '', $head);
        }
        // Limpiamos posibles ini_set previos
        $iniKeys = array('log_errors','display_errors','error_reporting');
        foreach ($iniKeys as $ik) {
            $re = '/^\s*@?ini_set\s*\(\s*[\'"]'.preg_quote($ik,'/').'[\'"]\s*,\s*.+?\)\s*;\s*$/mi';
            $head = preg_replace($re, '', $head);
        }

        // Construimos bloque nuevo
        $block = "\n/** PHSBOT LOG CONTROLS (auto) */\n";
        if (array_key_exists('WP_DEBUG',$opts)) {
            $block .= "define('WP_DEBUG', ".( $opts['WP_DEBUG'] ? 'true':'false' ).");\n";
        }
        if (array_key_exists('WP_DEBUG_LOG',$opts)) {
            $v = $opts['WP_DEBUG_LOG'];
            if ($v === true || $v === false) {
                $block .= "define('WP_DEBUG_LOG', ".($v?'true':'false').");\n";
            } elseif (is_string($v) && $v !== '') {
                $block .= "define('WP_DEBUG_LOG', '".addslashes($v)."');\n";
            }
        }
        if (array_key_exists('WP_DEBUG_DISPLAY',$opts)) {
            $block .= "define('WP_DEBUG_DISPLAY', ".( $opts['WP_DEBUG_DISPLAY'] ? 'true':'false' ).");\n";
        }
        if (array_key_exists('ini_log_errors',$opts) && $opts['ini_log_errors'] !== null) {
            $block .= "@ini_set('log_errors', '".($opts['ini_log_errors']=='1'?'1':'0')."');\n";
        }
        if (array_key_exists('ini_display_errors',$opts) && $opts['ini_display_errors'] !== null) {
            $block .= "@ini_set('display_errors', '".($opts['ini_display_errors']=='1'?'1':'0')."');\n";
        }
        if (array_key_exists('ini_error_reporting',$opts) && $opts['ini_error_reporting'] !== null) {
            $block .= "@ini_set('error_reporting', ".intval($opts['ini_error_reporting']).");\n";
        }
        $block .= "/** END PHSBOT LOG CONTROLS */\n\n";

        $new = $head . $block . ($tail ?: '');
        $ok = file_put_contents($config, $new);
        if ($ok === false) {
            return array('ok'=>false,'msg'=>'No se pudo escribir en wp-config.php');
        }
        return array('ok'=>true,'msg'=>'wp-config.php actualizado (se creó copia de seguridad).');
    }
}

/* ==================== RENDER PRINCIPAL ==================== */
if (!function_exists('phsbot_render_logs_page')) {
    function phsbot_render_logs_page() {
        $cap = (is_multisite() && is_network_admin()) ? 'manage_network_options' : 'manage_options';
        if (!current_user_can($cap)) wp_die(__('No tienes permisos para acceder a esta página.', 'phsbot'), 403);

        // Rutas de logs
        $wp_log = '';
        if (defined('WP_DEBUG_LOG')) {
            if (WP_DEBUG_LOG === true)       $wp_log = WP_CONTENT_DIR . '/debug.log';
            elseif (is_string(WP_DEBUG_LOG)) $wp_log = WP_DEBUG_LOG;
        } else {
            $wp_log = WP_CONTENT_DIR . '/debug.log';
        }

        $php_log = (string) ini_get('error_log');
        if ($php_log === '' || stripos($php_log, 'syslog') !== false) $php_log = '';
        $same_file = ($php_log && $wp_log && realpath($php_log) === realpath($wp_log));

        // Query/estado UI
        $tab       = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'checks';
        $query     = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $lines     = isset($_GET['lines']) ? max(50, min(5000, intval($_GET['lines']))) : 800;
        $only_err  = !empty($_GET['only_err']) ? 1 : 0;
        $autoref   = !empty($_GET['autoref']) ? 1 : 0;
        $interval  = isset($_GET['autoref_ms']) ? max(2000, min(60000, intval($_GET['autoref_ms']))) : 5000;

        // Acciones
        $action  = isset($_REQUEST['phs_action']) ? sanitize_text_field($_REQUEST['phs_action']) : '';
        $nonce   = isset($_REQUEST['_wpnonce']) ? $_REQUEST['_wpnonce'] : '';
        $nonce_dl = wp_create_nonce('phsbot_logs_nonce');

        // Poda auto > 20MB
        $MAX_BYTES = 20 * 1024 * 1024;
        $auto_pruned_wp = false; $auto_pruned_php = false;

        if ($action === 'download' && wp_verify_nonce($nonce, 'phsbot_logs_nonce')) {
            $which = isset($_REQUEST['which']) ? sanitize_text_field($_REQUEST['which']) : 'wp';
            $file  = ($which==='php') ? $php_log : $wp_log;
            if ($file && file_exists($file)) {
                header('Content-Type: text/plain; charset=utf-8');
                header('Content-Disposition: attachment; filename="'.basename($file).'"');
                header('Content-Length: ' . filesize($file));
                readfile($file);
                exit;
            } else {
                wp_die('No existe el fichero solicitado.');
            }
        } elseif ($action === 'clear' && wp_verify_nonce($nonce, 'phsbot_logs_nonce')) {
            if ($wp_log && file_exists($wp_log) && is_writable($wp_log)) {
                $fp = @fopen($wp_log, 'w'); if ($fp) { fclose($fp); $cleared = true; }
            }
        } elseif ($action === 'clear_php' && wp_verify_nonce($nonce, 'phsbot_logs_nonce')) {
            if (!$same_file && $php_log && file_exists($php_log) && is_writable($php_log)) {
                $fp = @fopen($php_log, 'w'); if ($fp) { fclose($fp); $cleared_php = true; }
            }
        } elseif ($action === 'prune' && wp_verify_nonce($nonce, 'phsbot_logs_nonce')) {
            $which = isset($_REQUEST['which']) ? sanitize_text_field($_REQUEST['which']) : 'wp';
            $file  = ($which==='php') ? $php_log : $wp_log;
            if ($file && file_exists($file) && is_writable($file)) {
                if (phsbot_truncate_file_tail($file, 1048576)) {
                    if ($which==='php') $pruned_php = true; else $pruned_wp = true;
                }
            }
        } elseif ($action === 'apply_controls' && wp_verify_nonce($nonce, 'phsbot_logs_nonce')) {
            // Recogemos toggles
            $wp_debug         = !empty($_POST['wp_debug']) ? true : false;
            $wp_debug_log_on  = isset($_POST['wp_debug_log_mode']) && $_POST['wp_debug_log_mode']==='on';
            $wp_debug_log_off = isset($_POST['wp_debug_log_mode']) && $_POST['wp_debug_log_mode']==='off';
            $wp_debug_log_path= isset($_POST['wp_debug_log_path']) ? sanitize_text_field($_POST['wp_debug_log_path']) : '';
            $wp_debug_display = isset($_POST['wp_debug_display']) ? ( $_POST['wp_debug_display']==='1' ? true:false ) : false;

            $php_log_errors   = isset($_POST['php_log_errors']) ? ( $_POST['php_log_errors']==='1' ? '1':'0' ) : '1';
            $php_display_err  = isset($_POST['php_display_errors']) ? ( $_POST['php_display_errors']==='1' ? '1':'0' ) : '0';
            $php_level        = isset($_POST['php_error_level']) ? intval($_POST['php_error_level']) : E_ALL;

            // Aplicación inmediata (runtime)
            @ini_set('log_errors', $php_log_errors);
            @ini_set('display_errors', $php_display_err);
            @ini_set('error_reporting', $php_level);
            $runtime_applied = true;

            // Intento persistente en wp-config
            $toCfg = array(
                'WP_DEBUG'           => $wp_debug,
                'WP_DEBUG_DISPLAY'   => $wp_debug_display,
                'WP_DEBUG_LOG'       => null, // resolvemos abajo
                'ini_log_errors'     => $php_log_errors,
                'ini_display_errors' => $php_display_err,
                'ini_error_reporting'=> $php_level,
            );
            if ($wp_debug_log_on) {
                // On: true o ruta si se proporcionó
                $toCfg['WP_DEBUG_LOG'] = $wp_debug_log_path !== '' ? $wp_debug_log_path : true;
            } elseif ($wp_debug_log_off) {
                $toCfg['WP_DEBUG_LOG'] = false;
            } // else null (no tocar)

            $cfg_res = phsbot_update_wp_config($toCfg);
            if ($cfg_res['ok']) $controls_saved = true; else $controls_error = $cfg_res['msg'];
        }

        // Poda automática
        if ($wp_log && file_exists($wp_log) && is_writable($wp_log) && (@filesize($wp_log) > $MAX_BYTES)) {
            if (phsbot_truncate_file_tail($wp_log, 1048576)) $auto_pruned_wp = true;
        }
        if (!$same_file && $php_log && file_exists($php_log) && is_writable($php_log) && (@filesize($php_log) > $MAX_BYTES)) {
            if (phsbot_truncate_file_tail($php_log, 1048576)) $auto_pruned_php = true;
        }

        // Lecturas
        $error_regex = '/(PHP\s+(Fatal|Parse|Warning|Notice|Deprecated|Recoverable)|Fatal\s+error|Uncaught\s+Exception|Stack trace|Throwable|Warning\:|Notice\:|Deprecated\:)/i';
        $wp_data  = $wp_log ? phsbot_tail_file($wp_log, $lines) : array('exists'=>false,'content'=>array(),'filesize'=>0);
        $php_data = ($php_log && !$same_file) ? phsbot_tail_file($php_log, $lines) : array('exists'=>false,'content'=>array(),'filesize'=>0);
        $apply_filter = function($arr) use ($query, $only_err, $error_regex) {
            if (empty($arr)) return $arr;
            $out = array();
            foreach ($arr as $ln) {
                if ($only_err && !preg_match($error_regex, $ln)) continue;
                if ($query !== '' && stripos($ln, $query) === false) continue;
                $out[] = $ln;
            }
            return $out;
        };
        if ($query !== '' || $only_err) {
            $wp_data['content']  = $apply_filter($wp_data['content']);
            $php_data['content'] = $apply_filter($php_data['content']);
        }

        // Chequeos rápidos
        $checks = array(
            'Plugin cargado'               => class_exists('PHSBOT_Plugin') ? 'sí' : 'no',
            'Archivo common.php'           => function_exists('phsbot_settings') ? 'sí' : 'no',
            'Menú PhsBot (slug)'           => defined('PHSBOT_MENU_SLUG') ? PHSBOT_MENU_SLUG : '(no definido)',
            'Página ajustes (slug)'        => defined('PHSBOT_PAGE_SLUG') ? PHSBOT_PAGE_SLUG : '(no definido)',
            'Hook screen actual'           => function_exists('get_current_screen') && get_current_screen() ? get_current_screen()->id : '(desconocido)',
            'Multisite'                    => is_multisite() ? 'sí' : 'no',
            'Network admin'                => (is_multisite() && is_network_admin()) ? 'sí' : 'no',
            'Usuario puede manage_options' => current_user_can('manage_options') ? 'sí' : 'no',
            'WP_DEBUG'                     => (defined('WP_DEBUG') ? (WP_DEBUG?'true':'false') : '(no definido)'),
            'WP_DEBUG_LOG'                 => (defined('WP_DEBUG_LOG') ? (is_string(WP_DEBUG_LOG)?WP_DEBUG_LOG:(WP_DEBUG_LOG?'true':'false')) : '(no definido)'),
            'WP_DEBUG_DISPLAY'             => (defined('WP_DEBUG_DISPLAY') ? (WP_DEBUG_DISPLAY?'true':'false') : '(no definido)'),
            'ini log_errors'               => @ini_get('log_errors'),
            'ini display_errors'           => @ini_get('display_errors'),
            'ini error_reporting'          => @ini_get('error_reporting'),
            'Ruta WP debug.log'            => $wp_log ?: '(no configurado)',
            'Existe WP debug.log'          => ($wp_log && file_exists($wp_log)) ? 'sí' : 'no',
            'Escribible WP debug.log'      => ($wp_log && file_exists($wp_log)) ? (is_writable($wp_log) ? 'sí' : 'no') : '(n/a)',
            'Ruta PHP error_log'           => $php_log ?: '(no es fichero / syslog)',
            'Existe PHP error_log'         => ($php_log && file_exists($php_log)) ? 'sí' : 'no',
            'Escribible PHP error_log'     => ($php_log && file_exists($php_log)) ? (is_writable($php_log) ? 'sí' : 'no') : '(n/a)',
            'WP y PHP usan mismo log'      => $same_file ? 'sí' : 'no',
        );

        // Estado actual para la pestaña Controles
        $cur_wp_debug         = defined('WP_DEBUG') ? (bool)WP_DEBUG : false;
        $cur_wp_debug_log     = defined('WP_DEBUG_LOG') ? WP_DEBUG_LOG : false;
        $cur_wp_debug_display = defined('WP_DEBUG_DISPLAY') ? (bool)WP_DEBUG_DISPLAY : false;
        $cur_php_log_errors   = @ini_get('log_errors') ? (string)@ini_get('log_errors') : '0';
        $cur_php_display_err  = @ini_get('display_errors') ? (string)@ini_get('display_errors') : '0';
        $cur_php_level        = (int) @ini_get('error_reporting');

        // UI
        ?>
        <div class="wrap">
            <h1>PhsBot — Logs</h1>

            <?php if (!empty($auto_pruned_wp)): ?><div class="notice notice-warning"><p>WP <code>debug.log</code> superaba 20&nbsp;MB y se ha podado (~1&nbsp;MB final).</p></div><?php endif; ?>
            <?php if (!empty($auto_pruned_php)): ?><div class="notice notice-warning"><p>PHP <code>error_log</code> superaba 20&nbsp;MB y se ha podado (~1&nbsp;MB final).</p></div><?php endif; ?>
            <?php if (!empty($cleared)): ?><div class="notice notice-success"><p>WP <code>debug.log</code> vaciado.</p></div><?php endif; ?>
            <?php if (!empty($cleared_php)): ?><div class="notice notice-success"><p>PHP <code>error_log</code> vaciado.</p></div><?php endif; ?>
            <?php if (!empty($pruned_wp)): ?><div class="notice notice-success"><p>WP <code>debug.log</code> podado.</p></div><?php endif; ?>
            <?php if (!empty($pruned_php)): ?><div class="notice notice-success"><p>PHP <code>error_log</code> podado.</p></div><?php endif; ?>
            <?php if (!empty($controls_saved)): ?><div class="notice notice-success"><p>Controles aplicados y <code>wp-config.php</code> actualizado.</p></div><?php endif; ?>
            <?php if (!empty($runtime_applied) && empty($controls_saved)): ?><div class="notice notice-info"><p>Controles aplicados para esta sesión (runtime). Para persistir, habilita escritura en <code>wp-config.php</code>.</p></div><?php endif; ?>
            <?php if (!empty($controls_error)): ?><div class="notice notice-error"><p><?php echo esc_html($controls_error); ?></p></div><?php endif; ?>

            <style>
                .phs-tabs{display:flex; gap:6px; margin:14px 0;}
                .phs-tabs a{
                    padding:8px 12px; border:1px solid #cbd5e1; border-bottom:none; background:#f8fafc;
                    text-decoration:none; color:#0f172a; border-radius:6px 6px 0 0;
                }
                .phs-tabs a.active{ background:#fff; font-weight:600; }
                .phs-panel{border:1px solid #cbd5e1; background:#fff; padding:12px; border-radius:0 6px 6px 6px;}
                textarea.phs-log{width:100%; max-width:1200px; height:520px; font-family:Menlo,Consolas,monospace;}
                .phs-flex{display:flex; gap:10px; align-items:center; flex-wrap:wrap;}
                .phs-muted{color:#64748b;}
                .phs-grid{display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:12px;}
                .phs-card{border:1px solid #e5e7eb; border-radius:8px; padding:12px; background:#fafafa;}
                .phs-card h3{margin:0 0 8px 0;}
                .phs-small{font-size:12px; color:#6b7280;}
            </style>

            <div class="phs-tabs">
                <a class="<?php echo $tab==='checks'?'active':''; ?>" href="<?php echo esc_url(add_query_arg(array('tab'=>'checks'))); ?>">Chequeos</a>
                <a class="<?php echo $tab==='wp'?'active':''; ?>" href="<?php echo esc_url(add_query_arg(array('tab'=>'wp'))); ?>">WP debug.log</a>
                <a class="<?php echo $tab==='php'?'active':''; ?>" href="<?php echo esc_url(add_query_arg(array('tab'=>'php'))); ?>">PHP error_log</a>
                <a class="<?php echo $tab==='controls'?'active':''; ?>" href="<?php echo esc_url(add_query_arg(array('tab'=>'controls'))); ?>">Controles</a>
            </div>

            <div class="phs-panel">
                <?php if ($tab==='checks'): ?>

                    <h2 class="title">Chequeos rápidos</h2>
                    <table class="widefat striped" style="max-width:900px">
                        <tbody>
                        <?php foreach ($checks as $k => $v): ?>
                            <tr><td style="width:320px"><strong><?php echo esc_html($k); ?></strong></td><td><?php echo esc_html($v); ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                <?php elseif ($tab==='wp'): ?>

                    <h2 class="title">WP <code>debug.log</code></h2>
                    <form method="get" class="phs-flex" style="margin:10px 0;">
                        <input type="hidden" name="page" value="phsbot-logs">
                        <input type="hidden" name="tab" value="wp">
                        <label>Buscar:
                            <input type="text" name="s" value="<?php echo esc_attr($query); ?>" placeholder="Filtrar por texto..." style="width:260px">
                        </label>
                        <label>Solo errores
                            <input type="checkbox" name="only_err" value="1" <?php checked($only_err,1); ?>>
                        </label>
                        <label>Líneas:
                            <input type="number" name="lines" value="<?php echo esc_attr($lines); ?>" min="50" max="5000" step="50" style="width:90px">
                        </label>
                        <label>Auto-refresh
                            <input type="checkbox" name="autoref" value="1" <?php checked($autoref,1); ?>>
                        </label>
                        <label class="phs-muted">ms:
                            <input type="number" name="autoref_ms" value="<?php echo esc_attr($interval); ?>" min="2000" max="60000" step="500" style="width:90px">
                        </label>
                        <button class="button">Aplicar</button>
                        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=phsbot-logs&tab=wp')); ?>">Reset</a>
                        <?php if ($wp_log && file_exists($wp_log)): ?>
                            <a class="button button-primary" href="<?php echo esc_url(add_query_arg(array('phs_action'=>'download','which'=>'wp','_wpnonce'=>$nonce_dl), admin_url('admin.php?page=phsbot-logs&tab=wp'))); ?>">Descargar</a>
                            <?php if (is_writable($wp_log)): ?>
                                <a class="button button-link-delete" href="<?php echo esc_url(add_query_arg(array('phs_action'=>'clear','_wpnonce'=>$nonce_dl), admin_url('admin.php?page=phsbot-logs&tab=wp'))); ?>" onclick="return confirm('¿Vaciar WP debug.log?');">Vaciar WP log</a>
                                <a class="button" href="<?php echo esc_url(add_query_arg(array('phs_action'=>'prune','which'=>'wp','_wpnonce'=>$nonce_dl), admin_url('admin.php?page=phsbot-logs&tab=wp'))); ?>" onclick="return confirm('¿Podar WP debug.log a ~1 MB?');">Podar tamaño</a>
                            <?php endif; ?>
                            <span class="phs-muted">Tamaño actual: <?php echo size_format(@filesize($wp_log)); ?></span>
                        <?php endif; ?>
                    </form>

                    <?php if (!$wp_log || !$wp_data['exists']): ?>
                        <div class="notice notice-warning"><p>No existe <code>debug.log</code>. Revisa <code>WP_DEBUG</code> / <code>WP_DEBUG_LOG</code> en <code>wp-config.php</code>.</p></div>
                    <?php else: ?>
                        <p><em>Mostrando las últimas <?php echo intval($lines); ?> líneas<?php
                            echo $query ? ' filtradas por “'.esc_html($query).'”' : '';
                            echo $only_err ? ' (solo errores)' : '';
                        ?>.</em></p>
                        <textarea readonly class="phs-log" id="phs-log-wp"><?php echo esc_textarea(implode("", $wp_data['content'])); ?></textarea>
                    <?php endif; ?>

                <?php elseif ($tab==='php'): ?>

                    <h2 class="title">PHP <code>error_log</code></h2>
                    <?php if ($same_file): ?>
                        <p><em>El PHP <code>error_log</code> es el mismo que WP <code>debug.log</code>. Usa la pestaña anterior.</em></p>
                    <?php endif; ?>

                    <form method="get" class="phs-flex" style="margin:10px 0;">
                        <input type="hidden" name="page" value="phsbot-logs">
                        <input type="hidden" name="tab" value="php">
                        <label>Buscar:
                            <input type="text" name="s" value="<?php echo esc_attr($query); ?>" placeholder="Filtrar por texto..." style="width:260px">
                        </label>
                        <label>Solo errores
                            <input type="checkbox" name="only_err" value="1" <?php checked($only_err,1); ?>>
                        </label>
                        <label>Líneas:
                            <input type="number" name="lines" value="<?php echo esc_attr($lines); ?>" min="50" max="5000" step="50" style="width:90px">
                        </label>
                        <label>Auto-refresh
                            <input type="checkbox" name="autoref" value="1" <?php checked($autoref,1); ?>>
                        </label>
                        <label class="phs-muted">ms:
                            <input type="number" name="autoref_ms" value="<?php echo esc_attr($interval); ?>" min="2000" max="60000" step="500" style="width:90px">
                        </label>
                        <button class="button">Aplicar</button>
                        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=phsbot-logs&tab=php')); ?>">Reset</a>

                        <?php if ($php_log && file_exists($php_log)): ?>
                            <a class="button button-primary" href="<?php echo esc_url(add_query_arg(array('phs_action'=>'download','which'=>'php','_wpnonce'=>$nonce_dl), admin_url('admin.php?page=phsbot-logs&tab=php'))); ?>">Descargar</a>
                            <?php if (!$same_file && is_writable($php_log)): ?>
                                <a class="button button-link-delete"
                                   href="<?php echo esc_url(add_query_arg(array('phs_action'=>'clear_php','_wpnonce'=>$nonce_dl), admin_url('admin.php?page=phsbot-logs&tab=php'))); ?>"
                                   onclick="return confirm('¿Vaciar PHP error_log?');">Vaciar PHP log</a>
                                <a class="button"
                                   href="<?php echo esc_url(add_query_arg(array('phs_action'=>'prune','which'=>'php','_wpnonce'=>$nonce_dl), admin_url('admin.php?page=phsbot-logs&tab=php'))); ?>"
                                   onclick="return confirm('¿Podar PHP error_log a ~1 MB?');">Podar tamaño</a>
                            <?php endif; ?>
                            <span class="phs-muted">Tamaño actual: <?php echo size_format(@filesize($php_log)); ?></span>
                        <?php else: ?>
                            <span class="phs-muted">No hay archivo de PHP <code>error_log</code> (puede redirigirse a syslog/servidor).</span>
                        <?php endif; ?>
                    </form>

                    <?php if (!$php_log || !$php_data['exists']): ?>
                        <div class="notice notice-warning"><p>No se encontró un <code>error_log</code> de PHP como archivo local.</p></div>
                    <?php else: ?>
                        <p><em>Mostrando las últimas <?php echo intval($lines); ?> líneas<?php
                            echo $query ? ' filtradas por “'.esc_html($query).'”' : '';
                            echo $only_err ? ' (solo errores)' : '';
                        ?>.</em></p>
                        <textarea readonly class="phs-log" id="phs-log-php"><?php echo esc_textarea(implode("", $php_data['content'])); ?></textarea>
                    <?php endif; ?>

                <?php elseif ($tab==='controls'): ?>

                    <h2 class="title">Controles de logging</h2>
                    <p class="phs-muted">Puedes activar/desactivar logging y mostrar/ocultar errores. Al guardar:
                        <strong>1)</strong> se aplica inmediato para la sesión actual (runtime) y
                        <strong>2)</strong> se intenta escribir en <code>wp-config.php</code> (con copia de seguridad). Si no hay permisos, te avisaremos.</p>

                    <form method="post" class="phs-grid" action="<?php echo esc_url(admin_url('admin.php?page=phsbot-logs&tab=controls')); ?>">
                        <?php wp_nonce_field('phsbot_logs_nonce'); ?>
                        <input type="hidden" name="phs_action" value="apply_controls">

                        <div class="phs-card">
                            <h3>WordPress</h3>
                            <label><input type="checkbox" name="wp_debug" value="1" <?php checked($cur_wp_debug, true); ?>> WP_DEBUG</label><br>
                            <label><input type="checkbox" name="wp_debug_display" value="1" <?php checked($cur_wp_debug_display, true); ?>> WP_DEBUG_DISPLAY (mostrar errores en pantalla)</label>
                            <div style="margin-top:8px;">
                                <strong>WP_DEBUG_LOG</strong><br>
                                <label><input type="radio" name="wp_debug_log_mode" value="noop" checked> No cambiar</label><br>
                                <label><input type="radio" name="wp_debug_log_mode" value="on"> Activar (true)</label><br>
                                <label><input type="radio" name="wp_debug_log_mode" value="off"> Desactivar (false)</label><br>
                                <label><input type="radio" name="wp_debug_log_mode" value="path"> Ruta personalizada:</label>
                                <input type="text" name="wp_debug_log_path" class="regular-text" placeholder="<?php echo esc_attr(WP_CONTENT_DIR.'/debug.log'); ?>">
                                <div class="phs-small">Actual: <?php echo is_string($cur_wp_debug_log)? esc_html($cur_wp_debug_log) : ($cur_wp_debug_log?'true':'false'); ?></div>
                            </div>
                        </div>

                        <div class="phs-card">
                            <h3>PHP (ini)</h3>
                            <label>log_errors:
                                <select name="php_log_errors">
                                    <option value="1" <?php selected($cur_php_log_errors=='1'); ?>>1 (activar)</option>
                                    <option value="0" <?php selected($cur_php_log_errors=='0'); ?>>0 (desactivar)</option>
                                </select>
                            </label><br>
                            <label>display_errors:
                                <select name="php_display_errors">
                                    <option value="0" <?php selected($cur_php_display_err=='0'); ?>>0 (ocultar)</option>
                                    <option value="1" <?php selected($cur_php_display_err=='1'); ?>>1 (mostrar)</option>
                                </select>
                            </label><br>
                            <label>error_reporting:
                                <select name="php_error_level">
                                    <option value="<?php echo E_ALL; ?>" <?php selected($cur_php_level==E_ALL); ?>>E_ALL</option>
                                    <option value="<?php echo E_ERROR | E_WARNING | E_PARSE; ?>" <?php selected($cur_php_level==(E_ERROR|E_WARNING|E_PARSE)); ?>>E_ERROR|E_WARNING|E_PARSE</option>
                                    <option value="<?php echo E_ERROR; ?>" <?php selected($cur_php_level==E_ERROR); ?>>E_ERROR</option>
                                    <option value="0" <?php selected($cur_php_level==0); ?>>0 (ninguno)</option>
                                </select>
                            </label>
                            <p class="phs-small">Valores actuales: log_errors=<?php echo esc_html($cur_php_log_errors); ?>,
                                display_errors=<?php echo esc_html($cur_php_display_err); ?>,
                                error_reporting=<?php echo esc_html($cur_php_level); ?></p>
                        </div>

                        <div style="grid-column:1 / -1;">
                            <button class="button button-primary">Guardar controles</button>
                            <span class="phs-small">Se intentará escribir en <code>wp-config.php</code>. Si no es escribible, solo se aplicará en esta sesión.</span>
                        </div>
                    </form>

                <?php endif; ?>
            </div>

            <script>
            (function(){
                var autoref = <?php echo json_encode((bool)$autoref); ?>;
                var interval = <?php echo json_encode((int)$interval); ?>;
                if (autoref) setInterval(function(){ window.location.reload(); }, interval);
            })();
            </script>
        </div>
        <?php
    }
}