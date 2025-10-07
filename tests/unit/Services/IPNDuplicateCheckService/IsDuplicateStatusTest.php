<?php
declare( strict_types=1 );

namespace unit\Services\IPNDuplicateCheckService;

use unit\Services\BaseServiceTest;
use PowerBoard\Services\IPNDuplicateCheckService;

/**
 * Tests for IPNDuplicateCheckService::is_duplicate_status() method
 */
class IsDuplicateStatusTest extends BaseServiceTest {

	public function test_is_duplicate_status_returns_true_for_matching_statuses() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method = $this->getPrivateMethod( $service, 'is_duplicate_status' );

		// Test duplicate detection
		$this->assertTrue( $method->invokeArgs( $service, [ 'processing', 'processing' ] ) );
		$this->assertTrue( $method->invokeArgs( $service, [ 'completed', 'completed' ] ) );
		$this->assertTrue( $method->invokeArgs( $service, [ 'failed', 'failed' ] ) );
		$this->assertTrue( $method->invokeArgs( $service, [ 'pending', 'pending' ] ) );
	}

	public function test_is_duplicate_status_returns_false_for_different_statuses() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method = $this->getPrivateMethod( $service, 'is_duplicate_status' );

		// Test non-duplicates
		$this->assertFalse( $method->invokeArgs( $service, [ 'pending', 'processing' ] ) );
		$this->assertFalse( $method->invokeArgs( $service, [ 'processing', 'completed' ] ) );
		$this->assertFalse( $method->invokeArgs( $service, [ 'completed', 'failed' ] ) );
		$this->assertFalse( $method->invokeArgs( $service, [ 'failed', 'cancelled' ] ) );
	}

	public function test_is_duplicate_status_is_case_sensitive() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method = $this->getPrivateMethod( $service, 'is_duplicate_status' );

		// Case sensitivity test
		$this->assertFalse( $method->invokeArgs( $service, [ 'Processing', 'processing' ] ) );
		$this->assertFalse( $method->invokeArgs( $service, [ 'COMPLETED', 'completed' ] ) );
	}

	public function test_is_duplicate_status_handles_empty_strings() {
		$service = $this->createPartialMockService( IPNDuplicateCheckService::class );
		$method = $this->getPrivateMethod( $service, 'is_duplicate_status' );

		// Empty string tests
		$this->assertTrue( $method->invokeArgs( $service, [ '', '' ] ) );
		$this->assertFalse( $method->invokeArgs( $service, [ '', 'processing' ] ) );
		$this->assertFalse( $method->invokeArgs( $service, [ 'processing', '' ] ) );
	}
}
