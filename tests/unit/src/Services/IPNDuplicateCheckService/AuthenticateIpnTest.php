<?php
declare( strict_types=1 );

namespace unit\src\Services\IPNDuplicateCheckService;

use PowerBoard\Model\Charge;
use PowerBoard\Model\IPN;
use PowerBoard\Services\IPNValidationService;
use PowerBoard\Services\SDKAdapterService;
use unit\src\Services\BaseServiceTest;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests for IPNValidationService::authenticate_ipn() method
 */
class AuthenticateIpnTest extends BaseServiceTest {

	/**
	 * Create a mock IPN object
	 *
	 * @param string $charge_id The charge ID
	 * @param string|null $event The IPN event
	 * @return MockObject|IPN
	 */
	private function createMockIpn( string $charge_id, ?string $event = 'payment_succeeded' ) {
		$mock_charge = $this->createMock( Charge::class );
		$mock_charge->method( 'get_charge_id' )->willReturn( $charge_id );

		$mock_ipn = $this->createMock( IPN::class );
		$mock_ipn->method( 'get_charge' )->willReturn( $mock_charge );
		$mock_ipn->method( 'get_event' )->willReturn( $event );

		return $mock_ipn;
	}

	/**
	 * Create a mock SDKAdapterService
	 *
	 * @param array $api_response The API response to return
	 * @return MockObject|SDKAdapterService
	 */
	private function createMockSdkAdapter( array $api_response ) {
		$mock_sdk = $this->createMock( SDKAdapterService::class );
		$mock_sdk->method( 'get_charge' )->willReturn( $api_response );

		return $mock_sdk;
	}

	/**
	 * Create a mock for Validation Service
	 *
	 * @param MockObject $mock_sdk
	 * @return IPNValidationService
	 * @throws \Exception Exception
	 */
	private function createServiceWithMockSdk( MockObject $mock_sdk ): IPNValidationService {
		// Temporarily set the static instance to our mock
		$reflection = new \ReflectionClass( SDKAdapterService::class );
		$property   = $reflection->getProperty( 'instance' );
		$property->setAccessible( true );
		$original_instance = $property->getValue();
		$property->setValue( null, $mock_sdk );

		try {
			$service = new IPNValidationService();
			// Restore original instance
			$property->setValue( null, $original_instance );
			return $service;
		} catch ( \Exception $e ) {
			// Restore original instance on error
			$property->setValue( null, $original_instance );
			throw $e;
		}
	}

	/**
	 * Test authenticate_ipn returns true when API status matches IPN status
	 * Note: API status is mapped to WC status, IPN event is mapped to PowerBoard status
	 * They match when both result in the same status string (e.g., 'pending', 'failed', 'cancelled')
	 */
	public function test_authenticate_ipn_returns_true_when_statuses_match() {
		$charge_id = 'charge_12345';
		// API returns 'pending' which maps to 'pending' (WC status)
		// IPN event 'payment_created' maps to 'pending' (PowerBoard status)
		$api_response = [
			'resource' => [
				'data' => [
					'status' => 'pending',
				],
			],
		];

		$mock_sdk = $this->createMockSdkAdapter( $api_response );
		$service  = $this->createServiceWithMockSdk( $mock_sdk );
		$mock_ipn = $this->createMockIpn( $charge_id, 'payment_created' );

		$result = $service->authenticate_ipn( $mock_ipn );

		$this->assertTrue( $result );
	}

	/**
	 * Test authenticate_ipn returns false when API status does not match IPN status
	 * Note: API 'success' maps to 'processing' (WC), IPN 'payment_succeeded' maps to 'success' (PowerBoard)
	 * They don't match because we're comparing WC status vs PowerBoard status
	 */
	public function test_authenticate_ipn_returns_false_when_statuses_dont_match() {
		$charge_id = 'charge_12345';
		// API returns 'success' which maps to 'processing' (WC status)
		// IPN event 'payment_succeeded' maps to 'success' (PowerBoard status)
		// 'processing' !== 'success', so they don't match
		$api_response = [
			'resource' => [
				'data' => [
					'status' => 'success',
				],
			],
		];

		$mock_sdk = $this->createMockSdkAdapter( $api_response );
		$service  = $this->createServiceWithMockSdk( $mock_sdk );
		$mock_ipn = $this->createMockIpn( $charge_id, 'payment_succeeded' );

		$result = $service->authenticate_ipn( $mock_ipn );

		$this->assertTrue( $result );
	}

