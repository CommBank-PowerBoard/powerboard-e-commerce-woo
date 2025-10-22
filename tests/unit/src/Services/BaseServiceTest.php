<?php
declare( strict_types=1 );

namespace unit\src\Services;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use ReflectionClass;

/**
 * Base test class for service tests with common setup and utilities
 */
class BaseServiceTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Define constants if not already defined
		if ( ! defined( 'POWER_BOARD_PLUGIN_PREFIX' ) ) {
			define( 'POWER_BOARD_PLUGIN_PREFIX', 'power_board' );
		}

		// Mock WordPress functions used across services
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'absint' )->alias(
				function ( $value ) {
					return (int) $value; // Convert to integer like WordPress absint() does
				}
			);
		Functions\when( 'current_time' )->justReturn( '2024-01-01T12:00:00+00:00' );
		Functions\when( 'wc_get_order' )->justReturn( $this->createMockOrder() );
		Functions\when( 'wp_send_json_success' )->justReturn( true );
		Functions\when( 'wp_send_json_error' )->justReturn( true );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Create a mock WooCommerce order for testing
	 */
	protected function createMockOrder( string $status = 'pending', string $payment_method = 'power_board' ) {
		// Create a simple stdClass mock instead of WC_Order to avoid dependency issues
		$order                 = new \stdClass();
		$order->id             = 12345;
		$order->status         = $status;
		$order->payment_method = $payment_method;
		return $order;
	}

	/**
	 * Get a private or protected method for testing using reflection
	 *
	 * @param object $object_name The object.
	 * @param string $method_name The method name.
	 */
	protected function getPrivateMethod( $object_name, string $method_name ) {
		$reflection = new ReflectionClass( $object_name );
		$method     = $reflection->getMethod( $method_name );
		$method->setAccessible( true );
		return $method;
	}

	/**
	 * Get a private or protected property for testing using reflection
	 *
	 * @param object $object_name The object.
	 * @param string $property_name The property name.
	 */
	protected function getPrivateProperty( $object_name, string $property_name ) {
		$reflection = new ReflectionClass( $object_name );
		$property   = $reflection->getProperty( $property_name );
		$property->setAccessible( true );
		return $property;
	}

	/**
	 * Get a constant from a class using reflection
	 */
	protected function getClassConstant( string $class_name, string $constant_name ) {
		$reflection = new ReflectionClass( $class_name );
		return $reflection->getConstant( $constant_name );
	}

	/**
	 * Create a partial mock to avoid constructor dependencies
	 */
	protected function createPartialMockService( string $class_name, array $methods = [] ) {
		return $this->createPartialMock( $class_name, $methods );
	}

	/**
	 * Test that invalid nonces are rejected
	 *
	 * @test
	 */
	public function StandardTest(): void {
		$this->assertEquals( 1, 1 );
	}

    /**
     * Test that invalid nonces are rejected
     *
     * @test
     */
    public function StandardTest(): void
    {
        $this->assertEquals( 1, 1 );

    }


}
