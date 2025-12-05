<?php

declare( strict_types=1 );

namespace PowerBoard\Helpers\Util;

use PowerBoard\API\ConfigService;
use PowerBoard\Enums\ConfigAPIEnum;
use PowerBoard\Services\PaymentGateway\MasterWidgetPaymentService;

class PaymentGatewayHelper {

	public static function get_payment_gateway(): ?MasterWidgetPaymentService {
		$payment_gateways = WC()->payment_gateways->get_available_payment_gateways();

		if ( isset( $payment_gateways[ POWER_BOARD_PLUGIN_PREFIX ] ) ) {
			return $payment_gateways[ POWER_BOARD_PLUGIN_PREFIX ];
		} else {
			wp_send_json_error( [ 'message' => 'PowerBoard gateway not available' ] );
			return null;
		}
	}


	/**
	 * Check for https requests
	 *
	 * @return bool
	 */
	public static function is_https(): bool {

		if ( is_ssl() ) {
			return true;
		}

		$protocols = [ self::ssl_reverse_proxy(), self::ssl_proxy() ];

		foreach ( $protocols as $protocol ) {
			// Handle boolean values directly
			if ( is_bool( $protocol ) ) {
				return $protocol;
			}

			// Handle string values after sanitization
			if ( $protocol !== null ) {
				if ( strtolower( (string) $protocol ) === 'on' ) {
					return true;
				}

				if ( strtolower( (string) $protocol ) === 'https' ) {
					return true;
				}

				if ( (string) $protocol === '1' ) {
					return true;
				}

				if ( is_numeric( $protocol ) && (int) $protocol === 1 ) {
					return true;
				}
			}
		}

		return false;
	}

	private static function ssl_reverse_proxy() {
		return isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ?
			filter_var(
				wp_unslash( $_SERVER['HTTP_X_FORWARDED_PROTO'] ),
			FILTER_SANITIZE_FULL_SPECIAL_CHARS
			)
			: null;
	}

	private static function ssl_proxy() {
		return isset( $_SERVER['HTTP_X_FORWARDED_SSL'] ) ?
			filter_var(
				wp_unslash( $_SERVER['HTTP_X_FORWARDED_SSL'] ),
			FILTER_SANITIZE_FULL_SPECIAL_CHARS
			)
			: null;
	}
}
