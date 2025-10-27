<?php
declare( strict_types=1 );

namespace PowerBoard\Services\Settings;

use PowerBoard\API\ChargeService;
use PowerBoard\API\ConfigService;
use PowerBoard\Helpers\Util\JsonHelper;
use PowerBoard\Helpers\Util\LoggerHelper;

class APIAdapterService {
	private ?ChargeService $charge_service      = null;
	private static ?APIAdapterService $instance = null;

	public function __construct() {
		ConfigService::init();
	}

	public static function get_instance(): self {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function get_plugin_configuration_by_version(): array {
		$response = wp_remote_get( PLUGIN_VERSIONS_JSON_URL );

		if ( is_wp_error( $response ) ) {
			LoggerHelper::log( 'HTTP request to PLUGIN_VERSIONS_JSON_URL failed: ' . $response->get_error_message(), 'error' );
			$fallback_path = ABSPATH . 'wp-content/plugins/power-board/assets/fallback-compatibility.json';

			if ( file_exists( $fallback_path ) ) {
				LoggerHelper::log( 'Using fallback compatibility file: ' . $fallback_path );
				require_once ABSPATH . '/wp-admin/includes/file.php';
				WP_Filesystem();
				global $wp_filesystem;
				$fallback_contents = $wp_filesystem->get_contents( $fallback_path );

				if ( ! is_string( $fallback_contents ) || trim( $fallback_contents ) === '' ) {
					return [];
				}

				$response_body = $fallback_contents;
			} else {
				LoggerHelper::log( 'Fallback json file not found: ' . $fallback_path, 'error' );
				return [];
			}
		} else {
			$response_body = $response['body'];
		}

		$decoded = JsonHelper::decode_stringified_json( $response_body );

		if ( empty( $decoded ) || ! is_array( $decoded ) || empty( $decoded['woocommerce'][ POWER_BOARD_PLUGIN_VERSION ] ) ) {
			LoggerHelper::log( 'Invalid or missing configuration for plugin.', 'error' );
			return [];
		}

		$result = [];

		foreach ( $decoded['woocommerce'][ POWER_BOARD_PLUGIN_VERSION ] as $key => $value ) {
			if ( $key === 'env' ) {
				foreach ( $value as $env_value ) {
					$result['environment_url'][ $env_value['name'] ] = $env_value['client_sdk_url'];
				}
			} elseif ( $key === 'checkout_version' ) {
				$result['checkout_versions'] = array_combine( $value, $value );
			}
		}

		return $result;
	}

	public function get_configuration_template_by_id( string $id ): array {
		return $this->get_charge_service()->get_configuration_template_by_id( [ 'id' => $id ] )->call();
	}

	public function get_configuration_templates_ids( string $version ): array {
		return $this->get_charge_service()->get_configuration_templates_ids( $version )->call();
	}

	public function get_configuration_templates_for_validation(): array {
		return $this->get_charge_service()->get_configuration_templates_for_validation()->call();
	}

	public function get_customisation_templates_ids( string $version ): array {
		return $this->get_charge_service()->get_customisation_templates_ids( $version )->call();
	}

	protected function get_charge_service(): ChargeService {
		if ( empty( $this->charge_service ) ) {
			$this->charge_service = new ChargeService();
		}

		return $this->charge_service;
	}
}
