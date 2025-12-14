<?php
/**
 * PHSBOT – Leads (bootstrap + UI con pestañas, detalle en persiana)
 * Carga automática de /parts/*.php (ordenada + resto)
 */
if (!defined('ABSPATH')) exit;

/* ===========================
 * Constantes comunes
 * =========================== */
if (!defined('PHSBOT_LEADS_STORE_OPT'))    define('PHSBOT_LEADS_STORE_OPT',    'phsbot_leads_store');
if (!defined('PHSBOT_LEADS_SETTINGS_OPT')) define('PHSBOT_LEADS_SETTINGS_OPT', 'phsbot_leads_settings');
if (!defined('PHSBOT_CLIENT_RESET_OPT'))   define('PHSBOT_CLIENT_RESET_OPT',   'phsbot_client_reset_version');
if (!defined('PHSBOT_MAIN_SETTINGS_OPT'))  define('PHSBOT_MAIN_SETTINGS_OPT',  'phsbot_settings');
if (!defined('PHSBOT_SUMMARY_TTL'))        define('PHSBOT_SUMMARY_TTL',        120);
if (!defined('PHSBOT_INACTIVITY_SECS'))    define('PHSBOT_INACTIVITY_SECS',    10 * MINUTE_IN_SECONDS);

/* ===========================
 * Autocarga de PARTS
 * - Carga prioritaria para evitar dependencias
 * - Luego carga el resto de archivos existentes en /parts
 * =========================== */
(function(){
    $dir = __DIR__ . '/parts';
    if (!is_dir($dir)) return;

    // 1) Prioridad (si existen, se cargan en este orden)
    $priority = array(
        'store.php',
        'extractors.php',
        'settings.php',
        'scoring.php',
        'notify.php',
        'hooks.php',
        'cron.php',
        'admin_ajax.php',
        'reset.php',
        'public.php',
        'settings_ui.php',
        'adapter_chat_capture.php',
    );
    $loaded = array();

    foreach ($priority as $file) {
        $path = $dir . '/' . $file;
        if (file_exists($path)) {
            require_once $path;
            $loaded[$path] = true;
        }
    }

    // 2) Resto de archivos .php que haya en /parts (por si añades nuevos)
    foreach (glob($dir . '/*.php') as $php) {
        if (!isset($loaded[$php])) {
            require_once $php;
            $loaded[$php] = true;
        }
    }
})();

/* ===========================
 * Assets admin SOLO en la pantalla de Leads
 * =========================== */
add_action('admin_enqueue_scripts', function(){
    if (!isset($_GET['page']) || $_GET['page'] !== 'phsbot-leads') return;
    if (!current_user_can('manage_options')) return;

    $base = trailingslashit(plugin_dir_path(__FILE__));
    $url  = trailingslashit(plugins_url('', __FILE__));
    $vcss = @filemtime($base.'leads.css') ?: time();
    $vjs  = @filemtime($base.'leads.js')  ?: time();

    // CSS unificado (cargar primero)
    wp_enqueue_style(
        'phsbot-modules-unified',
        plugin_dir_url(dirname(__FILE__)) . 'core/assets/modules-unified.css',
        array(),
        '1.4'
    );

    // Shepherd.js para tours interactivos
    $main_file = dirname(__FILE__) . '/../phsbot.php';
    wp_enqueue_style(
        'shepherd-js',
        'https://cdn.jsdelivr.net/npm/shepherd.js@11.2.0/dist/css/shepherd.css',
        array(),
        '11.2.0'
    );

    wp_enqueue_style(
        'phsbot-shepherd-custom',
        plugin_dir_url(dirname(__FILE__)) . 'core/assets/phsbot-shepherd-custom.css',
        array('shepherd-js'),
        '1.0.0'
    );

    wp_enqueue_script(
        'shepherd-js',
        'https://cdn.jsdelivr.net/npm/shepherd.js@11.2.0/dist/js/shepherd.min.js',
        array(),
        '11.2.0',
        true
    );

    wp_enqueue_script(
        'phsbot-shepherd-tours',
        plugin_dir_url(dirname(__FILE__)) . 'core/assets/phsbot-shepherd-tours.js',
        array('jquery', 'shepherd-js'),
        '1.0.0',
        true
    );

    wp_enqueue_style('phsbot-leads-css', $url.'leads.css', array('phsbot-modules-unified'), $vcss);
    wp_enqueue_script('phsbot-leads-js', $url.'leads.js', array('jquery'), $vjs, true);

    // Puede que settings.php aún no exista; protegemos la llamada
    $set = function_exists('phsbot_leads_settings') ? phsbot_leads_settings() : array('telegram_threshold' => 8.0);

    wp_localize_script('phsbot-leads-js', 'PHSBOT_LEADS', array(
        'ajax'   => admin_url('admin-ajax.php'),
        'nonce'  => wp_create_nonce('phsbot_leads'),
        'i18n'   => array(
            'view'            => __('Ver', 'phsbot'),
            'close'           => __('Cerrar', 'phsbot'),
            'delete'          => __('Borrar', 'phsbot'),
            'confirm_close'   => __('¿Marcar este lead como cerrado?', 'phsbot'),
            'confirm_delete'  => __('¿Seguro que quieres borrar el lead seleccionado?', 'phsbot'),
            'no_rows'         => __('No hay leads para mostrar.', 'phsbot'),
            'reset_done'      => __('Memoria del navegador reseteada.', 'phsbot'),
        ),
        'settings' => array(
            'telegram_threshold' => (float) (isset($set['telegram_threshold']) ? $set['telegram_threshold'] : 8.0),
        ),
    ));
});

