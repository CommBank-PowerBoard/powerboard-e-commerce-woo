<?php
declare( strict_types=1 );

namespace unit;

require_once __DIR__ . '/bootstrap.php';

use PHPUnit\Framework\TestCase;
use PowerBoard\Controllers\Integrations\PaymentController;

/**
 * Unit tests for PaymentController
 */
class PaymentControllerTest extends TestCase {

	/**
	 * Test refund_process authorization - non-admin user (default bootstrap) should be denied
	 */
	public function test_refund_process_non_admin_user_denied() {
		$controller = new PaymentController();

		// Mock refund object
		$refund = \Mockery::mock( 'WC_Order_Refund' );

		$args = [
			'order_id' => 123,
			'amount'   => 50.00,
		];

		// With default bootstrap mocks (no permissions), should throw authorization exception
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Insufficient permissions to process refunds' );

		$controller->refund_process( $refund, $args );
	}

	protected function tearDown(): void {
		\Mockery::close();
		parent::tearDown();
	}
}
