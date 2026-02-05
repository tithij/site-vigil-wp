<?php
/**
 * Plugin Name: Vital Statistics
 * Plugin URI: https://yoursite.com/vital-statistics
 * Description: Simple website monitoring with uptime checks and privacy-friendly visitor analytics. Track current visitors, daily stats, and weekly trends in one clean dashboard.
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Your Name
 * Author URI: https://yoursite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: vital-statistics
 * Domain Path: /languages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SITE_VITALS_VERSION', '1.0.0');
define('SITE_VITALS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SITE_VITALS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SITE_VITALS_PLUGIN_FILE', __FILE__);

/**
 * Main Site Vitals Class
 */
class Site_Vitals {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
        // Core classes
        require_once SITE_VITALS_PLUGIN_DIR . 'includes/class-database.php';
        require_once SITE_VITALS_PLUGIN_DIR . 'includes/class-tracker.php';
        require_once SITE_VITALS_PLUGIN_DIR . 'includes/class-analytics.php';
        require_once SITE_VITALS_PLUGIN_DIR . 'includes/class-uptime.php';
        
        // Admin classes
        if (is_admin()) {
            require_once SITE_VITALS_PLUGIN_DIR . 'admin/class-admin.php';
            require_once SITE_VITALS_PLUGIN_DIR . 'admin/class-dashboard.php';
            require_once SITE_VITALS_PLUGIN_DIR . 'admin/class-settings.php';
        }
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Activation and deactivation
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Initialize components
        add_action('plugins_loaded', array($this, 'init'));
        
        // Enqueue tracking script on frontend
        add_action('wp_enqueue_scripts', array($this, 'enqueue_tracking_script'));
        
        // Add settings link on plugins page
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_action_links'));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        Site_Vitals_Database::create_tables();
        
        // Set default options
        $default_options = array(
            'enabled' => true,
            'track_admin' => false,
            'anonymize_ip' => true,
            'retention_days' => 90,
            'uptime_check_enabled' => true,
            'uptime_check_interval' => 5, // minutes
            'email_alerts' => false,
            'alert_email' => get_option('admin_email'),
            'delete_data_on_uninstall' => false, // Preserve data by default
        );
        
        add_option('site_vitals_settings', $default_options);
        
        // Schedule uptime checks
        if (!wp_next_scheduled('site_vitals_uptime_check')) {
            wp_schedule_event(time(), 'five_minutes', 'site_vitals_uptime_check');
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('site_vitals_uptime_check');
        wp_clear_scheduled_hook('site_vitals_cleanup_old_data');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain('vital-statistics', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Initialize admin if in admin area
        if (is_admin()) {
            Site_Vitals_Admin::get_instance();
        }
        
        // Schedule cleanup job if not scheduled
        if (!wp_next_scheduled('site_vitals_cleanup_old_data')) {
            wp_schedule_event(time(), 'daily', 'site_vitals_cleanup_old_data');
        }
    }
    
    /**
     * Enqueue tracking script on frontend
     */
    public function enqueue_tracking_script() {
        // Get settings
        $settings = get_option('site_vitals_settings', array());
        
        // Check if tracking is enabled
        if (empty($settings['enabled'])) {
            return;
        }
        
        // Don't track admins if setting is disabled
        if (empty($settings['track_admin']) && current_user_can('manage_options')) {
            return;
        }
        
        // Enqueue tracking script
        wp_enqueue_script(
            'site-vitals-tracker',
            SITE_VITALS_PLUGIN_URL . 'public/js/tracker.js',
            array(),
            SITE_VITALS_VERSION,
            true
        );
        
        // Pass data to script
        wp_localize_script('site-vitals-tracker', 'siteVitalsData', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('site_vitals_track'),
            'anonymize_ip' => !empty($settings['anonymize_ip']),
        ));
    }
    
    /**
     * Add action links to plugins page
     */
    public function add_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=site-vitals') . '">' . __('Dashboard', 'vital-statistics') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}

/**
 * Add custom cron schedule for 5 minutes
 */
add_filter('cron_schedules', function($schedules) {
    $schedules['five_minutes'] = array(
        'interval' => 300,
        'display' => __('Every 5 Minutes', 'vital-statistics')
    );
    return $schedules;
});

/**
 * Initialize the plugin
 */
function site_vitals() {
    return Site_Vitals::get_instance();
}

// Start the plugin
site_vitals();
