<?php
if (!defined('ABSPATH')) exit;

/** Botón admin → resetea memoria del navegador (sube la versión) */
add_action('wp_ajax_phsbot_leads_browser_reset', function(){
    if (!current_user_can('manage_options')) wp_send_json_error('no_perms', 403);
    check_ajax_referer('phsbot_leads', 'nonce');

    $v = (int) get_option(PHSBOT_CLIENT_RESET_OPT, 0);
    $v++;
    update_option(PHSBOT_CLIENT_RESET_OPT, $v, false);
    wp_send_json_success(array('version'=>$v));
});
