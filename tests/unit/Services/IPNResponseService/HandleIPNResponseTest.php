<?php
declare( strict_types=1 );

namespace unit\Services\IPNResponseService;

use Mockery;
use unit\Services\BaseServiceTest;
use PowerBoard\Services\IPNResponseService;
use PowerBoard\Services\IPNDuplicateCheckService;
use PowerBoard\Helpers\Util\PaymentGatewayHelper;

class HandleIPNResponseTest extends BaseServiceTest {

	protected function setUp(): void {
		parent::setUp();

		if ( ! defined( 'POWER_BOARD_PLUGIN_PREFIX' ) ) {
			define( 'POWER_BOARD_PLUGIN_PREFIX', 'power_board' );
		}
	}

	protected function tearDown(): void {
		Mockery::close();
		parent::tearDown();
	}

	public function test_handle_ipn_response_method_exists() {
		$service = $this->createPartialMockService( IPNResponseService::class );
		$this->assertTrue(
			method_exists( $service, 'handle_ipn_response' ),
			'method should exists'
		);

		$reflection = new \ReflectionMethod( $service, 'handle_ipn_response' );
		$this->assertTrue(
			$reflection->isPublic(),
			'method should be public'
		);
	}

	public function test_service_has_duplicate_check_property() {
		$service = $this->createPartialMockService( IPNResponseService::class );

		$reflection = new \ReflectionClass( $service );

		$this->assertTrue(
			$reflection->hasProperty( 'duplicate_check_service' ),
			'Service should have duplicate_check_service property'
		);

		$property = $reflection->getProperty( 'duplicate_check_service' );
		$this->assertTrue(
			$property->isProtected(),
			'duplicate_check_service property should be protected'
		);
	}

	public function test_constructor_has_no_parameters() {
		$reflection  = new \ReflectionClass( IPNResponseService::class );
		$constructor = $reflection->getConstructor();

		$this->assertEquals(
			0,
			$constructor->getNumberOfParameters(),
			'Constructor should have no parameters'
		);

		$this->assertTrue(
			$constructor->isPublic(),
			'Constructor should be public'
		);
	}
}
