<?php
/**
 * Analytics Class
 * Processes and retrieves visitor statistics
 */

if (!defined('ABSPATH')) {
    exit;
}

class Site_Vitals_Analytics {
    
    /**
     * Get current visitors (last 30 minutes)
     */
    public static function get_current_visitors() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'site_vitals_sessions';
        $thirty_minutes_ago = current_time('mysql', false);
        $thirty_minutes_ago = date('Y-m-d H:i:s', strtotime($thirty_minutes_ago . ' -30 minutes'));
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT session_id) 
            FROM $table 
            WHERE timestamp >= %s",
            $thirty_minutes_ago
        ));
        
        return intval($count);
    }
    
    /**
     * Get today's visitors
     */
    public static function get_today_visitors() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'site_vitals_sessions';
        $today_start = current_time('mysql', false);
        $today_start = date('Y-m-d 00:00:00', strtotime($today_start));
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT session_id) 
            FROM $table 
            WHERE timestamp >= %s",
            $today_start
        ));
        
        return intval($count);
    }
    
    /**
     * Get 7-day visitors
     */
    public static function get_week_visitors() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'site_vitals_sessions';
        $seven_days_ago = current_time('mysql', false);
        $seven_days_ago = date('Y-m-d 00:00:00', strtotime($seven_days_ago . ' -7 days'));
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT session_id) 
            FROM $table 
            WHERE timestamp >= %s",
            $seven_days_ago
        ));
        
        return intval($count);
    }
    
    /**
     * Get total page views for a period
     */
    public static function get_page_views($days = 7) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'site_vitals_sessions';
        $start_date = current_time('mysql', false);
        $start_date = date('Y-m-d 00:00:00', strtotime($start_date . " -{$days} days"));
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
            FROM $table 
            WHERE timestamp >= %s",
            $start_date
        ));
        
        return intval($count);
    }
    
    /**
     * Get top pages
     */
    public static function get_top_pages($limit = 10, $days = 7) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'site_vitals_sessions';
        $start_date = current_time('mysql', false);
        $start_date = date('Y-m-d 00:00:00', strtotime($start_date . " -{$days} days"));
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT page_url, COUNT(*) as views
            FROM $table 
            WHERE timestamp >= %s
            GROUP BY page_url
            ORDER BY views DESC
            LIMIT %d",
            $start_date,
            $limit
        ), ARRAY_A);
        
        return $results ? $results : array();
    }
    
    /**
     * Get top referrers
     */
    public static function get_top_referrers($limit = 10, $days = 7) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'site_vitals_sessions';
        $start_date = current_time('mysql', false);
        $start_date = date('Y-m-d 00:00:00', strtotime($start_date . " -{$days} days"));
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT referrer, COUNT(*) as visits
            FROM $table 
            WHERE timestamp >= %s 
            AND referrer IS NOT NULL 
            AND referrer != ''
            GROUP BY referrer
            ORDER BY visits DESC
            LIMIT %d",
            $start_date,
            $limit
        ), ARRAY_A);
        
        return $results ? $results : array();
    }
    
    /**
     * Get daily visitor trend
     */
    public static function get_daily_trend($days = 7) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'site_vitals_sessions';
        $start_date = current_time('mysql', false);
        $start_date = date('Y-m-d 00:00:00', strtotime($start_date . " -{$days} days"));
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(timestamp) as date, COUNT(DISTINCT session_id) as visitors
            FROM $table 
            WHERE timestamp >= %s
            GROUP BY DATE(timestamp)
            ORDER BY date ASC",
            $start_date
        ), ARRAY_A);
        
        return $results ? $results : array();
    }
    
    /**
     * Get device breakdown
     */
    public static function get_device_stats($days = 7) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'site_vitals_sessions';
        $start_date = current_time('mysql', false);
        $start_date = date('Y-m-d 00:00:00', strtotime($start_date . " -{$days} days"));
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT device_type, COUNT(DISTINCT session_id) as count
            FROM $table 
            WHERE timestamp >= %s
            GROUP BY device_type",
            $start_date
        ), ARRAY_A);
        
        return $results ? $results : array();
    }
    
    /**
     * Get browser breakdown
     */
    public static function get_browser_stats($days = 7) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'site_vitals_sessions';
        $start_date = current_time('mysql', false);
        $start_date = date('Y-m-d 00:00:00', strtotime($start_date . " -{$days} days"));
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT browser, COUNT(DISTINCT session_id) as count
            FROM $table 
            WHERE timestamp >= %s
            GROUP BY browser
            ORDER BY count DESC",
            $start_date
        ), ARRAY_A);
        
        return $results ? $results : array();
    }
}
