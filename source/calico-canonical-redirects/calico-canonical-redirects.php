<?php
/**
 * Plugin Name: Calico Canonical Redirects
 * Description: Preview, test, and enable HTTPS and preferred-domain redirects while preserving paths and tracking queries.
 * Version: 1.1.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: Calico Design
 * Author URI: https://calicodesign.co/
 * Plugin URI: https://github.com/CalicoDesignCo/calico-canonical-redirects
 * Update URI: https://github.com/CalicoDesignCo/calico-canonical-redirects
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: calico-canonical-redirects
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
require_once __DIR__ . '/includes/class-ccr-policy.php';
require_once __DIR__ . '/includes/class-ccr-updater.php';
CCR_Updater::register( __FILE__ );

final class Calico_Canonical_Redirects {
    const OPTION = 'calico_canonical_redirects';

    public static function settings() {
        return wp_parse_args( get_option( self::OPTION, array() ), array( 'mode' => 'preview', 'proxies' => '', 'tls' => false ) );
    }

    public static function proxies( $settings ) {
        return array_values( array_filter( preg_split( '/[\s,]+/', trim( $settings['proxies'] ) ), static function ( $ip ) { return (bool) filter_var( $ip, FILTER_VALIDATE_IP ); } ) );
    }

    public static function active( $settings ) {
        return ! ( defined( 'CALICO_CANONICAL_REDIRECTS_DISABLED' ) && CALICO_CANONICAL_REDIRECTS_DISABLED ) && ! is_multisite() && ! empty( $settings['tls'] ) && in_array( $settings['mode'], array( 'test', 'live' ), true );
    }

    public static function proxy_scheme() {
        $settings = self::settings();
        if ( self::active( $settings ) && CCR_Policy::secure( $_SERVER, self::proxies( $settings ) ) ) {
            // Keep WordPress's own canonical redirect from undoing trusted HTTPS detection.
            $_SERVER['HTTPS'] = 'on';
        }
    }

    public static function excluded() {
        return is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) || is_preview() || is_customize_preview();
    }

    public static function redirect() {
        $settings = self::settings();
        if ( ! self::active( $settings ) || headers_sent() ) { return; }
        $decision = CCR_Policy::decide( get_option( 'home' ), $_SERVER, self::proxies( $settings ), self::excluded() );
        if ( ! $decision['target'] ) { return; }
        nocache_headers();
        // Target is built exclusively from the saved home domain; allow that exact host.
        $allow = static function ( $hosts ) { $hosts[] = wp_parse_url( get_option( 'home' ), PHP_URL_HOST ); return $hosts; };
        add_filter( 'allowed_redirect_hosts', $allow );
        $sent = wp_safe_redirect( $decision['target'], 'live' === $settings['mode'] ? 301 : 302, 'Calico Canonical Redirects' );
        remove_filter( 'allowed_redirect_hosts', $allow );
        if ( $sent ) { exit; }
    }

    public static function sanitize( $input ) {
        $input = is_array( $input ) ? $input : array();
        $mode = in_array( $input['mode'] ?? '', array( 'preview', 'test', 'live' ), true ) ? $input['mode'] : 'preview';
        $ips = preg_split( '/[\s,]+/', trim( (string) ( $input['proxies'] ?? '' ) ) );
        $valid = array();
        foreach ( $ips as $ip ) {
            if ( '' === $ip ) { continue; }
            if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                add_settings_error( self::OPTION, 'proxy', 'Invalid proxy IP. Settings reverted to Preview; enter exact IP addresses, not CIDR ranges.' );
                $mode = 'preview';
            } else { $valid[] = $ip; }
        }
        $tls = ! empty( $input['tls'] );
        $check = CCR_Policy::decide( get_option( 'home' ), array( 'HTTP_HOST' => wp_parse_url( get_option( 'home' ), PHP_URL_HOST ), 'REQUEST_URI' => ( wp_parse_url( get_option( 'home' ), PHP_URL_PATH ) ?: '/' ), 'REQUEST_METHOD' => 'GET' ) );
        if ( 'preview' !== $mode && ( ! $tls || is_multisite() || ! $check['target'] ) ) {
            $mode = 'preview';
            add_settings_error( self::OPTION, 'blocked', 'Kept in Preview: confirm certificates, use a standard HTTPS Website Address, and use a single-site installation.' );
        }
        return array( 'mode' => $mode, 'proxies' => implode( "\n", array_unique( $valid ) ), 'tls' => $tls );
    }

    public static function register() {
        register_setting( 'ccr', self::OPTION, array( 'type' => 'array', 'sanitize_callback' => array( __CLASS__, 'sanitize' ) ) );
    }

    public static function menu() {
        add_options_page( 'Calico Canonical Redirects', 'Canonical Redirects', 'manage_options', 'calico-canonical-redirects', array( __CLASS__, 'page' ) );
    }

    public static function page() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $settings = self::settings();
        $home = get_option( 'home' );
        $host = wp_parse_url( $home, PHP_URL_HOST );
        $alternate = 0 === strpos( (string) $host, 'www.' ) ? substr( $host, 4 ) : 'www.' . $host;
        $base = rtrim( wp_parse_url( $home, PHP_URL_PATH ) ?? '', '/' );
        ?>
        <div class="wrap"><h1>Calico Canonical Redirects</h1>
        <?php settings_errors(); ?>
        <p>Preferred website: <strong><?php echo esc_html( $home ); ?></strong> (Settings → General → Site Address). HTTPS is required. Both www and non-www preferences are supported.</p>
        <p>Start in Preview, test with temporary 302 redirects, then select permanent 301 redirects after checking the results. Preview does not issue redirects from this plugin; hosting and other plugins can still redirect.</p>
        <?php if ( is_multisite() ) { echo '<div class="notice notice-warning inline"><p>Multisite is unsupported; redirects are disabled.</p></div>'; } ?>
        <?php if ( defined( 'CALICO_CANONICAL_REDIRECTS_DISABLED' ) && CALICO_CANONICAL_REDIRECTS_DISABLED ) { echo '<div class="notice notice-warning inline"><p>The emergency disable constant is active.</p></div>'; } ?>
        <form method="post" action="options.php">
        <?php settings_fields( 'ccr' ); ?>
        <table class="form-table" role="presentation"><tr><th scope="row"><label for="ccr-mode">Mode</label></th><td><select id="ccr-mode" name="<?php echo esc_attr( self::OPTION ); ?>[mode]">
        <?php foreach ( array( 'preview' => 'Preview — no redirects', 'test' => 'Test — temporary 302', 'live' => 'Live — permanent 301' ) as $value => $label ) { ?>
        <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['mode'], $value ); ?>><?php echo esc_html( $label ); ?></option>
        <?php } ?></select><p class="description">Test responses use no-cache headers. Browsers and CDNs may retain permanent redirects after you disable them.</p></td></tr>
        <tr><th scope="row"><label for="ccr-proxies">Trusted proxy IPs</label></th><td><textarea id="ccr-proxies" name="<?php echo esc_attr( self::OPTION ); ?>[proxies]" rows="4" cols="45"><?php echo esc_textarea( $settings['proxies'] ); ?></textarea><p class="description">Leave blank for direct HTTPS. If TLS terminates at a proxy, obtain its immediate connection IPs from your host. One exact IPv4/IPv6 address per line. Only these peers may supply X-Forwarded-Proto: https. CIDR ranges and forwarded lists are unsupported. Configure the proxy to overwrite this header.</p></td></tr>
        <tr><th scope="row">Certificate check</th><td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[tls]" value="1" <?php checked( $settings['tls'] ); ?>> I verified HTTPS certificates for both domain versions and checked the hosting/proxy HTTPS setup.</label><p class="description">This is your confirmation, not an automated SSL check. Use Full (strict) TLS for Cloudflare. Flexible TLS can cause loops.</p></td></tr></table>
        <?php submit_button(); ?></form>
        <h2>Redirect simulator</h2><p>Uses saved settings and the same policy as live redirects. These are simulated requests, not network or certificate tests. The proposed target is shown even in Preview.</p>
        <table class="widefat striped"><thead><tr><th>Simulated request</th><th>Proposed result</th></tr></thead><tbody>
        <?php foreach ( array( false, true ) as $https ) { foreach ( array( $host, $alternate ) as $candidate ) {
            $uri = $base . '/?utm_source=calico-test&gclid=example%2B123';
            $server = array( 'HTTP_HOST' => $candidate, 'REQUEST_URI' => $uri, 'REQUEST_METHOD' => 'GET', 'HTTPS' => $https ? 'on' : 'off' );
            $decision = CCR_Policy::decide( $home, $server ); ?>
            <tr><td><code><?php echo esc_html( ( $https ? 'https://' : 'http://' ) . $candidate . $uri ); ?></code></td><td><?php echo esc_html( $decision['target'] ?: $decision['reason'] ); ?></td></tr>
        <?php } } ?></tbody></table>
        <h2>Current connection</h2><p>Immediate peer IP: <code><?php echo esc_html( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ); ?></code>. Detected HTTPS: <strong><?php echo CCR_Policy::secure( $_SERVER, self::proxies( $settings ) ) ? 'yes' : 'no'; ?></strong>. This observation alone does not verify your proxy configuration.</p>
        <h2>Recovery and coverage</h2><p>Change Mode to Preview, deactivate the plugin, or add <code>define('CALICO_CANONICAL_REDIRECTS_DISABLED', true);</code> to wp-config.php before WordPress loads. Clear hosting/CDN caches after changes.</p>
        <p>Handles front-end GET/HEAD requests reaching WordPress. Login, admin, REST, AJAX, cron, XML-RPC, previews, CLI, POST requests, and static files are outside scope. Existing server rules and WordPress redirects may still apply. No .htaccess, site URLs, SEO tags, or page content are changed.</p>
        </div>
        <?php
    }
}
add_action( 'plugins_loaded', array( 'Calico_Canonical_Redirects', 'proxy_scheme' ), 0 );
add_action( 'template_redirect', array( 'Calico_Canonical_Redirects', 'redirect' ), 0 );
add_action( 'admin_init', array( 'Calico_Canonical_Redirects', 'register' ) );
add_action( 'admin_menu', array( 'Calico_Canonical_Redirects', 'menu' ) );
