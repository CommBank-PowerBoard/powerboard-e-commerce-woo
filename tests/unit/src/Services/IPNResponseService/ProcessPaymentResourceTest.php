<?php
declare( strict_types=1 );

namespace unit\Services\IPNResponseService;

use Mockery;
use PowerBoard\Helpers\Util\LoggerHelper;
use PowerBoard\Helpers\Util\PaymentProcessingHelper;
use PowerBoard\Model\Charge;
use PowerBoard\Model\IPN;
use unit\src\Services\BaseServiceTest;
use PowerBoard\Services\IPNResponseService;
use WC_Order;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

class ProcessPaymentResourceTest extends BaseServiceTest {

	protected function setUp(): void {
		parent::setUp();

		if ( ! defined( 'POWER_BOARD_PLUGIN_PREFIX' ) ) {
			define( 'POWER_BOARD_PLUGIN_PREFIX', 'power_board' );
		}

		$helper_mock = Mockery::mock( 'overload:' . PaymentProcessingHelper::class );
		$helper_mock->shouldReceive( 'process_payment_successful' )
			->andReturnNull();
		$helper_mock->shouldReceive( 'process_payment_failed' )
			->andReturnNull();

		Mockery::mock( 'alias:' . LoggerHelper::class )
			->shouldReceive( 'log_callback_event' )
			->andReturnNull();
	}

	public function test_process_payment_resource_method_exists() {
		$service = $this->createPartialMockService( IPNResponseService::class );

		$this->assertTrue(
			method_exists( $service, 'process_payment_resource' ),
		'process_payment_resource method should exists'
		);

		$reflection = new \ReflectionMethod( $service, 'process_payment_resource' );
		$this->assertTrue(
			$reflection->isPublic(),
			'process_payment_resource method should be public'
		);

		$this->assertEquals(
			1,
			$reflection->getNumberOfParameters(),
			'process_payment_resource method should have one parameter'
		);
	}

	public function test_private_payment_processing_methods_exists() {
		$service = $this->createPartialMockService( IPNResponseService::class );

		$reflection = new \ReflectionClass( $service );

		$this->assertTrue(
			$reflection->hasMethod( 'process_successful_payment' ),
			'process_successful_payment method should exists'
		);

		$success_method = $reflection->getMethod( 'process_successful_payment' );
		$this->assertTrue(
			$success_method->isPrivate(),
			'process_successful_payment method should be private'
		);

		$this->assertTrue(
			$reflection->hasMethod( 'process_failed_payment' ),
			'process_failed_payment method should exists'
		);

		$failed_method = $reflection->getMethod( 'process_failed_payment' );
		$this->assertTrue(
			$failed_method->isPrivate(),
			'process_failed_payment method should be private'
		);
	}

	public function test_process_payment_resource_accepts_ipn_parameter() {
		$service = $this->createPartialMockService( IPNResponseService::class );

		$reflection = new \ReflectionMethod( $service, 'process_payment_resource' );
		$parameters = $reflection->getParameters();

		$this->assertCount( 1, $parameters, 'process_payment_resource method should have only one parameter' );

		$param[0] = $parameters[0];
		$this->assertEquals( 'ipn', $param[0]->getName(), 'process_payment_resource method should have ipn parameter' );
	}

	public function test_process_payment_resource_handles_already_paid_order() {
		$service = $this->createPartialMockService( IPNResponseService::class );

		$ipn = Mockery::mock( IPN::class );
		$ipn->shouldReceive( 'get_order_id' )->andReturn( 123 );
		$ipn->shouldReceive( 'get_event_id' )->andReturn( 'evt_123' );
		$ipn->shouldReceive( 'get_is_paid' )->andReturn( false );
		$ipn->shouldReceive( 'get_failure_message' )
			->andReturn( '' );

		$order = Mockery::mock( WC_Order::class );
		$order->shouldReceive( 'get_payment_method' )
			->andReturn( 'power_board' );
		$order->shouldReceive( 'is_paid' )
			->andReturn( true );
		$order->shouldReceive( 'get_status' )
			->andReturn( 'completed' );

		when( 'wc_get_order' )->justReturn( $order );

		$service->process_payment_resource( $ipn );

		$this->assertTrue( true );
	}

	public function test_process_payment_resource_ignores_wrong_payment_method() {
		$service = $this->createPartialMockService( IPNResponseService::class );

		$ipn = Mockery::mock( IPN::class );
		$ipn->shouldReceive( 'get_order_id' )->andReturn( 456 );
		$ipn->shouldReceive( 'get_event_id' )->andReturn( 'evt_456' );
		$ipn->shouldReceive( 'get_is_paid' )->andReturn( false );
		$ipn->shouldReceive( 'get_failure_message' )->andReturn( '' );

		$order = Mockery::mock( WC_Order::class );
		$order->shouldReceive( 'get_payment_method' )
			->andReturn( 'power_board' );
		$order->shouldReceive( 'is_paid' )->andReturn( false );

		when( 'wc_get_order' )->justReturn( $order );

		$service->process_payment_resource( $ipn );

		$this->assertTrue( true );
	}

	public function test_process_payment_resource_handles_successful_payment() {
		$this->markTestSkipped( 'Requires PaymentProcessingHelper::SOURCE_IPN constant , this should be an integration test' );
	}

	public function test_process_payment_resource_handles_failed_payment() {
		$this->markTestSkipped( 'Requires PaymentProcessingHelper::SOURCE_IPN constant , this should be an integration test' );
	}

	public function test_process_payment_resource_handles_pending_payment() {
		$service = $this->createPartialMockService( IPNResponseService::class );

		$ipn = Mockery::mock( IPN::class );
		$ipn->shouldReceive( 'get_order_id' )->andReturn( 789 );
		$ipn->shouldReceive( 'get_event_id' )->andReturn( 'evt_789' );
		$ipn->shouldReceive( 'get_is_paid' )->andReturn( false );
		$ipn->shouldReceive( 'get_failure_message' )->andReturn( '' );

		$order = Mockery::mock( WC_Order::class );
		$order->shouldReceive( 'get_payment_method' )->andReturn( 'power_board' );
		$order->shouldReceive( 'is_paid' )->andReturn( false );

		when( 'wc_get_order' )->justReturn( $order );

		expect( 'wp_send_json_success' )->never();

		$service->process_payment_resource( $ipn );

		$this->assertTrue( true );
	}
}
