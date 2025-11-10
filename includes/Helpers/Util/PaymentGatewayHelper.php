<?php

declare( strict_types=1 );

namespace PowerBoard\Helpers\Util;

use PowerBoard\API\ConfigService;
use PowerBoard\Enums\ConfigAPIEnum;
use PowerBoard\Services\PaymentGateway\MasterWidgetPaymentService;

class PaymentGatewayHelper {

	public static function get_payment_gateway(): ?MasterWidgetPaymentService {
		$payment_gateways = WC()->payment_gateways->get_available_payment_gateways();

		if ( isset( $payment_gateways[POWER_BOARD_PLUGIN_PREFIX] ) ) {
			return $payment_gateways[POWER_BOARD_PLUGIN_PREFIX];
		} else {
			wp_send_json_error( [ 'message' => 'PowerBoard gateway not available' ] );
			return null;
		}
	}


	/**
	 * Standardize url from request into https://something.something
	 *
	 * @param string $http_host
	 * @return array|false|int|mixed|string|null
	 */
	private static function standardize_url( $http_host ) {

		if ( !is_string( $http_host ) || empty( $http_host ) ) {
			return null;
		}

		$http_host = str_replace( 'http://', '', $http_host );
		$http_host = str_replace( 'https://', '', $http_host );
		$http_host = wp_parse_url( 'https://' . $http_host, PHP_URL_HOST );

		return $http_host;
	}


	/**
	 * Check domain validity
	 *
	 * @param string $http_host
	 * @return bool
	 */
	public static function is_valid_domain_name( $http_host ): bool {

		$http_host = self::standardize_url( $http_host );

		if ( empty( $http_host ) ) {
			return false;
		}

		return str_ends_with( $http_host, ConfigAPIEnum::DOMAIN_API );
	}

	/**
	 * Check for https requests
	 *
	 * @return bool
	 */
	public static function is_https(): bool {
		// Safely get and sanitize the HTTPS server variable
		$https = isset( $_SERVER['HTTPS'] ) ? filter_var( $_SERVER['HTTPS'],
			FILTER_SANITIZE_FULL_SPECIAL_CHARS ) : null;

		// Handle boolean values directly
		if ( is_bool( $https ) ) {
			return $https;
		}

		// Handle string values after sanitization
		if ( $https !== null ) {
			// Check for 'on' (case-insensitive)
			if ( strtolower( (string) $https ) === 'on' ) {
				return true;
			}

			// Check for '1'
			if ( (string) $https === '1' ) {
				return true;
			}

			// Check for integer 1
			if ( is_numeric( $https ) && (int) $https === 1 ) {
				return true;
			}
		}

		return false;
	}
}
