<?php
declare( strict_types=1 );

namespace PowerBoard\Enums\AvailablePaymentMethods;

class APIAvailablePaymentMethodsEnum {
	public const CARD      = 'card';
	public const AFTERPAY  = 'afterpay_checkout';
	public const APPLEPAY  = 'applepay_wallet';
	public const GOOGLEPAY = 'googlepay_wallet';
	public const PAYPAL    = 'paypal_wallet';
	public const ZIP       = 'zip_checkout';

	public const PAYMENT_METHODS = [
		self::CARD,
		self::AFTERPAY,
		self::APPLEPAY,
		self::GOOGLEPAY,
		self::PAYPAL,
		self::ZIP,
	];
}
