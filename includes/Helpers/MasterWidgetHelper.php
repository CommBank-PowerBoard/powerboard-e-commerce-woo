<?php
declare( strict_types=1 );

namespace PowerBoard\Helpers;

use PowerBoard\Enums\ConfigAPIEnum;
use PowerBoard\Services\Settings\APIAdapterService;

class MasterWidgetHelper {
	const DEFAULT_PAYMENT_INFO = "Click 'Place Order' to securely complete your payment.";

	/**
	 * Returns description for payment info box
	 */
	public static function get_payment_info_text( \WC_Payment_Gateway $gateway ): string {
		$text = trim( (string) $gateway->get_option( 'description', '' ) );
		if ( $text === '' ) {
			$text = self::DEFAULT_PAYMENT_INFO;
		}

		return apply_filters( 'power_board_payment_info_text', $text, $gateway );
	}

	/**
	 * Validate phone number format
	 *
	 * Format phone number to be sent on the checkout API, the country code is optional on the frontend and mandatory on the API
	 *
	 * @param ?string $phone The phone number to validate
	 * @return bool True if phone number is valid, false otherwise
	 */
	public static function validate_phone_number( ?string $phone ): bool {

		if ( empty( $phone ) ) {
			return false;
		}

		if ( preg_match( '/^\+[1-9]{1}[0-9]{1,14}$/', preg_replace( '/\s+/', '', $phone ) ) ) {
			return true;
		}

		return false;
	}

	public static function get_widget_script_url(): string {
		$environment = DBSettingsHelper::get_environment();

		$plugin_configuration_environments = self::get_plugin_configuration_environments();

		$environment_key = '';

		switch ( $environment ) {
			case ConfigAPIEnum::PRODUCTION_ENVIRONMENT_VALUE:
				$environment_key = ConfigAPIEnum::PRODUCTION_ENVIRONMENT_URL_KEY;
				break;
			case ConfigAPIEnum::SANDBOX_ENVIRONMENT_VALUE:
				$environment_key = ConfigAPIEnum::SANDBOX_ENVIRONMENT_URL_KEY;
				break;
			case ConfigAPIEnum::STAGING_ENVIRONMENT_VALUE:
				$environment_key = ConfigAPIEnum::STAGING_ENVIRONMENT_URL_KEY;
				break;
		}

		return isset( $plugin_configuration_environments[ $environment_key ] ) ? (string) $plugin_configuration_environments[ $environment_key ] : '';
	}

	/**
	 * Uses functions (get_transient, set_transient) from WordPress
	 */
	private static function get_plugin_configuration_environments(): array {
		$stored_configuration_environment = get_transient( 'environment_url' );

		if ( ! empty( $stored_configuration_environment ) ) {
			$plugin_configuration_environments = $stored_configuration_environment;
		} else {
			$widget_api_adapter_service        = APIAdapterService::get_instance();
			$plugin_configuration              = $widget_api_adapter_service->get_plugin_configuration_by_version();
			$plugin_configuration_environments = $plugin_configuration['environment_url'];

			set_transient( 'environment_url', $plugin_configuration_environments, 60 );
		}

		return is_array( $plugin_configuration_environments ) ? $plugin_configuration_environments : [];
	}
}
