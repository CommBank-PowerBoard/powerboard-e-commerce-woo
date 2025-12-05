<?php
declare( strict_types=1 );

// Mock WordPress functions in PowerBoard\Helpers\Util namespace
namespace PowerBoard\Helpers\Util;

/**
 * Mock implementation of WordPress wp_parse_url function
 *
 * @param string $url The URL to parse
 * @param int $component The specific component to retrieve
 * @return mixed The parsed URL or component
 */
if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		$parsed = parse_url( $url );
		if ( $component === PHP_URL_HOST ) {
			return $parsed['host'] ?? null;
		}
		return $parsed;
	}
}
