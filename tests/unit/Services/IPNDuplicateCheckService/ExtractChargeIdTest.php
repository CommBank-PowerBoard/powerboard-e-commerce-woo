<?php
declare( strict_types=1 );

namespace unit\Services\IPNDuplicateCheckService;

use unit\Services\BaseServiceTest;
use PowerBoard\Services\IPNDuplicateCheckService;

/**
 * Tests for IPNDuplicateCheckService::extract_charge_id() method
 */
class ExtractChargeIdTest extends BaseServiceTest {

	public function test_extract_charge_id_from_direct_field() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'extract_charge_id' );

		// Test direct charge_id
		$ipn_data = [ 'charge_id' => 'ch_direct_123' ];
		$result   = $method->invokeArgs( $service, [ $ipn_data ] );
		$this->assertEquals( 'ch_direct_123', $result );
	}

	public function test_extract_charge_id_from_alternative_fields() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'extract_charge_id' );

		// Test alternative field names
		$test_cases = [
			[ 'id' => 'ch_alt_456' ],
			[ 'charge' => 'ch_alt_789' ],
			[ 'transaction_id' => 'ch_trans_123' ],
		];

		foreach ( $test_cases as $data ) {
			$result = $method->invokeArgs( $service, [ $data ] );
			$this->assertNotNull( $result );
			$this->assertStringStartsWith( 'ch_', $result );
		}
	}

	public function test_extract_charge_id_from_nested_data() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'extract_charge_id' );

		// Test nested data structure (data.id)
		$ipn_data = [
			'data' => [
				'id' => 'ch_nested_456',
			],
		];
		$result   = $method->invokeArgs( $service, [ $ipn_data ] );
		$this->assertEquals( 'ch_nested_456', $result );

		// Test deeper nested structure (resource.data.id)
		$ipn_data = [
			'resource' => [
				'data' => [
					'id' => 'ch_deep_789',
				],
			],
		];
		$result   = $method->invokeArgs( $service, [ $ipn_data ] );
		$this->assertEquals( 'ch_deep_789', $result );
	}

	public function test_extract_charge_id_priority_order() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'extract_charge_id' );

		// Test that direct fields take priority over nested ones
		$ipn_data = [
			'charge_id' => 'ch_priority_direct',
			'id'        => 'ch_priority_alt',
			'data'      => [
				'id' => 'ch_priority_nested',
			],
		];

		$result = $method->invokeArgs( $service, [ $ipn_data ] );
		$this->assertEquals( 'ch_priority_direct', $result );
	}

	public function test_extract_charge_id_sanitization() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'extract_charge_id' );

		// Test that sanitize_text_field is applied (mocked to return input)
		$ipn_data = [ 'charge_id' => '  ch_spaces_123  ' ];
		$result   = $method->invokeArgs( $service, [ $ipn_data ] );
		$this->assertEquals( '  ch_spaces_123  ', $result );  // Our mock returns input as-is
	}

	public function test_extract_charge_id_returns_null_when_not_found() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'extract_charge_id' );

		// Test missing charge_id
		$ipn_data = [ 'order_id' => '12345' ];
		$result   = $method->invokeArgs( $service, [ $ipn_data ] );
		$this->assertNull( $result );

		// Test empty data
		$result = $method->invokeArgs( $service, [ [] ] );
		$this->assertNull( $result );

		// Test nested structure without charge info
		$ipn_data = [
			'data' => [
				'amount' => 100,
			],
		];
		$result   = $method->invokeArgs( $service, [ $ipn_data ] );
		$this->assertNull( $result );
	}

	public function test_extract_charge_id_handles_empty_values() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'extract_charge_id' );

		// Test empty string values
		$ipn_data = [ 'charge_id' => '' ];
		$result   = $method->invokeArgs( $service, [ $ipn_data ] );
		$this->assertNull( $result );

		// Test null values
		$ipn_data = [ 'charge_id' => null ];
		$result   = $method->invokeArgs( $service, [ $ipn_data ] );
		$this->assertNull( $result );
	}

	public function test_extract_charge_id_with_non_string_values() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'extract_charge_id' );

		// Test numeric charge_id (should be converted to string)
		$ipn_data = [ 'charge_id' => 123456 ];
		$result   = $method->invokeArgs( $service, [ $ipn_data ] );
		$this->assertEquals( '123456', $result );

		// Test boolean values
		$ipn_data = [ 'charge_id' => false ];
		$result   = $method->invokeArgs( $service, [ $ipn_data ] );
		$this->assertNull( $result );  // Should be treated as empty
	}
}
