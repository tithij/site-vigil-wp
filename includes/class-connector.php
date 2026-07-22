<?php
/**
 * CMS Plugin Connector v2 — authenticated, scoped, read-only client of the
 * get-site-summary Edge Function, reached via the connect handshake
 * (create-connect-session / exchange-connect-code). No self-check, no
 * ping/visit history stored here — Cloudflare originates every check, this
 * plugin only displays a cached summary.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Site_Vigil_Connector {

    const API_BASE = 'https://phypjtigarnygtfvsseh.supabase.co/functions/v1';
    const APP_URL   = 'https://app.site-vigil.com';

    const TOKEN_OPTION      = 'site_vigil_plugin_token';
    const WEBSITE_ID_OPTION = 'site_vigil_website_id';
    const SUMMARY_TRANSIENT = 'site_vigil_summary_cache';
    const SUMMARY_TTL       = 300; // seconds — matches the spec's 5-minute client-side cache.

    public static function init() {
        add_action( 'admin_post_site_vigil_connect_callback', [ __CLASS__, 'handle_redirect_callback' ] );
        add_action( 'admin_post_site_vigil_connect_manual', [ __CLASS__, 'handle_manual_code' ] );
        add_action( 'admin_post_site_vigil_disconnect', [ __CLASS__, 'handle_disconnect' ] );
        add_action( 'admin_post_site_vigil_refresh_summary', [ __CLASS__, 'handle_refresh' ] );
    }

    public static function is_connected() {
        return (bool) get_option( self::TOKEN_OPTION, '' );
    }

    private static function callback_url() {
        return admin_url( 'admin-post.php?action=site_vigil_connect_callback' );
    }

    public static function connect_url() {
        $params = [
            'return_url'     => self::callback_url(),
            'site_url'       => home_url( '/' ),
            'plugin'         => 'wordpress',
            'plugin_version' => SITE_VIGIL_VERSION,
        ];
        return self::APP_URL . '/connect?' . http_build_query( $params );
    }

    /** Redirect-flow callback — the dashboard's /connect page sends the browser here with ?code=. */
    public static function handle_redirect_callback() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Forbidden', 403 );
        }
        $code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
        self::exchange_and_store( $code );
        wp_safe_redirect( admin_url( 'options-general.php?page=site-vigil' ) );
        exit;
    }

    /** Manual pairing-code form submit — the "Already have a pairing code?" field. */
    public static function handle_manual_code() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Forbidden', 403 );
        }
        check_admin_referer( 'site_vigil_connect_manual' );
        $code = isset( $_POST['site_vigil_pairing_code'] ) ? sanitize_text_field( wp_unslash( $_POST['site_vigil_pairing_code'] ) ) : '';
        self::exchange_and_store( $code );
        wp_safe_redirect( admin_url( 'options-general.php?page=site-vigil' ) );
        exit;
    }

    private static function exchange_and_store( $code ) {
        $code = trim( $code );
        if ( '' === $code ) {
            set_transient( 'site_vigil_connect_error', 'No pairing code was provided.', 60 );
            return;
        }

        $response = wp_remote_post(
            self::API_BASE . '/exchange-connect-code',
            [
                'timeout' => 15,
                'headers' => [ 'Content-Type' => 'application/json' ],
                'body'    => wp_json_encode( [ 'code' => $code ] ),
            ]
        );

        if ( is_wp_error( $response ) ) {
            set_transient( 'site_vigil_connect_error', 'Could not reach Site Vigil: ' . $response->get_error_message(), 60 );
            return;
        }

        $status = wp_remote_retrieve_response_code( $response );
        $body   = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 !== $status || empty( $body['plugin_token'] ) ) {
            $message = isset( $body['error'] ) ? $body['error'] : 'Connection code expired or invalid — generate a new one and try again.';
            set_transient( 'site_vigil_connect_error', $message, 60 );
            return;
        }

        update_option( self::TOKEN_OPTION, $body['plugin_token'] );
        update_option( self::WEBSITE_ID_OPTION, $body['website_id'] );
        delete_transient( self::SUMMARY_TRANSIENT );
    }

    /**
     * Local-only: forgets the stored token. The authoritative server-side
     * revoke is the dashboard's own Disconnect action (Admin → Site
     * Management) — this plugin holds a plugin_token, not a user session, so
     * it can't call admin-actions itself.
     */
    public static function handle_disconnect() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Forbidden', 403 );
        }
        check_admin_referer( 'site_vigil_disconnect' );
        delete_option( self::TOKEN_OPTION );
        delete_option( self::WEBSITE_ID_OPTION );
        delete_transient( self::SUMMARY_TRANSIENT );
        wp_safe_redirect( admin_url( 'options-general.php?page=site-vigil' ) );
        exit;
    }

    public static function handle_refresh() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Forbidden', 403 );
        }
        check_admin_referer( 'site_vigil_refresh_summary' );
        delete_transient( self::SUMMARY_TRANSIENT );
        wp_safe_redirect( admin_url( 'options-general.php?page=site-vigil' ) );
        exit;
    }

    private static function fetch_summary() {
        $token = get_option( self::TOKEN_OPTION, '' );
        if ( ! $token ) {
            return null;
        }

        $cached = get_transient( self::SUMMARY_TRANSIENT );
        if ( false !== $cached ) {
            return $cached;
        }

        $response = wp_remote_post(
            self::API_BASE . '/get-site-summary',
            [
                'timeout' => 15,
                'headers' => [ 'Authorization' => 'Bearer ' . $token ],
            ]
        );

        if ( is_wp_error( $response ) ) {
            return [ 'error' => 'Could not reach Site Vigil: ' . $response->get_error_message() ];
        }

        $status = wp_remote_retrieve_response_code( $response );
        $body   = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 401 === $status || 404 === $status ) {
            // Token revoked, or the site was deleted from the dashboard —
            // forget the stale connection rather than keep showing it as live.
            delete_option( self::TOKEN_OPTION );
            delete_option( self::WEBSITE_ID_OPTION );
            delete_transient( self::SUMMARY_TRANSIENT );
            return [ 'disconnected' => true ];
        }

        if ( 429 === $status ) {
            // Rate limited — show whatever was last cached rather than an error.
            return false !== $cached ? $cached : [ 'error' => 'Rate limited — try refreshing again shortly.' ];
        }

        if ( 200 !== $status ) {
            return [ 'error' => isset( $body['error'] ) ? $body['error'] : 'Unexpected error fetching summary.' ];
        }

        set_transient( self::SUMMARY_TRANSIENT, $body, self::SUMMARY_TTL );
        return $body;
    }

    private static function deep_link_url() {
        $website_id = get_option( self::WEBSITE_ID_OPTION, '' );
        // v1 fallback per spec §4 — no SSO magic-link precedent exists
        // anywhere in the dashboard yet, so this defers to a plain login redirect.
        return self::APP_URL . '/login?redirect=' . rawurlencode( '/site/' . $website_id );
    }

    public static function render_settings_section() {
        $error = get_transient( 'site_vigil_connect_error' );
        if ( $error ) {
            delete_transient( 'site_vigil_connect_error' );
        }
        ?>
        <hr />
        <h2>Site Vigil Connector</h2>
        <?php if ( $error ) : ?>
            <div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
        <?php endif; ?>

        <?php if ( self::is_connected() ) : ?>
            <?php self::render_widget(); ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px;">
                <?php wp_nonce_field( 'site_vigil_disconnect' ); ?>
                <input type="hidden" name="action" value="site_vigil_disconnect" />
                <?php submit_button( 'Disconnect', 'secondary', 'submit', false ); ?>
            </form>
        <?php else : ?>
            <p>Connect this site to your Site Vigil dashboard to see live status here.</p>
            <p><a href="<?php echo esc_url( self::connect_url() ); ?>" class="button button-primary">Connect automatically</a></p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'site_vigil_connect_manual' ); ?>
                <input type="hidden" name="action" value="site_vigil_connect_manual" />
                <p>
                    <label for="site_vigil_pairing_code"><strong>Already have a pairing code?</strong></label><br />
                    <input type="text" id="site_vigil_pairing_code" name="site_vigil_pairing_code" class="regular-text" placeholder="e.g. 8F3K-QZ2P" />
                    <?php submit_button( 'Connect with code', 'secondary', 'submit', false ); ?>
                </p>
            </form>
        <?php endif; ?>
        <?php
    }

    private static function render_widget() {
        $summary = self::fetch_summary();
        if ( ! $summary ) {
            return;
        }

        if ( ! empty( $summary['disconnected'] ) ) {
            echo '<div class="notice notice-warning"><p>Disconnected — reconnect below.</p></div>';
            return;
        }
        if ( ! empty( $summary['error'] ) ) {
            echo '<div class="notice notice-error"><p>' . esc_html( $summary['error'] ) . '</p></div>';
            return;
        }

        $status_colors = [ 'ONLINE' => '#31b898', 'DEGRADED' => '#e0a800', 'OFFLINE' => '#dc3545' ];
        $status        = isset( $summary['status'] ) ? $summary['status'] : 'UNKNOWN';
        $color         = isset( $status_colors[ $status ] ) ? $status_colors[ $status ] : '#71717a';
        ?>
        <table class="widefat" style="max-width:520px;">
            <tbody>
                <tr>
                    <td><strong>Status</strong></td>
                    <td><span style="color: <?php echo esc_attr( $color ); ?>; font-weight:bold;"><?php echo esc_html( $status ); ?></span></td>
                </tr>
                <tr><td><strong>Uptime (30d)</strong></td><td><?php echo esc_html( $summary['uptime_pct_30d'] ?? '—' ); ?>%</td></tr>
                <tr>
                    <td><strong>Last incident</strong></td>
                    <td>
                        <?php if ( ! empty( $summary['last_incident'] ) ) : ?>
                            <?php echo esc_html( $summary['last_incident']['type'] ); ?> — <?php echo esc_html( $summary['last_incident']['duration_minutes'] ); ?> min
                            (<?php echo esc_html( human_time_diff( strtotime( $summary['last_incident']['started_at'] ), current_time( 'timestamp' ) ) ); ?> ago)
                        <?php else : ?>
                            None in the last 30 days
                        <?php endif; ?>
                    </td>
                </tr>
                <tr><td><strong>SSL expires in</strong></td><td><?php echo isset( $summary['ssl_days_remaining'] ) && null !== $summary['ssl_days_remaining'] ? esc_html( $summary['ssl_days_remaining'] ) . ' days' : '—'; ?></td></tr>
                <tr><td><strong>Domain expires in</strong></td><td><?php echo isset( $summary['domain_days_remaining'] ) && null !== $summary['domain_days_remaining'] ? esc_html( $summary['domain_days_remaining'] ) . ' days' : '—'; ?></td></tr>
                <tr><td><strong>Sessions today</strong></td><td><?php echo esc_html( $summary['sessions_today'] ?? 0 ); ?></td></tr>
                <tr><td><strong>Active now</strong></td><td><?php echo esc_html( $summary['active_now'] ?? 0 ); ?></td></tr>
            </tbody>
        </table>
        <p style="margin-top:8px;">
            <a href="<?php echo esc_url( self::deep_link_url() ); ?>" class="button" target="_blank" rel="noopener noreferrer">View full details on Site Vigil</a>
            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=site_vigil_refresh_summary' ), 'site_vigil_refresh_summary' ) ); ?>" class="button">Refresh</a>
        </p>
        <?php if ( ! empty( $summary['generated_at'] ) ) : ?>
            <p style="color:#71717a;font-size:12px;">Updated <?php echo esc_html( human_time_diff( strtotime( $summary['generated_at'] ), current_time( 'timestamp' ) ) ); ?> ago.</p>
        <?php endif; ?>
        <?php
    }
}
