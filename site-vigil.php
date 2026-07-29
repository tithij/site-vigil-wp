<?php
/**
 * Plugin Name: Site Vigil
 * Plugin URI: https://site-vigil.com
 * Description: Lightweight visitor tracking and uptime monitoring for WordPress, powered by Site Vigil.
 * Version: 0.4.3
 * Author: Site Vigil
 * License: GPL v2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SITE_VIGIL_VERSION', '0.4.3' );
define( 'SITE_VIGIL_OPTION_KEY', 'site_vigil_tracking_id' );

require_once plugin_dir_path( __FILE__ ) . 'includes/class-connector.php';
Site_Vigil_Connector::init();

// Self-hosted auto-updates via GitHub Releases (spec §9.2) — no WP.org
// listing exists, so without this the plugin would never notify admins of
// new versions. Points at release *assets* (the built, .distignore-clean
// zip), not GitHub's auto-generated source archive.
require_once plugin_dir_path( __FILE__ ) . 'includes/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$site_vigil_update_checker = PucFactory::buildUpdateChecker(
    'https://github.com/tithij/site-vigil-wp/',
    __FILE__,
    'site-vigil'
);
$site_vigil_update_checker->getVcsApi()->enableReleaseAssets( '/^site-vigil-wp-.*\.zip$/i' );

add_action( 'admin_menu', 'site_vigil_add_settings_page' );
function site_vigil_add_settings_page() {
    add_options_page(
        'Site Vigil',
        'Site Vigil',
        'manage_options',
        'site-vigil',
        'site_vigil_render_settings_page'
    );
}

function site_vigil_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    ?>
    <div class="wrap">
        <h1>Site Vigil</h1>
        <?php Site_Vigil_Connector::render_settings_section(); ?>
    </div>
    <?php
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'site_vigil_action_links' );
function site_vigil_action_links( $links ) {
    $settings_link = '<a href="' . admin_url( 'options-general.php?page=site-vigil' ) . '">Settings</a>';
    array_unshift( $links, $settings_link );
    return $links;
}

add_action( 'wp_head', 'site_vigil_inject_tracker', 5 );
function site_vigil_inject_tracker() {
    if ( is_admin_bar_showing() && current_user_can( 'edit_posts' ) ) {
        return;
    }

    $tracking_id = get_option( SITE_VIGIL_OPTION_KEY, '' );
    if ( empty( $tracking_id ) ) {
        return;
    }

    // Endpoint and session key match the site-vigil-visitors npm package (core.ts).
    $endpoint    = 'https://t.site-vigil.com';
    $session_key = 'tithij_session';
    ?>
<script type="module">
(function () {
  var siteId     = <?php echo wp_json_encode( $tracking_id ); ?>;
  var endpoint   = <?php echo wp_json_encode( $endpoint ); ?>;
  var sessionKey = <?php echo wp_json_encode( $session_key ); ?>;

  function getSessionId() {
    var id = sessionStorage.getItem(sessionKey);
    if (!id) {
      id = (crypto.randomUUID ? crypto.randomUUID() : 'session-' + Math.random().toString(36).slice(2) + '-' + Date.now());
      sessionStorage.setItem(sessionKey, id);
    }
    return id;
  }

  function send(action) {
    fetch(endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        siteId:    siteId,
        timestamp: new Date().toISOString(),
        page:      window.location.pathname,
        referrer:  document.referrer,
        action:    action,
        sessionId: getSessionId(),
      }),
      keepalive: true,
    }).catch(function () {});
  }

  send('visit');
  window.addEventListener('beforeunload', function () { send('leave'); });
})();
</script>
<?php
}
