<?php

declare( strict_types=1 );

namespace unit\src\Helpers\Util\PaymentGatewayHelper;

use PHPUnit\Framework\TestCase;
use PowerBoard\Helpers\Util\PaymentGatewayHelper;
use Brain\Monkey;
use Brain\Monkey\Functions;

class IsHttpsTest extends TestCase {
	private ?array $original_server;

	public function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Store original $_SERVER to restore it after each test
		$this->original_server = $_SERVER ?? [];
	}

	public function tearDown(): void {
		// Restore original $_SERVER after each test
		$_SERVER = $this->original_server;
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test method returns true when is_ssl() returns true
	 */
	public function test_is_https_returns_true_when_is_ssl_returns_true() {
		Functions\expect( 'is_ssl' )
			->once()
			->andReturn( true );

		$result = PaymentGatewayHelper::is_https();

		$this->assertTrue( $result );
	}

	/**
	 * Test method returns false when is_ssl() returns false and HTTP_X_FORWARDED_PROTO and HTTP_X_FORWARDED_SSL don't exist
	 */
	public function test_is_https_returns_false() {
		Functions\expect( 'is_ssl' )
			->once()
			->andReturn( false );

		$result = PaymentGatewayHelper::is_https();

		$this->assertFalse( $result );
	}

	/**
	 * Test method returns true when HTTP_X_FORWARDED_PROTO is set to 'on'
	 */
	public function test_is_https_returns_true_when_forwarded_proto_is_on() {
		$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'on';

		Functions\expect( 'is_ssl' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_unslash' )
			->once()
			->with( 'on' )
			->andReturn( 'on' );

		$result = PaymentGatewayHelper::is_https();

		$this->assertTrue( $result );
	}

	/**
	 * Test method returns true when HTTP_X_FORWARDED_PROTO is set to '1'
	 */
	public function test_is_https_returns_true_when_forwarded_proto_is_one() {
		$_SERVER['HTTP_X_FORWARDED_PROTO'] = '1';

		Functions\expect( 'is_ssl' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_unslash' )
			->once()
			->with( '1' )
			->andReturn( '1' );

		$result = PaymentGatewayHelper::is_https();

		$this->assertTrue( $result );
	}

	/**
	 * Test method returns false when HTTP_X_FORWARDED_PROTO is set to 'https' (not supported by implementation)
	 */
	public function test_is_https_returns_false_when_forwarded_proto_is_https() {
		$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';

		Functions\expect( 'is_ssl' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_unslash' )
			->once()
			->with( 'https' )
			->andReturn( 'https' );

		$result = PaymentGatewayHelper::is_https();

		$this->assertTrue( $result );
	}

	/**
	 * Test method returns false when HTTP_X_FORWARDED_PROTO is set to 'http'
	 */
	public function test_is_https_returns_false_when_forwarded_proto_is_http() {
		$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'http';

		Functions\expect( 'is_ssl' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_unslash' )
			->once()
			->with( 'http' )
			->andReturn( 'http' );

		$result = PaymentGatewayHelper::is_https();

		$this->assertFalse( $result );
	}

	/**
	 * Test method returns true when HTTP_X_FORWARDED_SSL is set to 'on'
	 */
	public function test_is_https_returns_true_when_forwarded_ssl_is_on() {
		$_SERVER['HTTP_X_FORWARDED_SSL'] = 'on';

		Functions\expect( 'is_ssl' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_unslash' )
			->once()
			->with( 'on' )
			->andReturn( 'on' );

		$result = PaymentGatewayHelper::is_https();

		$this->assertTrue( $result );
	}

	/**
	 * Test method returns true when HTTP_X_FORWARDED_SSL is set to 'ON' (uppercase)
	 */
	public function test_is_https_returns_true_when_forwarded_ssl_is_on_uppercase() {
		$_SERVER['HTTP_X_FORWARDED_SSL'] = 'ON';

		Functions\expect( 'is_ssl' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_unslash' )
			->once()
			->with( 'ON' )
			->andReturn( 'ON' );

		$result = PaymentGatewayHelper::is_https();

		$this->assertTrue( $result );
	}

	/**
	 * Test method returns true when HTTP_X_FORWARDED_SSL is set to '1'
	 */
	public function test_is_https_returns_true_when_forwarded_ssl_is_one() {
		$_SERVER['HTTP_X_FORWARDED_SSL'] = '1';

		Functions\expect( 'is_ssl' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_unslash' )
			->once()
			->with( '1' )
			->andReturn( '1' );

		$result = PaymentGatewayHelper::is_https();

		$this->assertTrue( $result );
	}

	/**
	 * Test method returns true when HTTP_X_FORWARDED_SSL is set to integer 1
	 */
	public function test_is_https_returns_true_when_forwarded_ssl_is_integer_one() {
		$_SERVER['HTTP_X_FORWARDED_SSL'] = 1;

		Functions\expect( 'is_ssl' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_unslash' )
			->once()
			->with( 1 )
			->andReturn( 1 );

		$result = PaymentGatewayHelper::is_https();

		$this->assertTrue( $result );
	}

	/**
	 * Test method returns false when HTTP_X_FORWARDED_SSL is set to 'off'
	 */
	public function test_is_https_returns_false_when_forwarded_ssl_is_off() {
		$_SERVER['HTTP_X_FORWARDED_SSL'] = 'off';

		Functions\expect( 'is_ssl' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_unslash' )
			->once()
			->with( 'off' )
			->andReturn( 'off' );

		$result = PaymentGatewayHelper::is_https();

		$this->assertFalse( $result );
	}

	/**
	 * Test method returns false when HTTP_X_FORWARDED_SSL is set to '0'
	 */
	public function test_is_https_returns_false_when_forwarded_ssl_is_zero() {
		$_SERVER['HTTP_X_FORWARDED_SSL'] = '0';

		Functions\expect( 'is_ssl' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_unslash' )
			->once()
			->with( '0' )
			->andReturn( '0' );

		$result = PaymentGatewayHelper::is_https();

		$this->assertFalse( $result );
	}

	/**
	 * Test method returns false when HTTP_X_FORWARDED_SSL is set to integer 0
	 */
	public function test_is_https_returns_false_when_forwarded_ssl_is_integer_zero() {
		$_SERVER['HTTP_X_FORWARDED_SSL'] = 0;

		Functions\expect( 'is_ssl' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_unslash' )
			->once()
			->with( 0 )
			->andReturn( 0 );

		$result = PaymentGatewayHelper::is_https();

		$this->assertFalse( $result );
	}

	/**
	 * Test method returns false when HTTP_X_FORWARDED_SSL is set to empty string
	 */
	public function test_is_https_returns_false_when_forwarded_ssl_is_empty_string() {
		$_SERVER['HTTP_X_FORWARDED_SSL'] = '';

		Functions\expect( 'is_ssl' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_unslash' )
			->once()
			->with( '' )
			->andReturn( '' );

		$result = PaymentGatewayHelper::is_https();

		$this->assertFalse( $result );
	}

	/**
	 * Test method returns false when neither HTTP_X_FORWARDED_PROTO nor HTTP_X_FORWARDED_SSL are set
	 */
	public function test_is_https_returns_false_when_no_proxy_headers_are_set() {
		unset( $_SERVER['HTTP_X_FORWARDED_PROTO'] );
		unset( $_SERVER['HTTP_X_FORWARDED_SSL'] );

		Functions\expect( 'is_ssl' )
			->once()
			->andReturn( false );

		$result = PaymentGatewayHelper::is_https();

		$this->assertFalse( $result );
	}

	/**
	 * Test method returns false when $_SERVER is empty
	 */
	public function test_is_https_returns_false_when_server_is_empty() {
		$_SERVER = [];

		Functions\expect( 'is_ssl' )
			->once()
			->andReturn( false );

		$result = PaymentGatewayHelper::is_https();

		$this->assertFalse( $result );
	}

	/**
	 * Test method returns true when HTTP_X_FORWARDED_PROTO is set to 'on' alongside other keys
	 */
	public function test_is_https_returns_true_when_forwarded_proto_is_set_with_other_keys() {
		$_SERVER = [
			'HTTP_HOST'              => 'example.com',
			'REQUEST_METHOD'         => 'GET',
			'SERVER_NAME'            => 'example.com',
			'HTTP_X_FORWARDED_PROTO' => 'on',
		];

		Functions\expect( 'is_ssl' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_unslash' )
			->once()
			->with( 'on' )
			->andReturn( 'on' );

		$result = PaymentGatewayHelper::is_https();

		$this->assertTrue( $result );
	}

	/**
	 * Test method returns true when HTTP_X_FORWARDED_SSL is set alongside other keys
	 */
	public function test_is_https_returns_true_when_forwarded_ssl_is_set_with_other_keys() {
		$_SERVER = [
			'HTTP_HOST'            => 'example.com',
			'REQUEST_METHOD'       => 'GET',
			'SERVER_NAME'          => 'example.com',
			'HTTP_X_FORWARDED_SSL' => 'on',
		];

		Functions\expect( 'is_ssl' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_unslash' )
			->once()
			->with( 'on' )
			->andReturn( 'on' );

		$result = PaymentGatewayHelper::is_https();

		$this->assertTrue( $result );
	}

	/**
	 * Test method returns true when HTTP_X_FORWARDED_PROTO is set to 'on' even if HTTP_X_FORWARDED_SSL is 'off'
	 */
	public function test_is_https_returns_true_when_forwarded_proto_on_takes_precedence() {
		$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'on';
		$_SERVER['HTTP_X_FORWARDED_SSL']   = 'off';

		Functions\expect( 'is_ssl' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_unslash' )
			->once()
			->with( 'on' )
			->andReturn( 'on' );

		$result = PaymentGatewayHelper::is_https();

		$this->assertTrue( $result );
	}
}