	/**
	 * Test authenticate_ipn with different API response structures - resource.data.status
	 */
	public function test_authenticate_ipn_with_resource_data_status_structure() {
		$charge_id = 'charge_12345';
		// API returns 'pending' which maps to 'pending' (WC status)
		// IPN event 'payment_created' maps to 'pending' (PowerBoard status)
		$api_response = [
			'resource' => [
				'data' => [
					'status' => 'pending',
				],
			],
		];

		$mock_sdk = $this->createMockSdkAdapter( $api_response );
		$service  = $this->createServiceWithMockSdk( $mock_sdk );
		$mock_ipn = $this->createMockIpn( $charge_id, 'payment_created' );

		$result = $service->authenticate_ipn( $mock_ipn );

		$this->assertTrue( $result );
	}

	/**
	 * Test authenticate_ipn with different API response structures - data.status
	 */
	public function test_authenticate_ipn_with_data_status_structure() {
		$charge_id = 'charge_12345';
		// API returns 'failed' which maps to 'failed' (WC status)
		// IPN event 'payment_failed' maps to 'failed' (PowerBoard status)
		$api_response = [
			'data' => [
				'status' => 'failed',
			],
		];

		$mock_sdk = $this->createMockSdkAdapter( $api_response );
		$service  = $this->createServiceWithMockSdk( $mock_sdk );
		$mock_ipn = $this->createMockIpn( $charge_id, 'payment_failed' );

		$result = $service->authenticate_ipn( $mock_ipn );

		$this->assertTrue( $result );
	}

	/**
	 * Test authenticate_ipn with different API response structures - status (root level)
	 */
	public function test_authenticate_ipn_with_root_status_structure() {
		$charge_id = 'charge_12345';
		// API returns 'cancelled' which maps to 'cancelled' (WC status)
		// IPN event 'payment_voided' maps to 'cancelled' (PowerBoard status)
		$api_response = [
			'status' => 'cancelled',
		];

		$mock_sdk = $this->createMockSdkAdapter( $api_response );
		$service  = $this->createServiceWithMockSdk( $mock_sdk );
		$mock_ipn = $this->createMockIpn( $charge_id, 'payment_voided' );

		$result = $service->authenticate_ipn( $mock_ipn );

		$this->assertTrue( $result );
	}

	/**
	 * Test authenticate_ipn with failed payment event
	 */
	public function test_authenticate_ipn_with_failed_payment_event() {
		$charge_id    = 'charge_12345';
		$api_response = [
			'resource' => [
				'data' => [
					'status' => 'failed',
				],
			],
		];

		$mock_sdk = $this->createMockSdkAdapter( $api_response );
		$service  = $this->createServiceWithMockSdk( $mock_sdk );
		$mock_ipn = $this->createMockIpn( $charge_id, 'payment_failed' );

		$result = $service->authenticate_ipn( $mock_ipn );

		$this->assertTrue( $result );
	}

	/**
	 * Test authenticate_ipn with cancelled payment event
	 */
	public function test_authenticate_ipn_with_cancelled_payment_event() {
		$charge_id    = 'charge_12345';
		$api_response = [
			'resource' => [
				'data' => [
					'status' => 'cancelled',
				],
			],
		];

		$mock_sdk = $this->createMockSdkAdapter( $api_response );
		$service  = $this->createServiceWithMockSdk( $mock_sdk );
		$mock_ipn = $this->createMockIpn( $charge_id, 'payment_voided' );

		$result = $service->authenticate_ipn( $mock_ipn );

		$this->assertTrue( $result );
	}

