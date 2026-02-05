<?php
/**
 * Dashboard Page
 * Main analytics and monitoring dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class Site_Vitals_Dashboard {
    
    /**
     * Render dashboard page
     */
    public static function render() {
        // Get settings
        $settings = get_option('site_vitals_settings', array());
        $uptime_enabled = !empty($settings['uptime_check_enabled']);
        
        // Get stats
        $current_visitors = Site_Vitals_Analytics::get_current_visitors();
        $today_visitors = Site_Vitals_Analytics::get_today_visitors();
        $week_visitors = Site_Vitals_Analytics::get_week_visitors();
        
        // Only get uptime stats if enabled
        if ($uptime_enabled) {
            $uptime_status = Site_Vitals_Uptime::get_current_status();
            $uptime_percentage = Site_Vitals_Uptime::get_uptime_percentage(7);
            $avg_response = Site_Vitals_Uptime::get_average_response_time(7);
        }
        
        // Get additional stats
        $top_pages = Site_Vitals_Analytics::get_top_pages(3, 7);
        $top_referrers = Site_Vitals_Analytics::get_top_referrers(3, 7);
        $device_stats = Site_Vitals_Analytics::get_device_stats(7);
        
        // Calculate device percentages
        $total_devices = 0;
        foreach ($device_stats as $device) {
            $total_devices += $device['count'];
        }
        
        $device_percentages = array();
        foreach ($device_stats as $device) {
            $device_percentages[$device['device_type']] = $total_devices > 0 
                ? round(($device['count'] / $total_devices) * 100) 
                : 0;
        }

        // Get current user for greeting
        $current_user = wp_get_current_user();
        $display_name = $current_user->display_name ? $current_user->display_name : 'Dolly';
        
        ?>
        <div class="wrap sv-dashboard-wrap">
            <!-- Header -->
            <div class="sv-header">
                <div>
                    <h1><?php _e('Vital Statistics Dashboard', 'vital-statistics'); ?></h1>
                    <p class="sv-subtitle"><?php _e('Real-time performance and audience overview', 'vital-statistics'); ?></p>
                </div>
                <div class="sv-header-greeting">
                    <?php printf(__('You’re lookin’ swell, %s', 'vital-statistics'), esc_html($display_name)); ?>
                </div>
            </div>

            <!-- Top Row: Audience & Uptime -->
            <div class="sv-grid sv-grid-2col">
                
                <!-- Audience Visitors Section -->
                <div class="sv-section-container">
                    <div class="sv-section-header">
                        <div class="sv-section-indicator sv-indicator-blue"></div>
                        <h2><?php _e('Audience Visitors', 'vital-statistics'); ?></h2>
                    </div>
                    <div class="sv-grid sv-grid-3col">
                        <div class="sv-dashboard-card">
                            <div class="sv-metric-label"><?php _e('NOW', 'vital-statistics'); ?></div>
                            <div class="sv-metric-value"><?php echo esc_html($current_visitors); ?></div>
                            <div class="sv-metric-subtitle"><?php _e('Last 30 mins', 'vital-statistics'); ?></div>
                        </div>
                        <div class="sv-dashboard-card">
                            <div class="sv-metric-label"><?php _e('TODAY', 'vital-statistics'); ?></div>
                            <div class="sv-metric-value"><?php echo esc_html($today_visitors); ?></div>
                            <div class="sv-metric-subtitle"><?php _e('Uniques', 'vital-statistics'); ?></div>
                        </div>
                        <div class="sv-dashboard-card">
                            <div class="sv-metric-label"><?php _e('7 DAYS', 'vital-statistics'); ?></div>
                            <div class="sv-metric-value"><?php echo esc_html($week_visitors); ?></div>
                            <div class="sv-metric-subtitle"><?php _e('Uniques', 'vital-statistics'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- System Uptime Section -->
                <div class="sv-section-container">
                    <div class="sv-section-header sv-section-header-with-badge">
                        <div class="sv-section-title-group">
                            <div class="sv-section-indicator sv-indicator-emerald"></div>
                            <h2><?php _e('System Uptime', 'vital-statistics'); ?></h2>
                        </div>
                        <?php if ($uptime_enabled): ?>
                            <span class="sv-status-pill sv-status-<?php echo esc_attr($uptime_status['status']); ?>">
                                <span class="sv-status-dot"></span>
                                <?php echo esc_html(strtoupper($uptime_status['status'])); ?>
                            </span>
                        <?php else: ?>
                            <span class="sv-status-pill sv-status-disabled">
                                <?php _e('DISABLED', 'vital-statistics'); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($uptime_enabled): ?>
                        <div class="sv-grid sv-grid-2col">
                            <!-- NOW Block -->
                            <div class="sv-dashboard-card">
                                <div class="sv-uptime-header">
                                    <div class="sv-metric-label"><?php _e('NOW', 'vital-statistics'); ?></div>
                                    <div class="sv-uptime-percentage">100% <?php _e('UP', 'vital-statistics'); ?></div>
                                </div>
                                <div class="sv-response-time">
                                    <span class="sv-response-value"><?php echo esc_html($uptime_status['response_time']); ?></span>
                                    <span class="sv-response-label"><?php _e('ms Response', 'vital-statistics'); ?></span>
                                </div>
                                <div class="sv-health-bar">
                                    <div class="sv-health-bar-fill" style="width: <?php echo $uptime_status['status'] === 'online' ? '100' : '0'; ?>%"></div>
                                </div>
                            </div>

                            <!-- 7 Days Block -->
                            <div class="sv-dashboard-card">
                                <div class="sv-uptime-header">
                                    <div class="sv-metric-label"><?php _e('7 DAY AVG', 'vital-statistics'); ?></div>
                                    <div class="sv-uptime-percentage"><?php echo esc_html($uptime_percentage); ?>% <?php _e('UP', 'vital-statistics'); ?></div>
                                </div>
                                <div class="sv-response-time">
                                    <span class="sv-response-value"><?php echo esc_html($avg_response); ?></span>
                                    <span class="sv-response-label"><?php _e('ms Response', 'vital-statistics'); ?></span>
                                </div>
                                <div class="sv-health-bar">
                                    <div class="sv-health-bar-fill" style="width: <?php echo esc_attr($uptime_percentage); ?>%"></div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="sv-disabled-message">
                            <p><?php _e('Uptime monitoring is currently disabled.', 'vital-statistics'); ?>
                                <a href="<?php echo admin_url('admin.php?page=site-vitals-settings'); ?>">
                                    <?php _e('Enable it in settings', 'vital-statistics'); ?>
                                </a>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Statistics Section Header -->
            <div class="sv-section-header sv-standalone-header">
                <div class="sv-section-indicator sv-indicator-slate"></div>
                <h2><?php _e('STATISTICS (7 DAYS)', 'vital-statistics'); ?></h2>
            </div>

            <!-- 3-Card Statistics Section -->
            <div class="sv-grid sv-grid-3col">
                <!-- Card 1: Top Pages -->
                <div class="sv-stat-card">
                    <div class="sv-stat-header"><?php _e('TOP PAGES', 'vital-statistics'); ?></div>
                    <?php if (!empty($top_pages)): ?>
                        <table class="sv-table">
                            <tbody>
                                <?php foreach ($top_pages as $page): ?>
                                    <tr>
                                        <td class="sv-table-cell-primary"><?php echo esc_html(parse_url($page['page_url'], PHP_URL_PATH)); ?></td>
                                        <td class="sv-table-cell-secondary"><?php echo esc_html($page['views']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="sv-empty-state">
                            <p><?php _e('No page data found', 'vital-statistics'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Card 2: Referrers -->
                <div class="sv-stat-card">
                    <div class="sv-stat-header"><?php _e('TOP REFERRERS', 'vital-statistics'); ?></div>
                    <?php if (!empty($top_referrers)): ?>
                        <table class="sv-table">
                            <tbody>
                                <?php foreach ($top_referrers as $referrer): 
                                    $ref_url = $referrer['referrer'];
                                    $host = parse_url($ref_url, PHP_URL_HOST);
                                    $display = $host ? $host : __('Direct / Unknown', 'vital-statistics');
                                ?>
                                    <tr>
                                        <td class="sv-table-cell-primary" title="<?php echo esc_attr($ref_url); ?>"><?php echo esc_html($display); ?></td>
                                        <td class="sv-table-cell-secondary"><?php echo esc_html($referrer['visits']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="sv-empty-state">
                            <p><?php _e('No referrer data found', 'vital-statistics'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Card 3: Devices -->
                <div class="sv-stat-card">
                    <div class="sv-stat-header"><?php _e('DEVICES', 'vital-statistics'); ?></div>
                    <?php if (!empty($device_stats)): ?>
                        <table class="sv-table">
                            <tbody>
                                <?php 
                                $device_icons = array(
                                    'desktop' => 'sv-dot-blue',
                                    'mobile' => 'sv-dot-emerald',
                                    'tablet' => 'sv-dot-amber'
                                );
                                foreach ($device_stats as $device): 
                                    $device_type = $device['device_type'];
                                    $icon_class = isset($device_icons[$device_type]) ? $device_icons[$device_type] : 'sv-dot-slate';
                                ?>
                                    <tr>
                                        <td class="sv-table-cell-primary sv-device-cell">
                                            <span class="sv-device-dot <?php echo esc_attr($icon_class); ?>"></span>
                                            <?php echo esc_html(ucfirst($device_type)); ?>
                                        </td>
                                        <td class="sv-table-cell-secondary">
                                            <?php echo esc_html(isset($device_percentages[$device_type]) ? $device_percentages[$device_type] : 0); ?>%
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="sv-empty-state">
                            <p><?php _e('No device data found', 'vital-statistics'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
}