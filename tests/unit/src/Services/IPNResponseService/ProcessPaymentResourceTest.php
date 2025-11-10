<?php
declare( strict_types=1 );

namespace unit\Services\IPNResponseService;

use Mockery;
use PowerBoard\Helpers\Util\LoggerHelper;
use PowerBoard\Helpers\Util\PaymentProcessingHelper;
use PowerBoard\Model\Charge;
use PowerBoard\Model\IPN;
use PowerBoard\Services\IPNValidationService;
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

	public function test_process_payment_resource_handles_successful_payment() {
		$this->markTestSkipped( 'Requires PaymentProcessingHelper::SOURCE_IPN constant , this should be an integration test' );
	}

	public function test_process_payment_resource_handles_failed_payment() {
		$this->markTestSkipped( 'Requires PaymentProcessingHelper::SOURCE_IPN constant , this should be an integration test' );
	}
}
