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
}
