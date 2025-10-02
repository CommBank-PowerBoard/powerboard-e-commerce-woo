<?php
declare( strict_types=1 );

namespace PowerBoard\API;

use PowerBoard\Enums\ConfigAPIEnum;
use PowerBoard\Helpers\DBSettingsHelper;

class ConfigService {
	public static ?string $environment  = null;
	public static ?string $access_token = null;

	public static function init(): void {
		$settings           = DBSettingsHelper::get_powerboard_settings();
		self::$environment  = $settings[ DBSettingsHelper::LOCAL_ENVIRONMENT_ID ];
		self::$access_token = $settings[ DBSettingsHelper::LOCAL_ACCESS_TOKEN_ID ];
	}

	public static function build_api_url( ?string $endpoint = null ): string {
		if ( self::$environment === ConfigAPIEnum::PRODUCTION_ENVIRONMENT_VALUE ) {
			return ConfigAPIEnum::PRODUCTION_API_URL . ConfigAPIEnum::API_VERSION_URL . $endpoint;
		} elseif ( self::$environment === ConfigAPIEnum::STAGING_ENVIRONMENT_VALUE ) {
			return ConfigAPIEnum::STAGING_API_URL . ConfigAPIEnum::API_VERSION_URL . $endpoint;
		} else {
			return ConfigAPIEnum::SANDBOX_API_URL . ConfigAPIEnum::API_VERSION_URL . $endpoint;
		}
	}
}
