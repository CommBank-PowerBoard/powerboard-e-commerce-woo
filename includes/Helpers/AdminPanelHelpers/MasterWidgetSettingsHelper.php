<?php
declare( strict_types=1 );

namespace PowerBoard\Helpers\AdminPanelHelpers;

use PowerBoard\Enums\AdminPanelSettings\MasterWidgetSettingsEnum;
use PowerBoard\Helpers\DBSettingsHelper;
use PowerBoard\Helpers\Util\LoggerHelper;
use PowerBoard\Services\Settings\APIAdapterService;
use PowerBoard\Services\Validation\ConnectionValidationService;

class MasterWidgetSettingsHelper {
	public static function get_input_type( string $key ): string {
		switch ( $key ) {
			case MasterWidgetSettingsEnum::VERSION:
			case MasterWidgetSettingsEnum::CONFIGURATION_ID:
			case MasterWidgetSettingsEnum::CUSTOMISATION_ID:
			default:
				return 'select';
		}
	}

	public static function get_label( string $key ): string {
		switch ( $key ) {
			case MasterWidgetSettingsEnum::VERSION:
				return 'Version';
			case MasterWidgetSettingsEnum::CONFIGURATION_ID:
				return 'Configuration Template ID';
			case MasterWidgetSettingsEnum::CUSTOMISATION_ID:
				return 'Customisation Template ID (optional)';
			default:
				return ucfirst( strtolower( str_replace( '_', ' ', $key ) ) );
		}
	}

	public static function get_options_for_ui( string $key ): array {
		$settings     = DBSettingsHelper::get_powerboard_settings();
		$env          = $settings[ DBSettingsHelper::LOCAL_ENVIRONMENT_ID ];
		$access_token = $settings[ DBSettingsHelper::LOCAL_ACCESS_TOKEN_ID ];
		$version      = $settings[ DBSettingsHelper::LOCAL_VERSION_ID ];

		switch ( $key ) {
			case MasterWidgetSettingsEnum::VERSION:
				return self::get_checkout_versions_for_ui( $env, $access_token );
			case MasterWidgetSettingsEnum::CONFIGURATION_ID:
				return self::get_configuration_ids_for_ui( $env, $access_token, $version );
			case MasterWidgetSettingsEnum::CUSTOMISATION_ID:
				return self::get_customisation_ids_for_ui( $env, $access_token, $version );
			default:
				return [];
		}
	}

	public static function get_checkout_versions_for_ui( $env, $access_token ): array {
		if ( ! self::is_power_board_settings_page() || empty( $env ) || empty( $access_token ) ) {
			return [];
		}

		$stored_checkout_versions = get_transient( 'checkout_versions' );
		if ( ! empty( $stored_checkout_versions ) ) {
			$checkout_versions_for_ui = $stored_checkout_versions;

		} else {
			$api_adapter_service  = APIAdapterService::get_instance();
			$plugin_configuration = $api_adapter_service->get_plugin_configuration_by_version();

			if ( empty( $plugin_configuration['checkout_versions'] ) || ! is_array( $plugin_configuration['checkout_versions'] ) ) {
				LoggerHelper::log( 'No valid checkout versions found in plugin configuration.', 'error' );
				if ( function_exists( 'add_settings_error' ) ) {
					\add_settings_error(
						'powerboard_checkout_version',
						'invalid_checkout_versions',
						'Failed to load Checkout Versions. Please check the compatibility registry URL or API response.',
						'error'
					);
				}
				$checkout_versions_for_ui = [];
			} else {
				$checkout_versions_for_ui = $plugin_configuration['checkout_versions'];
			}

			set_transient( 'checkout_versions', $checkout_versions_for_ui, 60 );
			set_transient( 'environment_url', $plugin_configuration['environment_url'] ?? [], 60 );
		}

		return [ '' => 'Select a checkout version' ] + $checkout_versions_for_ui;
	}

