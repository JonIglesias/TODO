<?php
if (!defined('ABSPATH')) exit;

// Cargar AJAX handler
require_once __DIR__ . '/autopilot-ajax-improved.php';

class AP_Autopilot {

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu'], 11);
    }

    public function register_menu() {
        add_submenu_page(
            'autopost-ia',
            'Autopilot',
            'Autopilot',
            'manage_options',
            'autopost-autopilot',
            [$this, 'render_page']
        );
    }

    public function render_page() {
        require_once __DIR__ . '/autopilot-wizard-improved.php';
    }
}

// Inicializar
new AP_Autopilot();
