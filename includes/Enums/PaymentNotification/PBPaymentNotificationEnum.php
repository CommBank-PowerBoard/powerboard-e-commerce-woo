<?php
declare( strict_types=1 );

namespace PowerBoard\Enums\PaymentNotification;

class PBPaymentNotificationEnum {
	public const PAYMENT_SUCCEEDED  = 'payment_succeeded';
	public const PAYMENT_FAILED     = 'payment_failed';
	public const PAYMENT_CREATED    = 'payment_created';
	public const PAYMENT_CAPTURED   = 'payment_captured';
	public const PAYMENT_VOIDED     = 'payment_voided';
	public const CHECKOUT_CREATED   = 'checkout_created';
	public const CHECKOUT_CANCELLED = 'checkout_cancelled';
	public const CHECKOUT_EXPIRED   = 'checkout_expired';
	public const CHECKOUT_FAILED    = 'checkout_failed';
	public const CHECKOUT_COMPLETED = 'checkout_completed';

	public const EVENTS = [
		self::PAYMENT_SUCCEEDED,
		self::PAYMENT_FAILED,
		self::PAYMENT_CREATED,
		self::PAYMENT_CAPTURED,
		self::PAYMENT_VOIDED,
		self::CHECKOUT_CREATED,
		self::CHECKOUT_CANCELLED,
		self::CHECKOUT_EXPIRED,
		self::CHECKOUT_FAILED,
		self::CHECKOUT_COMPLETED,
	];

	public const FAIL_EVENTS = [
		self::PAYMENT_FAILED,
		self::CHECKOUT_FAILED,
		self::CHECKOUT_CANCELLED,
	];

	public static function get_ipn_event( $api_event ): ?string {
		if ( in_array( $api_event, APIPaymentNotificationEnum::API_EVENTS, true ) ) {
			$available_events = [
				APIPaymentNotificationEnum::PAYMENT_SUCCEEDED => self::PAYMENT_SUCCEEDED,
				APIPaymentNotificationEnum::PAYMENT_FAILED => self::PAYMENT_FAILED,
				APIPaymentNotificationEnum::PAYMENT_CREATED => self::PAYMENT_CREATED,
				APIPaymentNotificationEnum::PAYMENT_CAPTURED => self::PAYMENT_CAPTURED,
				APIPaymentNotificationEnum::PAYMENT_VOIDED => self::PAYMENT_VOIDED,
				APIPaymentNotificationEnum::CHECKOUT_CREATED => self::CHECKOUT_CREATED,
				APIPaymentNotificationEnum::CHECKOUT_CANCELLED => self::CHECKOUT_CANCELLED,
				APIPaymentNotificationEnum::CHECKOUT_EXPIRED => self::CHECKOUT_EXPIRED,
				APIPaymentNotificationEnum::CHECKOUT_FAILED => self::CHECKOUT_FAILED,
				APIPaymentNotificationEnum::CHECKOUT_COMPLETED => self::CHECKOUT_COMPLETED,
			];

			return $available_events[ $api_event ];
		}

		return null;
	}
}
