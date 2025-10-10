<?php
declare( strict_types=1 );

namespace PowerBoard\Helpers\Util;

use PowerBoard\API\ConfigService;
use PowerBoard\Services\PaymentGateway\MasterWidgetPaymentService;

class PaymentGatewayHelper {
	/**
	 * Most common Country Code top-level domains
	 *
	 * @var array
	 */
	private const COUNTRY_CODE_TLD = [
		'uk',
		'au',
		'ca',
		'us',
		'de',
		'fr',
		'jp',
		'in',
		'cn',
		'ru',
		'br',
		'nl',
		'it',
		'es',
		'pl',
		'mx',
		'co',
		'tr',
		'se',
		'no',
		'dk',
		'at',
		'be',
		'ch',
		'ie',
		'pt',
		'nz',
		'za',
		'sg',
		'ph',
		'id',
		'my',
		'kr',
		'th',
		'vn',
		'tw',
		'hk',
		'mo',
		'il',
		'ae',
		'sa',
		'qa',
	];

	public static function get_payment_gateway(): ?MasterWidgetPaymentService {
		$payment_gateways = WC()->payment_gateways->get_available_payment_gateways();

		if ( isset( $payment_gateways[ POWER_BOARD_PLUGIN_PREFIX ] ) ) {
			return $payment_gateways[ POWER_BOARD_PLUGIN_PREFIX ];
		} else {
			wp_send_json_error( [ 'message' => 'PowerBoard gateway not available' ] );
			return null;
		}
	}

	public static function is_valid_domain_name( $http_host ): bool {

		if ( !is_string( $http_host ) ) {
			return false;
		}

		// Check if the host includes a port
			$http_host = str_replace( 'http://', '', $http_host );
			$http_host = str_replace( 'https://', '', $http_host );

			// If it includes a port, remove it
			$domain_name = wp_parse_url( 'http://' . $http_host, PHP_URL_HOST );

		// Remove subdomains and keep only the main domain
		// This extracts the main domain by keeping only the last two parts
		if ( !empty( $domain_name ) ) {
			$domain_name = self::resolve_subdomain_url( $domain_name );
		}

		$config_domain = ConfigService::get_domain();

		if ( !is_string( $config_domain ) ) {
			return false;
		}

		$config_domain = str_replace( 'http://', '', $config_domain );
		$config_domain = str_replace( 'https://', '', $config_domain );

		$config_domain = wp_parse_url( 'http://' . $config_domain, PHP_URL_HOST );

		// Also remove subdomains from the config domain for comparison
		if ( !empty( $config_domain ) ) {
			$config_domain = self::resolve_subdomain_url( $config_domain );
		}

		if ( empty( $domain_name ) || empty( $config_domain ) ) {
			return false;
		}

		return preg_replace( '/\s+/', '', $domain_name ) === preg_replace( '/\s+/', '', $config_domain );
	}

	private static function resolve_subdomain_url( $url ) {
		$parts = explode( '.', $url );
		$count = count( $parts );

		if ( $count > 2 ) {
			$last = $parts[ $count - 1 ];
			// If last label is a common country-code Top-Level Domain (e.g., uk, au, jp), include 3 labels to handle Second-Level Domains
			if ( in_array( $last, self::COUNTRY_CODE_TLD, true ) && $count >= 3 ) {
				return $parts[ $count - 3 ] . '.' . $parts[ $count - 2 ] . '.' . $parts[ $count - 1 ];
			}

			return $parts[ $count - 2 ] . '.' . $parts[ $count - 1 ];
		}

		return $parts[0] . '.' . $parts[1];
	}
}
