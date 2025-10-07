<?php
declare( strict_types=1 );

namespace unit\Services\IPNDuplicateCheckService;

use PHPUnit\Framework\TestCase;
use PowerBoard\Services\IPNDuplicateCheckService;
use ReflectionClass;
use Brain\Monkey;
use Brain\Monkey\Functions;

/**
 * Tests for IPNDuplicateCheckService::map_powerboard_status_to_wc() method
 */
class MapPowerboardStatusToWCTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		
		// Define constants if not already defined
		if ( ! defined( 'POWER_BOARD_PLUGIN_PREFIX' ) ) {
			define( 'POWER_BOARD_PLUGIN_PREFIX', 'power_board' );
		}
		
		// Mock WordPress functions
		Functions\when( 'sanitize_text_field' )->returnArg();
	}
	
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Get a private or protected method for testing using reflection
	 */
	private function getPrivateMethod( object $object, string $methodName ) {
		$reflection = new ReflectionClass( $object );
		$method = $reflection->getMethod( $methodName );
		$method->setAccessible( true );
		return $method;
	}

	/**
	 * Get a constant from a class using reflection
	 */
	private function getClassConstant( string $className, string $constantName ) {
		$reflection = new ReflectionClass( $className );
		return $reflection->getConstant( $constantName );
	}

	/**
	 * Create a partial mock to avoid constructor dependencies
	 */
	private function createPartialMockService( string $className, array $methods = [] ) {
		return $this->createPartialMock( $className, $methods );
	}

	public function test_map_powerboard_status_to_wc_with_valid_statuses() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method = $this->getPrivateMethod( $service, 'map_powerboard_status_to_wc' );

		$testCases = [
			'complete' => 'completed',
			'completed' => 'completed',
			'success' => 'processing',
			'successful' => 'processing',
			'processing' => 'processing',
			'pending' => 'pending',
			'failed' => 'failed',
			'cancelled' => 'cancelled',
			'refunded' => 'refunded',
			'charged_back' => 'refunded'
		];

		foreach ( $testCases as $powerboard_status => $expected_wc_status ) {
			$result = $method->invokeArgs( $service, [ $powerboard_status ] );
			$this->assertEquals( 
				$expected_wc_status, 
				$result, 
				"Failed mapping '{$powerboard_status}' to '{$expected_wc_status}'" 
			);
		}
	}

	public function test_map_powerboard_status_to_wc_with_unknown_statuses() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method = $this->getPrivateMethod( $service, 'map_powerboard_status_to_wc' );

		// Test unknown statuses (should default to 'pending')
		$unknown_statuses = [
			'unknown_status',
			'invalid',
			'new_status',
			'random_string',
			'123'
		];

		foreach ( $unknown_statuses as $status ) {
			$result = $method->invokeArgs( $service, [ $status ] );
			$this->assertEquals( 'pending', $result, "Unknown status '{$status}' should default to 'pending'" );
		}
	}

	public function test_map_powerboard_status_to_wc_is_case_insensitive() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method = $this->getPrivateMethod( $service, 'map_powerboard_status_to_wc' );

		// Test case insensitive mapping
		$case_variations = [
			'SUCCESS' => 'processing',
			'Success' => 'processing',
			'COMPLETE' => 'completed',
			'Complete' => 'completed',
			'FAILED' => 'failed',
			'Failed' => 'failed',
			'PENDING' => 'pending'
		];

		foreach ( $case_variations as $powerboard_status => $expected_wc_status ) {
			$result = $method->invokeArgs( $service, [ $powerboard_status ] );
			$this->assertEquals( 
				$expected_wc_status, 
				$result, 
				"Case insensitive mapping failed for '{$powerboard_status}'" 
			);
		}
	}

	public function test_map_powerboard_status_to_wc_handles_null_and_empty() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method = $this->getPrivateMethod( $service, 'map_powerboard_status_to_wc' );

		// Test null input
		$result = $method->invokeArgs( $service, [ null ] );
		$this->assertEquals( 'pending', $result );

		// Test empty string
		$result = $method->invokeArgs( $service, [ '' ] );
		$this->assertEquals( 'pending', $result );

		// Test whitespace
		$result = $method->invokeArgs( $service, [ '   ' ] );
		$this->assertEquals( 'pending', $result );
	}

	public function test_map_powerboard_status_to_wc_trims_whitespace() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method = $this->getPrivateMethod( $service, 'map_powerboard_status_to_wc' );

		// Test whitespace handling with actual whitespace characters
		$whitespace_cases = [
			'  success  ' => 'processing',
			"\tcomplete\t" => 'completed',  // Actual tab characters
			'  failed  ' => 'failed',
			'pending ' => 'pending',
			" \n processing \n " => 'processing',  // Newlines and spaces
			"\r\n cancelled \r\n" => 'cancelled'   // Carriage returns
		];

		foreach ( $whitespace_cases as $powerboard_status => $expected_wc_status ) {
			$result = $method->invokeArgs( $service, [ $powerboard_status ] );
			$this->assertEquals( 
				$expected_wc_status, 
				$result, 
				"Whitespace trimming failed for '" . addslashes($powerboard_status) . "'" 
			);
		}
	}

	public function test_map_powerboard_status_to_wc_matches_constants() {
		// Verify the mapping uses the POWERBOARD_TO_WC_STATUS_MAP constant
		$statusMap = $this->getClassConstant( IPNDuplicateCheckService::class, 'POWERBOARD_TO_WC_STATUS_MAP' );
		
		$this->assertIsArray( $statusMap );
		$this->assertArrayHasKey( 'complete', $statusMap );
		$this->assertArrayHasKey( 'success', $statusMap );
		$this->assertArrayHasKey( 'pending', $statusMap );
		$this->assertArrayHasKey( 'failed', $statusMap );

		// Verify some key mappings match our expectations
		$this->assertEquals( 'completed', $statusMap['complete'] );
		$this->assertEquals( 'processing', $statusMap['success'] );
		$this->assertEquals( 'pending', $statusMap['pending'] );
		$this->assertEquals( 'refunded', $statusMap['charged_back'] );
	}

	public function test_map_powerboard_status_to_wc_comprehensive_mapping() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method = $this->getPrivateMethod( $service, 'map_powerboard_status_to_wc' );

		// Test all statuses from the constant
		$statusMap = $this->getClassConstant( IPNDuplicateCheckService::class, 'POWERBOARD_TO_WC_STATUS_MAP' );
		
		foreach ( $statusMap as $powerboard_status => $expected_wc_status ) {
			$result = $method->invokeArgs( $service, [ $powerboard_status ] );
			$this->assertEquals( 
				$expected_wc_status, 
				$result, 
				"Constant mapping verification failed for '{$powerboard_status}' -> '{$expected_wc_status}'" 
			);
		}
	}
}
