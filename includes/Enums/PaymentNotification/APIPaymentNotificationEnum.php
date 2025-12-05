<?php
declare( strict_types=1 );

namespace PowerBoard\Enums\PaymentNotification;

class APIPaymentNotificationEnum {

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

	public const API_EVENTS = [
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
}
