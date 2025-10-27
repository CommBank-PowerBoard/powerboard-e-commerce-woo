<?php
declare( strict_types=1 );

namespace PowerBoard\Helpers;

use PowerBoard\Enums\EnvironmentSettingsEnum;
use PowerBoard\Enums\MasterWidgetSettingsEnum;
use PowerBoard\Enums\SettingGroupsEnum;
use PowerBoard\Services\HashService;

class DBSettingsHelper {
	const LOCAL_ACCESS_TOKEN_ID              = 'access_token';
	const LOCAL_ENVIRONMENT_ID               = 'environment';
	const LOCAL_VERSION_ID                   = 'version';
	const LOCAL_CONFIGURATION_TEMPLATE_ID    = 'configuration_template';
	const LOCAL_CUSTOMISATION_TEMPLATE_ID    = 'customisation_template';
	const LOCAL_AVAILABLE_PAYMENT_METHODS_ID = 'available_payment_methods';

	protected const POWER_BOARD_SETTINGS_KEY       = 'woocommerce_power_board_settings';
	protected const POWER_BOARD_PLUGIN_ID          = POWER_BOARD_PLUGIN_PREFIX;
	protected const  AVAILABLE_PAYMENT_METHODS_KEY = 'available_payment_methods';


	public static function get_environment(): ?string {
		$environment_key = self::get_environment_key();
		return self::get_setting_by_key( $environment_key );
	}

	public static function get_access_token(): ?string {
		$token_key       = self::get_access_token_key();
		$encrypted_token = self::get_setting_by_key( $token_key );

		return self::decrypt_token( $encrypted_token );
	}

	public static function get_version(): ?string {
		$version_key = self::get_version_key();
		return self::get_setting_by_key( $version_key );
	}

	public static function get_configuration_id(): ?string {
		$configuration_id = self::get_configuration_template_key();
		return self::get_setting_by_key( $configuration_id );
	}

	public static function get_customisation_id(): ?string {
		$customisation_id = self::get_customisation_template_key();
		return self::get_setting_by_key( $customisation_id );
	}

	public static function get_available_payment_methods(): ?array {
		return self::get_setting_by_key( self::AVAILABLE_PAYMENT_METHODS_KEY );
	}

	public static function get_powerboard_settings(): ?array {
		$settings = self::get_db_settings();

		return [
			self::LOCAL_ENVIRONMENT_ID               => isset( $settings[ self::get_environment_key() ] ) ? $settings[ self::get_environment_key() ] : '',
			self::LOCAL_ACCESS_TOKEN_ID              => isset( $settings[ self::get_access_token_key() ] ) ? self::decrypt_token( $settings[ self::get_access_token_key() ] ) : '',
			self::LOCAL_VERSION_ID                   => isset( $settings[ self::get_version_key() ] ) ? $settings[ self::get_version_key() ] : '',
			self::LOCAL_CONFIGURATION_TEMPLATE_ID    => isset( $settings[ self::get_configuration_template_key() ] ) ? $settings[ self::get_configuration_template_key() ] : '',
			self::LOCAL_CUSTOMISATION_TEMPLATE_ID    => isset( $settings[ self::get_customisation_template_key() ] ) ? $settings[ self::get_customisation_template_key() ] : '',
			self::LOCAL_AVAILABLE_PAYMENT_METHODS_ID => isset( $settings[ self::AVAILABLE_PAYMENT_METHODS_KEY ] ) ? $settings[ self::AVAILABLE_PAYMENT_METHODS_KEY ] : '',
		];
	}

	public static function get_option_name( array $fragments ): string {
		return implode( '_', array_merge( [ self::POWER_BOARD_PLUGIN_ID ], $fragments ) );
	}

	public static function get_access_token_key(): string {
		return self::get_db_key_by_local_key( self::LOCAL_ACCESS_TOKEN_ID );
	}

	public static function get_environment_key(): string {
		return self::get_db_key_by_local_key( self::LOCAL_ENVIRONMENT_ID );
	}

	public static function get_version_key(): string {
		return self::get_db_key_by_local_key( self::LOCAL_VERSION_ID );
	}

	public static function get_configuration_template_key(): string {
		return self::get_db_key_by_local_key( self::LOCAL_CONFIGURATION_TEMPLATE_ID );
	}

	public static function get_customisation_template_key(): string {
		return self::get_db_key_by_local_key( self::LOCAL_CUSTOMISATION_TEMPLATE_ID );
	}

	public static function get_available_payment_methods_key(): string {
		return self::AVAILABLE_PAYMENT_METHODS_KEY;
	}

	private static function get_db_key_by_local_key( $local_key ): string {
		$key_array_fragments = [];
		switch ( $local_key ) {
			case self::LOCAL_ENVIRONMENT_ID:
				$key_array_fragments = [
					SettingGroupsEnum::ENVIRONMENT,
					EnvironmentSettingsEnum::ENVIRONMENT,
				];
				break;
			case self::LOCAL_ACCESS_TOKEN_ID:
				$key_array_fragments = [
					SettingGroupsEnum::CREDENTIALS,
					'ACCESS_KEY',
				];
				break;
			case self::LOCAL_VERSION_ID:
				$key_array_fragments = [
					SettingGroupsEnum::CHECKOUT,
					MasterWidgetSettingsEnum::VERSION,
				];
				break;
			case self::LOCAL_CONFIGURATION_TEMPLATE_ID:
				$key_array_fragments = [
					SettingGroupsEnum::CHECKOUT,
					MasterWidgetSettingsEnum::CONFIGURATION_ID,
				];
				break;
			case self::LOCAL_CUSTOMISATION_TEMPLATE_ID:
				$key_array_fragments = [
					SettingGroupsEnum::CHECKOUT,
					MasterWidgetSettingsEnum::CUSTOMISATION_ID,
				];
				break;
			case self::LOCAL_AVAILABLE_PAYMENT_METHODS_ID:
				return self::AVAILABLE_PAYMENT_METHODS_KEY;
		}

		return self::get_option_name( $key_array_fragments );
	}

	private static function decrypt_token( $encrypted_token ): ?string {
		try {
			$decrypted_key = HashService::decrypt( $encrypted_token );
		} catch ( \Exception $error ) {
			$decrypted_key = null;
		}

		return $decrypted_key;
	}

	private static function get_setting_by_key( $key ) {
		$settings = self::get_db_settings();

		if ( is_array( $settings ) && array_key_exists( $key, $settings ) ) {
			return $settings[ $key ];
		}
		return null;
	}

	private static function get_db_settings() {
		return get_option( self::POWER_BOARD_SETTINGS_KEY );
	}
}
