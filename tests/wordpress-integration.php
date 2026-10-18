<?php
// Run inside a disposable WordPress Playground, with release ZIPs at /artifacts.
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
$checks = array();
function verify_ccr( $condition, $label ) {
    global $checks;
    if ( ! $condition ) { throw new Exception( 'FAIL: ' . $label ); }
    $checks[] = $label;
}
wp_set_current_user( 1 );
$plugin = 'calico-canonical-redirects/calico-canonical-redirects.php';
deactivate_plugins( $plugin, true );
delete_option( 'calico_canonical_redirects' );
$installer = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
$installed = $installer->install( '/artifacts/calico-canonical-redirects.zip' );
verify_ccr( ! is_wp_error( $installed ) && true === $installed, 'Exact release ZIP installs on clean WordPress' );
$activation = activate_plugin( $plugin );
verify_ccr( ! is_wp_error( $activation ) && is_plugin_active( $plugin ), 'Plugin activates without errors' );
$data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin );
verify_ccr( '1.1.0' === $data['Version'], 'Installed version is 1.1.0' );
verify_ccr( 'preview' === Calico_Canonical_Redirects::settings()['mode'], 'Clean activation starts in Preview' );
verify_ccr( false !== has_filter( 'update_plugins_github.com', array( 'CCR_Updater', 'update' ) ), 'Native WordPress updater hook registered' );
update_option( 'home', 'https://example.com' );
update_option( Calico_Canonical_Redirects::OPTION, array( 'mode' => 'test', 'tls' => true, 'proxies' => '' ) );
verify_ccr( Calico_Canonical_Redirects::active( Calico_Canonical_Redirects::settings() ), 'Saved Test mode can be read by real WordPress' );
$policy = CCR_Policy::decide( 'https://example.com', array( 'HTTP_HOST' => 'www.example.com', 'REQUEST_URI' => '/menu/?gclid=A%2BB', 'REQUEST_METHOD' => 'GET' ) );
verify_ccr( 'https://example.com/menu/?gclid=A%2BB' === $policy['target'], 'Installed policy preserves target and query' );

// The first public release has no older public updater-enabled version. Use a
// local header-only older-version fixture, then exercise real WP update APIs.
$path = WP_PLUGIN_DIR . '/' . $plugin;
$new_source = file_get_contents( $path );
file_put_contents( $path, str_replace( 'Version: 1.1.0', 'Version: 1.0.9', $new_source ) );
wp_clean_plugins_cache( true );
$metadata = array( 'id' => 1, 'tag_name' => 'v1.1.0', 'draft' => false, 'prerelease' => false, 'assets' => array( array( 'name' => 'calico-canonical-redirects.zip', 'state' => 'uploaded', 'size' => filesize( '/artifacts/calico-canonical-redirects.zip' ), 'browser_download_url' => CCR_Updater::REPO . '/releases/download/v1.1.0/calico-canonical-redirects.zip' ) ) );
add_filter( 'pre_http_request', static function ( $pre, $args, $url ) use ( $metadata ) {
    if ( CCR_Updater::API === $url ) { return array( 'headers' => array(), 'body' => wp_json_encode( $metadata ), 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array() ); }
    if ( false !== strpos( $url, 'api.wordpress.org/plugins/update-check/' ) ) { return array( 'headers' => array(), 'body' => '{"plugins":{},"translations":[],"no_update":{}}', 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array() ); }
    return $pre;
}, 10, 3 );
delete_site_transient( CCR_Updater::CACHE );
wp_update_plugins();
$updates = get_site_transient( 'update_plugins' );
verify_ccr( isset( $updates->response[$plugin] ) && '1.1.0' === $updates->response[$plugin]->new_version, 'WordPress offers 1.1.0 to an isolated older-version fixture' );
$package = $updates->response[$plugin]->package;
verify_ccr( CCR_Updater::REPO . '/releases/download/v1.1.0/calico-canonical-redirects.zip' === $package, 'Offered package is version-pinned' );
// Substitute the exact built ZIP for network transport only, keeping the real
// Plugin_Upgrader flow and actual installed plugin/settings intact.
add_filter( 'upgrader_pre_download', static function ( $reply, $url ) use ( $package ) {
    if ( $url !== $package ) { return $reply; }
    $temp = wp_tempnam( 'ccr-package.zip' ); copy( '/artifacts/calico-canonical-redirects.zip', $temp ); return $temp;
}, 10, 2 );
$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
$result = $upgrader->bulk_upgrade( array( $plugin ) );
verify_ccr( is_array( $result ) && ! empty( $result[$plugin] ) && ! is_wp_error( $result[$plugin] ), 'Real WordPress Plugin_Upgrader completes the standard bulk/AJAX upgrade path' );
wp_clean_plugins_cache( true );
$after = get_plugin_data( $path );
verify_ccr( '1.1.0' === $after['Version'], 'Upgrade installs actual 1.1.0 files' );
verify_ccr( is_plugin_active( $plugin ), 'Plugin remains active after upgrade' );
verify_ccr( 'test' === get_option( Calico_Canonical_Redirects::OPTION )['mode'], 'Saved redirect settings survive upgrade' );
file_put_contents( '/verification/wordpress-results.json', wp_json_encode( array( 'wordpress' => $GLOBALS['wp_version'], 'php' => PHP_VERSION, 'checks' => $checks, 'transport' => 'GitHub metadata and package download substituted with release fixture and exact built ZIP; real WordPress install/update APIs' ), JSON_PRETTY_PRINT ) );
