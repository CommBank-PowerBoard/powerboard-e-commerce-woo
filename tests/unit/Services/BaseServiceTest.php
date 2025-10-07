<?php
declare( strict_types=1 );

namespace unit\Services;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use ReflectionClass;

/**
 * Base test class for service tests with common setup and utilities
 */
abstract class BaseServiceTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		
		// Define constants if not already defined
		if ( ! defined( 'POWER_BOARD_PLUGIN_PREFIX' ) ) {
			define( 'POWER_BOARD_PLUGIN_PREFIX', 'power_board' );
		}
		
		// Mock WordPress functions used across services
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'absint' )->alias( function( $value ) {
			return (int) $value; // Convert to integer like WordPress absint() does
		});
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
		$order = new \stdClass();
		$order->id = 12345;
		$order->status = $status;
		$order->payment_method = $payment_method;
		return $order;
	}

	/**
	 * Get a private or protected method for testing using reflection
	 */
	protected function getPrivateMethod( object $object, string $methodName ) {
		$reflection = new ReflectionClass( $object );
		$method = $reflection->getMethod( $methodName );
		$method->setAccessible( true );
		return $method;
	}

	/**
	 * Get a private or protected property for testing using reflection
	 */
	protected function getPrivateProperty( object $object, string $propertyName ) {
		$reflection = new ReflectionClass( $object );
		$property = $reflection->getProperty( $propertyName );
		$property->setAccessible( true );
		return $property;
	}

	/**
	 * Get a constant from a class using reflection
	 */
	protected function getClassConstant( string $className, string $constantName ) {
		$reflection = new ReflectionClass( $className );
		return $reflection->getConstant( $constantName );
	}

	/**
	 * Create a partial mock to avoid constructor dependencies
	 */
	protected function createPartialMockService( string $className, array $methods = [] ) {
		return $this->createPartialMock( $className, $methods );
	}
}
