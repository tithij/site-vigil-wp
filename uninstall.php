<?php
/**
 * Uninstall Script
 * Fired when the plugin is uninstalled.
 */

// Exit if uninstall not called from WordPress
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Get settings to check if user wants to delete data
$settings = get_option('site_vitals_settings', array());
$delete_data_on_uninstall = isset($settings['delete_data_on_uninstall']) ? $settings['delete_data_on_uninstall'] : false;

// Only delete data if user has explicitly opted in
if ($delete_data_on_uninstall) {
    // Delete custom tables
    $table_sessions = $wpdb->prefix . 'site_vitals_sessions';
    $table_uptime = $wpdb->prefix . 'site_vitals_uptime';
    
    $wpdb->query("DROP TABLE IF EXISTS $table_sessions");
    $wpdb->query("DROP TABLE IF EXISTS $table_uptime");
    
    // Delete options
    delete_option('site_vitals_settings');
    
    // Delete transients
    delete_transient('site_vitals_last_downtime_alert');
}

// Always clear scheduled events (these should be cleared regardless)
wp_clear_scheduled_hook('site_vitals_uptime_check');
wp_clear_scheduled_hook('site_vitals_cleanup_old_data');
