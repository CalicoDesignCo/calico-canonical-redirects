<?php
require __DIR__ . '/wp-stubs.php';
$GLOBALS['cache'] = false;
$GLOBALS['response'] = array( 'code' => 200, 'body' => '' );
$GLOBALS['requests'] = 0;
function get_site_transient( $key ) { return $GLOBALS['cache']; }
function set_site_transient( $key, $value, $ttl ) { $GLOBALS['cache'] = $value; $GLOBALS['ttl'] = $ttl; }
function delete_site_transient( $key ) { $GLOBALS['cache'] = false; }
function wp_safe_remote_get( $url, $args ) { ++$GLOBALS['requests']; $GLOBALS['request_args'] = $args; return $GLOBALS['response']; }
function is_wp_error( $response ) { return false === $response; }
function wp_remote_retrieve_response_code( $response ) { return $response['code']; }
function wp_remote_retrieve_body( $response ) { return $response['body']; }
function esc_url( $s ) { return htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' ); }
$count = 0;
function check( $ok, $label ) { global $count; ++$count; if ( ! $ok ) { fwrite( STDERR, "FAIL: $label\n" ); exit( 1 ); } echo "PASS: $label\n"; }
$release = array( 'id' => 1, 'tag_name' => 'v1.1.0', 'draft' => false, 'prerelease' => false, 'assets' => array( array( 'name' => 'calico-canonical-redirects.zip', 'state' => 'uploaded', 'size' => 123, 'browser_download_url' => CCR_Updater::REPO . '/releases/download/v1.1.0/calico-canonical-redirects.zip' ) ) );
check( CCR_Updater::parse( $release )['version'] === '1.1.0', 'Stable release version parsed' );
foreach ( array( 'draft', 'prerelease' ) as $flag ) { $bad = $release; $bad[$flag] = true; check( false === CCR_Updater::parse( $bad ), "$flag excluded" ); }
foreach ( array( 'v1.1.0-rc1', 'main', 'v1.1' ) as $tag ) { $bad = $release; $bad['tag_name'] = $tag; check( false === CCR_Updater::parse( $bad ), "Nonstable tag excluded: $tag" ); }
$bad = $release; $bad['assets'] = array(); check( false === CCR_Updater::parse( $bad ), 'Missing installable asset excluded' );
$bad = $release; $bad['assets'][0]['browser_download_url'] = 'https://evil.example/package.zip'; check( false === CCR_Updater::parse( $bad ), 'Foreign asset URL rejected' );
$bad = $release; $bad['assets'][0]['browser_download_url'] = CCR_Updater::REPO . '/releases/latest/download/calico-canonical-redirects.zip'; check( false === CCR_Updater::parse( $bad ), 'Unpinned latest URL rejected' );
$bad = $release; $bad['assets'][0]['size'] = 0; check( false === CCR_Updater::parse( $bad ), 'Empty asset excluded' );
$GLOBALS['response']['body'] = json_encode( $release );
$plugin = 'calico-canonical-redirects/calico-canonical-redirects.php';
$result = CCR_Updater::update( false, array( 'Version' => '1.0.0' ), $plugin, array() );
check( '1.1.0' === $result['version'] && false === strpos( $result['package'], '/latest/' ), 'Older installation gets pinned update' );
check( false === CCR_Updater::update( false, array( 'Version' => '1.1.0' ), $plugin, array() ), 'Equal version not offered' );
check( false === CCR_Updater::update( false, array( 'Version' => '2.0.0' ), $plugin, array() ), 'Downgrade not offered' );
check( 'untouched' === CCR_Updater::update( 'untouched', array( 'Version' => '0' ), 'other/other.php', array() ), 'Other GitHub plugins untouched' );
check( 1 === $GLOBALS['requests'] && 21600 === $GLOBALS['ttl'], 'Successful metadata cached' );
check( true === $GLOBALS['request_args']['sslverify'] && 8 === $GLOBALS['request_args']['timeout'], 'TLS and bounded timeout retained' );
check( ! isset( $GLOBALS['request_args']['headers']['Authorization'] ), 'No account token shipped' );
check( CCR_Updater::details( false, 'plugin_information', (object) array( 'slug' => CCR_Updater::SLUG ) )->version === '1.1.0', 'WordPress details dialog populated' );
check( 'original' === CCR_Updater::details( 'original', 'plugin_information', (object) array( 'slug' => 'other' ) ), 'Unrelated details untouched' );
foreach ( array( array( 'code' => 403, 'body' => '{}' ), array( 'code' => 200, 'body' => '{invalid' ), false ) as $response ) {
    $GLOBALS['cache'] = false; $GLOBALS['response'] = $response;
    check( false === CCR_Updater::release() && 900 === $GLOBALS['ttl'], 'API failure safely negatively cached' );
}
$_GET['force-check'] = 1; CCR_Updater::refresh(); check( false === $GLOBALS['cache'], 'Check again invalidates updater cache' );
echo "Updater: $count checks passed.\n";
