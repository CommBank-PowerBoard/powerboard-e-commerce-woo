<?php

namespace PowerBoard\Model;

class PaymentSource {

	/**
	 * Payment gateway type identifier.
	 *
	 * @var string
	 */
	private $gateway_type;

	/**
	 * User-friendly name of the payment gateway (e.g., 'Stripe', 'PayPal').
	 *
	 * @var string
	 */
	private $gateway_name;

	/**
	 * Payment source type.
	 *
	 * @var string
	 */
	private $type;

	/**
	 * Card network scheme when paying by card.
	 *
	 * @var string
	 */
	private $card_scheme;

	/**
	 * Type of wallet used when applicable (e.g., 'Apple Pay', 'Google Pay').
	 *
	 * @var string
	 */
	private $wallet_type;

	/**
	 * Initialize the payment source metadata from a raw data array.
	 *
	 * @param array $data Raw payment source information as received from gateway or API.
	 */
	public function __construct( $data ) {
		$this->set_payment_source_wallet_type( $data );
		$this->set_card_scheme( $data );
		$this->set_gateway_type( $data );
		$this->set_gateway_name( $data );
		$this->set_type( $data );
	}

	/**
	 * Get the normalized card network scheme.
	 *
	 * @return string Card scheme (e.g., 'VISA'), or empty string if not available.
	 */
	public function get_card_scheme() {
		return $this->card_scheme;
	}

	/**
	 * Get the payment gateway display name.
	 *
	 * @return string Gateway name (e.g., 'Stripe', 'PayPal'), or empty string if unknown.
	 */
	public function get_gateway_name() {
		return $this->gateway_name;
	}

	/**
	 * Get the type of the gateway.
	 *
	 * @return string Gateway type (e.g., 'redirect', 'hosted', 'card'), or empty string.
	 */
	public function get_gateway_type() {
		return $this->gateway_type;
	}

	/**
	 * Get the payment source type.
	 *
	 * @return string Type label (e.g., 'Card', 'Wallet', 'Bank'), or empty string.
	 */
	public function get_type() {
		return $this->type;
	}

	/**
	 * Get the wallet type if a wallet was used.
	 *
	 * @return string Wallet type (e.g., 'Apple Pay', 'Google Pay', 'PayPal'), or empty string.
	 */
	public function get_wallet_type() {
		return $this->wallet_type;
	}

	/**
	 * Normalize and set the wallet type from raw data.
	 *
	 * @param array $data Input data containing an optional 'wallet_type' key.
	 */
	private function set_payment_source_wallet_type( $data ) {
		$wallet_type = $data['wallet_type'] ?? '';
		if ( !empty( $wallet_type ) ) {
			$map               = [
				'google' => 'Google Pay',
				'apple'  => 'Apple Pay',
				'paypal' => 'PayPal',
			];
			$this->wallet_type = $map[ $wallet_type ] ?? ucwords( $wallet_type );
		} else {
			$this->wallet_type = '';
		}
	}

	/**
	 * Normalize and set the card scheme from raw data.
	 * Accepts either 'card_scheme' or legacy 'scheme' keys.
	 *
	 * @param array $data Input data that may contain 'card_scheme' or 'scheme'.
	 */
	private function set_card_scheme( $data ) {
		$scheme = (string) ( $data['card_scheme'] ?? $data['scheme'] ?? '' );
		if ( !empty( $scheme ) ) {
			$this->card_scheme = strtoupper( $scheme );
		}
	}

	/**
	 * Set the gateway type from raw data if provided.
	 *
	 * @param array $data Input data that may contain 'gateway_type'.
	 */
	private function set_gateway_type( $data ) {
		if ( !empty( $data['gateway_type'] ) ) {
			$this->gateway_type = (string) $data['gateway_type'];
		}
	}

	/**
	 * Set the gateway display name from raw data if provided.
	 *
	 * @param array $data Input data that may contain 'gateway_name'.
	 */
	private function set_gateway_name( $data ) {
		if ( !empty( $data['gateway_name'] ) ) {
			$this->gateway_name = (string) $data['gateway_name'];
		}
	}

	/**
	 * Set the payment source type from raw data.
	 *
	 * @param array $data Input data that may contain 'type'.
	 */
	private function set_type( $data ) {
		if ( !empty( $data['type'] ) ) {
			$this->type = ucwords( (string) $data['type'] );
		}
	}
}
