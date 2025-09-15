<?php
declare( strict_types=1 );

namespace PowerBoard\Services;

use PowerBoard\API\ChargeService;
use PowerBoard\API\ConfigService;

class SDKAdapterService {
	private ?ChargeService $charge_service      = null;
	private static ?SDKAdapterService $instance = null;

	public static function get_instance(): self {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		ConfigService::init();
	}

	public function refunds( array $params ): array {
		return $this->init_charge_service()->refunds( $params )->call();
	}

	public function get_charge( string $charge_id ): array {
		return $this->init_charge_service()->get_charge_by_id( $charge_id )->call();
	}

	public function create_checkout_intent( array $params ): array {
		return $this->init_charge_service()->create_checkout_intent( $params )->call();
	}

	protected function init_charge_service(): ChargeService {
		if ( empty( $this->charge_service ) ) {
			$this->charge_service = new ChargeService();
		}

		return $this->charge_service;
	}
}
