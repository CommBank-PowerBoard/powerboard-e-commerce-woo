<?php
declare( strict_types=1 );

namespace PowerBoard\API;

use PowerBoard\Abstracts\AbstractApiService;
use PowerBoard\Enums\APIActionEnum;

class ChargeService extends AbstractApiService {
	const ENDPOINT               = 'charges';
	const CREATE_INTENT_ENDPOINT = 'checkouts/intent';
	const GET_TEMPLATES_ENDPOINT = 'checkouts/templates';
	const REFUNDS_ENDPOINT       = 'refunds';

	protected array $allowed_action = [
		'create-intent'      => self::METHOD_POST,
		'templates'          => self::METHOD_GET,
		'get-template-by-id' => self::METHOD_GET,
		'refunds'            => self::METHOD_POST,
		'get-charge'         => self::METHOD_GET,
	];

	public function create_checkout_intent( array $params ): self {
		$this->parameters     = $params;
		$this->request_action = APIActionEnum::CREATE_INTENT;

		$this->set_action( 'create-intent' );

		return $this;
	}

	public function get_configuration_template_by_id( array $params ): self {
		$this->parameters     = $params;
		$this->request_action = APIActionEnum::GET_CONFIGURATION_TEMPLATE_BY_ID;

		$this->set_action( 'get-template-by-id' );

		return $this;
	}

	public function get_configuration_templates_ids( string $version ): self {
		$this->parameters = [
			'type'    => 'configuration',
			'version' => $this->validate_version( $version ),
		];

		$this->set_action( 'templates' );
		$this->request_action = APIActionEnum::GET_CONFIGURATION_TEMPLATE_IDS;

		return $this;
	}

	public function get_configuration_templates_for_validation(): self {
		$this->parameters = [ 'type' => 'configuration' ];
		$this->set_action( 'templates' );
		$this->request_action = APIActionEnum::GET_CONFIGURATION_TEMPLATES_FOR_VALIDATION;

		return $this;
	}

	public function get_customisation_templates_ids( string $version ): self {
		$this->parameters     = [
			'type'    => 'customisation',
			'version' => $this->validate_version( $version ),
		];
		$this->request_action = APIActionEnum::GET_CUSTOMISATION_TEMPLATE_IDS;

		$this->set_action( 'templates' );

		return $this;
	}

	public function refunds( array $params ): self {
		// Validate charge_id if present in parameters
		if ( isset( $params['charge_id'] ) ) {
			$params['charge_id'] = $this->validate_charge_id( $params['charge_id'] );
		}

		$this->parameters     = $params;
		$this->request_action = APIActionEnum::REFUND;

		$this->set_action( 'refunds' );

		return $this;
	}

	public function build_endpoint(): ?string {
		switch ( $this->action ) {
			case 'refunds':
				$charge_id = $this->validate_charge_id( $this->parameters['charge_id'] ?? '' );
				$result    = self::ENDPOINT . '/' . $charge_id . '/' . self::REFUNDS_ENDPOINT;
				unset( $this->parameters['charge_id'] );
				break;
			case 'get-charge':
				$charge_id = $this->validate_charge_id( $this->parameters['charge_id'] ?? '' );
				$result    = self::ENDPOINT . '/' . $charge_id;
				unset( $this->parameters['charge_id'] );
				break;
			case 'get-template-by-id':
				$result = self::GET_TEMPLATES_ENDPOINT . '/' . $this->parameters['id'];
				unset( $this->parameters['id'] );
				break;
			case 'create-intent':
				$result = self::CREATE_INTENT_ENDPOINT;
				break;
			case 'templates':
				$type   = $this->validate_template_type( $this->parameters['type'] ?? '' );
				$result = self::GET_TEMPLATES_ENDPOINT . '?type=' . $type;

				$version = $this->parameters['version'] ?? '';
				if ( ! empty( $version ) ) {
					$validated_version = $this->validate_version( $version );
					$result           .= '&version=' . rawurlencode( $validated_version );
				}
				unset( $this->parameters['charge_id'] );
				break;
			default:
				$result = self::ENDPOINT;
		}

		return $result;
	}

	public function get_charge_by_id( string $charge_id ): self {
		// Validate charge_id before using it
		$validated_charge_id = $this->validate_charge_id( $charge_id );

		$this->parameters     = [ 'charge_id' => $validated_charge_id ];
		$this->request_action = APIActionEnum::GET_CHARGE_BY_ID;

		$this->set_action( 'get-charge' );

		return $this;
	}

	/**
	 * Validates charge_id parameter to prevent path traversal and injection attacks
	 *
	 * @param string $charge_id The charge ID to validate.
	 * @return string Validated charge ID.
	 * @throws \InvalidArgumentException If charge ID is invalid.
	 */
	private function validate_charge_id( string $charge_id ): string {
		if ( empty( $charge_id ) ) {
			throw new \InvalidArgumentException( 'Charge ID cannot be empty' );
		}

		// PowerBoard charge IDs are alphanumeric with hyphens and underscores, 8-50 characters
		// This regex also prevents path traversal (no dots or slashes allowed)
		if ( ! preg_match( '/^[a-zA-Z0-9_-]{8,50}$/', $charge_id ) ) {
			throw new \InvalidArgumentException( 'Invalid charge ID format' );
		}

		return $charge_id;
	}

	/**
	 * Validates template type parameter using whitelist approach
	 *
	 * @param string $type The template type to validate.
	 * @return string Validated template type.
	 * @throws \InvalidArgumentException If type is invalid.
	 */
	private function validate_template_type( string $type ): string {
		$allowed_types = [ 'configuration', 'customisation' ];

		if ( ! in_array( $type, $allowed_types, true ) ) {
			throw new \InvalidArgumentException(
				'Invalid template type. Allowed types: ' . \esc_html( implode( ', ', $allowed_types ) )
			);
		}

		return $type;
	}

	/**
	 * Validates version parameter to prevent injection attacks
	 *
	 * @param string $version The version to validate.
	 * @return string Validated version string.
	 * @throws \InvalidArgumentException If version is invalid.
	 */
	private function validate_version( string $version ): string {
		if ( empty( $version ) ) {
			return '';
		}

		// Version should be semantic version format (e.g., "1.0", "2.1.3", "1.0.0-beta")
		if ( ! preg_match( '/^[0-9]+(\.[0-9]+)*(-[a-zA-Z0-9]+)?$/', $version ) ) {
			throw new \InvalidArgumentException( 'Invalid version format. Expected semantic version (e.g., "1.0", "2.1.3")' );
		}

		// Ensure version is not excessively long
		if ( strlen( $version ) > 20 ) {
			throw new \InvalidArgumentException( 'Version string too long' );
		}

		return $version;
	}
}
