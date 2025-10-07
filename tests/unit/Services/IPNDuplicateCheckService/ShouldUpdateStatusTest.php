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
		$method = $this->getPrivateMethod( $service, 'should_update_status' );

		// Test priority-based updates (higher priority should update)
		$this->assertTrue( $method->invokeArgs( $service, [ 'pending', 'processing' ] ) ); // 1 -> 2
		$this->assertTrue( $method->invokeArgs( $service, [ 'processing', 'completed' ] ) ); // 2 -> 6
		$this->assertTrue( $method->invokeArgs( $service, [ 'failed', 'completed' ] ) ); // 4 -> 6
		$this->assertTrue( $method->invokeArgs( $service, [ 'pending', 'failed' ] ) ); // 1 -> 4
		$this->assertTrue( $method->invokeArgs( $service, [ 'cancelled', 'refunded' ] ) ); // 3 -> 5
	}

	public function test_should_update_status_with_lower_priority() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method = $this->getPrivateMethod( $service, 'should_update_status' );

		// Test lower priority should not update
		$this->assertFalse( $method->invokeArgs( $service, [ 'completed', 'processing' ] ) ); // 6 -> 2
		$this->assertFalse( $method->invokeArgs( $service, [ 'completed', 'failed' ] ) ); // 6 -> 4
		$this->assertFalse( $method->invokeArgs( $service, [ 'processing', 'pending' ] ) ); // 2 -> 1
		$this->assertFalse( $method->invokeArgs( $service, [ 'refunded', 'cancelled' ] ) ); // 5 -> 3
		$this->assertFalse( $method->invokeArgs( $service, [ 'failed', 'pending' ] ) ); // 4 -> 1
	}

	public function test_should_update_status_with_same_priority() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method = $this->getPrivateMethod( $service, 'should_update_status' );

		// Test same priority should not update
		$this->assertFalse( $method->invokeArgs( $service, [ 'processing', 'processing' ] ) ); // 2 -> 2
		$this->assertFalse( $method->invokeArgs( $service, [ 'completed', 'completed' ] ) ); // 6 -> 6
		$this->assertFalse( $method->invokeArgs( $service, [ 'failed', 'failed' ] ) ); // 4 -> 4
	}

	public function test_should_update_status_with_unknown_statuses() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method = $this->getPrivateMethod( $service, 'should_update_status' );

		// Test unknown statuses (should default to priority 0)
		$this->assertTrue( $method->invokeArgs( $service, [ 'unknown', 'pending' ] ) ); // 0 -> 1
		$this->assertFalse( $method->invokeArgs( $service, [ 'pending', 'unknown' ] ) ); // 1 -> 0
		$this->assertFalse( $method->invokeArgs( $service, [ 'unknown', 'unknown' ] ) ); // 0 -> 0
	}

	public function test_should_update_status_priority_order() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method = $this->getPrivateMethod( $service, 'should_update_status' );

		// Test complete priority chain: pending < processing < cancelled < failed < refunded < completed
		
		// pending (1) to higher priorities
		$this->assertTrue( $method->invokeArgs( $service, [ 'pending', 'processing' ] ) );
		$this->assertTrue( $method->invokeArgs( $service, [ 'pending', 'cancelled' ] ) );
		$this->assertTrue( $method->invokeArgs( $service, [ 'pending', 'failed' ] ) );
		$this->assertTrue( $method->invokeArgs( $service, [ 'pending', 'refunded' ] ) );
		$this->assertTrue( $method->invokeArgs( $service, [ 'pending', 'completed' ] ) );

		// completed (6) to lower priorities  
		$this->assertFalse( $method->invokeArgs( $service, [ 'completed', 'refunded' ] ) );
		$this->assertFalse( $method->invokeArgs( $service, [ 'completed', 'failed' ] ) );
		$this->assertFalse( $method->invokeArgs( $service, [ 'completed', 'cancelled' ] ) );
		$this->assertFalse( $method->invokeArgs( $service, [ 'completed', 'processing' ] ) );
		$this->assertFalse( $method->invokeArgs( $service, [ 'completed', 'pending' ] ) );
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
		$this->assertGreaterThan( $priorities['pending'], $priorities['processing'] );
		$this->assertGreaterThan( $priorities['processing'], $priorities['cancelled'] );
		$this->assertGreaterThan( $priorities['cancelled'], $priorities['failed'] );
		$this->assertGreaterThan( $priorities['failed'], $priorities['refunded'] );
		$this->assertGreaterThan( $priorities['refunded'], $priorities['completed'] );
	}
}
