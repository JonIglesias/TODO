<?php
// File: menu.php
if (!defined('ABSPATH')) exit;

/* ============================================================
 *  Render principal (wrapper del panel ORIGINAL)
 *  - No re-instancia la clase para no duplicar hooks.
 *  - Usa las constantes públicas de PHSBOT_Plugin.
 * ============================================================ */
if (!function_exists('phsbot_render_settings')) {
    function phsbot_render_settings() {
        if (class_exists('PHSBOT_Plugin')) {
            ?>
            <div class="wrap">
                <h1>PHSBOT — Ajustes</h1>
                <form method="post" action="options.php">
                    <?php
                    settings_fields(PHSBOT_Plugin::OPTION_GROUP);
                    do_settings_sections(PHSBOT_Plugin::PAGE_SLUG);
                    submit_button('Guardar ajustes');
                    ?>
                </form>
            </div>
            <?php
            return;
        }
        // Fallback si por lo que sea no carga la clase
        echo '<div class="wrap"><h1>PHSBot</h1><p>No se encontró la clase PHSBOT_Plugin.</p></div>';
    }
}

/* Router (por si cambias el nombre en el futuro) */
if (!function_exists('phsbot_settings_router')) {
    function phsbot_settings_router() {
        if (function_exists('phsbot_config_render_page')) { phsbot_config_render_page(); return; }
if (function_exists('phsbot_render_settings')) { phsbot_render_settings(); return;}
        echo '<div class="wrap"><h1>PHSBot</h1><p>No hay página principal de configuración.</p></div>';
    }
}

/* ============================================================
 *  Menú de admin
 * ============================================================ */
add_action('admin_menu', function () {
    $cap_menu      = defined('PHSBOT_CAP_MENU')     ? PHSBOT_CAP_MENU     : 'read';
    $cap_settings  = defined('PHSBOT_CAP_SETTINGS') ? PHSBOT_CAP_SETTINGS : 'manage_options';
    $menu_slug     = defined('PHSBOT_MENU_SLUG')    ? PHSBOT_MENU_SLUG    : 'phsbot';

    // Top-level
    add_menu_page(
        'PHSBot',
        'PHSBot',
        $cap_menu,
        $menu_slug,
        'phsbot_settings_router',
        'dashicons-format-chat',
        60
    );

    // Submenú principal (mismo slug)
    add_submenu_page(
        $menu_slug,
        'Configuración',
        'Configuración',
        $cap_settings,
        $menu_slug,
        'phsbot_settings_router'
    );

    // Submenú: Chat & Widget
    add_submenu_page(
        $menu_slug,
        'Chat & Widget',
        'Chat & Widget',
        $cap_settings,
        'phsbot-chat',
        function () {
            if (function_exists('phsbot_render_chat_settings')) { phsbot_render_chat_settings(); return; }
            if (function_exists('phsbot_render_chat_page')) { phsbot_render_chat_page(); return; }
            echo '<div class="wrap"><h1>Chat &amp; Widget</h1><p>No se encontró el módulo de chat.</p></div>';
        }
    );

    // Submenú: Inyecciones
    add_submenu_page(
        $menu_slug,
        'Inyecciones',
        'Inyecciones',
        $cap_settings,
        'phsbot-inject',
        function () {
            if (function_exists('phsbot_render_inject_admin')) { phsbot_render_inject_admin(); return; }
            if (function_exists('phsbot_render_inject_page')) { phsbot_render_inject_page(); return; }
            if (function_exists('phsbot_inject_admin_page')) { phsbot_inject_admin_page(); return; }
            echo '<div class="wrap"><h1>Inyecciones</h1><p>No se encontró el módulo de Inyecciones.</p></div>';
        }
    );

    // Submenú: Base de Conocimiento
    add_submenu_page(
        $menu_slug,
        'Base de Conocimiento',
        'Base de Conocimiento',
        $cap_settings,
        'phsbot-kb',
        function () {
            if (function_exists('phsbot_render_kb_admin')) { phsbot_render_kb_admin(); return; }
            if (function_exists('phsbot_render_kb_minimal')) { phsbot_render_kb_minimal(); return; }
            if (function_exists('phsbot_render_kb_page')) { phsbot_render_kb_page(); return; }
            echo '<div class="wrap"><h1>Base de Conocimiento</h1><p>No se encontró el módulo de KB.</p></div>';
        }
    );

    // Submenú: Leads & Scoring
    if (function_exists('phsbot_leads_admin_page')) {
        add_submenu_page(
            $menu_slug,
            'Leads & Scoring',
            'Leads & Scoring',
            $cap_settings,
            'phsbot-leads',
            'phsbot_leads_admin_page'
        );
    }

    // Submenú: Estadísticas
    if (function_exists('phsbot_stats_page')) {
        add_submenu_page(
            $menu_slug,
            'PHSBOT · Estadísticas',
            'Estadísticas',
            $cap_settings,
            'phsbot-estadisticas',
            'phsbot_stats_page'
        );
    }

    // Limpieza de slugs antiguos
    $legacy = array('phsbot-settings','phsbot_chat','phsbot-chat','phsbot_kb_page','phsbot_leads','phsbot_leads_old');
    foreach ($legacy as $slug) { remove_submenu_page($menu_slug, $slug); }
    if (defined('PHSBOT_CONFIG_SLUG')) { remove_submenu_page($menu_slug, PHSBOT_CONFIG_SLUG); } else { remove_submenu_page($menu_slug, 'phsbot_config'); }
}, 60);