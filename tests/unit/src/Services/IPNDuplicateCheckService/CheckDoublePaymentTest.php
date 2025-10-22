<?php
declare( strict_types=1 );

namespace unit\src\Services\IPNDuplicateCheckService;

use Mockery;
use PowerBoard\Helpers\Util\LoggerHelper;
use PowerBoard\Services\IPNDuplicateCheckService;
use unit\src\Services\BaseServiceTest;
use WC_Order;

class CheckDoublePaymentTest extends BaseServiceTest {

	protected function setUp(): void {
		parent::setUp();

		if ( ! defined( 'POWER_BOARD_PLUGIN_PREFIX' ) ) {
			define( 'POWER_BOARD_PLUGIN_PREFIX', 'power_board' );
		}

		if ( ! defined( 'POWER_BOARD_PLUGIN_NAME' ) ) {
			define( 'POWER_BOARD_PLUGIN_NAME', 'PowerBoard for WooCommerce' );
		}

		Mockery::mock( 'alias:' . LoggerHelper::class )
			->shouldReceive( 'log_callback_event' )
			->andReturn( true );
	}
	protected function tearDown(): void {
		Mockery::close();
		parent::tearDown();
	}

	public function test_no_warning_when_no_stored_charge_id() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'check_double_payment' );

		$order = Mockery::mock( WC_Order::class );
		$order->shouldReceive( 'get_meta' )
			->once()
			->with( '_powerboard_charge_id' )
			->andReturn( null );

		$result = $method->invokeArgs( $service, [ $order, 'ch_new123', 'pending', 'processing' ] );

		$this->assertNull( $result, 'Method should return null' );
	}

	public function test_no_warning_when_charge_id_matches() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'check_double_payment' );

		$order = Mockery::mock( WC_Order::class );
		$order->shouldReceive( 'get_meta' )
			->once()
			->with( '_powerboard_charge_id' )
			->andReturn( 'ch_123' );
		$order->shouldReceive( 'get_id' )
			->once()
			->andReturn( '456' );

		$result = $method->invokeArgs( $service, [ $order, 'ch_123', 'pending', 'processing' ] );

		$this->assertNull( $result, 'Method should return null' );
	}

	public function test_logs_warning_for_double_successful_payment() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'check_double_payment' );

		$order = Mockery::mock( WC_Order::class );
		$order->shouldReceive( 'get_meta' )
			->with( '_powerboard_charge_id' )
			->andReturn( 'ch_old123' );
		$order->shouldReceive( 'get_id' )
			->andReturn( '456' );

		$result = $method->invokeArgs( $service, [ $order, 'ch_new456', 'processing', 'processing' ] );
		$this->assertNull( $result, 'Method should return null' );

		$result = $method->invokeArgs( $service, [ $order, 'ch_new456', 'processing', 'completed' ] );
		$this->assertNull( $result, 'Method should return null' );

		$result = $method->invokeArgs( $service, [ $order, 'ch_new456', 'completed', 'processing' ] );
		$this->assertNull( $result, 'Method should return null' );
	}

	public function test_logs_info_for_retry_after_failure() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'check_double_payment' );

		$order = Mockery::mock( WC_Order::class );
		$order->shouldReceive( 'get_meta' )
			->with( '_powerboard_charge_id' )
			->andReturn( 'ch_failed123' );
		$order->shouldReceive( 'get_id' )
			->andReturn( '789' );

		$result = $method->invokeArgs( $service, [ $order, 'ch_retry456', 'failed', 'failed' ] );
		$this->assertNull( $result, 'Method should return null' );
	}

	public function test_logs_info_for_different_charge_pending_to_success() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'check_double_payment' );

		$order = Mockery::mock( WC_Order::class );
		$order->shouldReceive( 'get_meta' )
			->with( '_powerboard_charge_id' )
			->andReturn( 'ch_pending123' );
		$order->shouldReceive( 'get_id' )
			->andReturn( 999 );

		$result = $method->invokeArgs( $service, [ $order, 'ch_success456', 'pending', 'processing' ] );
		$this->assertNull( $result, 'Method should return null' );
	}

	public function test_various_status_combinations() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'check_double_payment' );

		$order = Mockery::mock( WC_Order::class );
		$order->shouldReceive( 'get_meta' )
			->with( '_powerboard_charge_id' )
			->andReturn( 'ch_old' );
		$order->shouldReceive( 'get_id' )
			->andReturn( 999 );

		$scenarios = [
			[ 'failed' => 'failed' ],
			[ 'failed' => 'processing' ],
			[ 'pending' => 'failed' ],
			[ 'pending' => 'pending' ],
			[ 'cancelled' => 'processing' ],
		];

		foreach ( $scenarios as $current_status => $target_statuses ) {
			foreach ( $target_statuses as $target_status ) {
				$result = $method->invokeArgs( $service, [ $order, 'ch_new', $current_status, $target_status ] );
				$this->assertNull( $result, "Status combination '{$current_status}' => '{$target_status}' should not trigger a warning" );
			}
		}
	}

	public function test_edge_cases() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method  = $this->getPrivateMethod( $service, 'check_double_payment' );

		$order = Mockery::mock( WC_Order::class );
		$order->shouldReceive( 'get_meta' )
			->with( '_powerboard_charge_id' )
			->andReturn( '' );

		$result = $method->invokeArgs( $service, [ $order, 'ch_new', 'processing', 'completed' ] );
		$this->assertNull( $result, 'Method should return null' );
	}
}
