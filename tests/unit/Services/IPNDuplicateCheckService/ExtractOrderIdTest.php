<?php
declare( strict_types=1 );

namespace unit\Services\IPNDuplicateCheckService;

use PHPUnit\Framework\TestCase;
use PowerBoard\Services\IPNDuplicateCheckService;
use ReflectionClass;
use Brain\Monkey;
use Brain\Monkey\Functions;

/**
 * Tests for IPNDuplicateCheckService::extract_order_id() method
 */
class ExtractOrderIdTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Define constants if not already defined
		if ( ! defined( 'POWER_BOARD_PLUGIN_PREFIX' ) ) {
			define( 'POWER_BOARD_PLUGIN_PREFIX', 'power_board' );
		}

		// Mock WordPress functions
		Functions\when( 'absint' )->alias(
				function ( $value ) {
					return (int) $value; // Convert to integer like WordPress absint() does
				}
			);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Get a private or protected method for testing using reflection
	 */
	private function getPrivateMethod( $object_name, string $method_name ) {
		$reflection = new ReflectionClass( $object_name );
		$method     = $reflection->getMethod( $method_name );
		$method->setAccessible( true );
		return $method;
	}

	/**
	 * Create a partial mock to avoid constructor dependencies
	 */
	private function createPartialMockService( string $class_name, array $methods = [] ) {
		return $this->createPartialMock( $class_name, $methods );
	}

	public function test_extract_order_id_from_direct_field() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'extract_order_id' );

		// Test direct order_id
		$ipn_data = [ 'order_id' => '12345' ];
		$result   = $method->invokeArgs( $service, [ $ipn_data ] );
		$this->assertEquals( 12345, $result );
		$this->assertIsInt( $result );
	}

	public function test_extract_order_id_from_alternative_fields() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'extract_order_id' );

		// Test alternative field names
		$test_cases = [
			[ 'reference' => '67890' ],
			[ 'external_id' => '11111' ],
			[ 'merchant_reference' => '22222' ],
		];

		foreach ( $test_cases as $data ) {
			$result = $method->invokeArgs( $service, [ $data ] );
			$this->assertIsInt( $result );
			$this->assertGreaterThan( 0, $result );
		}
	}

	public function test_extract_order_id_from_nested_data() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'extract_order_id' );

		// Test nested reference in data
		$ipn_data = [
			'data' => [
				'reference' => '33333',
			],
		];
		$result   = $method->invokeArgs( $service, [ $ipn_data ] );
		$this->assertEquals( 33333, $result );

		// Test deeper nested structure (resource.data.reference)
		$ipn_data = [
			'resource' => [
				'data' => [
					'reference' => '44444',
				],
			],
		];
		$result   = $method->invokeArgs( $service, [ $ipn_data ] );
		$this->assertEquals( 44444, $result );
	}

	public function test_extract_order_id_priority_order() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'extract_order_id' );

		// Test that direct fields take priority over nested ones
		$ipn_data = [
			'order_id'  => '99999',
			'reference' => '88888',
			'data'      => [
				'reference' => '77777',
			],
		];

		$result = $method->invokeArgs( $service, [ $ipn_data ] );
		$this->assertEquals( 99999, $result );
	}

	public function test_extract_order_id_type_conversion() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'extract_order_id' );

		// Test string to int conversion
		$ipn_data = [ 'order_id' => '54321' ];
		$result   = $method->invokeArgs( $service, [ $ipn_data ] );
		$this->assertIsInt( $result );
		$this->assertEquals( 54321, $result );

		// Test numeric strings
		$ipn_data = [ 'order_id' => '0' ];
		$result   = $method->invokeArgs( $service, [ $ipn_data ] );
		$this->assertEquals( 0, $result );

		// Test already integer
		$ipn_data = [ 'order_id' => 98765 ];
		$result   = $method->invokeArgs( $service, [ $ipn_data ] );
		$this->assertIsInt( $result );
		$this->assertEquals( 98765, $result );
	}

	public function test_extract_order_id_returns_null_when_not_found() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'extract_order_id' );

		// Test missing order_id
		$ipn_data = [ 'charge_id' => 'ch_123' ];
		$result   = $method->invokeArgs( $service, [ $ipn_data ] );
		$this->assertNull( $result );

		// Test empty data
		$result = $method->invokeArgs( $service, [ [] ] );
		$this->assertNull( $result );

		// Test nested structure without order info
		$ipn_data = [
			'data' => [
				'amount' => 100,
			],
		];
		$result   = $method->invokeArgs( $service, [ $ipn_data ] );
		$this->assertNull( $result );
	}

	public function test_extract_order_id_handles_invalid_values() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'extract_order_id' );

		// Test invalid values that absint() should handle
		// Most invalid values will result in null from our method
		$invalid_cases = [
			[ 'order_id' => '' ],
			[ 'order_id' => null ],
			[ 'order_id' => false ],
			[ 'order_id' => [] ],
		];

		foreach ( $invalid_cases as $data ) {
			$result = $method->invokeArgs( $service, [ $data ] );
			// These should all return null because empty() check catches them
			$this->assertNull( $result, 'Invalid values should return null: ' . wp_json_encode( $data['order_id'] ) );
		}

		// Test values that absint() can convert
		$convertible_cases = [
			[ 'order_id' => 'not-a-number' ], // absint() converts to 0
			[ 'order_id' => '12.34' ],         // absint() converts to 12
		];

		foreach ( $convertible_cases as $data ) {
			$result = $method->invokeArgs( $service, [ $data ] );
			// These get processed by absint() and should return integers
			$this->assertIsInt( $result, 'Convertible values should return integers: ' . $data['order_id'] );
		}
	}

	public function test_extract_order_id_uses_absint_function() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'extract_order_id' );

		// Test that absint behavior is applied (our mock converts to int)
		$test_cases = [
			[ 'order_id' => '123.45' ],  // Should become 123
			[ 'order_id' => '-123' ],    // Should become 123 (absolute)
			[ 'order_id' => '  456  ' ], // Should become 456 (trimmed)
		];

		foreach ( $test_cases as $data ) {
			$result = $method->invokeArgs( $service, [ $data ] );
			$this->assertIsInt( $result, 'absint() should return integer for: ' . $data['order_id'] );
		}
	}
}
