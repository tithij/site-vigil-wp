<?php
/**
 * Database Handler
 * Creates and manages custom database tables
 */

if (!defined('ABSPATH')) {
    exit;
}

class Site_Vitals_Database {
    
    /**
     * Create database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Table for visitor sessions
        $table_sessions = $wpdb->prefix . 'site_vitals_sessions';
        
        $sql_sessions = "CREATE TABLE $table_sessions (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id varchar(32) NOT NULL,
            page_url varchar(500) NOT NULL,
            referrer varchar(500) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            country_code varchar(2) DEFAULT NULL,
            device_type varchar(20) DEFAULT 'desktop',
            browser varchar(50) DEFAULT NULL,
            os varchar(50) DEFAULT NULL,
            timestamp datetime NOT NULL,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY timestamp (timestamp),
            KEY page_url (page_url(191))
        ) $charset_collate;";
        
        // Table for uptime checks
        $table_uptime = $wpdb->prefix . 'site_vitals_uptime';
        
        $sql_uptime = "CREATE TABLE $table_uptime (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            check_time datetime NOT NULL,
            status varchar(20) NOT NULL,
            response_time int(11) DEFAULT NULL,
            status_code int(11) DEFAULT NULL,
            error_message text DEFAULT NULL,
            PRIMARY KEY (id),
            KEY check_time (check_time),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_sessions);
        dbDelta($sql_uptime);
    }
    
    /**
     * Drop tables (used on uninstall)
     */
    public static function drop_tables() {
        global $wpdb;
        
        $table_sessions = $wpdb->prefix . 'site_vitals_sessions';
        $table_uptime = $wpdb->prefix . 'site_vitals_uptime';
        
        $wpdb->query("DROP TABLE IF EXISTS $table_sessions");
        $wpdb->query("DROP TABLE IF EXISTS $table_uptime");
    }
    
    /**
     * Clean up old data based on retention settings
     */
    public static function cleanup_old_data() {
        global $wpdb;
        
        $settings = get_option('site_vitals_settings', array());
        $retention_days = isset($settings['retention_days']) ? intval($settings['retention_days']) : 90;
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
        
        // Clean sessions
        $table_sessions = $wpdb->prefix . 'site_vitals_sessions';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_sessions WHERE timestamp < %s",
            $cutoff_date
        ));
        
        // Clean uptime checks (keep more uptime data)
        $table_uptime = $wpdb->prefix . 'site_vitals_uptime';
        $uptime_cutoff = date('Y-m-d H:i:s', strtotime("-180 days")); // 6 months
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_uptime WHERE check_time < %s",
            $uptime_cutoff
        ));
    }
}

// Schedule cleanup
add_action('site_vitals_cleanup_old_data', array('Site_Vitals_Database', 'cleanup_old_data'));
