<?php
declare( strict_types=1 );

namespace PowerBoard\Enums\AvailablePaymentMethods;

class PBAvailablePaymentMethodsEnum {
	public const CARD_KEY       = 'cards';
	public const CARD           = [
		self::CARD_KEY => [
			'nice_name' => 'Cards',
			'image'     => 'cards.svg',
		],
	];
	public const AFTERPAY_KEY   = 'afterpay';
	public const AFTERPAY       = [
		self::AFTERPAY_KEY => [
			'nice_name' => 'Afterpay',
			'image'     => 'afterpay.png',
		],
	];
	public const APPLE_PAY_KEY  = 'apple-pay';
	public const APPLE_PAY      = [
		self::APPLE_PAY_KEY => [
			'nice_name' => 'Apple Pay',
			'image'     => 'apple-pay.svg',
		],
	];
	public const GOOGLE_PAY_KEY = 'google-pay';
	public const GOOGLE_PAY     = [
		self::GOOGLE_PAY_KEY => [
			'nice_name' => 'Google Pay',
			'image'     => 'google-pay.svg',
		],
	];
	public const PAYPAL_KEY     = 'paypal';
	public const PAYPAL         = [
		self::PAYPAL_KEY => [
			'nice_name' => 'PayPal',
			'image'     => 'paypal.svg',
		],
	];
	public const ZIP_KEY        = 'zip';
	public const ZIP            = [
		self::ZIP_KEY => [
			'nice_name' => 'Zip',
			'image'     => 'zip.svg',
		],
	];

	public const PAYMENT_METHODS = [
		self::CARD_KEY,
		self::AFTERPAY_KEY,
		self::APPLE_PAY_KEY,
		self::GOOGLE_PAY_KEY,
		self::PAYPAL_KEY,
		self::ZIP_KEY,
	];

	public static function get_available_payment_method( $api_event ): ?string {
		if ( in_array( $api_event, APIAvailablePaymentMethodsEnum::PAYMENT_METHODS, true ) ) {
			$available_events = [
				APIAvailablePaymentMethodsEnum::CARD      => self::CARD_KEY,
				APIAvailablePaymentMethodsEnum::AFTERPAY  => self::AFTERPAY_KEY,
				APIAvailablePaymentMethodsEnum::APPLEPAY  => self::APPLE_PAY_KEY,
				APIAvailablePaymentMethodsEnum::GOOGLEPAY => self::GOOGLE_PAY_KEY,
				APIAvailablePaymentMethodsEnum::PAYPAL    => self::PAYPAL_KEY,
				APIAvailablePaymentMethodsEnum::ZIP       => self::ZIP_KEY,
			];

			return $available_events[ $api_event ];
		}

		return null;
	}
}
