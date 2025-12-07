<?php
// File: integrations.php (placeholder "Integraciones")
if (!defined('ABSPATH')) exit;

if (!defined('PHSBOT_CAP_SETTINGS')) define('PHSBOT_CAP_SETTINGS', 'manage_options');
if (!defined('PHSBOT_MENU_SLUG')) define('PHSBOT_MENU_SLUG', 'phsbot');


if (!function_exists('phsbot_render_integrations_page')) {
    function phsbot_render_integrations_page() {
        if (!current_user_can(PHSBOT_CAP_SETTINGS)) {
            wp_die(__('No tienes permisos para acceder a esta página.', 'phsbot'), 403);
        }
        ?>
        <div class="wrap">
            <h1>PhsBot — Integraciones</h1>
            <p class="description">Vista específica para pruebas de conectividad y ajustes avanzados de Telegram, WhatsApp, OpenAI, etc.</p>
            <p>Por ahora, usa la pestaña <em>Configuración</em> para los tokens. Aquí añadiremos tests de conexión y logs de intentos.</p>
        </div>
        <?php
    }
}
