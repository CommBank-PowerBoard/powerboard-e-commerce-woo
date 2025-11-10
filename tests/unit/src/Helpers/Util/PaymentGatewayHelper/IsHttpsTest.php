<?php

declare( strict_types=1 );

namespace unit\src\Helpers\Util\PaymentGatewayHelper;

use PHPUnit\Framework\TestCase;
use PowerBoard\Helpers\Util\PaymentGatewayHelper;

class IsHttpsTest extends TestCase {
	private ?array $original_server;

	public function setUp(): void {
		parent::setUp();

		// Store original $_SERVER to restore it after each test
		$this->original_server = $_SERVER;
	}

	public function tearDown(): void {
		// Restore original $_SERVER after each test
		$_SERVER = $this->original_server;
		parent::tearDown();
	}

	/**
	 * Test method returns true when $_SERVER['HTTPS'] is set to 'on'
	 */
	public function test_is_https_returns_true_when_https_is_on() {
		$_SERVER['HTTPS'] = 'ON';

		$result = PaymentGatewayHelper::is_https();

		$this->assertTrue( $result );
	}

	/**
	 * Test method returns true when $_SERVER['HTTPS'] is set to 'on'
	 */
	public function test_is_https_returns_true_when_https_is_on1() {
		$_SERVER['HTTPS'] = 'on';

		$result = PaymentGatewayHelper::is_https();

		$this->assertTrue( $result );
	}

	/**
	 * Test method returns true when $_SERVER['HTTPS'] is set to 'on'
	 */
	public function test_is_https_returns_true_when_https_is_on2() {
		$_SERVER['HTTPS'] = 'On';

		$result = PaymentGatewayHelper::is_https();

		$this->assertTrue( $result );
	}

	/**
	 * Test method returns true when $_SERVER['HTTPS'] is set to '1'
	 */
	public function test_is_https_returns_true_when_https_is_one() {
		$_SERVER['HTTPS'] = '1';

		$result = PaymentGatewayHelper::is_https();

		$this->assertTrue( $result );
	}

	/**
	 * Test method returns true when $_SERVER['HTTPS'] is set to 'off'
	 * Note: The current implementation only checks if the key exists, not its value
	 */
	public function test_is_https_returns_true_when_https_is_off() {
		$_SERVER['HTTPS'] = 'off';

		$result = PaymentGatewayHelper::is_https();

		$this->assertFalse( $result );
	}

	/**
	 * Test method returns true when $_SERVER['HTTPS'] is set to '0'
	 * Note: The current implementation only checks if the key exists, not its value
	 */
	public function test_is_https_returns_true_when_https_is_zero() {
		$_SERVER['HTTPS'] = '0';

		$result = PaymentGatewayHelper::is_https();

		$this->assertFalse( $result );
	}

	/**
	 * Test method returns true when $_SERVER['HTTPS'] is set to empty string
	 * Note: The current implementation only checks if the key exists, not its value
	 */
	public function test_is_https_returns_true_when_https_is_empty_string() {
		$_SERVER['HTTPS'] = '';

		$result = PaymentGatewayHelper::is_https();

		$this->assertFalse( $result );
	}

	/**
	 * Test method returns false when $_SERVER['HTTPS'] is not set
	 */
	public function test_is_https_returns_false_when_https_is_not_set() {
		unset( $_SERVER['HTTPS'] );

		$result = PaymentGatewayHelper::is_https();

		$this->assertFalse( $result );
	}

	/**
	 * Test method returns false when $_SERVER is empty
	 */
	public function test_is_https_returns_false_when_server_is_empty() {
		$_SERVER = [];

		$result = PaymentGatewayHelper::is_https();

		$this->assertFalse( $result );
	}

	/**
	 * Test method returns true when $_SERVER['HTTPS'] is set to boolean true
	 */
	public function test_is_https_returns_true_when_https_is_boolean_true() {
		$_SERVER['HTTPS'] = true;

		$result = PaymentGatewayHelper::is_https();

		$this->assertTrue( $result );
	}

	/**
	 * Test method returns true when $_SERVER['HTTPS'] is set to boolean false
	 * Note: The current implementation only checks if the key exists, not its value
	 */
	public function test_is_https_returns_true_when_https_is_boolean_false() {
		$_SERVER['HTTPS'] = false;

		$result = PaymentGatewayHelper::is_https();

		$this->assertFalse( $result );
	}

	/**
	 * Test method returns true when $_SERVER['HTTPS'] is set to integer 1
	 */
	public function test_is_https_returns_true_when_https_is_integer_one() {
		$_SERVER['HTTPS'] = 1;

		$result = PaymentGatewayHelper::is_https();

		$this->assertTrue( $result );
	}

	/**
	 * Test method returns true when $_SERVER['HTTPS'] is set to integer 0
	 * Note: The current implementation only checks if the key exists, not its value
	 */
	public function test_is_https_returns_true_when_https_is_integer_zero() {
		$_SERVER['HTTPS'] = 0;

		$result = PaymentGatewayHelper::is_https();

		$this->assertFalse( $result );
	}

	/**
	 * Test method handles case when $_SERVER has other keys but not HTTPS
	 */
	public function test_is_https_returns_false_when_server_has_other_keys_but_not_https() {
		$_SERVER = [
			'HTTP_HOST'      => 'example.com',
			'REQUEST_METHOD' => 'GET',
			'SERVER_NAME'    => 'example.com',
		];

		$result = PaymentGatewayHelper::is_https();

		$this->assertFalse( $result );
	}

	/**
	 * Test method returns true when $_SERVER['HTTPS'] is set alongside other keys
	 */
	public function test_is_https_returns_true_when_https_is_set_with_other_keys() {
		$_SERVER = [
			'HTTP_HOST'      => 'example.com',
			'REQUEST_METHOD' => 'GET',
			'SERVER_NAME'    => 'example.com',
			'HTTPS'          => 'on',
		];

		$result = PaymentGatewayHelper::is_https();

		$this->assertTrue( $result );
	}
}