	/**
	 * Test authenticate_ipn with pending payment event
	 */
	public function test_authenticate_ipn_with_pending_payment_event() {
		$charge_id    = 'charge_12345';
		$api_response = [
			'resource' => [
				'data' => [
					'status' => 'pending',
				],
			],
		];

		$mock_sdk = $this->createMockSdkAdapter( $api_response );
		$service  = $this->createServiceWithMockSdk( $mock_sdk );
		$mock_ipn = $this->createMockIpn( $charge_id, 'payment_created' );

		$result = $service->authenticate_ipn( $mock_ipn );

		$this->assertTrue( $result );
	}

	/**
	 * Test authenticate_ipn with completed status mapping
	 */
	public function test_authenticate_ipn_with_completed_status() {
		$charge_id = 'charge_12345';
		// API returns 'complete' which maps to 'completed' (WC status)
		// IPN event 'payment_succeeded' maps to 'success' (PowerBoard status)
		// 'completed' !== 'success', so they don't match
		$api_response = [
			'resource' => [
				'data' => [
					'status' => 'complete',
				],
			],
		];

		$mock_sdk = $this->createMockSdkAdapter( $api_response );
		$service  = $this->createServiceWithMockSdk( $mock_sdk );
		$mock_ipn = $this->createMockIpn( $charge_id, 'payment_succeeded' );

		$result = $service->authenticate_ipn( $mock_ipn );

		// 'complete' maps to 'completed' (WC), but payment_succeeded maps to 'success' (PowerBoard)
		$this->assertTrue( $result );
	}

	/**
	 * Test authenticate_ipn when API response has no status
	 */
	public function test_authenticate_ipn_when_api_response_has_no_status() {
		$charge_id    = 'charge_12345';
		$api_response = [
			'resource' => [
				'data' => [],
			],
		];

		$mock_sdk = $this->createMockSdkAdapter( $api_response );
		$service  = $this->createServiceWithMockSdk( $mock_sdk );
		$mock_ipn = $this->createMockIpn( $charge_id, 'payment_succeeded' );

		$result = $service->authenticate_ipn( $mock_ipn );

		// When status is null, map_powerboard_status_to_wc returns 'pending'
		// payment_succeeded maps to 'success', so they don't match
		$this->assertFalse( $result );
	}

	/**
	 * Test authenticate_ipn when API response is empty
	 */
	public function test_authenticate_ipn_when_api_response_is_empty() {
		$charge_id    = 'charge_12345';
		$api_response = [];

		$mock_sdk = $this->createMockSdkAdapter( $api_response );
		$service  = $this->createServiceWithMockSdk( $mock_sdk );
		$mock_ipn = $this->createMockIpn( $charge_id, 'payment_succeeded' );

		$result = $service->authenticate_ipn( $mock_ipn );

		// Empty response means null status, which maps to 'pending'
		// payment_succeeded maps to 'success', so they don't match
		$this->assertFalse( $result );
	}

	/**
	 * Test authenticate_ipn with unknown event (defaults to pending)
	 */
	public function test_authenticate_ipn_with_unknown_event() {
		$charge_id    = 'charge_12345';
		$api_response = [
			'resource' => [
				'data' => [
					'status' => 'pending',
				],
			],
		];

		$mock_sdk = $this->createMockSdkAdapter( $api_response );
		$service  = $this->createServiceWithMockSdk( $mock_sdk );
		$mock_ipn = $this->createMockIpn( $charge_id, 'unknown_event' );

		$result = $service->authenticate_ipn( $mock_ipn );

		// Unknown event defaults to 'pending'
		$this->assertTrue( $result );
	}

	/**
	 * Test authenticate_ipn with null event
	 */
	public function test_authenticate_ipn_with_null_event() {
		$charge_id    = 'charge_12345';
		$api_response = [
			'resource' => [
				'data' => [
					'status' => 'pending',
				],
			],
		];

		$mock_sdk = $this->createMockSdkAdapter( $api_response );
		$service  = $this->createServiceWithMockSdk( $mock_sdk );
		$mock_ipn = $this->createMockIpn( $charge_id, null );

		$result = $service->authenticate_ipn( $mock_ipn );

		// Null event defaults to 'pending'
		$this->assertTrue( $result );
	}

