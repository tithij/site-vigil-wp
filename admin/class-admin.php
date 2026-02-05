<?php
/**
 * Admin Class
 * Handles admin area functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class Site_Vitals_Admin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Add admin menu pages
     */
    public function add_admin_menu() {
        // Main dashboard page
        add_menu_page(
            __('Vital Statistics', 'vital-statistics'),
            __('Vital Stats', 'vital-statistics'),
            'manage_options',
            'site-vitals',
            array('Site_Vitals_Dashboard', 'render'),
            'dashicons-chart-line',
            30
        );
        
        // Settings page
        add_submenu_page(
            'site-vitals',
            __('Settings', 'vital-statistics'),
            __('Settings', 'vital-statistics'),
            'manage_options',
            'site-vitals-settings',
            array('Site_Vitals_Settings', 'render')
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'site-vitals') === false) {
            return;
        }
        
        // CSS with time-based cache busting
        wp_enqueue_style(
            'site-vitals-admin',
            SITE_VITALS_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            SITE_VITALS_VERSION . '-' . time()
        );
        
        // JS
        wp_enqueue_script(
            'site-vitals-admin',
            SITE_VITALS_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            SITE_VITALS_VERSION,
            true
        );
    }
}
