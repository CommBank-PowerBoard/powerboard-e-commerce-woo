<?php

namespace unit\src\Helpers\DBSettingsHelper;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use PowerBoard\Helpers\DBSettingsHelper;

class ValidateDBKeysTest extends TestCase {
	const ACCESS_TOKEN_KEY              = 'power_board_CREDENTIALS_ACCESS_KEY';
	const ENVIRONMENT_KEY               = 'power_board_ENVIRONMENT_ENVIRONMENT';
	const VERSION_KEY                   = 'power_board_CHECKOUT_VERSION';
	const CONFIGURATION_ID_KEY          = 'power_board_CHECKOUT_CONFIGURATION_ID';
	const CUSTOMISATION_ID_KEY          = 'power_board_CHECKOUT_CUSTOMISATION_ID';
	const AVAILABLE_PAYMENT_METHODS_KEY = 'available_payment_methods';

	/**
	 * Sets up test environment for each test method
	 *
	 * Initializes Brain\Monkey for WordPress function mocking and defines
	 * the POWER_BOARD_PLUGIN_PREFIX constant if not already defined.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		if ( ! defined( 'POWER_BOARD_PLUGIN_PREFIX' ) ) {
			define( 'POWER_BOARD_PLUGIN_PREFIX', 'power_board' );
		}
	}

	/**
	 * Tests that get_access_token_key returns the correct database key
	 *
	 * Verifies the method returns the properly formatted WordPress option key
	 * for storing the PowerBoard access token credentials.
	 *
	 * @test
	 * @return void
	 */
	public function test_get_access_token_key(): void {
		$result   = DBSettingsHelper::get_access_token_key();
		$expected = self::ACCESS_TOKEN_KEY;
		$this->assertEquals( $expected, $result );
	}

	/**
	 * Tests that get_environment_key returns the correct database key
	 *
	 * Verifies the method returns the properly formatted WordPress option key
	 * for storing the PowerBoard environment setting.
	 *
	 * @test
	 * @return void
	 */
	public function test_get_environment_key(): void {
		$result   = DBSettingsHelper::get_environment_key();
		$expected = self::ENVIRONMENT_KEY;
		$this->assertEquals( $expected, $result );
	}

	/**
	 * Tests that get_version_key returns the correct database key
	 *
	 * Verifies the method returns the properly formatted WordPress option key
	 * for storing the PowerBoard checkout version setting.
	 *
	 * @test
	 * @return void
	 */
	public function test_get_version_key(): void {
		$result   = DBSettingsHelper::get_version_key();
		$expected = self::VERSION_KEY;
		$this->assertEquals( $expected, $result );
	}

	/**
	 * Tests that get_configuration_template_key returns the correct database key
	 *
	 * Verifies the method returns the properly formatted WordPress option key
	 * for storing the PowerBoard configuration template ID.
	 *
	 * @test
	 * @return void
	 */
	public function test_get_configuration_template_key(): void {
		$result   = DBSettingsHelper::get_configuration_template_key();
		$expected = self::CONFIGURATION_ID_KEY;
		$this->assertEquals( $expected, $result );
	}

	/**
	 * Tests that get_customisation_template_key returns the correct database key
	 *
	 * Verifies the method returns the properly formatted WordPress option key
	 * for storing the PowerBoard customisation template ID.
	 *
	 * @test
	 * @return void
	 */
	public function test_get_customisation_template_key(): void {
		$result   = DBSettingsHelper::get_customisation_template_key();
		$expected = self::CUSTOMISATION_ID_KEY;
		$this->assertEquals( $expected, $result );
	}

	/**
	 * Tests that get_available_payment_methods_key returns the correct database key
	 *
	 * Verifies the method returns the correct WordPress option key
	 * for storing the available payment methods configuration.
	 *
	 * @test
	 * @return void
	 */
	public function test_get_available_payment_methods_key(): void {
		$result   = DBSettingsHelper::get_available_payment_methods_key();
		$expected = self::AVAILABLE_PAYMENT_METHODS_KEY;
		$this->assertEquals( $expected, $result );
	}

	/**
	 * Tests that get_option_name builds the correct option key from fragments
	 *
	 * Verifies the method correctly combines the plugin prefix with provided
	 * key fragments to create a properly formatted WordPress option name.
	 *
	 * @test
	 * @return void
	 */
	public function test_get_option_name(): void {
		$result   = DBSettingsHelper::get_option_name( [ 'CREDENTIALS', 'ACCESS_KEY' ] );
		$expected = self::ACCESS_TOKEN_KEY;
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
