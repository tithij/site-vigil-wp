<?php
/**
 * Uptime Monitoring Class
 * Performs uptime checks and stores results
 */

if (!defined('ABSPATH')) {
    exit;
}

class Site_Vitals_Uptime {
    
    /**
     * Initialize uptime monitoring
     */
    public static function init() {
        add_action('site_vitals_uptime_check', array(__CLASS__, 'perform_check'));
    }
    
    /**
     * Perform uptime check
     */
    public static function perform_check() {
        global $wpdb;
        
        // Get settings
        $settings = get_option('site_vitals_settings', array());
        
        if (empty($settings['uptime_check_enabled'])) {
            return;
        }
        
        $site_url = home_url('/');
        
        // Perform HTTP request
        $start_time = microtime(true);
        $response = wp_remote_get($site_url, array(
            'timeout' => 10,
            'sslverify' => false,
        ));
        $end_time = microtime(true);
        
        // Calculate response time
        $response_time = round(($end_time - $start_time) * 1000); // in milliseconds
        
        // Determine status
        if (is_wp_error($response)) {
            $status = 'offline';
            $status_code = null;
            $error_message = $response->get_error_message();
            
            // Send alert if email alerts are enabled
            if (!empty($settings['email_alerts'])) {
                self::send_downtime_alert($error_message);
            }
        } else {
            $status_code = wp_remote_retrieve_response_code($response);
            $status = ($status_code >= 200 && $status_code < 300) ? 'online' : 'offline';
            $error_message = null;
            
            if ($status === 'offline' && !empty($settings['email_alerts'])) {
                self::send_downtime_alert("HTTP Status Code: {$status_code}");
            }
        }
        
        // Store result
        $table = $wpdb->prefix . 'site_vitals_uptime';
        $wpdb->insert(
            $table,
            array(
                'check_time' => current_time('mysql'),
                'status' => $status,
                'response_time' => $response_time,
                'status_code' => $status_code,
                'error_message' => $error_message,
            ),
            array('%s', '%s', '%d', '%d', '%s')
        );
    }
    
    /**
     * Get current site status
     */
    public static function get_current_status() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'site_vitals_uptime';
        
        $result = $wpdb->get_row(
            "SELECT * FROM $table ORDER BY check_time DESC LIMIT 1",
            ARRAY_A
        );
        
        return $result ? $result : array(
            'status' => 'unknown',
            'response_time' => 0,
        );
    }
    
    /**
     * Get uptime percentage
     */
    public static function get_uptime_percentage($days = 7) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'site_vitals_uptime';
        $start_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE check_time >= %s",
            $start_date
        ));
        
        if (!$total) {
            return 100;
        }
        
        $online = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE check_time >= %s AND status = 'online'",
            $start_date
        ));
        
        return round(($online / $total) * 100, 2);
    }
    
    /**
     * Get average response time
     */
    public static function get_average_response_time($days = 7) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'site_vitals_uptime';
        $start_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $avg = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(response_time) FROM $table WHERE check_time >= %s AND status = 'online'",
            $start_date
        ));
        
        return $avg ? round($avg) : 0;
    }
    
    /**
     * Get uptime history
     */
    public static function get_uptime_history($days = 7) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'site_vitals_uptime';
        $start_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE check_time >= %s ORDER BY check_time ASC",
            $start_date
        ), ARRAY_A);
        
        return $results ? $results : array();
    }
    
    /**
     * Send downtime alert email
     */
    private static function send_downtime_alert($error_message) {
        $settings = get_option('site_vitals_settings', array());
        $email = isset($settings['alert_email']) ? $settings['alert_email'] : get_option('admin_email');
        
        // Check if we've sent an alert recently (avoid spam)
        $last_alert = get_transient('site_vitals_last_downtime_alert');
        if ($last_alert) {
            return; // Already sent alert in last 30 minutes
        }
        
        $subject = sprintf('[%s] Site Down Alert', get_bloginfo('name'));
        $message = sprintf(
            "Your website %s appears to be down.\n\nError: %s\n\nTime: %s\n\nThis is an automated alert from Site Vitals.",
            home_url('/'),
            $error_message,
            current_time('mysql')
        );
        
        wp_mail($email, $subject, $message);
        
        // Set transient to prevent alert spam (30 minutes)
        set_transient('site_vitals_last_downtime_alert', time(), 1800);
    }
}

// Initialize uptime monitoring
Site_Vitals_Uptime::init();
