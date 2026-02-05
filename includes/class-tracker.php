<?php
/**
 * Tracker Class
 * Handles visitor tracking and session management
 */

if (!defined('ABSPATH')) {
    exit;
}

class Site_Vitals_Tracker {
    
    /**
     * Initialize tracking hooks
     */
    public static function init() {
        add_action('wp_ajax_site_vitals_track', array(__CLASS__, 'track_visit'));
        add_action('wp_ajax_nopriv_site_vitals_track', array(__CLASS__, 'track_visit'));
    }
    
    /**
     * Track a visit (AJAX handler)
     */
    public static function track_visit() {
        // Verify nonce
        check_ajax_referer('site_vitals_track', 'nonce');
        
        global $wpdb;
        
        // Get data from request
        $page_url = isset($_POST['page']) ? esc_url_raw($_POST['page']) : '';
        $referrer = isset($_POST['referrer']) ? esc_url_raw($_POST['referrer']) : '';
        $session_id = isset($_POST['session_id']) ? sanitize_text_field($_POST['session_id']) : '';
        
        // Get IP address
        $ip_address = self::get_ip_address();
        
        // Anonymize IP if setting is enabled
        $settings = get_option('site_vitals_settings', array());
        if (!empty($settings['anonymize_ip'])) {
            $ip_address = self::anonymize_ip($ip_address);
        }
        
        // Get user agent
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
        
        // Parse user agent for device/browser/OS info
        $device_info = self::parse_user_agent($user_agent);
        
        // Insert session record
        $table = $wpdb->prefix . 'site_vitals_sessions';
        $result = $wpdb->insert(
            $table,
            array(
                'session_id' => $session_id,
                'page_url' => $page_url,
                'referrer' => $referrer,
                'ip_address' => $ip_address,
                'user_agent' => $user_agent,
                'device_type' => $device_info['device'],
                'browser' => $device_info['browser'],
                'os' => $device_info['os'],
                'timestamp' => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error();
        }
    }
    
    /**
     * Get visitor's IP address
     */
    private static function get_ip_address() {
        $ip_keys = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER)) {
                $ip = explode(',', $_SERVER[$key]);
                $ip = trim($ip[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '';
    }
    
    /**
     * Anonymize IP address (GDPR compliant)
     */
    private static function anonymize_ip($ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // IPv4: Remove last octet
            $parts = explode('.', $ip);
            $parts[3] = '0';
            return implode('.', $parts);
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // IPv6: Remove last 80 bits
            $parts = explode(':', $ip);
            for ($i = 5; $i < 8; $i++) {
                $parts[$i] = '0';
            }
            return implode(':', $parts);
        }
        
        return $ip;
    }
    
    /**
     * Parse user agent string
     */
    private static function parse_user_agent($user_agent) {
        $device = 'desktop';
        $browser = 'Unknown';
        $os = 'Unknown';
        
        // Detect device type
        if (preg_match('/(tablet|ipad|playbook)|(android(?!.*mobile))/i', $user_agent)) {
            $device = 'tablet';
        } elseif (preg_match('/(up\.browser|up\.link|mmp|symbian|smartphone|midp|wap|phone|android|iemobile)/i', $user_agent)) {
            $device = 'mobile';
        }
        
        // Detect browser
        if (preg_match('/MSIE/i', $user_agent) && !preg_match('/Opera/i', $user_agent)) {
            $browser = 'Internet Explorer';
        } elseif (preg_match('/Firefox/i', $user_agent)) {
            $browser = 'Firefox';
        } elseif (preg_match('/Chrome/i', $user_agent) && !preg_match('/Edge/i', $user_agent)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Safari/i', $user_agent) && !preg_match('/Chrome/i', $user_agent)) {
            $browser = 'Safari';
        } elseif (preg_match('/Opera/i', $user_agent) || preg_match('/OPR/i', $user_agent)) {
            $browser = 'Opera';
        } elseif (preg_match('/Edge/i', $user_agent)) {
            $browser = 'Edge';
        }
        
        // Detect OS
        if (preg_match('/windows|win32/i', $user_agent)) {
            $os = 'Windows';
        } elseif (preg_match('/macintosh|mac os x/i', $user_agent)) {
            $os = 'Mac OS';
        } elseif (preg_match('/linux/i', $user_agent)) {
            $os = 'Linux';
        } elseif (preg_match('/android/i', $user_agent)) {
            $os = 'Android';
        } elseif (preg_match('/iPhone|iPad|iPod/i', $user_agent)) {
            $os = 'iOS';
        }
        
        return array(
            'device' => $device,
            'browser' => $browser,
            'os' => $os,
        );
    }
}

// Initialize tracker
Site_Vitals_Tracker::init();
