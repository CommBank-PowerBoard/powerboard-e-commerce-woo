<?php
declare( strict_types=1 );

namespace unit\Services\IPNResponseService;

use unit\Services\BaseServiceTest;
use PowerBoard\Services\IPNResponseService;

/**
 * Tests for IPNResponseService::map_event_to_status() method
 */
class MapEventToStatusTest extends BaseServiceTest {

	public function test_map_event_to_status_with_success_events() {
		$service = $this->createPartialMockService( IPNResponseService::class );
		$method = $this->getPrivateMethod( $service, 'map_event_to_status' );

		$successEvents = [
			'payment_succeeded',
			'payment_captured', 
			'checkout_completed'
		];

		foreach ( $successEvents as $event ) {
			$result = $method->invokeArgs( $service, [ $event ] );
			$this->assertEquals( 'success', $result, "Event '{$event}' should map to 'success'" );
		}
	}

	public function test_map_event_to_status_with_failure_events() {
		$service = $this->createPartialMockService( IPNResponseService::class );
		$method = $this->getPrivateMethod( $service, 'map_event_to_status' );

		$failureEvents = [
			'payment_failed',
			'checkout_failed'
		];

		foreach ( $failureEvents as $event ) {
			$result = $method->invokeArgs( $service, [ $event ] );
			$this->assertEquals( 'failed', $result, "Event '{$event}' should map to 'failed'" );
		}
	}

	public function test_map_event_to_status_with_cancellation_events() {
		$service = $this->createPartialMockService( IPNResponseService::class );
		$method = $this->getPrivateMethod( $service, 'map_event_to_status' );

		$cancellationEvents = [
			'payment_voided',
			'checkout_cancelled',
			'checkout_expired'
		];

		foreach ( $cancellationEvents as $event ) {
			$result = $method->invokeArgs( $service, [ $event ] );
			$this->assertEquals( 'cancelled', $result, "Event '{$event}' should map to 'cancelled'" );
		}
	}

	public function test_map_event_to_status_with_pending_events() {
		$service = $this->createPartialMockService( IPNResponseService::class );
		$method = $this->getPrivateMethod( $service, 'map_event_to_status' );

		$pendingEvents = [
			'payment_created',
			'checkout_created'
		];

		foreach ( $pendingEvents as $event ) {
			$result = $method->invokeArgs( $service, [ $event ] );
			$this->assertEquals( 'pending', $result, "Event '{$event}' should map to 'pending'" );
		}
	}

	public function test_map_event_to_status_with_unknown_events() {
		$service = $this->createPartialMockService( IPNResponseService::class );
		$method = $this->getPrivateMethod( $service, 'map_event_to_status' );

		$unknownEvents = [
			'unknown_event',
			'invalid_event',
			'payment_unknown',
			'checkout_unknown',
			'random_string'
		];

		foreach ( $unknownEvents as $event ) {
			$result = $method->invokeArgs( $service, [ $event ] );
			$this->assertEquals( 'pending', $result, "Unknown event '{$event}' should default to 'pending'" );
		}
	}

	public function test_map_event_to_status_handles_null_and_empty() {
		$service = $this->createPartialMockService( IPNResponseService::class );
		$method = $this->getPrivateMethod( $service, 'map_event_to_status' );

		// Test edge cases
		$this->assertEquals( 'pending', $method->invokeArgs( $service, [ null ] ) );
		$this->assertEquals( 'pending', $method->invokeArgs( $service, [ '' ] ) );
		$this->assertEquals( 'pending', $method->invokeArgs( $service, [ '   ' ] ) );
	}

	public function test_map_event_to_status_comprehensive_coverage() {
		$service = $this->createPartialMockService( IPNResponseService::class );
		$method = $this->getPrivateMethod( $service, 'map_event_to_status' );

		// Test all events that should be handled
		$eventMappings = [
			'payment_succeeded' => 'success',
			'payment_captured' => 'success',
			'checkout_completed' => 'success',
			'payment_failed' => 'failed',
			'checkout_failed' => 'failed',
			'payment_voided' => 'cancelled',
			'checkout_cancelled' => 'cancelled',
			'checkout_expired' => 'cancelled',
			'payment_created' => 'pending',
			'checkout_created' => 'pending',
		];

		foreach ( $eventMappings as $event => $expectedStatus ) {
			$result = $method->invokeArgs( $service, [ $event ] );
			$this->assertEquals(
				$expectedStatus,
				$result,
				"Event '{$event}' should map to '{$expectedStatus}'"
			);
		}
	}

	public function test_map_event_to_status_case_sensitivity() {
		$service = $this->createPartialMockService( IPNResponseService::class );
		$method = $this->getPrivateMethod( $service, 'map_event_to_status' );

		// Test case sensitivity - mapping should be exact
		$this->assertEquals( 'pending', $method->invokeArgs( $service, [ 'PAYMENT_SUCCEEDED' ] ) ); // Should default to pending
		$this->assertEquals( 'pending', $method->invokeArgs( $service, [ 'Payment_Failed' ] ) ); // Should default to pending
		$this->assertEquals( 'success', $method->invokeArgs( $service, [ 'payment_succeeded' ] ) ); // Exact case should work
	}
}
