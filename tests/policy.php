<?php
require __DIR__ . '/../source/calico-canonical-redirects/includes/class-ccr-policy.php';
$count = 0;
function check( $actual, $expected, $label ) {
    global $count;
    ++$count;
    if ( $actual !== $expected ) { fwrite( STDERR, "FAIL: $label\nExpected " . var_export( $expected, true ) . "\nGot " . var_export( $actual, true ) . "\n" ); exit( 1 ); }
    echo "PASS: $label\n";
}
function decide( $changes = array(), $home = 'https://example.com', $trusted = array(), $excluded = false ) {
    return CCR_Policy::decide( $home, array_merge( array( 'HTTP_HOST' => 'www.example.com', 'REQUEST_URI' => '/menu/?utm_source=meta&gclid=A%2BB&x=1&x=2', 'REQUEST_METHOD' => 'GET', 'HTTPS' => 'off' ), $changes ), $trusted, $excluded )['target'];
}
$target = 'https://example.com/menu/?utm_source=meta&gclid=A%2BB&x=1&x=2';
check( decide(), $target, 'HTTP www becomes preferred HTTPS with exact tracking' );
check( decide( array( 'HTTP_HOST' => 'example.com' ) ), $target, 'HTTP canonical host upgrades' );
check( decide( array( 'HTTPS' => 'on' ) ), $target, 'HTTPS www normalizes host' );
check( decide( array( 'HTTP_HOST' => 'example.com', 'HTTPS' => 'on' ) ), '', 'Canonical HTTPS does not loop' );
check( decide( array( 'REQUEST_METHOD' => 'HEAD' ) ), $target, 'HEAD redirects' );
foreach ( array( 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS' ) as $method ) { check( decide( array( 'REQUEST_METHOD' => $method ) ), '', "$method preserved" ); }
check( decide( array(), 'http://example.com' ), '', 'Insecure home setting blocks redirect' );
check( decide( array( 'HTTP_HOST' => 'example.com' ), 'https://www.example.com' ), str_replace( 'https://example.com', 'https://www.example.com', $target ), 'Preferred www supported' );
foreach ( array( 'evil.com', 'example.com.evil.com', 'example.com@evil.com', 'www.example.com:81', 'example.com.' ) as $bad ) { check( decide( array( 'HTTP_HOST' => $bad ) ), '', 'Reject host: ' . $bad ); }
check( decide( array( 'HTTP_HOST' => 'WWW.EXAMPLE.COM:80' ) ), $target, 'Case and default port normalized' );
check( decide( array( 'HTTP_HOST' => 'example.com:443', 'HTTPS' => 'on' ) ), $target, 'Explicit HTTPS port removed once' );
foreach ( array( '//evil.com/path', '/\\evil.com', "/a\r\nLocation: https://evil.com", '/a%0d%0aLocation:x', '/a%5cb', '/a#fragment', 'https://evil.com', '/a b', '' ) as $bad ) { check( decide( array( 'REQUEST_URI' => $bad ) ), '', 'Unsafe URI rejected: ' . json_encode( $bad ) ); }
check( decide( array( 'REQUEST_URI' => '/caf%C3%A9/a%20b?redirect=https%3A%2F%2Fevil.com' ) ), 'https://example.com/caf%C3%A9/a%20b?redirect=https%3A%2F%2Fevil.com', 'Encoded paths and URL query retained under fixed host' );
check( decide( array( 'REQUEST_URI' => '/wp/menu/' ), 'https://example.com/wp' ), 'https://example.com/wp/menu/', 'Subdirectory not duplicated' );
check( decide( array( 'REQUEST_URI' => '/wp-other/' ), 'https://example.com/wp' ), '', 'Sibling installation excluded' );
check( decide( array(), 'https://example.com:8443' ), '', 'Nonstandard target port blocked' );
check( decide( array(), 'https://user:pass@example.com' ), '', 'Credentials in home blocked' );
check( decide( array(), 'https://127.0.0.1' ), '', 'IP target blocked' );
check( decide( array(), 'https://localhost' ), '', 'Single label target blocked' );
check( decide( array(), 'https://example.com', array(), true ), '', 'Excluded WordPress request skipped' );
$proxy = array( 'HTTP_HOST' => 'example.com', 'REMOTE_ADDR' => '192.0.2.10', 'HTTP_X_FORWARDED_PROTO' => 'https' );
check( decide( $proxy ), $target, 'Untrusted forwarded HTTPS ignored' );
check( decide( $proxy, 'https://example.com', array( '192.0.2.10' ) ), '', 'Trusted proxy prevents HTTPS loop' );
$proxy['HTTP_X_FORWARDED_PROTO'] = 'https,http';
check( decide( $proxy, 'https://example.com', array( '192.0.2.10' ) ), $target, 'Ambiguous forwarded list ignored' );
check( decide( array( 'HTTP_HOST' => 'example.com', 'SERVER_PORT' => '443' ) ), '', 'Native HTTPS port detected' );
check( decide( array( 'HTTP_HOST' => 'example.com', 'HTTPS' => '1' ) ), '', 'Native HTTPS numeric flag detected' );
check( decide( array( 'HTTP_HOST' => 'example.com', 'REMOTE_ADDR' => '2001:db8::1', 'HTTP_X_FORWARDED_PROTO' => 'https' ), 'https://example.com', array( '2001:db8::1' ) ), '', 'Trusted IPv6 proxy supported' );
echo "Policy: $count checks passed.\n";
