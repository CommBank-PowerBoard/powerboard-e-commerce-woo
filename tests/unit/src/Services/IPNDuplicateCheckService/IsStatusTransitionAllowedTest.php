<?php
declare( strict_types=1 );

namespace unit\src\Services\IPNDuplicateCheckService;

use PowerBoard\Services\IPNDuplicateCheckService;
use unit\src\Services\BaseServiceTest;

/**
 * Tests for IPNDuplicateCheckService::is_status_transition_allowed() method
 */
class IsStatusTransitionAllowedTest extends BaseServiceTest {

	/**
	 * Test success IPN with failed order allows transition from failed to processing
	 */
	public function test_success_ipn_with_failed_order_allows_transition() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'is_status_transition_allowed' );

		$this->assertTrue(
			$method->invokeArgs( $service, [ 'failed', 'processing', 'success' ] ),
			'Success IPN should allow transition from failed to processing'
		);

		$this->assertTrue(
			$method->invokeArgs( $service, [ 'failed', 'processing', 'successful' ] ),
			'Success IPN should allow transition from failed to processing'
		);

		$this->assertfalse(
			$method->invokeArgs( $service, [ 'success', 'processing', 'failed' ] ),
			'Success IPN should allow transition from failed to processing'
		);
	}

	/**
	 * Test that success IPN with pending order allows transition from pending to processing
	 */
	public function test_success_ipn_with_pending_order_allows_transition() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'is_status_transition_allowed' );

		$this->assertTrue(
			$method->invokeArgs( $service, [ 'pending', 'processing', 'success' ] ),
			'Success IPN should allow transition from pending to processing'
		);

		$this->assertTrue(
			$method->invokeArgs( $service, [ 'pending', 'processing', 'successful' ] ),
			'Success IPN should allow transition from pending to processing'
		);
	}

	/**
	 * Test failed IPN with pending order allows transition from failed to processing
	 */
	public function test_failed_ipn_with_pending_order_allows_transition() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'is_status_transition_allowed' );

		$this->assertTrue(
			$method->invokeArgs( $service, [ 'pending', 'failed', 'failed' ] ),
			'Failed IPN should allow transition from failed to processing'
		);
	}

	/**
	 * Test failed IPN with processing order blocks transition from processing to failed
	 */
	public function test_failed_ipn_with_processing_order_blocks_transition() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'is_status_transition_allowed' );

		$this->assertFalse(
			$method->invokeArgs( $service, [ 'processing', 'failed', 'failed' ] ),
			'Failed IPN should not allow update from processing to failed'
		);
	}

	/**
	 * Test failed IPN with completed order blocks transition from completed to failed
	 */
	public function test_failed_ipn_with_completed_order_blocks_transition() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'is_status_transition_allowed' );

		$this->assertFalse(
			$method->invokeArgs( $service, [ 'completed', 'failed', 'failed' ] ),
			'Failed IPN should not allow update from completed to failed'
		);
	}

	/**
	 * Test refund IPN blocks transition from any status to refunded
	 */
	public function test_refund_ipn_blocks_transition() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'is_status_transition_allowed' );

		$this->assertFalse(
			$method->invokeArgs( $service, [ 'completed', 'refunded', 'refunded' ] ),
			'Refund IPN should not allow update status'
		);

		$this->assertFalse(
			$method->invokeArgs( $service, [ 'processing', 'refunded', 'refunded' ] ),
			'Refund IPN should not allow update status'
		);
	}

	/**
	 * Test pending IPN blocks transitions
	 */
	public function test_pending_ipn_blocks_transition() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'is_status_transition_allowed' );

		$this->assertFalse(
			$method->invokeArgs( $service, [ 'pending', 'pending', 'pending' ] ),
			'Pending IPN should not allow update status'
		);

		$this->assertFalse(
			$method->invokeArgs( $service, [ 'failed', 'pending', 'pending' ] ),
			'Pending IPN should not allow update status from failed to pending'
		);
	}

	/**
	 * Test default allows other transitions
	 */
	public function test_default_allows_other_transitions() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'is_status_transition_allowed' );

		$this->assertFalse(
			$method->invokeArgs( $service, [ 'pending', 'cancelled', 'cancelled' ] ),
			'Cancelled transition should not be allowed'
		);

		$this->assertFalse(
			$method->invokeArgs( $service, [ 'processing', 'completed', 'completed' ] ),
			'Completed transition should be allowed'
		);
	}

	/**
	 * Test business logic scenarios
	 */
	public function test_business_logic_scenarios() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'is_status_transition_allowed' );

		$this->assertTrue(
			$method->invokeArgs( $service, [ 'failed', 'processing', 'success' ] ),
			'Should allow successfull retry payment after failed order'
		);

		$this->assertFalse(
			$method->invokeArgs( $service, [ 'processing', 'failed', 'failed' ] ),
			'Should not allow processing order to be failed'
		);

		$this->assertFalse(
			$method->invokeArgs( $service, [ 'completed', 'failed', 'failed' ] ),
			'Should not allow completed order to be failed'
		);

		$this->assertTrue(
			$method->invokeArgs( $service, [ 'pending', 'failed', 'failed' ] ),
			'Should allow failed order to be pending'
		);

		$this->assertTrue(
			$method->invokeArgs( $service, [ 'pending', 'processing', 'success' ] ),
			'Should allow pending order to be processing/success'
		);
	}
}
