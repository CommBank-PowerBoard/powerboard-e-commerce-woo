<?php
declare( strict_types=1 );

namespace PowerBoard\Enums;

class AvailablePaymentMethodsEnum {
	public const CARD       = [
		'cards' => [
			'nice_name' => 'Cards',
			'image'     => 'cards.svg',
		],
	];
	public const AFTERPAY   = [
		'afterpay' => [
			'nice_name' => 'Afterpay',
			'image'     => 'afterpay.png',
		],
	];
	public const APPLE_PAY  = [
		'apple-pay' => [
			'nice_name' => 'Apple Pay',
			'image'     => 'apple-pay.svg',
		],
	];
	public const GOOGLE_PAY = [
		'google-pay' => [
			'nice_name' => 'Google Pay',
			'image'     => 'google-pay.svg',
		],
	];
	public const PAYPAL     = [
		'paypal' => [
			'nice_name' => 'PayPal',
			'image'     => 'paypal.svg',
		],
	];
	public const ZIP        = [
		'zip' => [
			'nice_name' => 'Zip',
			'image'     => 'zip.svg',
		],
	];
}
