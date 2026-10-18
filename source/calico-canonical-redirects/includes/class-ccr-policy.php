<?php
/** Pure redirect policy, shared by runtime and simulator. */
final class CCR_Policy {
    public static function secure( array $server, array $trusted ) {
        if ( ( isset( $server['HTTPS'] ) && in_array( strtolower( (string) $server['HTTPS'] ), array( 'on', '1' ), true ) ) || ( isset( $server['SERVER_PORT'] ) && '443' === (string) $server['SERVER_PORT'] ) ) {
            return true;
        }
        // Only an explicitly trusted immediate peer may assert the external scheme.
        return in_array( $server['REMOTE_ADDR'] ?? '', $trusted, true ) && 'https' === strtolower( trim( $server['HTTP_X_FORWARDED_PROTO'] ?? '' ) );
    }

    public static function decide( $home, array $server, array $trusted = array(), $excluded = false ) {
        $skip = static function ( $reason ) { return array( 'target' => '', 'reason' => $reason ); };
        $parts = parse_url( $home );
        if ( ! is_array( $parts ) || 'https' !== ( $parts['scheme'] ?? '' ) || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) || ( isset( $parts['port'] ) && 443 !== $parts['port'] ) ) {
            return $skip( 'Website Address must be a standard HTTPS URL.' );
        }
        $host = strtolower( $parts['host'] );
        if ( ! filter_var( $host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME ) || false === strpos( $host, '.' ) || filter_var( $host, FILTER_VALIDATE_IP ) ) {
            return $skip( 'A public domain name is required.' );
        }
        if ( $excluded || ! in_array( $server['REQUEST_METHOD'] ?? 'GET', array( 'GET', 'HEAD' ), true ) ) {
            return $skip( 'Excluded request or non-GET/HEAD method.' );
        }
        $authority = strtolower( $server['HTTP_HOST'] ?? '' );
        if ( ! preg_match( '/^([a-z0-9.-]+)(?::(80|443))?$/D', $authority, $match ) ) {
            return $skip( 'Invalid request host.' );
        }
        $request_host = $match[1];
        $alternate = 0 === strpos( $host, 'www.' ) ? substr( $host, 4 ) : 'www.' . $host;
        if ( ! in_array( $request_host, array( $host, $alternate ), true ) ) {
            return $skip( 'Unrelated host; no redirect.' );
        }
        $uri = $server['REQUEST_URI'] ?? '/';
        // Reject ambiguous or header-injection input instead of silently rewriting it.
        if ( ! is_string( $uri ) || ! preg_match( '~^/(?!/)~', $uri ) || preg_match( '/[\x00-\x20\x7f\\\\#]/', $uri ) || preg_match( '/%(?:0[ad]|5c)/i', $uri ) ) {
            return $skip( 'Invalid or unsafe request path.' );
        }
        $base = rtrim( $parts['path'] ?? '', '/' );
        $path = explode( '?', $uri, 2 )[0];
        if ( '' !== $base && $path !== $base && 0 !== strpos( $path, $base . '/' ) ) {
            return $skip( 'Outside this WordPress installation path.' );
        }
        if ( self::secure( $server, $trusted ) && $request_host === $host && ! isset( $match[2] ) ) {
            return $skip( 'Already canonical; no redirect.' );
        }
        return array( 'target' => 'https://' . $host . $uri, 'reason' => 'Normalize HTTPS and preferred domain.' );
    }
}
