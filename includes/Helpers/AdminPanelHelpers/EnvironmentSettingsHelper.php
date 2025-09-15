<?php
declare( strict_types=1 );

namespace PowerBoard\Helpers\AdminPanelHelpers;

use PowerBoard\Enums\ConfigAPIEnum;
use PowerBoard\Enums\EnvironmentSettingsEnum;

class EnvironmentSettingsHelper {
	public static function get_input_type( string $key ): string {
		switch ( $key ) {
			case EnvironmentSettingsEnum::ENVIRONMENT:
			default:
				return 'select';
		}
	}

	public static function get_label( string $key ): string {
		switch ( $key ) {
			case EnvironmentSettingsEnum::ENVIRONMENT:
				return 'Environment';
			default:
				return ucfirst( strtolower( str_replace( '_', ' ', $key ) ) );
		}
	}

	public static function get_options_for_ui( string $key ): array {
		switch ( $key ) {
			case EnvironmentSettingsEnum::ENVIRONMENT:
				return self::get_environments_list_for_ui();
			default:
				return [];
		}
	}

	public static function get_environments_list_for_ui(): array {
		return [
			''                                          => 'Select an environment',
			ConfigAPIEnum::STAGING_ENVIRONMENT_VALUE    => ConfigAPIEnum::STAGING_ENVIRONMENT_NAME,
			ConfigAPIEnum::SANDBOX_ENVIRONMENT_VALUE    => ConfigAPIEnum::SANDBOX_ENVIRONMENT_NAME,
			ConfigAPIEnum::PRODUCTION_ENVIRONMENT_VALUE => ConfigAPIEnum::PRODUCTION_ENVIRONMENT_NAME,
		];
	}

	public static function get_default( string $key ): string {
		switch ( $key ) {
			case EnvironmentSettingsEnum::ENVIRONMENT:
			default:
				$result = '';
		}

		return $result;
	}
}
