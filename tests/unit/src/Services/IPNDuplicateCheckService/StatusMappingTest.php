<?php
declare( strict_types=1 );

namespace unit\src\Services\IPNDuplicateCheckService;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use PowerBoard\Services\IPNValidationService;
use ReflectionClass;
use function Brain\Monkey;
use function Brain\Monkey\Functions;

/**
 * Tests for IPNDuplicateCheckService status mapping functionality
 * Demonstrates the new organized test structure: one file per method/functionality
 */
class StatusMappingTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Define constants if not already defined
		if ( ! defined( 'POWER_BOARD_PLUGIN_PREFIX' ) ) {
			define( 'POWER_BOARD_PLUGIN_PREFIX', 'power_board' );
		}

		// Mock WordPress functions
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'absint' )->alias(
				function ( $value ) {
					return (int) $value;
				}
			);
	}

	/**
	 * Test the PowerBoard to WooCommerce status mapping
	 */
	public function test_powerboard_status_mapping() {
		$service = $this->createPartialMock( IPNValidationService::class, [] );

		$test_cases = [
			'complete'       => 'completed',
			'completed'      => 'completed',
			'success'        => 'processing',
			'successful'     => 'processing',
			'processing'     => 'processing',
			'pending'        => 'pending',
			'failed'         => 'failed',
			'cancelled'      => 'cancelled',
			'refunded'       => 'refunded',
			'charged_back'   => 'refunded',
			'unknown_status' => 'pending', // Default mapping
		];

		$reflection = new ReflectionClass( $service );
		$method     = $reflection->getMethod( 'map_powerboard_status_to_wc' );
		$method->setAccessible( true );

		foreach ( $test_cases as $powerboard_status => $expected_wc_status ) {
			$result = $method->invokeArgs( $service, [ $powerboard_status ] );
			$this->assertEquals(
				$expected_wc_status,
				$result,
				"Failed mapping '{$powerboard_status}' to '{$expected_wc_status}'"
			);
		}
	}

	/**
	 * Test the status mapping constant structure
	 */
	public function test_status_mapping_constant() {
		$reflection = new ReflectionClass( IPNValidationService::class );
		$status_map = $reflection->getConstant( 'POWERBOARD_TO_WC_STATUS_MAP' );

		$this->assertIsArray( $status_map );
		$this->assertArrayHasKey( 'complete', $status_map );
		$this->assertArrayHasKey( 'success', $status_map );
		$this->assertArrayHasKey( 'pending', $status_map );
		$this->assertArrayHasKey( 'failed', $status_map );

		// Verify some key mappings
		$this->assertEquals( 'completed', $status_map['complete'] );
		$this->assertEquals( 'processing', $status_map['success'] );
		$this->assertEquals( 'pending', $status_map['pending'] );
		$this->assertEquals( 'refunded', $status_map['charged_back'] );
	}

	/**
	 * Test case sensitivity in status mapping
	 */
	public function test_status_mapping_case_sensitivity() {
		$service    = $this->createPartialMock( IPNValidationService::class, [] );
		$reflection = new ReflectionClass( $service );
		$method     = $reflection->getMethod( 'map_powerboard_status_to_wc' );
		$method->setAccessible( true );

		// Test case insensitive behavior (method should lowercase input)
		$this->assertEquals( 'processing', $method->invokeArgs( $service, [ 'SUCCESS' ] ) );
		$this->assertEquals( 'completed', $method->invokeArgs( $service, [ 'COMPLETE' ] ) );
		$this->assertEquals( 'failed', $method->invokeArgs( $service, [ 'FAILED' ] ) );
	}
}