/* ===========================
 * Página admin con pestañas y detalle en persiana
 * =========================== */
if (!function_exists('phsbot_arr_get')) {
    function phsbot_arr_get($arr, $key, $default=null){
        return (is_array($arr) && array_key_exists($key, $arr)) ? $arr[$key] : $default;
    }
}

if (!function_exists('phsbot_leads_admin_page')) {
    function phsbot_leads_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos suficientes.', 'phsbot'));
        }

        $active_tab = (isset($_GET['tab']) && $_GET['tab'] === 'settings') ? 'settings' : 'leads';

        echo '<div class="wrap phsbot-module-wrap">';

        // Header gris estilo GeoWriter
        echo '  <div class="phsbot-module-header" style="display: flex; justify-content: space-between; align-items: center;">';
        echo '    <h1 style="margin: 0; color: rgba(0, 0, 0, 0.8);">'.esc_html__('Leads & Scoring','phsbot').'</h1>';
        echo '  </div>';

        if (!empty($_GET['phsbot_saved'])) {
            echo '<div class="phsbot-alert phsbot-alert-success">'.esc_html__('Ajustes guardados.', 'phsbot').'</div>';
        }

        $leads_url    = add_query_arg(array('page'=>'phsbot-leads','tab'=>'leads'), admin_url('admin.php'));
        $settings_url = add_query_arg(array('page'=>'phsbot-leads','tab'=>'settings'), admin_url('admin.php'));
        echo '  <h2 class="nav-tab-wrapper" style="margin-bottom:24px">';
        echo '    <a href="'.esc_url($leads_url).'" class="nav-tab '.($active_tab==='leads'?'nav-tab-active':'').'">'.esc_html__('Leads','phsbot').'</a>';
        echo '    <a href="'.esc_url($settings_url).'" class="nav-tab '.($active_tab==='settings'?'nav-tab-active':'').'">'.esc_html__('Configuración','phsbot').'</a>';
        echo '  </h2>';

        if ($active_tab === 'settings') {
            echo '  <div class="phsbot-module-container">';
            echo '    <div class="phsbot-module-content">';
            if (function_exists('phsbot_leads_render_settings_panel')) {
                phsbot_leads_render_settings_panel();
            } else {
                echo '<p>'.esc_html__('No hay panel de ajustes disponible.', 'phsbot').'</p>';
            }
            echo '    </div>';
            echo '  </div>';
            echo '</div>';
            return;
        }

        echo '  <div class="phsbot-module-container">';
        echo '    <div class="phsbot-module-content">';

        // Datos
        $map  = function_exists('phsbot_leads_all') ? phsbot_leads_all() : array();
        $list = array_values($map);
        usort($list, function($a,$b){
            return intval(phsbot_arr_get($b,'last_seen',0)) <=> intval(phsbot_arr_get($a,'last_seen',0));
        });

        // Filas
        $rows = '';
        if (empty($list)) {
            $rows .= '<tr class="no-items"><td colspan="9">' . esc_html__('No hay leads para mostrar.', 'phsbot') . '</td></tr>';
        } else {
            foreach ($list as $lead) {
                $cid    = esc_attr(phsbot_arr_get($lead,'cid',''));
                $name   = esc_html(phsbot_arr_get($lead,'name',''));
                $email  = esc_html(phsbot_arr_get($lead,'email',''));
                $phone  = esc_html(phsbot_arr_get($lead,'phone',''));
                $scoreV = isset($lead['score']) ? number_format_i18n((float)$lead['score'], 1) : '–';
                $scoreC = (isset($lead['score']) && (float)$lead['score'] >= 8) ? ' ok' : '';
                $closed = !empty($lead['closed']);
                $state  = $closed ? esc_html__('Cerrado', 'phsbot') : esc_html__('Abierto', 'phsbot');
                $stateC = $closed ? 'closed' : 'open';
                $page   = esc_attr(phsbot_arr_get($lead,'page',''));
                $ts     = intval(phsbot_arr_get($lead,'last_seen', time()));
                $date   = esc_html(date('d/m/Y', $ts));
                $open   = $closed ? '0' : '1';

                $rows .= '<tr data-cid="'.$cid.'" data-open="'.$open.'">';
                $rows .= '<td class="check-col"><input type="checkbox" class="phsbot-lead-check" value="'.$cid.'" /></td>';
                $rows .= '<td>'.$date.'</td>';
                $rows .= '<td>'.$name.'</td>';
                $rows .= '<td>'.$email.'</td>';
                $rows .= '<td>'.$phone.'</td>';
                $rows .= '<td><span class="phsbot-badge'.$scoreC.'">'.$scoreV.'</span></td>';
                $rows .= '<td><span class="phsbot-state '.$stateC.'">'.$state.'</span></td>';
                $rows .= '<td class="phsbot-truncate" title="'.$page.'">'.esc_html($page).'</td>';
                $rows .= '<td class="actions-col">';
                $rows .= '  <button class="button button-small phsbot-view" data-cid="'.$cid.'">'.esc_html__('Ver','phsbot').'</button> ';
                if (!$closed) {
                    $rows .= '  <button class="button button-small phsbot-close" data-cid="'.$cid.'">'.esc_html__('Cerrar','phsbot').'</button> ';
                }
                $rows .= '  <button class="button button-small button-link-delete phsbot-del" data-cid="'.$cid.'">'.esc_html__('Borrar','phsbot').'</button>';
                $rows .= '</td></tr>';
            }
        }

        // Toolbar + tabla
        echo '  <div class="phsbot-leads-toolbar" style="margin-top:10px">';
        echo '    <input type="search" id="phsbot-leads-search" placeholder="'.esc_attr__('Buscar por nombre, email, teléfono, página…','phsbot').'" />';
        echo '    <label class="phsbot-switch"><input type="checkbox" id="phsbot-leads-open-only" /><span>'.esc_html__('Solo abiertos','phsbot').'</span></label>';
        echo '    <button class="button" id="phsbot-leads-purge" title="'.esc_attr__('Purgar cerrados > 30 días','phsbot').'">'.esc_html__('Purgar cerrados (>30d)','phsbot').'</button>';
        echo '    <button class="button button-secondary" id="phsbot-leads-refresh">'.esc_html__('Actualizar','phsbot').'</button>';
        echo '    <button class="button button-link" id="phsbot-leads-reset-browser">'.esc_html__('Reset chat (navegador)','phsbot').'</button>';
        echo '  </div>';

        echo '  <table class="widefat fixed striped phsbot-leads-table" id="phsbot-leads-table">';
        echo '    <thead><tr>';
        echo '      <th class="check-col"><input type="checkbox" id="phsbot-leads-checkall" /></th>';
        echo '      <th>'.esc_html__('Fecha','phsbot').'</th>';
        echo '      <th>'.esc_html__('Nombre','phsbot').'</th>';
        echo '      <th>'.esc_html__('Email','phsbot').'</th>';
        echo '      <th>'.esc_html__('Teléfono','phsbot').'</th>';
        echo '      <th>'.esc_html__('Score','phsbot').'</th>';
        echo '      <th>'.esc_html__('Estado','phsbot').'</th>';
        echo '      <th>'.esc_html__('Página','phsbot').'</th>';
        echo '      <th class="actions-col">'.esc_html__('Acciones','phsbot').'</th>';
        echo '    </tr></thead>';
        echo '    <tbody>'.$rows.'</tbody>';
        echo '  </table>';

        echo '  <div class="phsbot-leads-bulk">';
        echo '    <button class="button button-primary" id="phsbot-leads-del-selected">'.esc_html__('Borrar seleccionados','phsbot').'</button>';
        echo '  </div>';

        echo '    </div>'; // .phsbot-module-content
        echo '  </div>'; // .phsbot-module-container
        echo '</div>'; // .wrap.phsbot-module-wrap
    }
}