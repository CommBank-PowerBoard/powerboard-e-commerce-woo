<?php
declare( strict_types=1 );

namespace unit\Services\IPNDuplicateCheckService;

use unit\Services\BaseServiceTest;
use PowerBoard\Services\IPNDuplicateCheckService;

/**
 * Tests for IPNDuplicateCheckService::parse_ipn_payload() method
 */
class ParseIPNPayloadTest extends BaseServiceTest {

	public function test_parse_ipn_payload_with_valid_json() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );

		// Test valid JSON
		$json_payload = '{"charge_id":"ch_test_123","order_id":"12345","status":"success"}';
		$result       = $service->parse_ipn_payload( $json_payload );

		$this->assertIsArray( $result );
		$this->assertEquals( 'ch_test_123', $result['charge_id'] );
		$this->assertEquals( '12345', $result['order_id'] );
		$this->assertEquals( 'success', $result['status'] );
	}

	public function test_parse_ipn_payload_with_complex_json() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );

		// Test complex nested JSON
		$complex_payload = wp_json_encode(
			[
				'charge_id' => 'ch_nested_456',
				'order_id'  => 67890,
				'status'    => 'completed',
				'metadata'  => [
					'timestamp' => time(),
					'source'    => 'webhook',
				],
				'customer'  => [
					'email' => 'test@example.com',
					'name'  => 'Test Customer',
				],
			]
			);

		$result = $service->parse_ipn_payload( $complex_payload );

		$this->assertIsArray( $result );
		$this->assertEquals( 'ch_nested_456', $result['charge_id'] );
		$this->assertEquals( 67890, $result['order_id'] );
		$this->assertEquals( 'completed', $result['status'] );
		$this->assertIsArray( $result['metadata'] );
		$this->assertIsArray( $result['customer'] );
	}

	public function test_parse_ipn_payload_with_invalid_json() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );

		// Test invalid JSON
		$invalid_json = '{"invalid": json}';
		$result       = $service->parse_ipn_payload( $invalid_json );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	public function test_parse_ipn_payload_with_malformed_json() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );

		$malformed_cases = [
			'{"missing_quote: "value"}',
			'{"trailing_comma": "value",}',
			'{incomplete json',
			'not json at all',
			'{"escaped_quotes": "value with \" quote"}',  // This should actually work
		];

		foreach ( $malformed_cases as $malformed ) {
			$result = $service->parse_ipn_payload( $malformed );
			$this->assertIsArray( $result, "Failed to return array for: {$malformed}" );

			// Most should be empty, except properly escaped JSON
			if ( strpos( $malformed, 'escaped_quotes' ) === false ) {
				$this->assertEmpty( $result, "Should be empty for malformed JSON: {$malformed}" );
			}
		}
	}

	public function test_parse_ipn_payload_with_empty_input() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );

		// Test empty payload
		$result = $service->parse_ipn_payload( '' );
		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	public function test_parse_ipn_payload_with_null_and_whitespace() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );

		// Test various edge cases
		$edge_cases = [
			'null',
			'   ',
			'\n\t',
			'{}',
			'[]',
		];

		foreach ( $edge_cases as $edge_case ) {
			$result = $service->parse_ipn_payload( $edge_case );
			$this->assertIsArray( $result, "Failed for edge case: '{$edge_case}'" );
		}
	}

	public function test_parse_ipn_payload_preserves_data_types() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );

		// Test that data types are preserved
		$payload = wp_json_encode(
			[
				'string_field' => 'text',
				'int_field'    => 123,
				'float_field'  => 45.67,
				'bool_field'   => true,
				'null_field'   => null,
				'array_field'  => [ 'item1', 'item2' ],
				'object_field' => [ 'key' => 'value' ],
			]
			);

		$result = $service->parse_ipn_payload( $payload );

		$this->assertIsString( $result['string_field'] );
		$this->assertIsInt( $result['int_field'] );
		$this->assertIsFloat( $result['float_field'] );
		$this->assertIsBool( $result['bool_field'] );
		$this->assertNull( $result['null_field'] );
		$this->assertIsArray( $result['array_field'] );
		$this->assertIsArray( $result['object_field'] );
	}
}
