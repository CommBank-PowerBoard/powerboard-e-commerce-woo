<?php
declare( strict_types=1 );

namespace PowerBoard\Enums\PaymentNotification;

class APIPaymentNotificationEnum {

	public const PAYMENT_SUCCEEDED  = 'webhook.payment_succeeded';
	public const PAYMENT_FAILED     = 'webhook.payment_failed';
	public const PAYMENT_CREATED    = 'webhook.payment_created';
	public const PAYMENT_CAPTURED   = 'webhook.payment_captured';
	public const PAYMENT_VOIDED     = 'webhook.payment_voided';
	public const PAYMENT_REFUNDED   = 'webhook.payment_refunded';
	public const CHECKOUT_CREATED   = 'webhook.checkout_created';
	public const CHECKOUT_CANCELLED = 'webhook.checkout_cancelled';
	public const CHECKOUT_EXPIRED   = 'webhook.checkout_expired';
	public const CHECKOUT_FAILED    = 'webhook.checkout_failed';
	public const CHECKOUT_COMPLETED = 'webhook.checkout_completed';

	public const API_EVENTS = [
		self::PAYMENT_SUCCEEDED,
		self::PAYMENT_FAILED,
		self::PAYMENT_CREATED,
		self::PAYMENT_CAPTURED,
		self::PAYMENT_VOIDED,
		self::PAYMENT_REFUNDED,
		self::CHECKOUT_CREATED,
		self::CHECKOUT_CANCELLED,
		self::CHECKOUT_EXPIRED,
		self::CHECKOUT_FAILED,
		self::CHECKOUT_COMPLETED,
	];
}
