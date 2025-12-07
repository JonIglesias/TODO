<?php
/**
 * Uninstall cleanup for PHSBOT
 * This file is executed when the plugin is uninstalled from the WordPress Plugins screen.
 * It removes options and clears scheduled hooks. Heavy options are removed; no posts/terms are touched.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Options to delete
$opts = array(
    'phsbot_settings',
    'phsbot_chat_settings',
    'phsbot_kb_prompt',
    'phsbot_kb_document',
    'phsbot_inject_rules',
    'phsbot_leads_store',
    'phsbot_leads_settings',
    'phsbot_client_reset_version',
);

// Delete options if they exist
foreach ($opts as $opt) {
    if (get_option($opt, null) !== null) {
        delete_option($opt);
    }
}

// Clear scheduled hooks (if any)
if (function_exists('wp_clear_scheduled_hook')) {
    @wp_clear_scheduled_hook('phsbot_leads_daily_digest');
    @wp_clear_scheduled_hook('phsbot_leads_inactive_check');
}

// Best-effort: also unschedule next occurrences explicitly (some hosts)
if (function_exists('wp_next_scheduled') && function_exists('wp_unschedule_event')) {
    $hooks = array('phsbot_leads_daily_digest', 'phsbot_leads_inactive_check');
    foreach ($hooks as $hook) {
        if ($ts = @wp_next_scheduled($hook)) {
            @wp_unschedule_event($ts, $hook);
        }
    }
}
