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
        self::print_styles();
        ?>
        <div id="site-vigil-connector" class="postbox svc-card">
            <div class="postbox-header">
                <h2 class="hndle"><span>Site Vigil Connector</span></h2>
            </div>
            <div class="inside">
                <?php if ( $error ) : ?>
                    <div class="notice notice-error inline"><p><?php echo esc_html( $error ); ?></p></div>
                <?php endif; ?>

                <?php if ( self::is_connected() ) : ?>
                    <?php self::render_widget(); ?>
                <?php else : ?>
                    <p class="svc-lead">Connect this site to your Site Vigil dashboard to see live status here.</p>
                    <p>
                        <a href="<?php echo esc_url( self::connect_url() ); ?>" class="button button-primary button-hero" target="_blank" rel="noopener noreferrer">Connect automatically</a>
                    </p>
                    <details class="svc-manual">
                        <summary>Already have a pairing code?</summary>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="svc-manual__form">
                            <?php wp_nonce_field( 'site_vigil_connect_manual' ); ?>
                            <input type="hidden" name="action" value="site_vigil_connect_manual" />
                            <input type="text" id="site_vigil_pairing_code" name="site_vigil_pairing_code" class="regular-text" placeholder="e.g. 8F3K-QZ2P" />
                            <?php submit_button( 'Connect with code', 'secondary', 'submit', false ); ?>
                        </form>
                    </details>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private static function print_styles() {
        static $printed = false;
        if ( $printed ) {
            return;
        }
        $printed = true;
        ?>
        <style>
            #site-vigil-connector.svc-card { max-width: 640px; }
            #site-vigil-connector .svc-lead { font-size: 14px; color: #3c434a; margin-top: 0; }
            #site-vigil-connector .svc-manual { margin-top: 16px; border-top: 1px solid #dcdcde; padding-top: 12px; }
            #site-vigil-connector .svc-manual summary { cursor: pointer; font-weight: 600; color: #2271b1; }
            #site-vigil-connector .svc-manual summary:hover { color: #135e96; }
            #site-vigil-connector .svc-manual__form { display: flex; align-items: center; gap: 8px; margin-top: 10px; flex-wrap: wrap; }
            #site-vigil-connector .svc-head { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
            #site-vigil-connector .svc-badge { display: inline-flex; align-items: center; gap: 7px; font: 600 12px/1 -apple-system, sans-serif; letter-spacing: .04em; text-transform: uppercase; padding: 5px 11px; border-radius: 999px; }
            #site-vigil-connector .svc-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
            #site-vigil-connector .svc-updated { color: #787c82; font-size: 12px; }
            #site-vigil-connector .svc-stats { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; }
            #site-vigil-connector .svc-stat { background: #f6f7f7; border: 1px solid #e0e0e0; border-radius: 6px; padding: 10px 12px; }
            #site-vigil-connector .svc-stat--wide { grid-column: 1 / -1; }
            #site-vigil-connector .svc-stat__label { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .03em; color: #787c82; margin-bottom: 4px; }
            #site-vigil-connector .svc-stat__value { display: block; font-size: 16px; font-weight: 600; color: #1d2327; }
            #site-vigil-connector .svc-stat__value--small { font-size: 13px; font-weight: 500; }
            #site-vigil-connector .svc-actions { margin-top: 16px; display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
            #site-vigil-connector .svc-danger-link { color: #d63638; text-decoration: none; margin-left: auto; }
            #site-vigil-connector .svc-danger-link:hover { color: #b32d2e; text-decoration: underline; }
            #site-vigil-connector .svc-confirm { margin-top: 16px; padding: 12px 14px; border: 1px solid #d63638; background: #fcf0f1; border-radius: 6px; }
            #site-vigil-connector .svc-confirm p { margin: 0 0 10px; font-size: 13px; color: #1d2327; }
            #site-vigil-connector .svc-confirm__actions { display: flex; gap: 8px; }
            #site-vigil-connector .svc-confirm__danger { background: #d63638; border-color: #d63638; }
            #site-vigil-connector .svc-confirm__danger:hover { background: #b32d2e; border-color: #b32d2e; }
        </style>
        <script>
            function svcShowDisconnectConfirm() {
                document.getElementById( 'svc-actions' ).style.display = 'none';
                document.getElementById( 'svc-confirm' ).style.display = 'block';
            }
            function svcHideDisconnectConfirm() {
                document.getElementById( 'svc-confirm' ).style.display = 'none';
                document.getElementById( 'svc-actions' ).style.display = 'flex';
            }
        </script>
        <?php
    }

    private static function render_widget() {
        $summary = self::fetch_summary();
        if ( ! $summary ) {
            return;
        }

        if ( ! empty( $summary['disconnected'] ) ) {
            ?>
            <div class="notice notice-warning inline"><p>Disconnected from Site Vigil.</p></div>
            <p><a href="<?php echo esc_url( admin_url( 'options-general.php?page=site-vigil' ) ); ?>" class="button">Reload to reconnect</a></p>
            <?php
            return;
        }
        if ( ! empty( $summary['error'] ) ) {
            echo '<div class="notice notice-error inline"><p>' . esc_html( $summary['error'] ) . '</p></div>';
            return;
        }

        $status_meta = [
            'ONLINE'   => [ 'label' => 'Online',   'color' => '#10b981' ],
            'DEGRADED' => [ 'label' => 'Degraded', 'color' => '#f59e0b' ],
            'OFFLINE'  => [ 'label' => 'Offline',  'color' => '#f43f5e' ],
        ];
        $status = isset( $summary['status'] ) ? $summary['status'] : 'UNKNOWN';
        $meta   = isset( $status_meta[ $status ] ) ? $status_meta[ $status ] : [ 'label' => ucfirst( strtolower( $status ) ), 'color' => '#8c8f94' ];
        ?>
        <div class="svc-head">
            <span class="svc-badge" style="color:<?php echo esc_attr( $meta['color'] ); ?>;background:<?php echo esc_attr( $meta['color'] ); ?>1a;">
                <span class="svc-dot" style="background:<?php echo esc_attr( $meta['color'] ); ?>;"></span>
                <?php echo esc_html( $meta['label'] ); ?>
            </span>
            <?php if ( ! empty( $summary['generated_at'] ) ) : ?>
                <span class="svc-updated">Updated <?php echo esc_html( human_time_diff( strtotime( $summary['generated_at'] ), current_time( 'timestamp' ) ) ); ?> ago</span>
            <?php endif; ?>
        </div>

        <div class="svc-stats">
            <div class="svc-stat">
                <span class="svc-stat__label">Uptime (30d)</span>
                <span class="svc-stat__value"><?php echo esc_html( $summary['uptime_pct_30d'] ?? '—' ); ?>%</span>
            </div>
            <div class="svc-stat">
                <span class="svc-stat__label">SSL expires in</span>
                <span class="svc-stat__value"><?php echo isset( $summary['ssl_days_remaining'] ) && null !== $summary['ssl_days_remaining'] ? esc_html( $summary['ssl_days_remaining'] ) . ' days' : '—'; ?></span>
            </div>
            <div class="svc-stat">
                <span class="svc-stat__label">Domain expires in</span>
                <span class="svc-stat__value"><?php echo isset( $summary['domain_days_remaining'] ) && null !== $summary['domain_days_remaining'] ? esc_html( $summary['domain_days_remaining'] ) . ' days' : '—'; ?></span>
            </div>
            <div class="svc-stat">
                <span class="svc-stat__label">Sessions today</span>
                <span class="svc-stat__value"><?php echo esc_html( $summary['sessions_today'] ?? 0 ); ?></span>
            </div>
            <div class="svc-stat">
                <span class="svc-stat__label">Active now</span>
                <span class="svc-stat__value"><?php echo esc_html( $summary['active_now'] ?? 0 ); ?></span>
            </div>
            <div class="svc-stat svc-stat--wide">
                <span class="svc-stat__label">Last incident</span>
                <span class="svc-stat__value svc-stat__value--small">
                    <?php if ( ! empty( $summary['last_incident'] ) ) : ?>
                        <?php echo esc_html( $summary['last_incident']['type'] ); ?> — <?php echo esc_html( $summary['last_incident']['duration_minutes'] ); ?> min
                        (<?php echo esc_html( human_time_diff( strtotime( $summary['last_incident']['started_at'] ), current_time( 'timestamp' ) ) ); ?> ago)
                    <?php else : ?>
                        None in the last 30 days
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <div class="svc-actions" id="svc-actions">
            <a href="<?php echo esc_url( self::deep_link_url() ); ?>" class="button button-primary" target="_blank" rel="noopener noreferrer">View full details →</a>
            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=site_vigil_refresh_summary' ), 'site_vigil_refresh_summary' ) ); ?>" class="button">Refresh</a>
            <button type="button" class="button-link svc-danger-link" onclick="svcShowDisconnectConfirm();">Disconnect</button>
        </div>
        <div class="svc-confirm" id="svc-confirm" style="display:none;">
            <p>Disconnect this site from Site Vigil? It will stop reporting status here until reconnected with a new pairing code.</p>
            <div class="svc-confirm__actions">
                <button type="button" class="button" onclick="svcHideDisconnectConfirm();">Cancel</button>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
                    <?php wp_nonce_field( 'site_vigil_disconnect' ); ?>
                    <input type="hidden" name="action" value="site_vigil_disconnect" />
                    <button type="submit" class="button button-primary svc-confirm__danger">Disconnect</button>
                </form>
            </div>
        </div>
        <?php
    }
}
