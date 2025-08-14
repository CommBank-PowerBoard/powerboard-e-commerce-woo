<?php
declare( strict_types=1 );

namespace PowerBoard\Helpers;

use PowerBoard\Enums\MasterWidgetSettingsEnum;
use PowerBoard\Enums\SettingGroupsEnum;
use PowerBoard\Services\PaymentGateway\MasterWidgetPaymentService;
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

	public static function get_options_for_ui( string $key, $env, $access_token, $version ): array {
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
		/* @noinspection PhpUndefinedFunctionInspection */
		if ( ! self::is_power_board_settings_page() || ! empty( get_transient( 'is_fetching_versions' ) ) ) {
			return [];
		}
		/* @noinspection PhpUndefinedFunctionInspection */
		set_transient( 'is_fetching_versions', true, 60 );

		/* @noinspection PhpUndefinedFunctionInspection */
		$stored_checkout_versions = get_transient( 'checkout_versions' );
		if ( ! empty( $stored_checkout_versions ) ) {
			$checkout_versions_for_ui = $stored_checkout_versions;
		} else {
			$api_adapter_service  = self::init_api_adapter( $env, $access_token );
			$plugin_configuration = $api_adapter_service->get_plugin_configuration_by_version();

			if ( empty( $plugin_configuration['checkout_versions'] ) || ! is_array( $plugin_configuration['checkout_versions'] ) ) {
				LoggerHelper::log( 'No valid checkout versions found in plugin configuration.', 'error' );
				/* @noinspection PhpUndefinedFunctionInspection */
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

			/* @noinspection PhpUndefinedFunctionInspection */
			set_transient( 'checkout_versions', $checkout_versions_for_ui, 60 );
			/* @noinspection PhpUndefinedFunctionInspection */
			set_transient( 'environment_url', $plugin_configuration['environment_url'] ?? [], 60 );
		}
		/* @noinspection PhpUndefinedFunctionInspection */
		delete_transient( 'is_fetching_versions' );
		return [ '' => 'Select a checkout version' ] + $checkout_versions_for_ui;
	}

	/**
	 * Uses functions (get_transient, delete_transient and set_transient) from WordPress
	 */
	public static function get_configuration_ids_for_ui( $env, $access_token, $version ): array {
		/* @noinspection PhpUndefinedFunctionInspection */
		if ( ! self::is_power_board_settings_page() || ! empty( get_transient( 'is_fetching_configuration_templates' ) ) ) {
			return [];
		}
		/* @noinspection PhpUndefinedFunctionInspection */
		set_transient( 'is_fetching_configuration_templates', true, 60 );

		/* @noinspection PhpUndefinedFunctionInspection */
		$stored_configuration_templates = get_transient( 'configuration_templates_' . $env );
		$has_error                      = false;
		$api_adapter_service            = self::init_api_adapter( $env, $access_token );
		$result                         = $api_adapter_service->get_configuration_templates_ids( $version );
		$has_error                      = $result['error'];

		if ( $has_error ) {
			$widget_api_adapter_service = APIAdapterService::get_instance();
			$widget_api_adapter_service->initialise( $env, $access_token );
			$valid_template       = ConnectionValidationService::get_configuration_templates_for_validation( $widget_api_adapter_service );
			$invalid_access_token = ! empty( $valid_template['error'] ) && $valid_template['status'] === 403;
			/* @noinspection PhpUndefinedFunctionInspection */
			set_transient( 'invalid_access_token', $invalid_access_token ? '1' : false );
		} else {
			/* @noinspection PhpUndefinedFunctionInspection */
			set_transient( 'invalid_access_token', false );
		}
		$data                    = $result['resource']['data'] ?? [];
		$configuration_templates = MasterWidgetTemplatesHelper::map_templates( $data, $version, ! empty( $has_error ) );

		$configuration_id_key = SettingsHelper::get_option_name(
				POWER_BOARD_PLUGIN_PREFIX,
				[
					SettingGroupsEnum::CHECKOUT,
					MasterWidgetSettingsEnum::CONFIGURATION_ID,
				]
			);
		MasterWidgetTemplatesHelper::validate_or_update_template_id( $configuration_templates, ! empty( $has_error ), $configuration_id_key, MasterWidgetSettingsEnum::CONFIGURATION_ID );

		/* @noinspection PhpUndefinedFunctionInspection */
		delete_transient( 'is_fetching_configuration_templates' );
		return $configuration_templates;
	}

	/**
	 * Uses functions (set_transient, delete_transient and get_transient) from WordPress
	 */
	public static function get_customisation_ids_for_ui( $env, $access_token, $version ): array {
		/* @noinspection PhpUndefinedFunctionInspection */
		if ( ! self::is_power_board_settings_page() || ! empty( get_transient( 'is_fetching_customisation_templates' ) ) ) {
			return [];
		}
		/* @noinspection PhpUndefinedFunctionInspection */
		set_transient( 'is_fetching_customisation_templates', true, 60 );

		/* @noinspection PhpUndefinedFunctionInspection */
		$stored_customisation_templates = get_transient( 'customisation_templates_' . $env );
		$has_error                      = false;
		$api_adapter_service            = self::init_api_adapter( $env, $access_token );
		$result                         = $api_adapter_service->get_customisation_templates_ids( $version );
		$has_error                      = $result['error'];
		$data                           = $result['resource']['data'] ?? [];
		$customisation_templates        = MasterWidgetTemplatesHelper::map_templates( $data, $version, ! empty( $has_error ), true );

		$customisation_id_key = SettingsHelper::get_option_name(
			POWER_BOARD_PLUGIN_PREFIX,
				[
					SettingGroupsEnum::CHECKOUT,
					MasterWidgetSettingsEnum::CUSTOMISATION_ID,
				]
			);
		MasterWidgetTemplatesHelper::validate_or_update_template_id( $customisation_templates, ! empty( $has_error ), $customisation_id_key, MasterWidgetSettingsEnum::CUSTOMISATION_ID );

		/* @noinspection PhpUndefinedFunctionInspection */
		delete_transient( 'is_fetching_customisation_templates' );
		return $customisation_templates;
	}

	public static function init_api_adapter( $env, $access_token ): APIAdapterService {
		$api_adapter_service = APIAdapterService::get_instance();
		$api_adapter_service->initialise( $env, $access_token );
		return $api_adapter_service;
	}

	/**
	 * Uses functions (sanitize_text_field and wp_unslash) from WordPress
	 * It is safe to ignore NonceVerification because we are sanitizing the strings and not using them to submit data
	 * phpcs:disable WordPress.Security.NonceVerification -- processed through the WooCommerce form handler
	 */
	public static function is_power_board_settings_page(): bool {
		/* @noinspection PhpUndefinedFunctionInspection */
		return isset( $_GET['page'] ) && sanitize_text_field( wp_unslash( $_GET['page'] ) ) === 'wc-settings' && isset( $_GET['tab'] ) && sanitize_text_field( wp_unslash( $_GET['tab'] ) ) === 'checkout' && isset( $_GET['section'] ) && sanitize_text_field( wp_unslash( $_GET['section'] ) ) === POWER_BOARD_PLUGIN_PREFIX;
	}
	// phpcs:enable

	/**
	 * @param $phone
	 * @return mixed|string
	 *
	 * format phone number to be sent on the checkout API, the country code is optional on the frontend and mandatory on the API
	 */
	public static function validate_phone_number( $phone ) {

		if ( empty( $phone ) ) {
			return '';
		}

		$phone = preg_replace( '/\s+/', '', $phone );
		//if phone comes with correct format, returns the phone
		if ( preg_match( '/^\+[1-9]{1}[0-9]{3,14}$/', $phone ) ) {
			return $phone;
		}

		// Remove all non-digit characters
		$cleanPhone = preg_replace( '/[^\d]/', '', $phone );

		// If starts with 0, remove it, match max length
		$digits = substr( $cleanPhone, 0, 13 );
		return "+61" . $digits;
	}

	public static function normalize_setting_string( array $settings, string $key, int $maxLength ): string {
		$value = trim( (string) ( $settings[ $key ] ?? '' ) );
		if ( $value === '' ) {
			return '';
		}
		return mb_substr( $value, 0, $maxLength );
	}

	public static function get_gateway_title( array $settings ): string {
		return self::normalize_setting_string( $settings, 'title', MasterWidgetPaymentService::TITLE_MAX ) ?: 'PowerBoard';
	}

	public static function get_gateway_description( array $settings ): string {
		return self::normalize_setting_string( $settings, 'description', MasterWidgetPaymentService::DESCRIPTION_MAX ) ?: 'Pay securely via PowerBoard.';
	}
}
