<?php
declare( strict_types=1 );

namespace unit\mocks\API;

use PowerBoard\Enums\ConfigAPIEnum;

/**
 * Mock ConfigService class for testing
 */
class ConfigService {
	private static $domain              = 'http://example.com';
	public static ?string $environment  = null;
	public static ?string $access_token = null;

	/**
	 * Initialize the ConfigService
	 *
	 * This method is called by the tests to set up the environment.
	 * In the real implementation, it would get settings from DBSettingsHelper,
	 * but in our mock we need to capture the environment from the test setup.
	 *
	 * @return void
	 */
	public static function init(): void {
		// In the BuildApiUrlTest, the test calls setup_settings() which mocks get_option
		// to return a specific environment value, then calls init().
		// We need to capture this environment value from the test.

		// Get the backtrace to find the calling test
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
		$backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );

		// Look for the BuildApiUrlTest class in the backtrace
		foreach ( $backtrace as $trace ) {
			if ( isset( $trace['class'] ) && strpos( $trace['class'], 'BuildApiUrlTest' ) !== false ) {
				// We found the test class, now get the test method
				$test_method = $trace['function'] ?? '';

				// Set the environment based on the test method
				if ( strpos( $test_method, 'production' ) !== false ) {
					self::$environment = ConfigAPIEnum::PRODUCTION_ENVIRONMENT_VALUE;
				} elseif ( strpos( $test_method, 'staging' ) !== false ) {
					self::$environment = ConfigAPIEnum::STAGING_ENVIRONMENT_VALUE;
				} else {
					self::$environment = ConfigAPIEnum::SANDBOX_ENVIRONMENT_VALUE;
				}

				break;
			}
		}
	}

	/**
	 * Get the domain
	 *
	 * @return string The domain
	 */
	public static function get_domain(): string {
		return self::$domain;
	}

	/**
	 * Helper method for tests to set the domain
	 *
	 * @param string $domain The domain to set
	 * @return void
	 */
	public static function set_test_domain( string $domain ): void {
		self::$domain = $domain;
	}

	/**
	 * Build API URL based on environment
	 *
	 * @param string|null $endpoint Optional endpoint to append
	 * @return string The complete API URL
	 */
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
