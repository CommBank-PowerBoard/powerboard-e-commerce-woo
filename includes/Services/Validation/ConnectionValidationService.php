<?php
declare( strict_types=1 );

namespace PowerBoard\Services\Validation;

use Exception;
use PowerBoard\API\ConfigService;
use PowerBoard\Enums\EnvironmentSettingsEnum;
use PowerBoard\Enums\MasterWidgetSettingsEnum;
use PowerBoard\Enums\SettingGroupsEnum;
use PowerBoard\Helpers\MasterWidgetTemplatesHelper;
use PowerBoard\Helpers\SettingsHelper;
use PowerBoard\Services\Settings\APIAdapterService;
use PowerBoard\Services\PaymentGateway\MasterWidgetPaymentService;

class ConnectionValidationService {
	private ?string $old_access_token                     = null;
	private static bool $invalid_credentials_shown_global = false;
	private static bool $no_version_selected_shown_global = false;
	private static bool $no_config_template_shown_global  = false;

	public ?MasterWidgetPaymentService $service = null;
	private ?array $errors                      = [];
	private ?array $data                        = [];
	private ?string $environment_settings       = null;
	private ?string $access_token_settings      = null;
	private ?string $configuration_id_settings  = null;
	private ?string $checkout_version           = null;
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
		$this->widget_api_adapter_service->initialise( $this->environment_settings, $this->access_token_settings );

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
					/* @noinspection PhpUndefinedFunctionInspection */
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
		$environment_settings_key   = SettingsHelper::get_option_name(
			$this->service->id,
			[
				SettingGroupsEnum::ENVIRONMENT,
				EnvironmentSettingsEnum::ENVIRONMENT,
			]
		);
		$this->environment_settings = $this->data[ $environment_settings_key ];

		$version_settings_key   = SettingsHelper::get_option_name(
			$this->service->id,
			[
				SettingGroupsEnum::CHECKOUT,
				MasterWidgetSettingsEnum::VERSION,
			]
		);
		$this->checkout_version = $this->data[ $version_settings_key ];

		$access_token_settings_key   = SettingsHelper::get_option_name(
			$this->service->id,
			[
				SettingGroupsEnum::CREDENTIALS,
				'ACCESS_KEY',
			]
		);
		$this->access_token_settings = $this->data[ $access_token_settings_key ];

