<?php
declare( strict_types=1 );

namespace unit\Services\IPNDuplicateCheckService;

use PHPUnit\Framework\TestCase;
use PowerBoard\Services\IPNDuplicateCheckService;
use ReflectionClass;

/**
 * Tests for IPNDuplicateCheckService constants
 */
class ConstantsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		
		// Define constants if not already defined
		if ( ! defined( 'POWER_BOARD_PLUGIN_PREFIX' ) ) {
			define( 'POWER_BOARD_PLUGIN_PREFIX', 'power_board' );
		}
	}

	/**
	 * Get a constant from a class using reflection
	 */
	private function getClassConstant( string $className, string $constantName ) {
		$reflection = new ReflectionClass( $className );
		return $reflection->getConstant( $constantName );
	}

	public function test_final_states_constant() {
		$finalStates = $this->getClassConstant( IPNDuplicateCheckService::class, 'FINAL_STATES' );

		$this->assertIsArray( $finalStates );
		$this->assertNotEmpty( $finalStates );
		
		// Check required final states
		$this->assertContains( 'completed', $finalStates );
		$this->assertContains( 'failed', $finalStates );
		$this->assertContains( 'cancelled', $finalStates );
		$this->assertContains( 'refunded', $finalStates );
		
		// Should not contain non-final states
		$this->assertNotContains( 'pending', $finalStates );
		$this->assertNotContains( 'processing', $finalStates );
		$this->assertNotContains( 'on-hold', $finalStates );
	}

	public function test_status_priority_constant() {
		$priorities = $this->getClassConstant( IPNDuplicateCheckService::class, 'STATUS_PRIORITY' );

		$this->assertIsArray( $priorities );
		$this->assertNotEmpty( $priorities );
		
		// Check all required statuses are present
		$requiredStatuses = [ 'pending', 'processing', 'cancelled', 'failed', 'refunded', 'completed' ];
		foreach ( $requiredStatuses as $status ) {
			$this->assertArrayHasKey( $status, $priorities, "Status '{$status}' missing from priority map" );
			$this->assertIsInt( $priorities[$status], "Priority for '{$status}' should be integer" );
			$this->assertGreaterThan( 0, $priorities[$status], "Priority for '{$status}' should be positive" );
		}

		// Verify priority ordering (higher number = higher priority)
		$this->assertGreaterThan( $priorities['pending'], $priorities['processing'] ); // processing > pending
		$this->assertGreaterThan( $priorities['processing'], $priorities['cancelled'] ); // cancelled > processing  
		$this->assertGreaterThan( $priorities['cancelled'], $priorities['failed'] ); // failed > cancelled
		$this->assertGreaterThan( $priorities['failed'], $priorities['refunded'] ); // refunded > failed
		$this->assertGreaterThan( $priorities['refunded'], $priorities['completed'] ); // completed > refunded

		// Test specific expected values
		$this->assertEquals( 1, $priorities['pending'] );
		$this->assertEquals( 2, $priorities['processing'] );
		$this->assertEquals( 3, $priorities['cancelled'] );
		$this->assertEquals( 4, $priorities['failed'] );
		$this->assertEquals( 5, $priorities['refunded'] );
		$this->assertEquals( 6, $priorities['completed'] );
	}

	public function test_powerboard_to_wc_status_map_constant() {
		$statusMap = $this->getClassConstant( IPNDuplicateCheckService::class, 'POWERBOARD_TO_WC_STATUS_MAP' );

		$this->assertIsArray( $statusMap );
		$this->assertNotEmpty( $statusMap );
		
		// Check essential mappings exist
		$essentialMappings = [
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

		foreach ( $essentialMappings as $powerboard_status => $expected_wc_status ) {
			$this->assertArrayHasKey( 
				$powerboard_status, 
				$statusMap, 
				"PowerBoard status '{$powerboard_status}' missing from mapping" 
			);
			$this->assertEquals( 
				$expected_wc_status, 
				$statusMap[$powerboard_status], 
				"Incorrect mapping for '{$powerboard_status}'" 
			);
		}

		// Verify all mapped values are valid WooCommerce statuses
		$validWCStatuses = [ 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed' ];
		foreach ( $statusMap as $powerboard_status => $wc_status ) {
			$this->assertContains( 
				$wc_status, 
				$validWCStatuses, 
				"Invalid WC status '{$wc_status}' mapped from '{$powerboard_status}'" 
			);
		}
	}

	public function test_constants_are_properly_typed() {
		// Test that constants have correct types
		$finalStates = $this->getClassConstant( IPNDuplicateCheckService::class, 'FINAL_STATES' );
		$priorities = $this->getClassConstant( IPNDuplicateCheckService::class, 'STATUS_PRIORITY' );
		$statusMap = $this->getClassConstant( IPNDuplicateCheckService::class, 'POWERBOARD_TO_WC_STATUS_MAP' );

		$this->assertIsArray( $finalStates );
		$this->assertIsArray( $priorities );
		$this->assertIsArray( $statusMap );

		// All final states should be strings
		foreach ( $finalStates as $state ) {
			$this->assertIsString( $state, 'Final states should be strings' );
		}

		// All priority values should be integers
		foreach ( $priorities as $status => $priority ) {
			$this->assertIsString( $status, 'Priority keys should be strings' );
			$this->assertIsInt( $priority, 'Priority values should be integers' );
		}

		// All status map entries should be strings
		foreach ( $statusMap as $powerboard_status => $wc_status ) {
			$this->assertIsString( $powerboard_status, 'PowerBoard statuses should be strings' );
			$this->assertIsString( $wc_status, 'WC statuses should be strings' );
		}
	}

	public function test_constants_consistency() {
		$finalStates = $this->getClassConstant( IPNDuplicateCheckService::class, 'FINAL_STATES' );
		$priorities = $this->getClassConstant( IPNDuplicateCheckService::class, 'STATUS_PRIORITY' );
		$statusMap = $this->getClassConstant( IPNDuplicateCheckService::class, 'POWERBOARD_TO_WC_STATUS_MAP' );

		// All final states should have priorities defined
		foreach ( $finalStates as $finalState ) {
			$this->assertArrayHasKey( 
				$finalState, 
				$priorities, 
				"Final state '{$finalState}' should have a priority defined" 
			);
		}

		// All WC statuses in the map should have priorities (except maybe some edge cases)
		$uniqueWCStatuses = array_unique( array_values( $statusMap ) );
		foreach ( $uniqueWCStatuses as $wc_status ) {
			// Allow some statuses that might not have priorities (like 'on-hold')
			if ( in_array( $wc_status, [ 'pending', 'processing', 'cancelled', 'failed', 'refunded', 'completed' ], true ) ) {
				$this->assertArrayHasKey( 
					$wc_status, 
					$priorities, 
					"WC status '{$wc_status}' should have a priority defined" 
				);
			}
		}
	}

	public function test_constants_have_no_duplicates() {
		$finalStates = $this->getClassConstant( IPNDuplicateCheckService::class, 'FINAL_STATES' );
		
		// Final states should have no duplicates
		$uniqueFinalStates = array_unique( $finalStates );
		$this->assertCount( 
			count( $finalStates ), 
			$uniqueFinalStates, 
			'FINAL_STATES should not contain duplicates' 
		);
	}
}
