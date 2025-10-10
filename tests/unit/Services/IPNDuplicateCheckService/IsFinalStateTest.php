<?php
declare( strict_types=1 );

namespace unit\Services\IPNDuplicateCheckService;

use unit\Services\BaseServiceTest;
use PowerBoard\Services\IPNDuplicateCheckService;

/**
 * Tests for IPNDuplicateCheckService::is_final_state() method
 */
class IsFinalStateTest extends BaseServiceTest {

	public function test_is_final_state_returns_true_for_final_statuses() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'is_final_state' );

		// Test final states
		$final_states = [ 'completed', 'failed', 'cancelled', 'refunded' ];
		foreach ( $final_states as $state ) {
			$this->assertTrue(
				$method->invokeArgs( $service, [ $state ] ),
				"State '{$state}' should be considered final"
			);
		}
	}

	public function test_is_final_state_returns_false_for_non_final_statuses() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'is_final_state' );

		// Test non-final states
		$non_final_states = [ 'pending', 'processing', 'on-hold', 'draft', 'checkout-draft' ];
		foreach ( $non_final_states as $state ) {
			$this->assertFalse(
				$method->invokeArgs( $service, [ $state ] ),
				"State '{$state}' should not be considered final"
			);
		}
	}

	public function test_is_final_state_is_case_sensitive() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'is_final_state' );

		// Test case sensitivity
		$this->assertFalse( $method->invokeArgs( $service, [ 'COMPLETED' ] ) );
		$this->assertFalse( $method->invokeArgs( $service, [ 'Failed' ] ) );
		$this->assertFalse( $method->invokeArgs( $service, [ 'Cancelled' ] ) );
		$this->assertFalse( $method->invokeArgs( $service, [ 'REFUNDED' ] ) );
	}

	public function test_is_final_state_handles_empty_and_invalid_input() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'is_final_state' );

		// Test edge cases
		$this->assertFalse( $method->invokeArgs( $service, [ '' ] ) );
		$this->assertFalse( $method->invokeArgs( $service, [ 'invalid-status' ] ) );
		$this->assertFalse( $method->invokeArgs( $service, [ 'unknown' ] ) );
	}

	public function test_is_final_state_matches_constants() {
		// Verify that the method uses the FINAL_STATES constant
		$final_states = $this->getClassConstant( IPNDuplicateCheckService::class, 'FINAL_STATES' );

		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'is_final_state' );

		foreach ( $final_states as $state ) {
			$this->assertTrue(
				$method->invokeArgs( $service, [ $state ] ),
				"Final state constant '{$state}' should be recognized as final"
			);
		}
	}
}
