<?php
declare( strict_types=1 );

namespace unit\Services\IPNDuplicateCheckService;

use unit\Services\BaseServiceTest;
use PowerBoard\Services\IPNDuplicateCheckService;

/**
 * Tests for IPNDuplicateCheckService::should_update_status() method
 */
class ShouldUpdateStatusTest extends BaseServiceTest {

	public function test_should_update_status_with_higher_priority() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'should_update_status' );

		// Test priority-based updates (higher priority should update)
		$this->assertTrue( $method->invokeArgs( $service, [ 'pending', 'failed' ] ) ); // 1 -> 2
		$this->assertTrue( $method->invokeArgs( $service, [ 'failed', 'processing' ] ) ); // 2 -> 4
		$this->assertTrue( $method->invokeArgs( $service, [ 'pending', 'processing' ] ) ); // 1 -> 4
		$this->assertTrue( $method->invokeArgs( $service, [ 'processing', 'completed' ] ) ); // 4 -> 5
		$this->assertTrue( $method->invokeArgs( $service, [ 'completed', 'refunded' ] ) ); // 5 -> 6
	}

	public function test_should_update_status_with_lower_priority() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'should_update_status' );

		// Test lower priority should not update
		$this->assertFalse( $method->invokeArgs( $service, [ 'refunded', 'completed' ] ) ); // 6 -> 5
		$this->assertFalse( $method->invokeArgs( $service, [ 'completed', 'processing' ] ) ); // 5 -> 4
		$this->assertFalse( $method->invokeArgs( $service, [ 'processing', 'failed' ] ) ); // 4 -> 2
		$this->assertFalse( $method->invokeArgs( $service, [ 'failed', 'pending' ] ) ); // 2 -> 1
		$this->assertFalse( $method->invokeArgs( $service, [ 'refunded', 'cancelled' ] ) ); // 6 -> 3
	}

	public function test_should_update_status_with_same_priority() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'should_update_status' );

		// Test same priority should not update
		$this->assertFalse( $method->invokeArgs( $service, [ 'processing', 'processing' ] ) ); // 4 -> 4
		$this->assertFalse( $method->invokeArgs( $service, [ 'completed', 'completed' ] ) ); // 5 -> 5
		$this->assertFalse( $method->invokeArgs( $service, [ 'failed', 'failed' ] ) ); // 2 -> 2
	}

	public function test_should_update_status_with_unknown_statuses() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'should_update_status' );

		// Test unknown statuses (should default to priority 0)
		$this->assertTrue( $method->invokeArgs( $service, [ 'unknown', 'pending' ] ) ); // 0 -> 1
		$this->assertFalse( $method->invokeArgs( $service, [ 'pending', 'unknown' ] ) ); // 1 -> 0
		$this->assertFalse( $method->invokeArgs( $service, [ 'unknown', 'unknown' ] ) ); // 0 -> 0
	}

	public function test_should_update_status_priority_order() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'should_update_status' );

		// Test complete priority chain: pending 1 < failed 2 < cancelled 3 < processing 4 < completed 5 < refunded 6

		// pending (1) to higher priorities
		$this->assertTrue( $method->invokeArgs( $service, [ 'pending', 'failed' ] ) );
		$this->assertTrue( $method->invokeArgs( $service, [ 'pending', 'cancelled' ] ) );
		$this->assertTrue( $method->invokeArgs( $service, [ 'pending', 'processing' ] ) );

		$this->assertTrue( $method->invokeArgs( $service, [ 'pending', 'completed' ] ) );

		$this->assertTrue( $method->invokeArgs( $service, [ 'pending', 'refunded' ] ) );

		// completed (6) to lower priorities
		$this->assertFalse( $method->invokeArgs( $service, [ 'refunded', 'completed' ] ) );
		$this->assertFalse( $method->invokeArgs( $service, [ 'refunded', 'processing' ] ) );
		$this->assertFalse( $method->invokeArgs( $service, [ 'refunded', 'cancelled' ] ) );
		$this->assertFalse( $method->invokeArgs( $service, [ 'refunded', 'failed' ] ) );
		$this->assertFalse( $method->invokeArgs( $service, [ 'refunded', 'pending' ] ) );
	}

	public function test_should_update_status_matches_priority_constants() {
		// Verify the method uses the STATUS_PRIORITY constant correctly
		$priorities = $this->getClassConstant( IPNDuplicateCheckService::class, 'STATUS_PRIORITY' );

		$this->assertIsArray( $priorities );
		$this->assertArrayHasKey( 'pending', $priorities );
		$this->assertArrayHasKey( 'processing', $priorities );
		$this->assertArrayHasKey( 'cancelled', $priorities );
		$this->assertArrayHasKey( 'failed', $priorities );
		$this->assertArrayHasKey( 'refunded', $priorities );
		$this->assertArrayHasKey( 'completed', $priorities );

		// Verify priority ordering
		$this->assertGreaterThan( $priorities['pending'], $priorities['failed'] );
		$this->assertGreaterThan( $priorities['failed'], $priorities['cancelled'] );
		$this->assertGreaterThan( $priorities['cancelled'], $priorities['processing'] );
		$this->assertGreaterThan( $priorities['processing'], $priorities['completed'] );
		$this->assertGreaterThan( $priorities['completed'], $priorities['refunded'] );
	}
}
