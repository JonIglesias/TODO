<?php
/**
 * Uninstall cleanup for GEO Writer
 * This file is executed when the plugin is uninstalled from the WordPress Plugins screen.
 * It removes ALL plugin data: tables, options, transients, post meta, and cron hooks.
 *
 * @package GEOWriter
 * @version 1.0
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// ============================================================================
// 1. DELETE CUSTOM TABLES
// ============================================================================

$tables = array(
    $wpdb->prefix . 'ap_campaigns',
    $wpdb->prefix . 'ap_queue',
    $wpdb->prefix . 'ap_logs',
    $wpdb->prefix . 'ap_token_usage',
    $wpdb->prefix . 'ap_execution_log',
    $wpdb->prefix . 'ap_locks',
    $wpdb->prefix . 'ap_nichos'
);

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}

// ============================================================================
// 2. DELETE OPTIONS
// ============================================================================

$options = array(
    'ap_api_url',
    'ap_license_key',
    'ap_company_desc',
    'ap_unsplash_key',
    'ap_pixabay_key',
    'ap_pexels_key',
    'ap_db_version'
);

foreach ($options as $option) {
    delete_option($option);
    // Also delete from sitemeta for multisite
    delete_site_option($option);
}

// ============================================================================
// 3. DELETE TRANSIENTS
// ============================================================================

$transients = array(
    'ap_active_plan_v11',
    'ap_license_info',
    'ap_generation_progress',
    'ap_execution_progress'
);

foreach ($transients as $transient) {
    delete_transient($transient);
    delete_site_transient($transient);
}

// Delete dynamic transients (autopilot_data_{user_id})
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_autopilot_data_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_autopilot_data_%'");

// Delete rate limiter transients
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ap_rate_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_ap_rate_%'");

// ============================================================================
// 4. DELETE POST META
// ============================================================================

// Delete all post meta created by the plugin
$meta_keys = array(
    '_autopost_schema_markup',
    '_ap_campaign_id',
    '_ap_queue_id',
    '_ap_generated',
    '_ap_tokens_used'
);

foreach ($meta_keys as $meta_key) {
    delete_post_meta_by_key($meta_key);
}

// ============================================================================
// 5. DELETE USER META (if any)
// ============================================================================

// Currently GEOWriter doesn't use user meta, but adding for future-proofing
// $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'ap_%'");

// ============================================================================
// 6. CLEAR SCHEDULED CRON JOBS
// ============================================================================

// Clear any scheduled hooks
$cron_hooks = array(
    'ap_process_queue',
    'ap_cleanup_logs',
    'ap_sync_licenses'
);

foreach ($cron_hooks as $hook) {
    wp_clear_scheduled_hook($hook);
}

// ============================================================================
// 7. FLUSH REWRITE RULES
// ============================================================================

flush_rewrite_rules();

// ============================================================================
// 8. CLEAN UP CAPABILITIES (if any custom roles were created)
// ============================================================================

// Currently GEOWriter doesn't create custom capabilities, but adding for completeness
// $role = get_role('administrator');
// if ($role) {
//     $role->remove_cap('manage_geowriter');
// }
