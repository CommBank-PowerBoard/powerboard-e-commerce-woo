<?php

namespace unit\src\Helpers\DBSettingsHelper;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PowerBoard\Helpers\DBSettingsHelper;

class ValidateGetSettingsFromDBTest extends TestCase {
	protected $settings = [];

	const ACCESS_TOKEN_KEY              = 'power_board_CREDENTIALS_ACCESS_KEY';
	const ENVIRONMENT_KEY               = 'power_board_ENVIRONMENT_ENVIRONMENT';
	const VERSION_KEY                   = 'power_board_CHECKOUT_VERSION';
	const CONFIGURATION_ID_KEY          = 'power_board_CHECKOUT_CONFIGURATION_ID';
	const CUSTOMISATION_ID_KEY          = 'power_board_CHECKOUT_CUSTOMISATION_ID';
	const AVAILABLE_PAYMENT_METHODS_KEY = 'available_payment_methods';

	/**
	 * Sets up test environment for each test method
	 *
	 * Initializes Brain\Monkey for WordPress function mocking, defines
	 * the POWER_BOARD_PLUGIN_PREFIX constant, and sets up mock settings data
	 * for database retrieval testing.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		if ( ! defined( 'POWER_BOARD_PLUGIN_PREFIX' ) ) {
			define( 'POWER_BOARD_PLUGIN_PREFIX', 'power_board' );
		}

		$this->settings = [
			self::ACCESS_TOKEN_KEY              => 'access_token',
			self::ENVIRONMENT_KEY               => 'environment',
			self::VERSION_KEY                   => 'version',
			self::CONFIGURATION_ID_KEY          => 'configuration_template',
			self::CUSTOMISATION_ID_KEY          => 'customisation_template',
			self::AVAILABLE_PAYMENT_METHODS_KEY => [],
		];
	}

	/**
	 * Tests that get_powerboard_settings returns correctly formatted settings array
	 *
	 * Verifies the method retrieves settings from WordPress options and returns
	 * them in the correct format with proper local key mappings for internal use.
	 *
	 * @test
	 * @return void
	 */
	public function test_get_powerboard_settings(): void {
		Functions\expect( 'get_option' )
			->once()
			->andReturn( $this->settings );

		$result   = DBSettingsHelper::get_powerboard_settings();
		$expected = [
			DBSettingsHelper::LOCAL_ENVIRONMENT_ID  => $this->settings[ self::ENVIRONMENT_KEY ],
			DBSettingsHelper::LOCAL_ACCESS_TOKEN_ID => $this->settings[ self::ACCESS_TOKEN_KEY ],
			DBSettingsHelper::LOCAL_VERSION_ID      => $this->settings[ self::VERSION_KEY ],
			DBSettingsHelper::LOCAL_CONFIGURATION_TEMPLATE_ID => $this->settings[ self::CONFIGURATION_ID_KEY ],
			DBSettingsHelper::LOCAL_CUSTOMISATION_TEMPLATE_ID => $this->settings[ self::CUSTOMISATION_ID_KEY ],
			DBSettingsHelper::LOCAL_AVAILABLE_PAYMENT_METHODS_ID => $this->settings[ self::AVAILABLE_PAYMENT_METHODS_KEY ],
		];
		$this->assertEquals( $expected, $result );
	}

	/**
	 * Tests that get_access_token retrieves and decrypts the access token
	 *
	 * Verifies the method correctly retrieves the encrypted access token from
	 * WordPress options and returns the decrypted value for API usage.
	 *
	 * @test
	 * @return void
	 */
	public function test_get_access_token(): void {
		Functions\expect( 'get_option' )
			->once()
			->andReturn( $this->settings );

		$result   = DBSettingsHelper::get_access_token();
		$expected = $this->settings[ self::ACCESS_TOKEN_KEY ];
		$this->assertEquals( $expected, $result );
	}

	/**
	 * Tests that get_environment retrieves the environment setting
	 *
	 * Verifies the method correctly retrieves the PowerBoard environment
	 * configuration (production, staging, or sandbox) from WordPress options.
	 *
	 * @test
	 * @return void
	 */
	public function test_get_environment(): void {
		Functions\expect( 'get_option' )
			->once()
			->andReturn( $this->settings );

		$result   = DBSettingsHelper::get_environment();
		$expected = $this->settings[ self::ENVIRONMENT_KEY ];
		$this->assertEquals( $expected, $result );
	}

	/**
	 * Tests that get_version retrieves the checkout version setting
	 *
	 * Verifies the method correctly retrieves the PowerBoard checkout version
	 * configuration from WordPress options.
	 *
	 * @test
	 * @return void
	 */
	public function test_get_version(): void {
		Functions\expect( 'get_option' )
			->once()
			->andReturn( $this->settings );

		$result   = DBSettingsHelper::get_version();
		$expected = $this->settings[ self::VERSION_KEY ];
		$this->assertEquals( $expected, $result );
	}

	/**
	 * Tests that get_configuration_id retrieves the configuration template ID
	 *
	 * Verifies the method correctly retrieves the PowerBoard configuration
	 * template identifier from WordPress options.
	 *
	 * @test
	 * @return void
	 */
	public function test_get_configuration_id(): void {
		Functions\expect( 'get_option' )
			->once()
			->andReturn( $this->settings );

		$result   = DBSettingsHelper::get_configuration_id();
		$expected = $this->settings[ self::CONFIGURATION_ID_KEY ];
		$this->assertEquals( $expected, $result );
	}

	/**
	 * Tests that get_customisation_id retrieves the customisation template ID
	 *
	 * Verifies the method correctly retrieves the PowerBoard customisation
	 * template identifier from WordPress options.
	 *
	 * @test
	 * @return void
	 */
	public function test_get_customisation_id(): void {
		Functions\expect( 'get_option' )
			->once()
			->andReturn( $this->settings );

		$result   = DBSettingsHelper::get_customisation_id();
		$expected = $this->settings[ self::CUSTOMISATION_ID_KEY ];
		$this->assertEquals( $expected, $result );
	}

	/**
	 * Tests that get_available_payment_methods retrieves payment method configuration
	 *
	 * Verifies the method correctly retrieves the available payment methods
	 * configuration array from WordPress options.
	 *
	 * @test
	 * @return void
	 */
	public function test_get_available_payment_methods(): void {
		Functions\expect( 'get_option' )
			->once()
			->andReturn( $this->settings );

		$result   = DBSettingsHelper::get_available_payment_methods();
		$expected = $this->settings[ self::AVAILABLE_PAYMENT_METHODS_KEY ];
		$this->assertEquals( $expected, $result );
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
