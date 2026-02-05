<?php
/**
 * Settings Page
 * Plugin configuration options
 */

if (!defined('ABSPATH')) {
    exit;
}

class Site_Vitals_Settings {
    
    /**
     * Render settings page
     */
    public static function render() {
        // Handle form submission
        if (isset($_POST['site_vitals_settings_submit'])) {
            check_admin_referer('site_vitals_settings');
            self::save_settings();
        }
        
        $settings = get_option('site_vitals_settings', array());
        ?>
        <div class="wrap">
            <h1><?php _e('Vital Statistics Settings', 'vital-statistics'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('site_vitals_settings'); ?>
                
                <table class="form-table">
                    <!-- Tracking Settings -->
                    <tr>
                        <th colspan="2"><h2><?php _e('Tracking Settings', 'vital-statistics'); ?></h2></th>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="enabled"><?php _e('Enable Tracking', 'vital-statistics'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="enabled" name="enabled" value="1" 
                                <?php checked(!empty($settings['enabled']), true); ?>>
                            <p class="description">
                                <?php _e('Enable visitor tracking on your website.', 'vital-statistics'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="track_admin"><?php _e('Track Admins', 'vital-statistics'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="track_admin" name="track_admin" value="1" 
                                <?php checked(!empty($settings['track_admin']), true); ?>>
                            <p class="description">
                                <?php _e('Track visits from logged-in administrators.', 'vital-statistics'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="anonymize_ip"><?php _e('Anonymize IP Addresses', 'vital-statistics'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="anonymize_ip" name="anonymize_ip" value="1" 
                                <?php checked(!empty($settings['anonymize_ip']), true); ?>>
                            <p class="description">
                                <?php _e('Make tracking GDPR compliant by anonymizing IP addresses.', 'vital-statistics'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="retention_days"><?php _e('Data Retention', 'vital-statistics'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="retention_days" name="retention_days" 
                                value="<?php echo esc_attr(isset($settings['retention_days']) ? $settings['retention_days'] : 90); ?>"
                                min="7" max="365">
                            <p class="description">
                                <?php _e('Number of days to keep visitor data (7-365).', 'vital-statistics'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Uptime Monitoring -->
                    <tr>
                        <th colspan="2"><h2><?php _e('Uptime Monitoring', 'vital-statistics'); ?></h2></th>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="uptime_check_enabled"><?php _e('Enable Uptime Checks', 'vital-statistics'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="uptime_check_enabled" name="uptime_check_enabled" value="1" 
                                <?php checked(!empty($settings['uptime_check_enabled']), true); ?>>
                            <p class="description">
                                <?php _e('Monitor your site uptime and response time.', 'vital-statistics'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="uptime_check_interval"><?php _e('Check Interval', 'vital-statistics'); ?></label>
                        </th>
                        <td>
                            <select id="uptime_check_interval" name="uptime_check_interval">
                                <option value="5" <?php selected(isset($settings['uptime_check_interval']) ? $settings['uptime_check_interval'] : 5, 5); ?>>
                                    <?php _e('Every 5 minutes', 'vital-statistics'); ?>
                                </option>
                                <option value="15" <?php selected(isset($settings['uptime_check_interval']) ? $settings['uptime_check_interval'] : 5, 15); ?>>
                                    <?php _e('Every 15 minutes', 'vital-statistics'); ?>
                                </option>
                                <option value="30" <?php selected(isset($settings['uptime_check_interval']) ? $settings['uptime_check_interval'] : 5, 30); ?>>
                                    <?php _e('Every 30 minutes', 'vital-statistics'); ?>
                                </option>
                                <option value="60" <?php selected(isset($settings['uptime_check_interval']) ? $settings['uptime_check_interval'] : 5, 60); ?>>
                                    <?php _e('Every hour', 'vital-statistics'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="email_alerts"><?php _e('Email Alerts', 'vital-statistics'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="email_alerts" name="email_alerts" value="1" 
                                <?php checked(!empty($settings['email_alerts']), true); ?>>
                            <p class="description">
                                <?php _e('Send email alert when site goes down.', 'vital-statistics'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="alert_email"><?php _e('Alert Email Address', 'vital-statistics'); ?></label>
                        </th>
                        <td>
                            <input type="email" id="alert_email" name="alert_email" 
                                value="<?php echo esc_attr(isset($settings['alert_email']) ? $settings['alert_email'] : get_option('admin_email')); ?>"
                                class="regular-text">
                        </td>
                    </tr>
                    
                    <!-- Data Management -->
                    <tr>
                        <th colspan="2"><h2><?php _e('Data Management', 'vital-statistics'); ?></h2></th>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="delete_data_on_uninstall"><?php _e('Delete Data on Uninstall', 'vital-statistics'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="delete_data_on_uninstall" name="delete_data_on_uninstall" value="1" 
                                <?php checked(!empty($settings['delete_data_on_uninstall']), true); ?>>
                            <p class="description">
                                <?php _e('⚠️ When enabled, all visitor data and settings will be permanently deleted when you uninstall this plugin. Leave unchecked to preserve your data for plugin updates or reinstallation.', 'vital-statistics'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Save Settings', 'vital-statistics'), 'primary', 'site_vitals_settings_submit'); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Save settings
     */
    private static function save_settings() {
        $settings = array(
            'enabled' => isset($_POST['enabled']),
            'track_admin' => isset($_POST['track_admin']),
            'anonymize_ip' => isset($_POST['anonymize_ip']),
            'retention_days' => isset($_POST['retention_days']) ? intval($_POST['retention_days']) : 90,
            'uptime_check_enabled' => isset($_POST['uptime_check_enabled']),
            'uptime_check_interval' => isset($_POST['uptime_check_interval']) ? intval($_POST['uptime_check_interval']) : 5,
            'email_alerts' => isset($_POST['email_alerts']),
            'alert_email' => isset($_POST['alert_email']) ? sanitize_email($_POST['alert_email']) : get_option('admin_email'),
            'delete_data_on_uninstall' => isset($_POST['delete_data_on_uninstall']),
        );
        
        update_option('site_vitals_settings', $settings);
        
        // Update cron schedule if interval changed
        wp_clear_scheduled_hook('site_vitals_uptime_check');
        
        $interval_map = array(
            5 => 'five_minutes',
            15 => 'fifteen_minutes',
            30 => 'thirty_minutes',
            60 => 'hourly',
        );
        
        $interval = isset($interval_map[$settings['uptime_check_interval']]) 
            ? $interval_map[$settings['uptime_check_interval']] 
            : 'five_minutes';
        
        wp_schedule_event(time(), $interval, 'site_vitals_uptime_check');
        
        echo '<div class="notice notice-success"><p>' . __('Settings saved successfully.', 'vital-statistics') . '</p></div>';
    }
}

// Add custom cron schedules
add_filter('cron_schedules', function($schedules) {
    $schedules['fifteen_minutes'] = array(
        'interval' => 900,
        'display' => __('Every 15 Minutes', 'vital-statistics')
    );
    $schedules['thirty_minutes'] = array(
        'interval' => 1800,
        'display' => __('Every 30 Minutes', 'vital-statistics')
    );
    return $schedules;
});
