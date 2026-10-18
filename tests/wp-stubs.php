<?php
// Test-only WordPress boundary; never packaged or installed.
define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['hooks'] = array();
$GLOBALS['errors'] = array();
$GLOBALS['settings'] = array( 'mode' => 'preview', 'tls' => true, 'proxies' => '' );
$GLOBALS['home'] = 'https://example.com';
function get_option( $key, $default = false ) { return 'home' === $key ? $GLOBALS['home'] : $GLOBALS['settings']; }
function wp_parse_args( $a, $b ) { return array_merge( $b, $a ); }
function wp_parse_url( $url, $part = -1 ) { return parse_url( $url, $part ); }
function is_multisite() { return $GLOBALS['multisite'] ?? false; }
function is_admin() { return $GLOBALS['admin'] ?? false; }
function wp_doing_ajax() { return false; }
function wp_doing_cron() { return false; }
function is_preview() { return $GLOBALS['preview'] ?? false; }
function is_customize_preview() { return false; }
function add_action( $hook, $fn, $priority = 10 ) { $GLOBALS['hooks'][$hook][] = $fn; }
function add_filter( $hook, $fn ) { $GLOBALS['filters'][$hook][] = $fn; }
function remove_filter( $hook, $fn ) { unset( $GLOBALS['filters'][$hook] ); }
function add_settings_error( $key, $code, $message ) { $GLOBALS['errors'][] = $message; }
function nocache_headers() { header( 'Cache-Control: no-cache, must-revalidate, max-age=0, no-store, private' ); }
function wp_safe_redirect( $target, $status, $by ) {
    $allowed = array();
    foreach ( $GLOBALS['filters']['allowed_redirect_hosts'] ?? array() as $fn ) { $allowed = $fn( $allowed ); }
    if ( ! in_array( parse_url( $target, PHP_URL_HOST ), $allowed, true ) ) { throw new Exception( 'Unapproved host' ); }
    header( 'Location: ' . $target, true, $status ); header( 'X-Redirect-By: ' . $by ); return true;
}
function current_user_can( $cap ) { return $GLOBALS['can_manage'] ?? true; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_textarea( $s ) { return esc_html( $s ); }
function settings_errors() {}
function settings_fields( $s ) { echo '<input type="hidden" name="_wpnonce" value="test-only">'; }
function submit_button() { echo '<button>Save Changes</button>'; }
function selected( $a, $b ) { if ( $a === $b ) { echo 'selected'; } }
function checked( $a ) { if ( $a ) { echo 'checked'; } }
require __DIR__ . '/../source/calico-canonical-redirects/calico-canonical-redirects.php';
