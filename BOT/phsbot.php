<?php
/**
 * Plugin Name: Conversa
 * Description: Chat con esteroides
 * Version: 1.4.1
 * Author: Jon Iglesias
 * License: GPLv2 or later
 */

if (!defined('ABSPATH')) exit;

// Definir constantes del plugin
define('PHSBOT_VERSION', '1.4.1');
define('PHSBOT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PHSBOT_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Sistema de actualizaciones automáticas desde GitHub
 */
// Cargar Parsedown (requerido por Plugin Update Checker)
if (!class_exists('Parsedown')) {
    require_once PHSBOT_PLUGIN_DIR . 'lib/Parsedown.php';
}

require PHSBOT_PLUGIN_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$conversaUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/bocetosmarketing/conversa-bot',
    __FILE__,
    'conversa-bot'
);

// Opcional: establecer rama específica para updates (por defecto usa releases)
// $conversaUpdateChecker->setBranch('main');

// Evita redefinir la clase; pero NO hagas return, para no cortar los requires de abajo.
if (!class_exists('PHSBOT_Plugin')) {

    class PHSBOT_Plugin {

        const OPTION_KEY   = 'phsbot_settings';
        const OPTION_GROUP = 'phsbot_settings_group';
        const PAGE_SLUG    = 'phsbot-settings'; // slug interno de la página de secciones/campos (no menú)


        /* ======== __construct: Registra hooks base del plugin ======== */
        function __construct() {
            add_action('admin_init', array($this, 'register_settings'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
            add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'settings_link'));
            add_action('plugins_loaded', array($this, 'ensure_defaults_and_migrate'));
        }
        /* ======== FIN __construct ======== */


        /* ======== get_settings: Devuelve settings fusionando con defaults ======== */
        static function get_settings() {
            $defaults = self::defaults();
            $opt = get_option(self::OPTION_KEY, array());
            if (!is_array($opt)) $opt = array();
            return wp_parse_args($opt, $defaults);
        }
        /* ======== FIN get_settings ======== */


        /* ======== get_setting: Devuelve un setting concreto con fallback ======== */
        static function get_setting($key, $default = null) {
            $all = self::get_settings();
            return array_key_exists($key, $all) ? $all[$key] : $default;
        }
        /* ======== FIN get_setting ======== */


        /* ======== get_allowed_domains: Devuelve array de dominios permitidos ======== */
        static function get_allowed_domains() {
            $s = self::get_settings();
            return (isset($s['allowed_domains']) && is_array($s['allowed_domains'])) ? $s['allowed_domains'] : array();
        }
        /* ======== FIN get_allowed_domains ======== */


        /* ======== register_settings: Settings API, secciones y campos base ======== */
        function register_settings() {
            register_setting(self::OPTION_GROUP, self::OPTION_KEY, array(
                'type'              => 'array',
                'sanitize_callback' => array($this, 'sanitize_settings'),
                'default'           => self::defaults(),
            ));

            add_settings_section('phs_section_general',      'General',                            '__return_false', self::PAGE_SLUG);
            add_settings_section('phs_section_integrations', 'Integraciones',                      '__return_false', self::PAGE_SLUG);
            add_settings_section('phs_section_appearance',   'Apariencia',                         '__return_false', self::PAGE_SLUG);
            add_settings_section('phs_section_sources',      'Fuentes de datos (crawler)',         '__return_false', self::PAGE_SLUG);

            // General
            add_settings_field('chat_active',   'Estado del chat',        array($this, 'field_chat_active'),   self::PAGE_SLUG, 'phs_section_general');
            add_settings_field('chat_position', 'Posición del chatbot',   array($this, 'field_chat_position'), self::PAGE_SLUG, 'phs_section_general');
            add_settings_field('chat_width',    'Anchura del chat',       array($this, 'field_chat_width'),    self::PAGE_SLUG, 'phs_section_general');
            add_settings_field('chat_height',   'Altura del chat',        array($this, 'field_chat_height'),   self::PAGE_SLUG, 'phs_section_general');

            // Integraciones
            add_settings_field('telegram_bot_token', 'Token de Telegram (Bot)',           array($this, 'field_telegram_bot_token'), self::PAGE_SLUG, 'phs_section_integrations');
            add_settings_field('telegram_chat_id',   'ID de Telegram (chat/user/channel)',array($this, 'field_telegram_chat_id'),   self::PAGE_SLUG, 'phs_section_integrations');
            add_settings_field('whatsapp_phone',     'Teléfono de WhatsApp (derivación)', array($this, 'field_whatsapp_phone'),     self::PAGE_SLUG, 'phs_section_integrations');

            // Apariencia
            add_settings_field('palette',            'Paleta predefinida',         array($this, 'field_palette'),           self::PAGE_SLUG, 'phs_section_appearance');
            add_settings_field('color_primary',      'Color primario',             array($this, 'field_color_primary'),     self::PAGE_SLUG, 'phs_section_appearance');
            add_settings_field('color_secondary',    'Color secundario',           array($this, 'field_color_secondary'),   self::PAGE_SLUG, 'phs_section_appearance');
            add_settings_field('color_background',   'Fondo del chat',             array($this, 'field_color_background'),  self::PAGE_SLUG, 'phs_section_appearance');
            add_settings_field('color_text',         'Texto principal',            array($this, 'field_color_text'),        self::PAGE_SLUG, 'phs_section_appearance');
            add_settings_field('color_bot_bubble',   'Burbuja BOT',                array($this, 'field_color_bot_bubble'),  self::PAGE_SLUG, 'phs_section_appearance');
            add_settings_field('color_user_bubble',  'Burbuja USUARIO',            array($this, 'field_color_user_bubble'), self::PAGE_SLUG, 'phs_section_appearance');
            add_settings_field('bubble_font_size',   'Tamaño de fuente (burbujas)',array($this, 'field_bubble_font_size'),  self::PAGE_SLUG, 'phs_section_appearance');

            // Fuentes (crawler)
            add_settings_field('allowed_domains', 'Dominios permitidos (uno por línea)', array($this, 'field_allowed_domains'), self::PAGE_SLUG, 'phs_section_sources');
        }
        /* ======== FIN register_settings ======== */


        /* ======== enqueue_admin_assets: Encola assets y JS inline para paletas ======== */
        function enqueue_admin_assets($hook) {
            // Encola assets en todas las pantallas del plugin (toplevel + submenús)
            $allow = array('toplevel_page_phsbot', 'settings_page_phsbot', 'toplevel_page_' . self::PAGE_SLUG);
            if (!in_array($hook, $allow, true)) {
                $screen = function_exists('get_current_screen') ? get_current_screen() : null;
                if ($screen && ($screen->id === 'toplevel_page_phsbot' || strpos($screen->id, 'phsbot_page_') === 0)) {
                    // ok
                } else {
                    return;
                }
            }

            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('wp-color-picker');

            // IMPORTANTE: NOWDOC para evitar interpolación de $i dentro del JS
            $inline_js = <<<'JS'
(function($){
    $(function(){
        // Color pickers
        $('.phs-color').wpColorPicker();

        // Paletas predefinidas
        var palettes = {
            none:{},
            phs_dark:{color_primary:'#7A1C1C',color_secondary:'#C4A484',color_background:'#121212',color_text:'#EFEFEF',color_bot_bubble:'#1F1F1F',color_user_bubble:'#2B2B2B'},
            phs_light:{color_primary:'#8A2B2B',color_secondary:'#D7C1A5',color_background:'#FFFFFF',color_text:'#1A1A1A',color_bot_bubble:'#F3F3F3',color_user_bubble:'#EDE7E1'},
            forest:{color_primary:'#1F5D3A',color_secondary:'#A6B37D',color_background:'#0E1A14',color_text:'#E7F0E9',color_bot_bubble:'#15241C',color_user_bubble:'#1E3628'},
            desert:{color_primary:'#A66A2C',color_secondary:'#D9C4A1',color_background:'#FFF9F0',color_text:'#2B2116',color_bot_bubble:'#F1E3CC',color_user_bubble:'#E7D4B6'}
        };
        $('#palette').on('change', function(){
            var set = palettes[$(this).val()]||{};
            for (var key in set) {
                var $i = $('input[name="phsbot_settings['+key+']"]');
                if ($i.length){
                    $i.val(set[key]).trigger('change');
                    try{ if($i.data('wpWpColorPicker')){ $i.wpColorPicker('color', set[key]); } }catch(e){}
                }
            }
        });

        // Slider de tamaño de fuente (burbujas): refleja "XX px"
        $(document).on('input change', '#bubble_font_size', function(){
            var v = parseInt(this.value,10)||0;
            if (v < 12) v = 12;
            if (v > 22) v = 22;
            this.value = v;
            $('#bubble_font_size_val').text(v + ' px');
        });
    });
})(jQuery);
JS;
            wp_add_inline_script('wp-color-picker', $inline_js);
            wp_add_inline_style('wp-color-picker', '.phs-wide{width:420px;max-width:100%}.phs-domains{width:520px;max-width:100%;min-height:140px}.description{opacity:.8}.phs-range{width:320px;max-width:100%}.phs-range-wrap{display:flex;align-items:center;gap:10px}');

            // Shepherd.js para tours interactivos
            wp_enqueue_style(
                'shepherd-js',
                'https://cdn.jsdelivr.net/npm/shepherd.js@11.2.0/dist/css/shepherd.css',
                [],
                '11.2.0'
            );

            wp_enqueue_style(
                'phsbot-shepherd-custom',
                plugin_dir_url(__FILE__) . 'core/assets/phsbot-shepherd-custom.css',
                ['shepherd-js'],
                '1.0.0'
            );

            wp_enqueue_script(
                'shepherd-js',
                'https://cdn.jsdelivr.net/npm/shepherd.js@11.2.0/dist/js/shepherd.min.js',
                [],
                '11.2.0',
                true
            );

            wp_enqueue_script(
                'phsbot-shepherd-tours',
                plugin_dir_url(__FILE__) . 'core/assets/phsbot-shepherd-tours.js',
                ['jquery', 'shepherd-js'],
                '1.0.0',
                true
            );
        }
        /* ======== FIN enqueue_admin_assets ======== */


        /* ======== settings_link: Añade enlace "Ajustes" en la lista de plugins ======== */
        function settings_link($links) {
            // Apunta al menú "PhsBot"
            $url = admin_url('admin.php?page=phsbot');
            $links[] = '<a href="' . esc_url($url) . '">Ajustes</a>';
            return $links;
        }
        /* ======== FIN settings_link ======== */


        /* ======== render_settings_page: Fallback de página de ajustes ======== */
        function render_settings_page() {
            if (!current_user_can('manage_options')) return;
            ?>
            <div class="wrap">
                <h1>Conversa — Ajustes base</h1>
                <form method="post" action="options.php">
                    <?php
                    settings_fields(self::OPTION_GROUP);
                    do_settings_sections(self::PAGE_SLUG);
                    submit_button('Guardar ajustes');
                    ?>
                </form>

                <script>
                // Refleja el valor inicial del slider si estuviera en esta página fallback
                (function(){
                    var el = document.getElementById('bubble_font_size');
                    var lbl = document.getElementById('bubble_font_size_val');
                    if (el && lbl) { lbl.textContent = parseInt(el.value||15,10) + ' px'; }
                })();
                </script>
            </div>
            <?php
        }
        /* ======== FIN render_settings_page ======== */


        /* ======== e: Helper para obtener valor de setting en campos ======== */
        static function e($key) {
            $s = self::get_settings();
            return isset($s[$key]) ? $s[$key] : '';
        }
        /* ======== FIN e ======== */


        /* ======== field_chat_active: Checkbox activar chat en front ======== */
        function field_chat_active() { $v = (bool) self::e('chat_active'); ?>
            <label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[chat_active]" value="1" <?php checked($v, true); ?>> Activar el chatbot en el front</label>
        <?php }
        /* ======== FIN field_chat_active ======== */


        /* ======== field_chat_position: Select posición del chat ======== */
        function field_chat_position() {
            $v = (string) self::e('chat_position');
            $options = array(
                'bottom-right' => 'Flotante — Abajo derecha',
                'bottom-left'  => 'Flotante — Abajo izquierda',
                'top-right'    => 'Flotante — Arriba derecha',
                'top-left'     => 'Flotante — Arriba izquierda',
                'inline'       => 'Inline (embebido)'
            );
            echo '<select name="'.esc_attr(self::OPTION_KEY).'[chat_position]">';
            foreach ($options as $val => $label) {
                echo '<option value="'.esc_attr($val).'" '.selected($v, $val, false).'>'.esc_html($label).'</option>';
            }
            echo '</select><p class="description">Se podrá sobrescribir por shortcode/Elementor.</p>';
        }
        /* ======== FIN field_chat_position ======== */


        /* ======== field_chat_width: Input ancho del chat ======== */
        function field_chat_width()  { $v = (string) self::e('chat_width'); ?>
            <input class="regular-text phs-wide" type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[chat_width]" value="<?php echo esc_attr($v); ?>" placeholder="360px, 100%, 80vw">
            <p class="description">Unidades: px, %, vw.</p>
        <?php }
        /* ======== FIN field_chat_width ======== */


        /* ======== field_chat_height: Input alto del chat ======== */
        function field_chat_height() { $v = (string) self::e('chat_height'); ?>
            <input class="regular-text phs-wide" type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[chat_height]" value="<?php echo esc_attr($v); ?>" placeholder="520px, 70vh">
            <p class="description">Unidades: px, vh.</p>
        <?php }
        /* ======== FIN field_chat_height ======== */


        /* ======== field_telegram_bot_token: Input token Telegram ======== */
        function field_telegram_bot_token() { $v = (string) self::e('telegram_bot_token'); ?>
            <input class="regular-text phs-wide" type="password" name="<?php echo esc_attr(self::OPTION_KEY); ?>[telegram_bot_token]" value="<?php echo esc_attr($v); ?>" autocomplete="off">
        <?php }
        /* ======== FIN field_telegram_bot_token ======== */


        /* ======== field_telegram_chat_id: Input chat_id Telegram ======== */
        function field_telegram_chat_id() { $v = (string) self::e('telegram_chat_id'); ?>
            <input class="regular-text phs-wide" type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[telegram_chat_id]" value="<?php echo esc_attr($v); ?>">
        <?php }
        /* ======== FIN field_telegram_chat_id ======== */


        /* ======== field_whatsapp_phone: Input teléfono WhatsApp ======== */
        function field_whatsapp_phone() { $v = (string) self::e('whatsapp_phone'); ?>
            <input class="regular-text phs-wide" type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[whatsapp_phone]" value="<?php echo esc_attr($v); ?>" placeholder="+346XXXXXXXX">
            <p class="description">Formato internacional (+34...).</p>
        <?php }
        /* ======== FIN field_whatsapp_phone ======== */


        /* ======== field_palette: Select de paletas predefinidas ======== */
        function field_palette() {
            $v = (string) self::e('palette');
            $options = array(
                'none'      => 'Sin paleta (manual)',
                'phs_dark'  => 'PHS Dark (vino/arena oscuro)',
                'phs_light' => 'PHS Light (vino/arena claro)',
                'forest'    => 'Forest (verdes)',
                'desert'    => 'Desert (ocres)',
            );
            echo '<select id="palette" name="'.esc_attr(self::OPTION_KEY).'[palette]">';
            foreach ($options as $val => $label) {
                echo '<option value="'.esc_attr($val).'" '.selected($v, $val, false).'>'.esc_html($label).'</option>';
            }
            echo '</select><p class="description">Autorrellena colores base.</p>';
        }
        /* ======== FIN field_palette ======== */


        /* ======== field_color_*: Inputs de color con color picker ======== */
        function field_color_primary()    { $this->render_color('color_primary'); }
        function field_color_secondary()  { $this->render_color('color_secondary'); }
        function field_color_background() { $this->render_color('color_background'); }
        function field_color_text()       { $this->render_color('color_text'); }
        function field_color_bot_bubble() { $this->render_color('color_bot_bubble'); }
        function field_color_user_bubble(){ $this->render_color('color_user_bubble'); }
        /* ======== FIN field_color_* ======== */


        /* ======== field_bubble_font_size: Slider 12–22 px para tamaño de fuente de burbujas ======== */
        function field_bubble_font_size() {
            $v = intval(self::e('bubble_font_size'));
            if ($v < 12) $v = 15; if ($v > 22) $v = 22;
            $name = esc_attr(self::OPTION_KEY).'[bubble_font_size]';
            ?>
            <div class="phs-range-wrap">
                <input id="bubble_font_size" class="phs-range" type="range" min="12" max="22" step="1" name="<?php echo $name; ?>" value="<?php echo esc_attr($v); ?>">
                <strong id="bubble_font_size_val"><?php echo esc_html($v); ?> px</strong>
            </div>
            <p class="description">Afecta al tamaño de texto de las burbujas en el front. Requiere que el front lea esta opción y la aplique como CSS variable.</p>
            <?php
        }
        /* ======== FIN field_bubble_font_size ======== */


        /* ======== render_color: Renderiza un input de color estándar ======== */
        function render_color($key) {
            $v = (string) self::e($key);
            echo '<input class="phs-color" type="text" name="'.esc_attr(self::OPTION_KEY).'['.esc_attr($key).']" value="'.esc_attr($v).'" data-default-color="'.esc_attr(self::defaults()[$key]).'">';
        }
        /* ======== FIN render_color ======== */


        /* ======== field_allowed_domains: Textarea de dominios permitidos ======== */
        function field_allowed_domains() {
            $text = implode("\n", self::get_allowed_domains());
            echo '<textarea class="phs-domains" name="'.esc_attr(self::OPTION_KEY).'[allowed_domains]">'.esc_textarea($text).'</textarea>';
            echo '<p class="description">Uno por línea. Ej: <code>tudominio.com</code> o <code>blog.tudominio.com</code>.</p>';
        }
        /* ======== FIN field_allowed_domains ======== */


        /* ======== sanitize_settings: Sanea y, MUY IMPORTANTE, preserva claves existentes ======== */
        function sanitize_settings($input) {
            // Partimos de lo que ya existe + defaults para NO perder claves ajenas a esta pantalla.
            $cur = get_option(self::OPTION_KEY, array());
            if (!is_array($cur)) $cur = array();
            $out = wp_parse_args($cur, self::defaults());

            // Flags / selects
            if (array_key_exists('chat_active', $input)) {
                $out['chat_active'] = !empty($input['chat_active']);
            }

            $allowed_positions = array('bottom-right','bottom-left','top-right','top-left','inline');
            if (array_key_exists('chat_position', $input)) {
                $pos = sanitize_text_field($input['chat_position']);
                $out['chat_position'] = in_array($pos, $allowed_positions, true) ? $pos : $out['chat_position'];
            }

            // Dimensiones (acepta "420" => "420px" y unidades px/%/vh/vw)
            if (array_key_exists('chat_width', $input)) {
                $out['chat_width'] = $this->sanitize_css_size($input['chat_width']);
            }
            if (array_key_exists('chat_height', $input)) {
                $out['chat_height'] = $this->sanitize_css_size($input['chat_height']);
            }

            // Conexiones
            if (array_key_exists('bot_license_key', $input)) {
                $out['bot_license_key'] = $this->sanitize_token($input['bot_license_key']);
            }
            if (array_key_exists('bot_api_url', $input)) {
                $out['bot_api_url'] = esc_url_raw($input['bot_api_url']);
            }
            if (array_key_exists('telegram_bot_token', $input)) {
                $out['telegram_bot_token'] = $this->sanitize_token($input['telegram_bot_token']);
            }
            if (array_key_exists('telegram_chat_id', $input)) {
                $out['telegram_chat_id'] = sanitize_text_field($input['telegram_chat_id']);
            }

            if (array_key_exists('whatsapp_phone', $input)) {
                $wa = isset($input['whatsapp_phone']) ? (string)$input['whatsapp_phone'] : '';
                $wa = preg_replace('/[^0-9+]/', '', $wa);
                $out['whatsapp_phone'] = ltrim($wa);
            }

            // Paleta
            if (array_key_exists('palette', $input)) {
                $pal = sanitize_text_field($input['palette']);
                $out['palette'] = in_array($pal, array('none','phs_dark','phs_light','forest','desert'), true) ? $pal : 'none';
            }

            // Colores base
            foreach (array('color_primary','color_secondary','color_background','color_text','color_bot_bubble','color_user_bubble','color_launcher_bg','color_launcher_icon','color_launcher_text') as $ck) {
                if (array_key_exists($ck, $input)) {
                    $c = $input[$ck];
                    $out[$ck] = $this->sanitize_hex_color_fallback($c, self::defaults()[$ck]);
                }
            }

            // ===== Campos usados por la pantalla custom o ampliados =====

            // Título cabecera (si llega)
            if (array_key_exists('chat_title', $input)) {
                $val = trim(wp_strip_all_tags((string)$input['chat_title']));
                $out['chat_title'] = ($val === '') ? 'Conversa' : $val;
            }

            // Color footer (si llega)
            if (array_key_exists('color_footer', $input)) {
                $out['color_footer'] = $this->sanitize_hex_color_fallback($input['color_footer'], '');
            }

            // Botones/tamaños de UI (si llegan)
            if (array_key_exists('btn_height', $input)) {
                $out['btn_height'] = max(36, min(56, intval($input['btn_height'])));
            }
            if (array_key_exists('head_btn_size', $input)) {
                $out['head_btn_size'] = max(20, min(34, intval($input['head_btn_size'])));
            }
            if (array_key_exists('mic_stroke_w', $input)) {
                $out['mic_stroke_w'] = max(1, min(3, intval($input['mic_stroke_w'])));
            }

            // Dominios permitidos (textarea o array)
            if (array_key_exists('allowed_domains', $input)) {
                $out['allowed_domains'] = $this->sanitize_domains($input['allowed_domains']);
            }

            // Tamaño de fuente de las burbujas (12–22)
            if (array_key_exists('bubble_font_size', $input)) {
                $v = intval($input['bubble_font_size']);
                if ($v < 12) $v = 12;
                if ($v > 22) $v = 22;
                $out['bubble_font_size'] = $v;
            }

            return $out;
        }
        /* ======== FIN sanitize_settings ======== */


        /* ======== sanitize_css_size: Normaliza tamaños CSS seguros ======== */
        function sanitize_css_size($val) {
            $val = trim((string)$val);
            if ($val === '') return '360px';
            // Acepta número solo (añade px) o número+unidad (px|%|vh|vw)
            if (preg_match('/^\d+(\.\d+)?$/', $val)) {
                return $val.'px';
            }
            if (preg_match('/^\d+(\.\d+)?(px|%|vh|vw)$/i', $val)) {
                return $val;
            }
            // fallback seguro
            return '360px';
        }
        /* ======== FIN sanitize_css_size ======== */


        /* ======== sanitize_token: Limpia claves/token, sin espacios ======== */
        function sanitize_token($val) {
            $val = trim((string)$val);
            $val = wp_kses($val, array());
            return preg_replace('/\s+/', '', $val);
        }
        /* ======== FIN sanitize_token ======== */


        /* ======== sanitize_hex_color_fallback: Valida color HEX con fallback ======== */
        function sanitize_hex_color_fallback($color, $fallback) {
            $color = trim((string)$color);
            if (preg_match('/^#([A-Fa-f0-9]{3}){1,2}$/', $color)) return $color;
            return $fallback;
        }
        /* ======== FIN sanitize_hex_color_fallback ======== */


        /* ======== sanitize_domains: Normaliza lista de dominios ======== */
        function sanitize_domains($raw) {
            $list = is_array($raw) ? $raw : preg_split('/\r\n|\r|\n/', (string)$raw);
            $clean = array();
            foreach ($list as $line) {
                $d = strtolower(trim($line));
                if ($d === '') continue;
                if (strpos($d, 'http://') === 0 || strpos($d, 'https://') === 0) {
                    $host = parse_url($d, PHP_URL_HOST);
                    if ($host) $d = $host;
                }
                if (preg_match('/^([a-z0-9-]+\.)+[a-z]{2,}$/', $d)) $clean[] = $d;
            }
            return array_values(array_unique($clean));
        }
        /* ======== FIN sanitize_domains ======== */


        /* ======== defaults: Valores por defecto (AMPLIADOS) ======== */
        static function defaults() {
            // Detectar dominio actual de forma segura
            $current_domain = '';
            if (function_exists('home_url')) {
                $parsed = parse_url(home_url(), PHP_URL_HOST);
                if ($parsed && is_string($parsed)) {
                    $current_domain = $parsed;
                }
            }

            // Fallback a localhost si no se puede detectar
            if (empty($current_domain)) {
                $current_domain = 'localhost';
            }

            return array(
                'chat_active'        => true,
                'chat_position'      => 'bottom-right',
                'chat_width'         => '360px',
                'chat_height'        => '520px',
                'bubble_font_size'   => 15,

                'bot_license_key'    => '',
                'bot_api_url'        => 'https://bocetosmarketing.com/api_claude_5/index.php',
                'telegram_bot_token' => '',
                'telegram_chat_id'   => '',
                'whatsapp_phone'     => '',

                'palette'            => 'none',
                'color_primary'      => '#7A1C1C',
                'color_secondary'    => '#C4A484',
                'color_background'   => '#FFFFFF',
                'color_text'         => '#1A1A1A',
                'color_bot_bubble'   => '#F3F3F3',
                'color_user_bubble'  => '#EDE7E1',

                // Colores del botón launcher
                'color_launcher_bg'   => '#1e1e1e',
                'color_launcher_icon' => '#ffffff',
                'color_launcher_text' => '#ffffff',

                // ===== Claves usadas por la pantalla custom =====
                'chat_title'         => 'Conversa',
                'color_footer'       => '',
                'btn_height'         => 44,
                'head_btn_size'      => 26,
                'mic_stroke_w'       => 1,

                // ✅ DINÁMICO: Detecta automáticamente el dominio donde se instala
                'allowed_domains'    => array($current_domain),
            );
        }
        /* ======== FIN defaults ======== */


        /* ======== ensure_defaults_and_migrate: Alta inicial + migración legacy ======== */
        function ensure_defaults_and_migrate() {
            $cur = get_option(self::OPTION_KEY, null);
            if ($cur === null) {
                $old = get_option('phs_chatbot_settings', null);
                if (is_array($old)) {
                    $merged = wp_parse_args($old, self::defaults());
                    add_option(self::OPTION_KEY, $merged, '', 'no'); // autoload NO
                } else {
                    add_option(self::OPTION_KEY, self::defaults(), '', 'no'); // autoload NO
                }
            } elseif (is_array($cur)) {
                $merged = wp_parse_args($cur, self::defaults());
                // Siempre actualizar para asegurar que todos los defaults están presentes
                if ($merged !== $cur || !isset($cur['color_launcher_bg'])) {
                    update_option(self::OPTION_KEY, $merged);
                }
            }
        }
        /* ======== FIN ensure_defaults_and_migrate ======== */

    }

    new PHSBOT_Plugin();
}

// Cargar el hub (incluye módulos)
require_once __DIR__ . '/common.php';

// phsbot.php  (al final del bootstrap del plugin)
$__extras = __DIR__ . '/extras/extras.php';
if (file_exists($__extras)) { require_once $__extras; }

// === Deactivation: clear scheduled hooks ===
if (function_exists('register_deactivation_hook')) {
    register_deactivation_hook(PHSBOT_DIR . 'phsbot.php', function () {
        if (function_exists('wp_clear_scheduled_hook')) {
            @wp_clear_scheduled_hook('phsbot_leads_daily_digest');
            @wp_clear_scheduled_hook('phsbot_leads_inactive_check');
        }
        if (function_exists('wp_next_scheduled') && function_exists('wp_unschedule_event')) {
            foreach (array('phsbot_leads_daily_digest','phsbot_leads_inactive_check') as $hook) {
                if ($ts = @wp_next_scheduled($hook)) { @wp_unschedule_event($ts, $hook); }
            }
        }
        if (function_exists('phsbot_leads_unschedule_cron')) { phsbot_leads_unschedule_cron(); }
    });
}
