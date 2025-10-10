<?php
declare( strict_types=1 );

namespace unit\Services\IPNDuplicateCheckService;

use unit\Services\BaseServiceTest;
use PowerBoard\Services\IPNDuplicateCheckService;

/**
 * Tests for IPNResponseService::map_event_to_status() method
 */
class MapEventToStatusTest extends BaseServiceTest {

	public function test_map_event_to_status_with_success_events() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'map_event_to_status' );

		$success_events = [
			'payment_succeeded',
			'payment_captured',
			'checkout_completed',
		];

		foreach ( $success_events as $event ) {
			$result = $method->invokeArgs( $service, [ $event ] );
			$this->assertEquals( 'success', $result, "Event '{$event}' should map to 'success'" );
		}
	}

	public function test_map_event_to_status_with_failure_events() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'map_event_to_status' );

		$failure_events = [
			'payment_failed',
			'checkout_failed',
		];

		foreach ( $failure_events as $event ) {
			$result = $method->invokeArgs( $service, [ $event ] );
			$this->assertEquals( 'failed', $result, "Event '{$event}' should map to 'failed'" );
		}
	}

	public function test_map_event_to_status_with_cancellation_events() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'map_event_to_status' );

		$cancellation_events = [
			'payment_voided',
			'checkout_cancelled',
			'checkout_expired',
		];

		foreach ( $cancellation_events as $event ) {
			$result = $method->invokeArgs( $service, [ $event ] );
			$this->assertEquals( 'cancelled', $result, "Event '{$event}' should map to 'cancelled'" );
		}
	}

	public function test_map_event_to_status_with_pending_events() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'map_event_to_status' );

		$pending_events = [
			'payment_created',
			'checkout_created',
		];

		foreach ( $pending_events as $event ) {
			$result = $method->invokeArgs( $service, [ $event ] );
			$this->assertEquals( 'pending', $result, "Event '{$event}' should map to 'pending'" );
		}
	}

	public function test_map_event_to_status_with_unknown_events() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'map_event_to_status' );

		$unknown_events = [
			'unknown_event',
			'invalid_event',
			'payment_unknown',
			'checkout_unknown',
			'random_string',
		];

		foreach ( $unknown_events as $event ) {
			$result = $method->invokeArgs( $service, [ $event ] );
			$this->assertEquals( 'pending', $result, "Unknown event '{$event}' should default to 'pending'" );
		}
	}

	public function test_map_event_to_status_handles_null_and_empty() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'map_event_to_status' );

		// Test edge cases
		$this->assertEquals( 'pending', $method->invokeArgs( $service, [ null ] ) );
		$this->assertEquals( 'pending', $method->invokeArgs( $service, [ '' ] ) );
		$this->assertEquals( 'pending', $method->invokeArgs( $service, [ '   ' ] ) );
	}

	public function test_map_event_to_status_comprehensive_coverage() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'map_event_to_status' );

		// Test all events that should be handled
		$event_mappings = [
			'payment_succeeded'  => 'success',
			'payment_captured'   => 'success',
			'checkout_completed' => 'success',
			'payment_failed'     => 'failed',
			'checkout_failed'    => 'failed',
			'payment_voided'     => 'cancelled',
			'checkout_cancelled' => 'cancelled',
			'checkout_expired'   => 'cancelled',
			'payment_created'    => 'pending',
			'checkout_created'   => 'pending',
		];

		foreach ( $event_mappings as $event => $expected_status ) {
			$result = $method->invokeArgs( $service, [ $event ] );
			$this->assertEquals(
				$expected_status,
				$result,
				"Event '{$event}' should map to '{$expected_status}'"
			);
		}
	}

	public function test_map_event_to_status_case_sensitivity() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'map_event_to_status' );

		// Test case sensitivity - mapping should be exact
		$this->assertEquals( 'pending', $method->invokeArgs( $service, [ 'PAYMENT_SUCCEEDED' ] ) ); // Should default to pending
		$this->assertEquals( 'pending', $method->invokeArgs( $service, [ 'Payment_Failed' ] ) ); // Should default to pending
		$this->assertEquals( 'success', $method->invokeArgs( $service, [ 'payment_succeeded' ] ) ); // Exact case should work
	}
}
