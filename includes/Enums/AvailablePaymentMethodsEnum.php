<?php
declare( strict_types=1 );

namespace PowerBoard\Enums;

class AvailablePaymentMethodsEnum {
	public const CARD       = [ 'cards' => 'Card' ];
	public const AFTERPAY   = [ 'afterpay' => 'Afterpay' ];
	public const APPLE_PAY  = [ 'apple-pay' => 'Apple Pay' ];
	public const GOOGLE_PAY = [ 'google-pay' => 'Google Pay' ];
	public const PAYPAL     = [ 'paypal' => 'PayPal' ];
	public const ZIP        = [ 'zip' => 'Zip' ];
}
