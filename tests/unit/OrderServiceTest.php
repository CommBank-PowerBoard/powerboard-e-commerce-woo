<?php
declare( strict_types=1 );

namespace unit;

use PHPUnit\Framework\TestCase;
use PowerBoard\Services\OrderService;

class OrderServiceTest extends TestCase {
	public function test_check_is_status_change_allowed_draft_to_processing() {
		$actual = OrderService::check_is_status_change_allowed( 'draft', 'processing' );

		$this->assertTrue( $actual, 'Should allow changing order status form draft to processing' );
	}
	public function test_check_is_status_change_allowed_processing_to_pending() {
		$actual = OrderService::check_is_status_change_allowed( 'processing', 'pending' );

		$this->assertTrue( $actual, 'Should allow changing order status form processing to pending' );
	}

	public function test_check_is_status_change_allowed_processing_to_on_hold() {
		$actual = OrderService::check_is_status_change_allowed( 'processing', 'on-hold' );

		$this->assertFalse( $actual, 'Should not allow changing order status form processing to on hold' );
	}

	public function test_check_is_status_change_allowed_processing_to_completed() {
		$actual = OrderService::check_is_status_change_allowed( 'processing', 'completed' );

		$this->assertTrue( $actual, 'Should allow changing order status form processing to completed' );
	}

	public function test_check_is_status_change_allowed_processing_to_cancelled() {
		$actual = OrderService::check_is_status_change_allowed( 'processing', 'cancelled' );

		$this->assertTrue( $actual, 'Should allow changing order status form processing to cancelled' );
	}

	public function test_check_is_status_change_allowed_processing_to_refunded() {
		$actual = OrderService::check_is_status_change_allowed( 'processing', 'refunded' );

		$this->assertTrue( $actual, 'Should allow changing order status form processing to refunded' );
	}

	public function test_check_is_status_change_allowed_processing_to_failed() {
		$actual = OrderService::check_is_status_change_allowed( 'processing', 'failed' );

		$this->assertTrue( $actual, 'Should allow changing order status form processing to failed' );
	}

	public function test_check_is_status_change_allowed_processing_to_draft() {
		$actual = OrderService::check_is_status_change_allowed( 'processing', 'draft' );

		$this->assertFalse( $actual, 'Should not allow changing order status form processing to draft' );
	}

	public function test_check_is_status_change_allowed_refunded_to_pending() {
		$actual = OrderService::check_is_status_change_allowed( 'refunded', 'pending' );

		$this->assertFalse( $actual, 'Should not allow changing order status form refunded to pending' );
	}

	public function test_check_is_status_change_allowed_refunded_to_processing() {
		$actual = OrderService::check_is_status_change_allowed( 'refunded', 'processing' );

		$this->assertFalse( $actual, 'Should not allow changing order status form refunded to processing' );
	}

	public function test_check_is_status_change_allowed_refunded_to_on_hold() {
		$actual = OrderService::check_is_status_change_allowed( 'refunded', 'on-hold' );

		$this->assertFalse( $actual, 'Should not allow changing order status form refunded to on hold' );
	}

	public function test_check_is_status_change_allowed_refunded_to_completed() {
		$actual = OrderService::check_is_status_change_allowed( 'refunded', 'completed' );

		$this->assertFalse( $actual, 'Should not allow changing order status form refunded to completed' );
	}

	public function test_check_is_status_change_allowed_refunded_to_cancelled() {
		$actual = OrderService::check_is_status_change_allowed( 'refunded', 'cancelled' );

		$this->assertTrue( $actual, 'Should allow changing order status form refunded to cancelled' );
	}

	public function test_check_is_status_change_allowed_refunded_to_failed() {
		$actual = OrderService::check_is_status_change_allowed( 'refunded', 'failed' );

		$this->assertTrue( $actual, 'Should allow changing order status form refunded to failed' );
	}

	public function test_check_is_status_change_allowed_refunded_to_draft() {
		$actual = OrderService::check_is_status_change_allowed( 'refunded', 'draft' );

		$this->assertFalse( $actual, 'Should not allow changing order status form refunded to draft' );
	}

	public function test_check_is_status_change_allowed_cancelled_to_pending() {
		$actual = OrderService::check_is_status_change_allowed( 'cancelled', 'pending' );

		$this->assertFalse( $actual, 'Should not allow changing order status form cancelled to pending' );
	}

	public function test_check_is_status_change_allowed_cancelled_to_processing() {
		$actual = OrderService::check_is_status_change_allowed( 'cancelled', 'processing' );

		$this->assertFalse( $actual, 'Should not allow changing order status form cancelled to processing' );
	}

	public function test_check_is_status_change_allowed_cancelled_to_on_hold() {
		$actual = OrderService::check_is_status_change_allowed( 'cancelled', 'on-hold' );

		$this->assertFalse( $actual, 'Should not allow changing order status form cancelled to on hold' );
	}

	public function test_check_is_status_change_allowed_cancelled_to_completed() {
		$actual = OrderService::check_is_status_change_allowed( 'cancelled', 'completed' );

		$this->assertFalse( $actual, 'Should not allow changing order status form cancelled to completed' );
	}

	public function test_check_is_status_change_allowed_cancelled_to_refunded() {
		$actual = OrderService::check_is_status_change_allowed( 'cancelled', 'refunded' );

		$this->assertTrue( $actual, 'Should allow changing order status form cancelled to refunded' );
	}

	public function test_check_is_status_change_allowed_cancelled_to_failed() {
		$actual = OrderService::check_is_status_change_allowed( 'cancelled', 'failed' );

		$this->assertTrue( $actual, 'Should allow changing order status form cancelled to failed' );
	}

	public function test_check_is_status_change_allowed_cancelled_to_draft() {
		$actual = OrderService::check_is_status_change_allowed( 'cancelled', 'draft' );

		$this->assertFalse( $actual, 'Should not allow changing order status form cancelled to draft' );
	}
}