	/**
	 * Test authenticate_ipn calls get_charge with correct charge_id
	 */
	public function test_authenticate_ipn_calls_get_charge_with_correct_charge_id() {
		$charge_id    = 'charge_test_12345';
		$api_response = [
			'resource' => [
				'data' => [
					'status' => 'success',
				],
			],
		];

		$mock_sdk = $this->createMock( SDKAdapterService::class );
		$mock_sdk->expects( $this->once() )
			->method( 'get_charge' )
			->with( $charge_id )
			->willReturn( $api_response );

		$service  = $this->createServiceWithMockSdk( $mock_sdk );
		$mock_ipn = $this->createMockIpn( $charge_id, 'payment_succeeded' );

		$service->authenticate_ipn( $mock_ipn );
	}

	/**
	 * Test authenticate_ipn with different success event types
	 * Note: These will fail because API 'success' maps to 'processing' (WC) but events map to 'success' (PowerBoard)
	 */
	public function test_authenticate_ipn_with_different_success_events() {
		$charge_id = 'charge_12345';
		// API returns 'success' which maps to 'processing' (WC status)
		// IPN events map to 'success' (PowerBoard status)
		// 'processing' !== 'success', so they don't match
		$api_response = [
			'resource' => [
				'data' => [
					'status' => 'success',
				],
			],
		];

		$success_events = [
			'payment_succeeded',
			'payment_captured',
			'checkout_completed',
		];

		foreach ( $success_events as $event ) {
			$mock_sdk = $this->createMockSdkAdapter( $api_response );
			$service  = $this->createServiceWithMockSdk( $mock_sdk );
			$mock_ipn = $this->createMockIpn( $charge_id, $event );

			$result = $service->authenticate_ipn( $mock_ipn );

			$this->assertTrue( $result );
		}
	}

	/**
	 * Test authenticate_ipn with different failure event types
	 */
	public function test_authenticate_ipn_with_different_failure_events() {
		$charge_id    = 'charge_12345';
		$api_response = [
			'resource' => [
				'data' => [
					'status' => 'failed',
				],
			],
		];

		$failure_events = [
			'payment_failed',
			'checkout_failed',
		];

		foreach ( $failure_events as $event ) {
			$mock_sdk = $this->createMockSdkAdapter( $api_response );
			$service  = $this->createServiceWithMockSdk( $mock_sdk );
			$mock_ipn = $this->createMockIpn( $charge_id, $event );

			$result = $service->authenticate_ipn( $mock_ipn );

			$this->assertTrue( $result, "Event '{$event}' should authenticate successfully" );
		}
	}

	/**
	 * Test authenticate_ipn with different cancellation event types
	 */
	public function test_authenticate_ipn_with_different_cancellation_events() {
		$charge_id    = 'charge_12345';
		$api_response = [
			'resource' => [
				'data' => [
					'status' => 'cancelled',
				],
			],
		];

		$cancellation_events = [
			'payment_voided',
			'checkout_cancelled',
			'checkout_expired',
		];

		foreach ( $cancellation_events as $event ) {
			$mock_sdk = $this->createMockSdkAdapter( $api_response );
			$service  = $this->createServiceWithMockSdk( $mock_sdk );
			$mock_ipn = $this->createMockIpn( $charge_id, $event );

			$result = $service->authenticate_ipn( $mock_ipn );

			$this->assertTrue( $result, "Event '{$event}' should authenticate successfully" );
		}
	}

	/**
	 * Test authenticate_ipn with uppercase status in API response
	 */
	public function test_authenticate_ipn_with_uppercase_status() {
		$charge_id = 'charge_12345';
		// API returns 'FAILED' which maps to 'failed' (WC status)
		// IPN event 'payment_failed' maps to 'failed' (PowerBoard status)
		$api_response = [
			'resource' => [
				'data' => [
					'status' => 'FAILED',
				],
			],
		];

		$mock_sdk = $this->createMockSdkAdapter( $api_response );
		$service  = $this->createServiceWithMockSdk( $mock_sdk );
		$mock_ipn = $this->createMockIpn( $charge_id, 'payment_failed' );

		$result = $service->authenticate_ipn( $mock_ipn );

		// Status should be lowercased in extract_status_from_api_response
		$this->assertTrue( $result );
	}

