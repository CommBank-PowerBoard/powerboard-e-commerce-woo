<?php
declare( strict_types=1 );

namespace PowerBoard\Helpers;

class PaymentMethodHelper {
	public static function get_payment_method( string $payment_method ): string {
		switch ( $payment_method ) {
			case 'card':
				return 'Card';
			case 'afterpay_checkout':
				return 'Afterpay';
			case 'zip_checkout':
				return 'Zip';
			case 'applepay_wallet':
				return 'Apple Pay';
			case 'googlepay_wallet':
				return 'Google Pay';
			case 'paypal_wallet':
				return 'PayPal';
			default:
				return $payment_method;
		}
	}

	public static function invoke_gateway_method( string $method_name ): void {
		/* @noinspection PhpUndefinedFunctionInspection */
		$payment_gateways = WC()->payment_gateways->get_available_payment_gateways();

		if ( isset( $payment_gateways[ POWER_BOARD_PLUGIN_PREFIX ] ) ) {
			$gateway = $payment_gateways[ POWER_BOARD_PLUGIN_PREFIX ];

			if ( method_exists( $gateway, $method_name ) ) {
				$gateway->$method_name();
			} else {
				/* @noinspection PhpUndefinedFunctionInspection */
				wp_send_json_error( [ 'message' => sprintf( 'PowerBoard gateway does not support %s', $method_name ) ] );
			}
		} else {
			/* @noinspection PhpUndefinedFunctionInspection */
			wp_send_json_error( [ 'message' => 'PowerBoard gateway not available' ] );
		}
	}
}
