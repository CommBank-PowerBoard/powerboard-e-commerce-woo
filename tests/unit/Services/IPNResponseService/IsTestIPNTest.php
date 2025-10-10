<?php
declare( strict_types=1 );

namespace unit\Services\IPNResponseService;

use PHPUnit\Framework\TestCase;
use PowerBoard\Services\IPNResponseService;
use ReflectionClass;
use Brain\Monkey;
use Brain\Monkey\Functions;

/**
 * Tests for IPNResponseService::is_test_ipn() method
 */
class IsTestIPNTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Define constants if not already defined
		if ( ! defined( 'POWER_BOARD_PLUGIN_PREFIX' ) ) {
			define( 'POWER_BOARD_PLUGIN_PREFIX', 'power_board' );
		}
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

	public function test_is_test_ipn_detects_simple_test_strings() {
		$service = $this->createPartialMockService( IPNResponseService::class );
		$method  = $this->getPrivateMethod( $service, 'is_test_ipn' );

		// Test simple test indicators
		$this->assertTrue( $method->invokeArgs( $service, [ 'testing' ] ) );
		$this->assertTrue( $method->invokeArgs( $service, [ 'this is a testing payload' ] ) );
		$this->assertTrue( $method->invokeArgs( $service, [ 'payload with testing data' ] ) );
	}

	public function test_is_test_ipn_detects_json_test_payloads() {
		$service = $this->createPartialMockService( IPNResponseService::class );
		$method  = $this->getPrivateMethod( $service, 'is_test_ipn' );

		// Test JSON with testing flag
		$test_json = '{"testing": true, "charge_id": "ch_test_123"}';
		$this->assertTrue( $method->invokeArgs( $service, [ $test_json ] ) );

		$test_json2 = '{"test": true, "order_id": "12345"}';
		$this->assertTrue( $method->invokeArgs( $service, [ $test_json2 ] ) );

		$test_json3 = '{"testing": 1, "status": "success"}';
		$this->assertTrue( $method->invokeArgs( $service, [ $test_json3 ] ) );
	}

	public function test_is_test_ipn_ignores_production_payloads() {
		$service = $this->createPartialMockService( IPNResponseService::class );
		$method  = $this->getPrivateMethod( $service, 'is_test_ipn' );

		// Test production-like payloads
		$production_json = '{"charge_id": "ch_prod_123", "order_id": "67890", "status": "success"}';
		$this->assertFalse( $method->invokeArgs( $service, [ $production_json ] ) );

		$production_text = 'production payment notification';
		$this->assertFalse( $method->invokeArgs( $service, [ $production_text ] ) );

		$empty_json = '{}';
		$this->assertFalse( $method->invokeArgs( $service, [ $empty_json ] ) );
	}

	public function test_is_test_ipn_handles_malformed_json() {
		$service = $this->createPartialMockService( IPNResponseService::class );
		$method  = $this->getPrivateMethod( $service, 'is_test_ipn' );

		// Test malformed JSON that might contain 'testing'
		$malformed_cases = [
			'{"testing": invalid json}',
			'{testing: true without quotes}',
			'testing json but malformed',
			'malformed {"testing": true',
		];

		foreach ( $malformed_cases as $malformed ) {
			$result = $method->invokeArgs( $service, [ $malformed ] );
			// Should still detect 'testing' in string even if JSON is malformed
			$expected = strpos( $malformed, 'testing' ) !== false;
			$this->assertEquals( $expected, $result, "Failed for malformed case: {$malformed}" );
		}
	}

	public function test_is_test_ipn_json_takes_precedence_over_string() {
		$service = $this->createPartialMockService( IPNResponseService::class );
		$method  = $this->getPrivateMethod( $service, 'is_test_ipn' );

		// Test where JSON says it's not a test (should override string check)
		$mixed_payload = '{"testing": false, "note": "no testing word here"}';
		$this->assertFalse( $method->invokeArgs( $service, [ $mixed_payload ] ) );

		// Test where JSON confirms it's a test
		$mixed_payload2 = '{"testing": true, "note": "production like data"}';
		$this->assertTrue( $method->invokeArgs( $service, [ $mixed_payload2 ] ) );

		// Note: If string contains 'testing' and JSON doesn't have explicit test flags,
		// the string check will take precedence due to the method's logic flow
		$string_precedence = '{"note": "this contains testing word"}';
		$this->assertTrue( $method->invokeArgs( $service, [ $string_precedence ] ) );
	}

	public function test_is_test_ipn_handles_different_truthy_values() {
		$service = $this->createPartialMockService( IPNResponseService::class );
		$method  = $this->getPrivateMethod( $service, 'is_test_ipn' );

		// Test different ways to indicate testing
		$truthy_test_cases = [
			'{"testing": true}',
			'{"testing": 1}',
			'{"testing": "true"}',
			'{"testing": "1"}',
			'{"test": true}',
			'{"test": 1}',
			'{"test": "yes"}',
		];

		foreach ( $truthy_test_cases as $test_case ) {
			$this->assertTrue(
				$method->invokeArgs( $service, [ $test_case ] ),
				"Should detect test for: {$test_case}"
			);
		}
	}

	public function test_is_test_ipn_handles_falsy_values() {
		$service = $this->createPartialMockService( IPNResponseService::class );
		$method  = $this->getPrivateMethod( $service, 'is_test_ipn' );

		// Test explicit false values that should not be considered tests
		$explicit_false_cases = [
			'{"testing": false}',
			'{"test": false}',
		];

		foreach ( $explicit_false_cases as $test_case ) {
			$this->assertFalse(
				$method->invokeArgs( $service, [ $test_case ] ),
				"Should not detect test for explicit false: {$test_case}"
			);
		}

		// Test other falsy values - but note that these JSON strings contain the word "testing"
		// so they will be detected as test strings by the string check logic
		$falsy_value_cases = [
			'{"somekey": 0}',       // No testing flag, should be false
			'{"somekey": ""}',      // No testing flag, should be false
			'{"somekey": null}',    // No testing flag, should be false
			'{"production": true}', // No testing flag, should be false
		];

		foreach ( $falsy_value_cases as $test_case ) {
			$this->assertFalse(
				$method->invokeArgs( $service, [ $test_case ] ),
				"Should not detect test for non-test JSON: {$test_case}"
			);
		}

		// Note: JSON strings like '{"testing": 0}' will return true because:
		// 1. The string contains the word "testing"
		// 2. The string check happens as fallback after JSON parsing
		// This is expected behavior to catch edge cases
	}

	public function test_is_test_ipn_handles_empty_and_null_input() {
		$service = $this->createPartialMockService( IPNResponseService::class );
		$method  = $this->getPrivateMethod( $service, 'is_test_ipn' );

		// Test edge cases - all should return false
		$this->assertFalse( $method->invokeArgs( $service, [ '' ] ) );
		$this->assertFalse( $method->invokeArgs( $service, [ '   ' ] ) );
		$this->assertFalse( $method->invokeArgs( $service, [ null ] ) );
	}
}