	/**
	 * Uses functions (get_transient, delete_transient and set_transient) from WordPress
	 */
	public static function get_configuration_ids_for_ui( $env, $access_token, $version ): array {
		if ( ! self::is_power_board_settings_page() || empty( $env ) || empty( $access_token ) ) {
			return [];
		}

		$version = is_string( $version ) && $version !== '' ? $version : null;

		if ( empty( $version ) ) {
			if ( function_exists( 'add_settings_error' ) ) {
				\add_settings_error(
					'powerboard_checkout_version',
					'missing_version',
					'First select the Checkout Version, then the templates will become available.',
					'error'
				);
			}
			return [];
		}

		$api_adapter_service = APIAdapterService::get_instance();
		$result              = $api_adapter_service->get_configuration_templates_ids( $version ) ?? [];

		$has_error = ! empty( $result['error'] );

		if ( $has_error ) {
			$valid_template       = ConnectionValidationService::get_configuration_templates_for_validation();
			$invalid_access_token = ! empty( $valid_template['error'] ) && $valid_template['status'] === 403;
			set_transient( 'invalid_access_token', $invalid_access_token ? '1' : false );
		} else {
			set_transient( 'invalid_access_token', false );
		}

		$data                    = is_array( $result['resource']['data'] ?? null ) ? $result['resource']['data'] : [];
		$configuration_templates = MasterWidgetTemplatesHelper::map_templates( $data, $version, $has_error );

		$configuration_id_key = DBSettingsHelper::get_configuration_template_key();
		MasterWidgetTemplatesHelper::validate_or_update_template_id( $configuration_templates, ! empty( $has_error ), $configuration_id_key, MasterWidgetSettingsEnum::CONFIGURATION_ID );

		return $configuration_templates;
	}

	/**
	 * Uses functions (set_transient, delete_transient and get_transient) from WordPress
	 */
	public static function get_customisation_ids_for_ui( $env, $access_token, $version ): array {
		if ( !self::is_power_board_settings_page() || empty( $env ) || empty( $access_token ) ) {
			return [];
		}

		$version = is_string( $version ) && $version !== '' ? $version : null;
		if ( empty( $version ) ) {
			return [];
		}
		$api_adapter_service     = APIAdapterService::get_instance();
		$result                  = $api_adapter_service->get_customisation_templates_ids( $version ) ?? [];
		$has_error               = ! empty( $result['error'] );
		$data                    = is_array( $result['resource']['data'] ?? null ) ? $result['resource']['data'] : [];
		$customisation_templates = MasterWidgetTemplatesHelper::map_templates( $data, $version, $has_error, true );

		$customisation_id_key = DBSettingsHelper::get_customisation_template_key();
		MasterWidgetTemplatesHelper::validate_or_update_template_id( $customisation_templates, ! empty( $has_error ), $customisation_id_key, MasterWidgetSettingsEnum::CUSTOMISATION_ID );
		return $customisation_templates;
	}

	/**
	 * Uses functions (sanitize_text_field and wp_unslash) from WordPress
	 * It is safe to ignore NonceVerification because we are sanitizing the strings and not using them to submit data
	 * phpcs:disable WordPress.Security.NonceVerification -- processed through the WooCommerce form handler
	 */
	public static function is_power_board_settings_page(): bool {
		return isset( $_GET['page'] ) && sanitize_text_field( wp_unslash( $_GET['page'] ) ) === 'wc-settings' && isset( $_GET['tab'] ) && sanitize_text_field( wp_unslash( $_GET['tab'] ) ) === 'checkout' && isset( $_GET['section'] ) && sanitize_text_field( wp_unslash( $_GET['section'] ) ) === POWER_BOARD_PLUGIN_PREFIX;
	}
	// phpcs:enable

	public static function normalize_setting_string( array $settings, string $key, ?int $max_len = null, ?string $value = null ): string {
		$val = trim( (string) ( $settings[ $key ] ?? '' ) );

		if ( $max_len !== null && $max_len >= 0 && $val !== '' && mb_strlen( $val ) > $max_len ) {
			$val = mb_substr( $val, 0, $max_len );
		}

		if ( $val === '' && $value !== null ) {
			return $value;
		}

		return $val;
	}

	public static function get_gateway_title( array $settings, ?int $max_len = null, ?string $value = 'PowerBoard' ): string {
		return self::normalize_setting_string( $settings, 'title', $max_len, $value );
	}

	public static function get_gateway_description( array $settings, ?int $max_len = null, ?string $value = 'Click \'Place Order\' to securely complete your payment.' ): string {
		return self::normalize_setting_string( $settings, 'description', $max_len, $value );
	}
}
