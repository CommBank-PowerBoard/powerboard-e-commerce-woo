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
	private function getClassConstant( string $class_name, string $constant_name ) {
		$reflection = new ReflectionClass( $class_name );
		return $reflection->getConstant( $constant_name );
	}

	public function test_final_states_constant() {
		$final_states = $this->getClassConstant( IPNDuplicateCheckService::class, 'FINAL_STATES' );

		$this->assertIsArray( $final_states );
		$this->assertNotEmpty( $final_states );

		// Check required final states
		$this->assertContains( 'completed', $final_states );
		$this->assertContains( 'failed', $final_states );
		$this->assertContains( 'cancelled', $final_states );
		$this->assertContains( 'refunded', $final_states );

		// Should not contain non-final states
		$this->assertNotContains( 'pending', $final_states );
		$this->assertNotContains( 'processing', $final_states );
		$this->assertNotContains( 'on-hold', $final_states );
	}

	public function test_status_priority_constant() {
		$priorities = $this->getClassConstant( IPNDuplicateCheckService::class, 'STATUS_PRIORITY' );

		$this->assertIsArray( $priorities );
		$this->assertNotEmpty( $priorities );

		// Check all required statuses are present
		$required_statuses = [ 'pending', 'processing', 'cancelled', 'failed', 'refunded', 'completed' ];
		foreach ( $required_statuses as $status ) {
			$this->assertArrayHasKey( $status, $priorities, "Status '{$status}' missing from priority map" );
			$this->assertIsInt( $priorities[ $status ], "Priority for '{$status}' should be integer" );
			$this->assertGreaterThan( 0, $priorities[ $status ], "Priority for '{$status}' should be positive" );
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
		$status_map = $this->getClassConstant( IPNDuplicateCheckService::class, 'POWERBOARD_TO_WC_STATUS_MAP' );

		$this->assertIsArray( $status_map );
		$this->assertNotEmpty( $status_map );

		// Check essential mappings exist
		$essential_mappings = [
			'complete'     => 'completed',
			'completed'    => 'completed',
			'success'      => 'processing',
			'successful'   => 'processing',
			'processing'   => 'processing',
			'pending'      => 'pending',
			'failed'       => 'failed',
			'cancelled'    => 'cancelled',
			'refunded'     => 'refunded',
			'charged_back' => 'refunded',
		];

		foreach ( $essential_mappings as $powerboard_status => $expected_wc_status ) {
			$this->assertArrayHasKey(
				$powerboard_status,
				$status_map,
				"PowerBoard status '{$powerboard_status}' missing from mapping"
			);
			$this->assertEquals(
				$expected_wc_status,
				$status_map[ $powerboard_status ],
				"Incorrect mapping for '{$powerboard_status}'"
			);
		}

		// Verify all mapped values are valid WooCommerce statuses
		$valid_wc_statuses = [ 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed' ];
		foreach ( $status_map as $powerboard_status => $wc_status ) {
			$this->assertContains(
				$wc_status,
				$valid_wc_statuses,
				"Invalid WC status '{$wc_status}' mapped from '{$powerboard_status}'"
			);
		}
	}

	public function test_constants_are_properly_typed() {
		// Test that constants have correct types
		$final_states = $this->getClassConstant( IPNDuplicateCheckService::class, 'FINAL_STATES' );
		$priorities   = $this->getClassConstant( IPNDuplicateCheckService::class, 'STATUS_PRIORITY' );
		$status_map   = $this->getClassConstant( IPNDuplicateCheckService::class, 'POWERBOARD_TO_WC_STATUS_MAP' );

		$this->assertIsArray( $final_states );
		$this->assertIsArray( $priorities );
		$this->assertIsArray( $status_map );

		// All final states should be strings
		foreach ( $final_states as $state ) {
			$this->assertIsString( $state, 'Final states should be strings' );
		}

		// All priority values should be integers
		foreach ( $priorities as $status => $priority ) {
			$this->assertIsString( $status, 'Priority keys should be strings' );
			$this->assertIsInt( $priority, 'Priority values should be integers' );
		}

		// All status map entries should be strings
		foreach ( $status_map as $powerboard_status => $wc_status ) {
			$this->assertIsString( $powerboard_status, 'PowerBoard statuses should be strings' );
			$this->assertIsString( $wc_status, 'WC statuses should be strings' );
		}
	}

	public function test_constants_consistency() {
		$final_states = $this->getClassConstant( IPNDuplicateCheckService::class, 'FINAL_STATES' );
		$priorities   = $this->getClassConstant( IPNDuplicateCheckService::class, 'STATUS_PRIORITY' );
		$status_map   = $this->getClassConstant( IPNDuplicateCheckService::class, 'POWERBOARD_TO_WC_STATUS_MAP' );

		// All final states should have priorities defined
		foreach ( $final_states as $final_state ) {
			$this->assertArrayHasKey(
				$final_state,
				$priorities,
				"Final state '{$final_state}' should have a priority defined"
			);
		}

		// All WC statuses in the map should have priorities (except maybe some edge cases)
		$unique_wc_statuses = array_unique( array_values( $status_map ) );
		foreach ( $unique_wc_statuses as $wc_status ) {
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
		$final_states = $this->getClassConstant( IPNDuplicateCheckService::class, 'FINAL_STATES' );

		// Final states should have no duplicates
		$unique_final_states = array_unique( $final_states );
		$this->assertCount(
			count( $final_states ),
			$unique_final_states,
			'FINAL_STATES should not contain duplicates'
		);
	}
}
