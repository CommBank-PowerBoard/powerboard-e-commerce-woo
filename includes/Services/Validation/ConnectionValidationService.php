<?php
declare( strict_types=1 );

namespace PowerBoard\Services\Validation;

use Exception;
use PowerBoard\API\ConfigService;
use PowerBoard\Enums\MasterWidgetSettingsEnum;
use PowerBoard\Helpers\AdminPanelHelpers\MasterWidgetTemplatesHelper;
use PowerBoard\Helpers\DBSettingsHelper;
use PowerBoard\Services\PaymentGateway\MasterWidgetPaymentService;
use PowerBoard\Services\Settings\APIAdapterService;

class ConnectionValidationService {
	private ?string $old_access_token = null;
	private ?string $old_environment  = null;

	public ?MasterWidgetPaymentService $service = null;
	private ?array $errors                      = [];
	private ?array $data                        = [];
	private ?string $environment_settings       = null;
	private ?string $access_token_settings      = null;

	// default value for version on init load
	private ?string $checkout_version = '1';

	private APIAdapterService $widget_api_adapter_service;

	/**
	 * Uses functions (do_action, update_option and apply_filters) from WordPress
	 * Uses a method (get_option_key) from WooCommerce
	 */
	public function __construct( MasterWidgetPaymentService $service ) {
		$this->service = $service;
		$this->prepare_form_data();

		$this->set_api_init_variables();

		$this->widget_api_adapter_service = APIAdapterService::get_instance();

		$this->validate();
	}

	/**
	 * Uses a function (do_action) from WordPress
	 */
	private function prepare_form_data(): void {
		/* @noinspection PhpUndefinedMethodInspection */
		$post_data = $this->service->get_post_data();
		/* @noinspection PhpUndefinedMethodInspection */
		foreach ( $this->service->get_form_fields() as $key => $field ) {
			try {
				/* @noinspection PhpUndefinedMethodInspection */
				$this->data[ $key ] = $this->service->get_field_value( $key, $field, $post_data );

				if ( $field['type'] === 'select' || $field['type'] === 'checkbox' ) {
					do_action(
						'woocommerce_update_non_option_setting',
						[
							'id'    => $key,
							'type'  => $field['type'],
							'value' => $this->data[ $key ],
						]
					);
				}
			} catch ( Exception $e ) {
				/* @noinspection PhpUndefinedMethodInspection */
				$this->service->add_error( $e->getMessage() );
			}
		}
	}

	private function validate(): void {
		if ( $this->validate_environment() ) {
			$this->validate_credential();
		}
	}

	private function set_api_init_variables(): void {
		$environment_settings_key   = DBSettingsHelper::get_environment_key();
		$this->environment_settings = $this->data[ $environment_settings_key ];

		$version_settings_key = DBSettingsHelper::get_version_key();
		if ( !empty( $this->data[ $version_settings_key ] ) ) {
			$this->checkout_version = $this->data[ $version_settings_key ];
		}

		$access_token_settings_key   = DBSettingsHelper::get_access_token_key();
		$this->access_token_settings = $this->data[ $access_token_settings_key ];
	}

	private function validate_environment(): bool {
		if ( ! empty( $this->environment_settings ) ) {
			return true;
		}

		$this->errors[] = 'No environment selected. Please select an environment and try again.';
		return false;
	}

	private function validate_credential(): void {
		if ( $this->access_token_settings === '********************' ) {
			$this->validate_checkout_version();
		} elseif (
				$this->check_access_key_connection()
			) {

			if ( $this->validate_checkout_version() ) {
				$this->get_configuration_templates();
				$this->get_customisation_templates();
			}
		}
	}

	private function validate_checkout_version(): bool {
		if ( ! empty( $this->checkout_version ) ) {
			return true;
		}

		$this->errors[] = __( 'No checkout version selected. Please select a version and try again.', 'power-board' );
		return false;
	}

	private function check_access_key_connection(): bool {
		$access_token_validation_failed = false;
		if ( $this->access_token_settings !== '********************' ) {
			$this->save_old_credential();
			ConfigService::$access_token = $this->access_token_settings;
			ConfigService::$environment  = $this->environment_settings;

			$access_token_validation_failed = ! $this->is_current_token_valid();

			if ( $access_token_validation_failed ) {
				$this->restore_credential();
				$this->errors[] = __( 'You have entered an invalid access token. Your changes have not been saved.', 'power-board' );
			} else {
				set_transient( 'invalid_access_token', false );
			}
		}

		return ! $access_token_validation_failed;
	}

	/**
	 * Uses functions (set_transient) from WordPress
	 */
	private function get_configuration_templates(): void {
		$transient_key           = 'configuration_templates_' . $this->environment_settings;
		$configuration_templates = get_transient( $transient_key );
		$has_error               = false;

		if ( $configuration_templates === false ) {
			$configuration_templates_result = $this->widget_api_adapter_service->get_configuration_templates_ids( $this->checkout_version );
			$has_error                      = ! empty( $configuration_templates_result['error'] );
			$configuration_templates        = MasterWidgetTemplatesHelper::map_templates(
				$configuration_templates_result['resource']['data'],
				$this->checkout_version,
				$has_error
			);

			if ( ! $has_error ) {
				set_transient( $transient_key, $configuration_templates, 60 );
			}
		}

		$configuration_id_key = DBSettingsHelper::get_configuration_template_key();
		MasterWidgetTemplatesHelper::validate_or_update_template_id(
			$configuration_templates,
			$has_error,
			$configuration_id_key,
			MasterWidgetSettingsEnum::CONFIGURATION_ID
		);
	}

	private function is_current_token_valid(): bool {
		$configuration_templates_result = $this->widget_api_adapter_service->get_configuration_templates_for_validation();
		$has_error                      = ! empty( $configuration_templates_result['error'] );

		if ( $has_error ) {
			return false;
		}

		return true;
	}

	public static function get_configuration_templates_for_validation(): array {
		$api_adapter_service = APIAdapterService::get_instance();
		return $api_adapter_service->get_configuration_templates_for_validation();
	}

	/**
	 * Uses functions (set_transient) from WordPress
	 */
	private function get_customisation_templates(): void {
		$transient_key           = 'customisation_templates_' . $this->environment_settings;
		$customisation_templates = get_transient( $transient_key );
		$has_error               = false;

		if ( $customisation_templates === false ) {
			$result                  = $this->widget_api_adapter_service->get_customisation_templates_ids( $this->checkout_version );
			$has_error               = ! empty( $result['error'] );
			$customisation_templates = MasterWidgetTemplatesHelper::map_templates(
				$result['resource']['data'],
				$this->checkout_version,
				$has_error,
				true
			);

			if ( ! $has_error ) {
				set_transient( $transient_key, $customisation_templates, 60 );
			}
		}

		$customisation_id_key = DBSettingsHelper::get_customisation_template_key();
		MasterWidgetTemplatesHelper::validate_or_update_template_id(
			$customisation_templates,
			$has_error,
			$customisation_id_key,
			MasterWidgetSettingsEnum::CUSTOMISATION_ID
		);
	}

	private function save_old_credential(): void {
		$this->old_access_token = ConfigService::$access_token;
		$this->old_environment  = ConfigService::$environment;
	}

	private function restore_credential(): void {
		ConfigService::$access_token = $this->old_access_token;
		ConfigService::$environment  = $this->old_environment;
	}

	public function get_errors(): array {
		return array_unique( $this->errors );
	}
}
