<?php
/**
 * Fires only on a real "Delete" from the Plugins screen (not on deactivate).
 * Clears everything this plugin ever wrote to wp_options/transients so a
 * clean uninstall leaves no orphaned state — required for a WP.org listing,
 * and good hygiene for self-hosted installs too.
 *
 * The server-side plugin_token revoke happens on the dashboard's Admin ->
 * Site Management "Disconnect" action, not here — this plugin holds a
 * plugin_token, not a user session, so it has no way to call that itself.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'site_vigil_tracking_id' );
delete_option( 'site_vigil_plugin_token' );
delete_option( 'site_vigil_website_id' );
delete_transient( 'site_vigil_summary_cache' );
delete_transient( 'site_vigil_connect_error' );

// Book-keeping written by the vendored plugin-update-checker library
// (includes/plugin-update-checker/), keyed off the 'site-vigil' slug passed
// to PucFactory::buildUpdateChecker() in site-vigil.php. PUC has no uninstall
// hook of its own, so this plugin has to clean up after it.
delete_option( 'external_updates-site-vigil' );
delete_site_transient( 'puc_manual_check_errors-site-vigil' );
