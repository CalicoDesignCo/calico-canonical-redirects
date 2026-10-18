<?php
require __DIR__ . '/wp-stubs.php';
// Fixture controls exist only in this isolated loopback test server.
$GLOBALS['settings']['mode'] = $_SERVER['HTTP_X_FIXTURE_MODE'] ?? 'preview';
if ( isset( $_SERVER['HTTP_X_FIXTURE_HTTPS'] ) ) { $_SERVER['HTTPS'] = 'on'; }
if ( isset( $_SERVER['HTTP_X_FIXTURE_PROXY'] ) ) { $GLOBALS['settings']['proxies'] = '127.0.0.1'; }
if ( isset( $_SERVER['HTTP_X_FIXTURE_DISABLED'] ) ) { define( 'CALICO_CANONICAL_REDIRECTS_DISABLED', true ); }
if ( isset( $_SERVER['HTTP_X_FIXTURE_ADMIN'] ) ) { $GLOBALS['admin'] = true; }
if ( isset( $_SERVER['HTTP_X_FIXTURE_REST'] ) ) { define( 'REST_REQUEST', true ); }
Calico_Canonical_Redirects::proxy_scheme();
Calico_Canonical_Redirects::redirect();
header( 'Content-Type: text/plain' );
echo 'No Calico redirect';
