<?php
declare( strict_types=1 );

namespace unit\src\API\ConfigService;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use PowerBoard\API\ConfigService;
use PowerBoard\Enums\ConfigAPIEnum;

class BuildApiUrlTest extends TestCase {
	protected ConfigService $config_service;
	protected string $endpoint = 'checkouts/intent';

	/**
	 * Sets up test environment for each test method
	 *
	 * Initializes Brain\Monkey for WordPress function mocking and creates
	 * a new ConfigService instance for testing.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->config_service = new ConfigService();
	}

	/**
	 * Sets up mock settings for testing different environment configurations
	 *
	 * @param string $environment The environment value to use in settings
	 * @return void
	 */
	private function setup_settings( $environment ) {
		// This is the format that WordPress get_option returns (raw database format)
		$db_settings = [
			'power_board_CREDENTIALS_ACCESS_KEY'    => 'access_token',
			'power_board_ENVIRONMENT_ENVIRONMENT'   => $environment,
			'power_board_CHECKOUT_VERSION'          => 'version',
			'power_board_CHECKOUT_CONFIGURATION_ID' => 'configuration_template',
			'power_board_CHECKOUT_CUSTOMISATION_ID' => 'customisation_template',
			'available_payment_methods'             => 'available_payment_methods',
		];

		// Mock the get_option function that DBSettingsHelper::get_powerboard_settings() calls internally
		Functions\expect( 'get_option' )
			->with( 'woocommerce_power_board_settings' )
			->andReturn( $db_settings );

		// Define the plugin prefix constant if not already defined
		if ( ! defined( 'POWER_BOARD_PLUGIN_PREFIX' ) ) {
			define( 'POWER_BOARD_PLUGIN_PREFIX', 'power_board' );
		}
	}

	/**
	 * Tests building API URL for production environment with a specific endpoint
	 *
	 * Verifies that when the environment is set to production, the correct
	 * production API URL is returned with the specified endpoint appended.
	 *
	 * @test
	 * @return void
	 */
	public function test_build_api_url_production_with_endpoint() {
		$this->setup_settings( ConfigAPIEnum::PRODUCTION_ENVIRONMENT_VALUE );
		$this->config_service->init();
		$actual   = $this->config_service->build_api_url( $this->endpoint );
		$expected = ConfigAPIEnum::PRODUCTION_API_URL . $this->endpoint;

		$this->assertEquals( $expected, $actual, 'Should return valid endpoint with a the correct production url.' );
	}

	/**
	 * Tests building API URL for staging environment with a specific endpoint
	 *
	 * Verifies that when the environment is set to staging, the correct
	 * staging API URL is returned with the specified endpoint appended.
	 *
	 * @test
	 * @return void
	 */
	public function test_build_api_url_staging_with_endpoint() {
		$this->setup_settings( ConfigAPIEnum::STAGING_ENVIRONMENT_VALUE );
		$this->config_service->init();
		$actual   = $this->config_service->build_api_url( $this->endpoint );
		$expected = ConfigAPIEnum::STAGING_API_URL . $this->endpoint;

		$this->assertEquals( $expected, $actual, 'Should return valid endpoint with a the correct staging url.' );
	}

	/**
	 * Tests building API URL for sandbox environment with a specific endpoint
	 *
	 * Verifies that when the environment is set to sandbox (or any other value),
	 * the correct sandbox API URL is returned with the specified endpoint appended.
	 *
	 * @test
	 * @return void
	 */
	public function test_build_api_url_sandbox_with_endpoint() {
		$this->setup_settings( ConfigAPIEnum::SANDBOX_ENVIRONMENT_VALUE );
		$this->config_service->init();
		$actual   = $this->config_service->build_api_url( $this->endpoint );
		$expected = ConfigAPIEnum::SANDBOX_API_URL . $this->endpoint;

		$this->assertEquals( $expected, $actual, 'Should return valid endpoint with a the correct sandbox url.' );
	}

	/**
	 * Tests building API URL for production environment without endpoint
	 *
	 * Verifies that when the environment is set to production and no endpoint
	 * is provided, the correct production API base URL is returned.
	 *
	 * @test
	 * @return void
	 */
	public function test_build_api_url_production() {
		$this->setup_settings( ConfigAPIEnum::PRODUCTION_ENVIRONMENT_VALUE );
		$this->config_service->init();
		$actual   = $this->config_service->build_api_url();
		$expected = ConfigAPIEnum::PRODUCTION_API_URL;

		$this->assertEquals( $expected, $actual, 'Should return the correct production url.' );
	}

	/**
	 * Tests building API URL for staging environment without endpoint
	 *
	 * Verifies that when the environment is set to staging and no endpoint
	 * is provided, the correct staging API base URL is returned.
	 *
	 * @test
	 * @return void
	 */
	public function test_build_api_url_staging() {
		$this->setup_settings( ConfigAPIEnum::STAGING_ENVIRONMENT_VALUE );
		$this->config_service->init();
		$actual   = $this->config_service->build_api_url();
		$expected = ConfigAPIEnum::STAGING_API_URL;

		$this->assertEquals( $expected, $actual, 'Should return the correct staging url.' );
	}

	/**
	 * Tests building API URL for sandbox environment without endpoint
	 *
	 * Verifies that when the environment is set to sandbox (default fallback) and
	 * no endpoint is provided, the correct sandbox API base URL is returned.
	 *
	 * @test
	 * @return void
	 */
	public function test_build_api_url_sandbox() {
		$this->setup_settings( ConfigAPIEnum::SANDBOX_ENVIRONMENT_VALUE );
		$this->config_service->init();
		$actual   = $this->config_service->build_api_url();
		$expected = ConfigAPIEnum::SANDBOX_API_URL;

		$this->assertEquals( $expected, $actual, 'Should return the correct sandbox url.' );
	}

	/**
	 * Cleans up test environment after each test method
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}
}
