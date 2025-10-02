<?php
declare( strict_types=1 );

namespace PowerBoard\Helpers\Util;

use PowerBoard\Enums\ConfigAPIEnum;

class PaymentNotificationHelper {
	protected const DOMAIN_NAMES = [
		ConfigAPIEnum::PRODUCTION_API_URL,
		ConfigAPIEnum::SANDBOX_API_URL,
		ConfigAPIEnum::STAGING_API_URL,
	];

	public static function is_valid_domain_name( $http_host ) {
		// Check if the host includes a port
		if ( strpos( $http_host, ':' ) !== false ) {
			// If it includes a port, remove it
			$domain_name = wp_parse_url( 'http://' . $http_host, PHP_URL_HOST );
		} else {
			// Otherwise, use the host as is
			$domain_name = $http_host;
		}

		return in_array( $domain_name, self::DOMAIN_NAMES, true );
	}
}
