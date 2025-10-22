<?php
declare( strict_types=1 );

namespace unit\src\Services\IPNDuplicateCheckService;

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

}
