<?php

namespace PowerBoard\Model;

use PowerBoard\Enums\PaymentNotification\PBPaymentNotificationEnum;

class Charge {
	/**
	 * Unique identifier of the charge.
	 *
	 * @var string
	 */
	protected $id;

	/**
	 * Metadata about the payment method used for this charge.
	 *
	 * @var PaymentSource
	 */
	protected $payment_source;

	/**
	 * Construct a Charge from raw charge payload.
	 *
	 * Expects keys like 'charge_id' and nested 'charge' data containing
	 * customer payment_source details.
	 *
	 * @param array $data Raw charge data from API/webhook.
	 */
	public function __construct( $data ) {
		if ( in_array( PBPaymentNotificationEnum::get_ipn_event( $data['event'] ), [ PBPaymentNotificationEnum::PAYMENT_FAILED, PBPaymentNotificationEnum::CHECKOUT_FAILED, PBPaymentNotificationEnum::CHECKOUT_CANCELLED ] ) ) {
			$this->set_charge_id( $data['error']['charge_id'] );
		} else {
			$this->set_charge_id( $data['charge']['_id'] );
		}

		$this->set_payment_source( $data['charge']['customer']['payment_source'] ?? $data['charge']['payment_source'] ?? [] );
	}

	/**
	 * Get a user-friendly label describing the payment source used for the charge.
	 *
	 * @return string Non-empty label if available, otherwise empty string.
	 */
	public function get_charge_label() {
		if ( $this->payment_source->get_wallet_type() ) {
			return $this->payment_source->get_wallet_type();
		}

		if ( $this->payment_source->get_card_scheme() ) {
			return $this->payment_source->get_card_scheme();
		}

		if ( $this->payment_source->get_gateway_type() ) {
			return $this->payment_source->get_gateway_type();
		}

		if ( $this->payment_source->get_gateway_name() ) {
			return $this->payment_source->get_gateway_name();
		}

		if ( $this->payment_source->get_type() ) {
			return $this->payment_source->get_type();
		}

		return '';
	}


	/**
	 * Set the charge identifier.
	 *
	 * @param string $id Charge identifier as provided by the API.
	 */
	public function set_charge_id( $id ): void {
		$this->id = $id;
	}


	/**
	 * Initialize and set the PaymentSource object from raw data.
	 *
	 * @param mixed $payment_source Raw payment source array from the charge payload.
	 */
	public function set_payment_source( $payment_source ): void {
		$this->payment_source = new PaymentSource( $payment_source );
	}

	/**
	 * Get the charge identifier.
	 *
	 * @return string Charge identifier.
	 */
	public function get_charge_id() {
		return $this->id;
	}

	/**
	 * Get the PaymentSource details associated with this charge.
	 *
	 * @return PaymentSource Payment source metadata object.
	 */
	public function get_payment_source() {
		return $this->payment_source;
	}
}