		$configuration_template_setting_key = SettingsHelper::get_option_name(
																$this->service->id,
																[
																	SettingGroupsEnum::CHECKOUT,
																	MasterWidgetSettingsEnum::CONFIGURATION_ID,
																]
															);
		$this->configuration_id_settings    = $this->data[ $configuration_template_setting_key ];
	}

	private function validate_environment(): bool {
		if ( ! empty( $this->environment_settings ) ) {
			return true;
		}

		$this->errors[] = 'No environment selected. Please select an environment and try again.';
		return false;
	}

	private function validate_credential(): void {
		if (
			$this->access_token_settings === '********************'
		) {
			if ( $this->validate_checkout_version() ) {
				$this->check_is_configuration_template_selected();
			}
		} else {
			if (
				$this->check_access_key_connection( $this->access_token_settings )
			) {
				if ( $this->validate_checkout_version() ) {
					$this->get_configuration_templates();
					$this->get_customisation_templates();
				}
				return;
			}

			if ( ! self::$invalid_credentials_shown_global ) {
				$this->errors[]                         = 'Invalid credentials. Please update and try again.';
				self::$invalid_credentials_shown_global = true;
			}
		}
	}

	private function validate_checkout_version(): bool {
		if ( ! empty( $this->checkout_version ) ) {
			return true;
		}

		if ( ! self::$no_version_selected_shown_global ) {
			$this->errors[]                         = 'No checkout version selected. Please select a version and try again.';
			self::$no_version_selected_shown_global = true;
		}

		return false;
	}

	private function check_is_configuration_template_selected(): void {
		if ( empty( $this->configuration_id_settings ) ) {
			if ( ! self::$no_config_template_shown_global ) {
				$this->errors[]                        = 'No configuration template ID selected. Please select a template and try again.';
				self::$no_config_template_shown_global = true;
			}
		}
	}

	private function check_access_key_connection( ?string $access_token ): bool {
		$access_token_validation_failed = false;
		if ( $access_token !== '********************' ) {
			$this->save_old_credential();
			ConfigService::$access_token = $access_token;

			$access_token_validation_failed = ! $this->is_current_token_valid();

			$this->restore_credential();

			if ( ! $access_token_validation_failed ) {
				ConfigService::$access_token = $access_token;
				/* @noinspection PhpUndefinedFunctionInspection */
				set_transient( 'invalid_access_token', false );
			}
		}

		return ! $access_token_validation_failed;
	}

	/**
	 * Uses functions (set_transient) from WordPress
	 */
	private function get_configuration_templates(): void {
		$transient_key = 'configuration_templates_' . $this->environment_settings;
		/* @noinspection PhpUndefinedFunctionInspection */
		$configuration_templates = get_transient( $transient_key );
		$has_error               = false;

		if ( $configuration_templates === false ) {
			$configuration_templates_result = $this->widget_api_adapter_service->get_configuration_templates_ids( $this->checkout_version );
			$has_error                      = ! empty( $configuration_templates_result['error'] );
			$configuration_templates        = MasterWidgetTemplatesHelper::map_templates(
				$configuration_templates_result['resource']['data'],
				$has_error
			);

			if ( ! $has_error ) {
				/* @noinspection PhpUndefinedFunctionInspection */
				set_transient( $transient_key, $configuration_templates, 60 );
			}
		}

		$configuration_id_key = SettingsHelper::get_option_name(
			$this->service->id,
			[
				SettingGroupsEnum::CHECKOUT,
				MasterWidgetSettingsEnum::CONFIGURATION_ID,
			]
		);

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

	public static function get_configuration_templates_for_validation( APIAdapterService $widget_api_adapter_service ): array {
		return $widget_api_adapter_service->get_configuration_templates_for_validation();
	}

	/**
	 * Uses functions (set_transient) from WordPress
	 */
	private function get_customisation_templates(): void {
		$transient_key = 'customisation_templates_' . $this->environment_settings;
		/* @noinspection PhpUndefinedFunctionInspection */
		$customisation_templates = get_transient( $transient_key );
		$has_error               = false;

		if ( $customisation_templates === false ) {
			$result                  = $this->widget_api_adapter_service->get_customisation_templates_ids( $this->checkout_version );
			$has_error               = ! empty( $result['error'] );
			$customisation_templates = MasterWidgetTemplatesHelper::map_templates(
				$result['resource']['data'],
				$has_error,
				true
			);

			if ( ! $has_error ) {
				/* @noinspection PhpUndefinedFunctionInspection */
				set_transient( $transient_key, $customisation_templates, 60 );
			}
		}

		$customisation_id_key = SettingsHelper::get_option_name(
			$this->service->id,
			[
				SettingGroupsEnum::CHECKOUT,
				MasterWidgetSettingsEnum::CUSTOMISATION_ID,
			]
		);

		MasterWidgetTemplatesHelper::validate_or_update_template_id(
			$customisation_templates,
			$has_error,
			$customisation_id_key,
			MasterWidgetSettingsEnum::CUSTOMISATION_ID
		);
	}

	private function save_old_credential(): void {
		$this->old_access_token = ConfigService::$access_token;
	}

	private function restore_credential(): void {
		ConfigService::$access_token = $this->old_access_token;
	}

	public function get_errors(): array {
		return array_unique( $this->errors );
	}

	public function has_errors(): bool {
		return count( array_unique( $this->errors ) ) > 0 || self::$invalid_credentials_shown_global || self::$no_version_selected_shown_global || self::$no_config_template_shown_global;
	}
}