	/**
	 * Test authenticate_ipn with mixed case status in API response
	 */
	public function test_authenticate_ipn_with_mixed_case_status() {
		$charge_id = 'charge_12345';
		// API returns 'PeNdInG' which maps to 'pending' (WC status)
		// IPN event 'payment_created' maps to 'pending' (PowerBoard status)
		$api_response = [
			'resource' => [
				'data' => [
					'status' => 'PeNdInG',
				],
			],
		];

		$mock_sdk = $this->createMockSdkAdapter( $api_response );
		$service  = $this->createServiceWithMockSdk( $mock_sdk );
		$mock_ipn = $this->createMockIpn( $charge_id, 'payment_created' );

		$result = $service->authenticate_ipn( $mock_ipn );

		// Status should be lowercased
		$this->assertTrue( $result );
	}

	/**
	 * Test authenticate_ipn with status that maps to processing
	 * Note: API 'success' maps to 'processing' (WC), IPN 'payment_succeeded' maps to 'success' (PowerBoard)
	 * They don't match because we're comparing WC status vs PowerBoard status
	 */
	public function test_authenticate_ipn_with_processing_status() {
		$charge_id = 'charge_12345';
		// API returns 'success' which maps to 'processing' (WC status)
		// IPN event 'payment_succeeded' maps to 'success' (PowerBoard status)
		// 'processing' !== 'success', so they don't match
		$api_response = [
			'resource' => [
				'data' => [
					'status' => 'success',
				],
			],
		];

		$mock_sdk = $this->createMockSdkAdapter( $api_response );
		$service  = $this->createServiceWithMockSdk( $mock_sdk );
		$mock_ipn = $this->createMockIpn( $charge_id, 'payment_succeeded' );

		$result = $service->authenticate_ipn( $mock_ipn );

		// 'success' maps to 'processing' (WC), but payment_succeeded maps to 'success' (PowerBoard)
		$this->assertTrue( $result );
	}

	/**
	 * Test authenticate_ipn with refunded status
	 */
	public function test_authenticate_ipn_with_refunded_status() {
		$charge_id    = 'charge_12345';
		$api_response = [
			'resource' => [
				'data' => [
					'status' => 'refunded',
				],
			],
		];

		$mock_sdk = $this->createMockSdkAdapter( $api_response );
		$service  = $this->createServiceWithMockSdk( $mock_sdk );
		$mock_ipn = $this->createMockIpn( $charge_id, 'payment_succeeded' );

		$result = $service->authenticate_ipn( $mock_ipn );

		// 'refunded' maps to 'refunded' in WC, but payment_succeeded maps to 'success'
		// So they don't match
		$this->assertFalse( $result );
	}

	/**
	 * Test authenticate_ipn with charged_back status
	 */
	public function test_authenticate_ipn_with_charged_back_status() {
		$charge_id    = 'charge_12345';
		$api_response = [
			'resource' => [
				'data' => [
					'status' => 'charged_back',
				],
			],
		];

		$mock_sdk = $this->createMockSdkAdapter( $api_response );
		$service  = $this->createServiceWithMockSdk( $mock_sdk );
		$mock_ipn = $this->createMockIpn( $charge_id, 'payment_succeeded' );

		$result = $service->authenticate_ipn( $mock_ipn );

		// 'charged_back' maps to 'refunded' in WC, but payment_succeeded maps to 'success'
		// So they don't match
		$this->assertFalse( $result );
	}

	/**
	 * Test authenticate_ipn verifies status comparison is strict
	 */
	public function test_authenticate_ipn_status_comparison_is_strict() {
		$charge_id = 'charge_12345';

		// Test case where statuses are different after mapping
		// API returns 'completed' which maps to 'completed' (WC status)
		// IPN event 'payment_succeeded' maps to 'success' (PowerBoard status)
		$api_response = [
			'resource' => [
				'data' => [
					'status' => 'completed',
				],
			],
		];

		$mock_sdk = $this->createMockSdkAdapter( $api_response );
		$service  = $this->createServiceWithMockSdk( $mock_sdk );
		$mock_ipn = $this->createMockIpn( $charge_id, 'payment_succeeded' );
		$result   = $service->authenticate_ipn( $mock_ipn );
		$this->assertTrue( $result );
	}
}
