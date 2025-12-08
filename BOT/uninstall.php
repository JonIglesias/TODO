<?php
/**
 * Uninstall cleanup for PHSBOT
 * This file is executed when the plugin is uninstalled from the WordPress Plugins screen.
 * It removes ALL plugin data: options, transients, post meta, user meta, and cron hooks.
 *
 * @package PHSBOT
 * @version 2.0
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// ============================================================================
// 1. DELETE OPTIONS
// ============================================================================

$options = array(
    // Main plugin settings
    'phsbot_settings',
    'phsbot_chat_settings',
    'phsbot_client_reset_version',

    // Knowledge Base
    'phsbot_kb_prompt',
    'phsbot_kb_extra_prompt',
    'phsbot_kb_extra_domains',
    'phsbot_kb_max_urls',
    'phsbot_kb_max_pages_main',
    'phsbot_kb_max_posts_main',
    'phsbot_kb_model',
    'phsbot_kb_site_override_on',
    'phsbot_kb_site_override',
    'phsbot_kb_document',
    'phsbot_kb_last_updated',
    'phsbot_kb_last_run',
    'phsbot_kb_last_model',
    'phsbot_kb_last_debug',
    'phsbot_kb_last_error',
    'phsbot_kb_job_started',
    'phsbot_kb_job_status',
    'phsbot_kb_models_cache',
    'phsbot_kb_version',

    // Inject rules
    'phsbot_inject_rules',

    // Leads
    'phsbot_leads_store',
    'phsbot_leads_settings',

    // Legacy (for migration from old versions)
    'phs_chatbot_settings'
);

foreach ($options as $option) {
    delete_option($option);
    // Also delete from sitemeta for multisite
    delete_site_option($option);
}

// ============================================================================
// 2. DELETE TRANSIENTS
// ============================================================================

$transients = array(
    'phsbot_openai_models_chat_v3',
    'phsbot_openai_models_chat_v2',
    'phsbot_openai_models_chat'
);

foreach ($transients as $transient) {
    delete_transient($transient);
    delete_site_transient($transient);
}

// Delete all phsbot-related transients (catch any dynamic ones)
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_phsbot_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_phsbot_%'");

// ============================================================================
// 3. DELETE POST META
// ============================================================================

// Delete all post meta created by the plugin (if any)
$meta_keys = array(
    '_phsbot_chat_history',
    '_phsbot_conversation_id'
);

foreach ($meta_keys as $meta_key) {
    delete_post_meta_by_key($meta_key);
}

// ============================================================================
// 4. DELETE USER META
// ============================================================================

// Delete all user meta created by the plugin
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'phsbot_%'");

// ============================================================================
// 5. CLEAR SCHEDULED CRON JOBS
// ============================================================================

$cron_hooks = array(
    'phsbot_leads_daily_digest',
    'phsbot_leads_inactive_check',
    'phsbot_kb_update_cron'
);

foreach ($cron_hooks as $hook) {
    if (function_exists('wp_clear_scheduled_hook')) {
        wp_clear_scheduled_hook($hook);
    }
}

// Best-effort: also unschedule next occurrences explicitly (some hosts require this)
if (function_exists('wp_next_scheduled') && function_exists('wp_unschedule_event')) {
    foreach ($cron_hooks as $hook) {
        if ($ts = wp_next_scheduled($hook)) {
            wp_unschedule_event($ts, $hook);
        }
    }
}

// ============================================================================
// 6. FLUSH REWRITE RULES
// ============================================================================

flush_rewrite_rules();

// ============================================================================
// 7. CLEAN UP CAPABILITIES (if any custom roles were created)
// ============================================================================

// Currently PHSBOT doesn't create custom capabilities, but adding for completeness
// $role = get_role('administrator');
// if ($role) {
//     $role->remove_cap('manage_phsbot');
// }

// ============================================================================
// 8. DELETE UPLOADED FILES (if any)
// ============================================================================

// Currently PHSBOT doesn't upload files, but adding for future-proofing
// $upload_dir = wp_upload_dir();
// $phsbot_dir = $upload_dir['basedir'] . '/phsbot';
// if (is_dir($phsbot_dir)) {
//     // Recursive delete
//     $files = new RecursiveIteratorIterator(
//         new RecursiveDirectoryIterator($phsbot_dir, RecursiveDirectoryIterator::SKIP_DOTS),
//         RecursiveIteratorIterator::CHILD_FIRST
//     );
//     foreach ($files as $fileinfo) {
//         $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
//         $todo($fileinfo->getRealPath());
//     }
//     rmdir($phsbot_dir);
// }
