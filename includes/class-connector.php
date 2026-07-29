<?php
/**
 * CMS Plugin Connector v2 — authenticated, scoped, read-only client of the
 * get-site-summary Edge Function, reached via the connect handshake
 * (create-connect-session / exchange-connect-code). No self-check, no
 * ping/visit history stored here — Cloudflare originates every check, this
 * plugin only displays a cached summary.
 *
 * Settings-screen markup/styling follows site-vigil-wp-settings-v2.html
 * (the shared connector-contract mockup) — keep in lockstep with the Joomla
 * port when either changes.
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

    /**
     * "Connect manually" form submit (spec §3a) — a single pairing-code
     * field. The Tracking ID is never typed by hand: exchange_and_store()
     * populates it from the bundled exchange-connect-code response, so a
     * separate Tracking ID input would just be a value the user watches get
     * silently overwritten.
     */
    public static function handle_manual_code() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Forbidden', 403 );
        }
        check_admin_referer( 'site_vigil_connect_manual' );

        $code = isset( $_POST['site_vigil_pairing_code'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['site_vigil_pairing_code'] ) ) ) : '';
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
        // Bundled response (spec §3/§3a) — this is the only place the
        // Tracking ID gets set from the manual flow; there's no UI field for
        // it, so nothing here can conflict with a manually typed value.
        if ( ! empty( $body['tracking_id'] ) ) {
            update_option( SITE_VIGIL_OPTION_KEY, sanitize_text_field( $body['tracking_id'] ) );
        }
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

    // ---- small inline icon set, mirrors the mockup's line-icon style ----
    private static function icon( $name ) {
        $icons = [
            'shield'    => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
            'connect'   => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6"/><path d="M10 14 21 3"/></svg>',
            'chev'      => '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M9 6l6 6-6 6"/></svg>',
            'warn'      => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M12 9v4M12 17h.01M10.3 3.9 2 18a2 2 0 0 0 1.7 3h16.6a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>',
            'active'    => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><circle cx="12" cy="12" r="3"/><circle cx="12" cy="12" r="9"/></svg>',
            'sessions'  => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><circle cx="9" cy="7" r="3.2"/><path d="M2.5 20c0-3.5 3-6 6.5-6s6.5 2.5 6.5 6"/></svg>',
            'uptime'    => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M12 20a8 8 0 1 0 0-16 8 8 0 0 0 0 16z"/><path d="M12 8v4l3 3"/></svg>',
            'domain'    => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>',
            'ssl'       => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M12 22s8-4.5 8-11V5l-8-3-8 3v6c0 6.5 8 11 8 11z"/></svg>',
            'response'  => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M13 2 3 14h8l-1 8 10-12h-8l1-8z"/></svg>',
            'check'     => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>',
            'arrow'     => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>',
        ];
        return isset( $icons[ $name ] ) ? $icons[ $name ] : '';
    }

    public static function render_settings_section() {
        $error = get_transient( 'site_vigil_connect_error' );
        if ( $error ) {
            delete_transient( 'site_vigil_connect_error' );
        }
        self::print_styles();
        $summary = self::is_connected() ? self::fetch_summary() : null;
        $show_connect_hero = ! self::is_connected() || ! empty( $summary['disconnected'] );
        ?>
        <div id="site-vigil-connector" class="svc-card">
            <div class="svc-brandbar">
                <div class="svc-brandmark">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 4 5v6c0 5 3.4 8.7 8 10 4.6-1.3 8-5 8-10V5l-8-3z"/><path d="m9 12 2 2 4-4"/></svg>
                </div>
                <span class="svc-brand-name">Site Vigil</span>
                <?php if ( self::is_connected() && ! $show_connect_hero ) : ?>
                    <span class="svc-brand-meta">Tracking ID: <span class="svc-mono"><?php echo esc_html( get_option( SITE_VIGIL_OPTION_KEY, '' ) ); ?></span></span>
                <?php endif; ?>
            </div>

            <div class="svc-body">
                <?php if ( $error ) : ?>
                    <div class="notice notice-error inline"><p><?php echo esc_html( $error ); ?></p></div>
                <?php endif; ?>

                <?php if ( $show_connect_hero ) : ?>
                    <?php if ( ! empty( $summary['disconnected'] ) ) : ?>
                        <div class="notice notice-warning inline"><p>Disconnected from Site Vigil — the connection was reset. Reconnect below.</p></div>
                    <?php endif; ?>
                    <?php self::render_connect_hero(); ?>
                <?php else : ?>
                    <?php self::render_widget( $summary ); ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private static function render_connect_hero() {
        ?>
        <div class="svc-connect-hero">
            <h2>Let's connect this site</h2>
            <p>Link this site to your Site Vigil dashboard to see uptime, SSL, domain, and visitor stats right here in wp&#8209;admin. One step sets up tracking and monitoring together — no separate tracking ID to copy.</p>
            <p class="svc-connect-primary">
                <a href="<?php echo esc_url( self::connect_url() ); ?>" class="button button-primary button-hero svc-btn-icon" target="_blank" rel="noopener noreferrer">
                    <?php echo self::icon( 'connect' ); ?> Connect automatically
                </a>
            </p>
            <div class="svc-trust-line"><?php echo self::icon( 'shield' ); ?> No password required — you'll approve this from your Site Vigil dashboard.</div>

            <details class="svc-manual">
                <summary><?php echo self::icon( 'chev' ); ?> Connect manually</summary>

                <div class="svc-fallback-note">
                    <?php echo self::icon( 'warn' ); ?>
                    <div>Get these from Site Vigil (Sites → this site → "Connect manually") — you're already logged in there.</div>
                </div>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'site_vigil_connect_manual' ); ?>
                    <input type="hidden" name="action" value="site_vigil_connect_manual" />

                    <div class="svc-field-row">
                        <label for="site_vigil_pairing_code">Pairing code</label>
                        <input type="text" id="site_vigil_pairing_code" name="site_vigil_pairing_code" class="svc-mono" placeholder="XXXX-XXXX" />
                    </div>
                    <div class="svc-fallback-actions">
                        <?php submit_button( 'Save', 'secondary', 'submit', false ); ?>
                    </div>
                    <p class="svc-pairing-hint">Your Tracking ID is set automatically from this — no need to copy it separately.</p>
                </form>
            </details>
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
            #site-vigil-connector{
                --sv-teal:#298E7A; --sv-teal-dark:#1F6E5E;
                --sv-success:#16A34A; --sv-success-tint:#EDFBF2;
                --sv-danger:#DC2626; --sv-danger-tint:#FDF0F0;
                --sv-warning:#D97706; --sv-warning-tint:#FEF6E9;
                --svc-border:#dcdcde; --svc-ink:#1d2327; --svc-muted:#6b7280;
                background:#fff; border:1px solid var(--svc-border); border-radius:10px;
                max-width:660px; overflow:hidden; box-shadow:0 1px 2px rgba(0,0,0,.03);
            }
            #site-vigil-connector .svc-mono{ font-family:ui-monospace,SFMono-Regular,Consolas,"Liberation Mono",monospace; }
            #site-vigil-connector .svc-brandbar{ display:flex; align-items:center; gap:10px; padding:14px 20px; border-bottom:1px solid var(--svc-border); }
            #site-vigil-connector .svc-brandmark{ width:26px; height:26px; border-radius:7px; background:linear-gradient(135deg,var(--sv-teal),var(--sv-teal-dark)); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
            #site-vigil-connector .svc-brand-name{ font-weight:600; font-size:15px; letter-spacing:-.01em; color:var(--svc-ink); }
            #site-vigil-connector .svc-brand-meta{ margin-left:auto; font-size:12.5px; color:var(--svc-muted); }
            #site-vigil-connector .svc-body{ padding:20px; }

            #site-vigil-connector .svc-connect-hero h2{ font-size:17px; margin:0 0 6px; font-weight:600; letter-spacing:-.01em; color:var(--svc-ink); }
            #site-vigil-connector .svc-connect-hero p{ font-size:13.5px; color:var(--svc-muted); line-height:1.55; margin:0 0 18px; max-width:52ch; }
            #site-vigil-connector .svc-connect-primary{ margin:0 0 12px; }
            #site-vigil-connector .svc-btn-icon{ display:inline-flex; align-items:center; gap:7px; }
            #site-vigil-connector .svc-trust-line{ display:flex; align-items:center; gap:7px; font-size:12px; color:#8b93a1; margin-top:6px; }
            #site-vigil-connector .svc-manual{ margin-top:18px; padding-top:16px; border-top:1px solid #eee; }
            #site-vigil-connector .svc-manual summary{ cursor:pointer; font-size:13px; color:var(--sv-teal); font-weight:600; list-style:none; display:flex; align-items:center; gap:6px; }
            #site-vigil-connector .svc-manual summary::-webkit-details-marker{ display:none; }
            #site-vigil-connector .svc-manual summary svg{ transition:.15s; }
            #site-vigil-connector .svc-manual[open] summary svg{ transform:rotate(90deg); }
            #site-vigil-connector .svc-fallback-note{ display:flex; gap:8px; align-items:flex-start; background:var(--sv-warning-tint); border:1px solid #f3ddb2; border-radius:7px; padding:10px 12px; margin-top:12px; font-size:12px; color:#7a5205; line-height:1.5; }
            #site-vigil-connector .svc-fallback-note svg{ flex-shrink:0; margin-top:1px; }
            #site-vigil-connector .svc-field-row{ margin-top:10px; }
            #site-vigil-connector .svc-field-row label{ display:block; font-size:11.5px; font-weight:600; color:var(--svc-muted); text-transform:uppercase; letter-spacing:.03em; margin-bottom:5px; }
            #site-vigil-connector .svc-field-row input{ font-size:13px; border:1px solid var(--svc-border); border-radius:7px; padding:9px 12px; width:100%; max-width:320px; }
            #site-vigil-connector .svc-fallback-actions{ margin-top:12px; }
            #site-vigil-connector .svc-pairing-hint{ font-size:11.5px; color:#9aa0a8; margin-top:8px; line-height:1.5; }

            #site-vigil-connector .svc-status-row{ display:flex; align-items:center; gap:12px; margin-bottom:4px; }
            #site-vigil-connector .svc-pulse-dot{ position:relative; width:10px; height:10px; border-radius:50%; flex-shrink:0; }
            #site-vigil-connector .svc-pulse-dot::after{ content:''; position:absolute; inset:-6px; border-radius:50%; opacity:.35; animation:svcPulse 2.2s ease-out infinite; }
            #site-vigil-connector .svc-pulse-dot.svc-online{ background:var(--sv-success); }
            #site-vigil-connector .svc-pulse-dot.svc-online::after{ background:var(--sv-success); }
            #site-vigil-connector .svc-pulse-dot.svc-degraded{ background:var(--sv-warning); }
            #site-vigil-connector .svc-pulse-dot.svc-degraded::after{ background:var(--sv-warning); }
            #site-vigil-connector .svc-pulse-dot.svc-down{ background:var(--sv-danger); }
            #site-vigil-connector .svc-pulse-dot.svc-down::after{ background:var(--sv-danger); animation-duration:1.1s; }
            @keyframes svcPulse{ 0%{ transform:scale(.6); opacity:.5; } 100%{ transform:scale(1.9); opacity:0; } }
            #site-vigil-connector .svc-status-text{ font-weight:600; font-size:15px; }
            #site-vigil-connector .svc-status-text.svc-online{ color:#0f7a44; }
            #site-vigil-connector .svc-status-text.svc-degraded{ color:#8a5a06; }
            #site-vigil-connector .svc-status-text.svc-down{ color:var(--sv-danger); }
            #site-vigil-connector .svc-status-meta{ margin-left:auto; font-size:12.5px; color:var(--svc-muted); }
            #site-vigil-connector .svc-lede{ font-size:13.5px; color:var(--svc-muted); margin:2px 0 16px; line-height:1.5; }

            #site-vigil-connector .svc-alert-banner{ display:flex; gap:10px; align-items:flex-start; background:var(--sv-danger-tint); border:1px solid #f6cccc; border-radius:8px; padding:12px 14px; margin-bottom:16px; font-size:13px; color:#7a1f1f; line-height:1.5; }
            #site-vigil-connector .svc-alert-banner svg{ flex-shrink:0; margin-top:1px; }
            #site-vigil-connector .svc-alert-banner b{ color:#5c1414; }

            #site-vigil-connector .svc-stats{ display:grid; grid-template-columns:repeat(3,1fr); gap:1px; background:var(--svc-border); border:1px solid var(--svc-border); border-radius:8px; overflow:hidden; margin-bottom:16px; }
            #site-vigil-connector .svc-stat{ background:#fff; padding:12px 13px; }
            #site-vigil-connector .svc-stat__label{ display:flex; align-items:center; gap:5px; font-size:10.5px; font-weight:600; text-transform:uppercase; letter-spacing:.04em; color:#8b93a1; margin-bottom:6px; }
            #site-vigil-connector .svc-stat__label svg{ opacity:.75; }
            #site-vigil-connector .svc-stat__value{ font-family:ui-monospace,SFMono-Regular,Consolas,monospace; font-weight:600; font-size:16px; color:var(--svc-ink); }
            #site-vigil-connector .svc-stat__value--muted{ color:#b8bec7; font-size:14px; }
            #site-vigil-connector .svc-stat__value--warn{ color:var(--sv-warning); }
            #site-vigil-connector .svc-stat__unit{ font-size:11px; font-weight:500; color:#8b93a1; }

            #site-vigil-connector .svc-incident-line{ display:flex; align-items:center; gap:8px; font-size:13px; color:var(--svc-muted); padding:11px 13px; background:#fafafb; border:1px solid var(--svc-border); border-radius:8px; margin-bottom:16px; }
            #site-vigil-connector .svc-incident-line b{ color:var(--svc-ink); font-weight:600; }
            #site-vigil-connector .svc-incident-line svg{ flex-shrink:0; color:var(--sv-success); }
            #site-vigil-connector .svc-incident-line.svc-none svg{ color:#9aa0a8; }

            #site-vigil-connector .svc-actions{ display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
            #site-vigil-connector .svc-btn-danger{ background:var(--sv-danger); border-color:var(--sv-danger); }
            #site-vigil-connector .svc-btn-danger:hover{ background:#b91c1c; border-color:#b91c1c; }
            #site-vigil-connector .svc-danger-link{ color:#b23a3a; margin-left:auto; }
            #site-vigil-connector .svc-danger-link:hover{ color:var(--sv-danger); }
            #site-vigil-connector .svc-confirm{ margin-top:16px; padding:12px 14px; border:1px solid #d63638; background:#fcf0f1; border-radius:6px; }
            #site-vigil-connector .svc-confirm p{ margin:0 0 10px; font-size:13px; color:#1d2327; }
            #site-vigil-connector .svc-confirm__actions{ display:flex; gap:8px; }
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

    private static function render_widget( $summary ) {
        if ( ! $summary || ! empty( $summary['error'] ) ) {
            $message = $summary['error'] ?? 'Unexpected error fetching summary.';
            echo '<div class="notice notice-error inline"><p>' . esc_html( $message ) . '</p></div>';
            return;
        }

        $status_meta = [
            'ONLINE'   => [ 'label' => 'Online',   'class' => 'svc-online' ],
            'DEGRADED' => [ 'label' => 'Degraded', 'class' => 'svc-degraded' ],
            'OFFLINE'  => [ 'label' => 'Down',      'class' => 'svc-down' ],
        ];
        $status = isset( $summary['status'] ) ? $summary['status'] : 'ONLINE';
        $meta   = isset( $status_meta[ $status ] ) ? $status_meta[ $status ] : [ 'label' => ucfirst( strtolower( $status ) ), 'class' => 'svc-online' ];

        $last_incident = $summary['last_incident'] ?? null;
        ?>
        <div class="svc-status-row">
            <span class="svc-pulse-dot <?php echo esc_attr( $meta['class'] ); ?>"></span>
            <span class="svc-status-text <?php echo esc_attr( $meta['class'] ); ?>"><?php echo esc_html( $meta['label'] ); ?></span>
            <span class="svc-status-meta">
                <?php if ( 'OFFLINE' === $status && $last_incident ) : ?>
                    Since <?php echo esc_html( human_time_diff( strtotime( $last_incident['started_at'] ), current_time( 'timestamp' ) ) ); ?> ago
                <?php elseif ( ! empty( $summary['generated_at'] ) ) : ?>
                    Checked <?php echo esc_html( human_time_diff( strtotime( $summary['generated_at'] ), current_time( 'timestamp' ) ) ); ?> ago
                <?php endif; ?>
            </span>
        </div>

        <?php if ( 'OFFLINE' === $status ) : ?>
            <p class="svc-lede">We're watching closely and will alert you the moment it recovers.</p>
            <div class="svc-alert-banner">
                <?php echo self::icon( 'warn' ); ?>
                <div><b>Confirmed before alerting</b> — this isn't a one&#8209;off blip. Site owners have been notified.</div>
            </div>
        <?php elseif ( 'DEGRADED' === $status ) : ?>
            <p class="svc-lede">Responding, but with some failed or slow checks recently.</p>
        <?php else : ?>
            <p class="svc-lede">Everything's healthy — monitored from Site Vigil's own regions.</p>
        <?php endif; ?>

        <div class="svc-stats">
            <div class="svc-stat">
                <span class="svc-stat__label"><?php echo self::icon( 'active' ); ?>Active now</span>
                <span class="svc-stat__value"><?php echo esc_html( $summary['active_now'] ?? 0 ); ?></span>
            </div>
            <div class="svc-stat">
                <span class="svc-stat__label"><?php echo self::icon( 'sessions' ); ?>Sessions today</span>
                <span class="svc-stat__value"><?php echo esc_html( $summary['sessions_today'] ?? 0 ); ?></span>
            </div>
            <div class="svc-stat">
                <span class="svc-stat__label"><?php echo self::icon( 'response' ); ?>Avg response</span>
                <?php if ( isset( $summary['avg_response_ms'] ) && null !== $summary['avg_response_ms'] ) : ?>
                    <span class="svc-stat__value"><?php echo esc_html( $summary['avg_response_ms'] ); ?><span class="svc-stat__unit">ms</span></span>
                <?php else : ?>
                    <span class="svc-stat__value svc-stat__value--muted">—</span>
                <?php endif; ?>
            </div>
            <div class="svc-stat">
                <span class="svc-stat__label"><?php echo self::icon( 'uptime' ); ?>Uptime (30d)</span>
                <span class="svc-stat__value <?php echo isset( $summary['uptime_pct_30d'] ) && $summary['uptime_pct_30d'] < 99 ? 'svc-stat__value--warn' : ''; ?>">
                    <?php echo isset( $summary['uptime_pct_30d'] ) ? esc_html( $summary['uptime_pct_30d'] ) . '%' : '—'; ?>
                </span>
            </div>
            <div class="svc-stat">
                <span class="svc-stat__label"><?php echo self::icon( 'domain' ); ?>Domain expires</span>
                <?php if ( isset( $summary['domain_days_remaining'] ) && null !== $summary['domain_days_remaining'] ) : ?>
                    <span class="svc-stat__value"><?php echo esc_html( $summary['domain_days_remaining'] ); ?> <span class="svc-stat__unit">days</span></span>
                <?php else : ?>
                    <span class="svc-stat__value svc-stat__value--muted">Not watched yet</span>
                <?php endif; ?>
            </div>
            <div class="svc-stat">
                <span class="svc-stat__label"><?php echo self::icon( 'ssl' ); ?>SSL certificate</span>
                <?php if ( isset( $summary['ssl_days_remaining'] ) && null !== $summary['ssl_days_remaining'] ) : ?>
                    <span class="svc-stat__value"><?php echo esc_html( $summary['ssl_days_remaining'] ); ?> <span class="svc-stat__unit">days</span></span>
                <?php else : ?>
                    <span class="svc-stat__value svc-stat__value--muted">Not watched yet</span>
                <?php endif; ?>
            </div>
        </div>

        <?php if ( 'OFFLINE' !== $status ) : ?>
        <div class="svc-incident-line <?php echo $last_incident ? '' : 'svc-none'; ?>">
            <?php echo self::icon( 'check' ); ?>
            <?php if ( $last_incident ) : ?>
                <?php $ended_at = strtotime( $last_incident['started_at'] ) + ( (int) $last_incident['duration_minutes'] * 60 ); ?>
                All clear for <b><?php echo esc_html( human_time_diff( $ended_at, current_time( 'timestamp' ) ) ); ?></b>
                — last issue was a <?php echo esc_html( $last_incident['duration_minutes'] ); ?>&#8209;minute outage.
            <?php else : ?>
                No incidents in the last 30 days.
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="svc-actions" id="svc-actions">
            <a href="<?php echo esc_url( self::deep_link_url() ); ?>" class="button button-primary<?php echo 'OFFLINE' === $status ? ' svc-btn-danger' : ''; ?>" target="_blank" rel="noopener noreferrer">
                <?php echo 'OFFLINE' === $status ? 'View incident' : 'View full dashboard'; ?> <?php echo self::icon( 'arrow' ); ?>
            </a>
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
                    <button type="submit" class="button button-primary">Disconnect</button>
                </form>
            </div>
        </div>
        <?php
    }
}
