<?php
declare( strict_types=1 );

namespace PowerBoard\Services;

use PowerBoard\Helpers\Util\LoggerHelper;
use PowerBoard\Model\IPN;
use Exception;
use WC_Order;

/**
 * IPN Duplicate Check Service
 *
 * Handles comprehensive duplicate checking for Instant Payment Notifications (IPN)
 * according to the following requirements:
 * - Status Check: Before applying any status change to a WooCommerce order
 * - Duplicate Prevention: Skip updates if status already matches
 * - State Preservation: No changes for duplicates
 * - Acknowledgment Response: Return HTTP 200 even for duplicates
 * - Final State Identification: Recognize final states
 * - Mismatch Detection: Check for conflicts in final states
 * - Conflict Resolution Trigger: Use getCharge API for conflicts
 * - Order Update based on API: Update only if API returns different status
 * - Logging of Resolution: Log all decisions
 */
class IPNDuplicateCheckService {

	/**
	 * Status mapping from PowerBoard to WooCommerce
	 */
	const POWERBOARD_TO_WC_STATUS_MAP = [
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

	private SDKAdapterService $sdk_adapter;

	public function __construct() {
		$this->sdk_adapter = SDKAdapterService::get_instance();
	}

	/**
	 * Process IPN notification and apply duplicate checking logic
	 *
	 * @param IPN $ipn The IPN payload data
	 * @return array Processing result with status and messages
	 */
	public function process_ipn_notification( IPN $ipn ): array {
		try {
			// Extract essential data from IPN
			$charge_id = $ipn->get_charge()->get_charge_id();
			$order_id = $ipn->get_order_id();
			$ipn_status = $this->map_event_to_status( $ipn->get_event() );

			// Get WooCommerce order
			$order = wc_get_order( $order_id );
			if ( !$order ) {
				LoggerHelper::log_callback_event(
					'IPN processing failed: Order not found',
					[
						'order_id'   => $order_id,
						'charge_id'  => $charge_id,
						'ipn_status' => $ipn_status,
					],
					'error'
				);

				return [
					'status'    => 'error',
					'message'   => 'Order not found',
					'http_code' => 404,
				];
			}

			$current_order_status = $order->get_status();
			$target_wc_status = $this->map_powerboard_status_to_wc( $ipn_status );

			$this->check_double_payment( $order, $charge_id, $current_order_status, $target_wc_status );

			// Check for duplicate status
			if ( $this->is_duplicate_status( $current_order_status, $target_wc_status ) ) {
				LoggerHelper::log_callback_event(
					'IPN duplicate detected - no action taken',
					[
						'order_id'       => $order_id,
						'charge_id'      => $charge_id,
						'current_status' => $current_order_status,
						'ipn_status'     => $ipn_status,
						'action'         => 'skipped',
					],
					'info'
				);

				return [
					'status'    => 'duplicate',
					'message'   => 'Duplicated',
					'http_code' => 200,
				];
			}

			if ( !$this->is_status_transition_allowed( $current_order_status, $target_wc_status, $ipn_status ) ) {
				LoggerHelper::log_callback_event(
					'IPN processing failed: Status transition not allowed',
					[
						'order_id'             => $order_id,
						'charge_id'            => $charge_id,
						'current_order_status' => $current_order_status,
						'ipn_status'           => $ipn_status,
						'target_wc_status'     => $target_wc_status,
						'action'               => 'skipped',
					],
					'info'
				);

				return [
					'status'    => 'ignored',
					'message'   => 'Not Allowed',
					'http_code' => 200,
				];
			}

			LoggerHelper::log_callback_event(
				'IPN received',
				[
					'order_id'             => $order_id,
					'charge_id'            => $charge_id,
					'current_order_status' => $current_order_status,
					'ipn_status'           => $ipn_status,
					'target_wc_status'     => $target_wc_status,
				],
				'info'
			);

			// Normal status update - current status is not final
			return [
				'status' => 'update_status',
			];

		} catch ( Exception $e ) {
			LoggerHelper::log_callback_event(
				'IPN processing exception',
				[
					'error'    => $e->getMessage(),
					'trace'    => $e->getTraceAsString(),
					'ipn_data' => $ipn_data ?? [],
				],
				'error'
			);

			return [
				'status'    => 'error',
				'message'   => 'Internal processing error',
				'http_code' => 500,
			];
		}
	}

	/**
	 * Map PowerBoard event to status for duplicate checking
	 *
	 * @param string|null $event PowerBoard event name
	 * @return string Status for duplicate checking
	 */
	private function map_event_to_status( ?string $event ): string {
		$event_to_status_map = [
			'payment_succeeded'  => 'success',
			'payment_captured'   => 'success',
			'checkout_completed' => 'success',
			'payment_failed'     => 'failed',
			'checkout_failed'    => 'failed',
			'payment_voided'     => 'cancelled',
			'checkout_cancelled' => 'cancelled',
			'checkout_expired'   => 'cancelled',
			'payment_created'    => 'pending',
			'checkout_created'   => 'pending',
		];

		return $event_to_status_map[ $event ] ?? 'pending';
	}

	/**
	 * Check if the target status is the same as current order status (duplicate)
	 *
	 * @param string $current_status Current WooCommerce order status
	 * @param string $target_status Target WooCommerce status from IPN
	 * @return bool True if it's a duplicate
	 */
	private function is_duplicate_status( string $current_status, string $target_status ): bool {
		return $current_status === $target_status;
	}

	/**
	 * Check if the target status is the status transition is allowed
	 * if ipn return success and the order is failed or pending we should mark the order as success
	 * if ipn return failed and the order is pending, should be marked as failed
	 * if ipn return failed and the order is success, do nothing
	 * if ipn return refund, we do nothing (in the future the current order-status need to be success in order to be possible to update it)
	 * if ipn return pending do nothing
	 *
	 * @param string $current_status Current WooCommerce order status
	 * @param string $target_status Target WooCommerce status from IPN
	 * @param string $ipn_status IPN status
	 * @return bool True if transition is allowed
	 */
	private function is_status_transition_allowed(
		string $current_status,
		string $target_status,
		string $ipn_status
	): bool {

		if ( in_array( $ipn_status, [ 'success', 'successful' ], true ) &&
			in_array( $current_status, [ 'pending', 'failed' ], true ) ) {
			return true;
		}

		if ( $ipn_status === 'failed' && $current_status === 'pending' ) {
			return true;
		}

		return false;
	}

	/**
	 *
	 * Check if the order has a double payment
	 *
	 * @param WC_Order $order
	 * @param string $new_charge_id
	 * @param string $current_status
	 * @param string $target_status
	 * @return void
	 */
	private function check_double_payment(
		WC_Order $order,
		string $new_charge_id,
		string $current_status,
		string $target_status
	): void {
		$stored_charge_id = $order->get_meta( '_power_board_charge_id' );
		$success_status = [ 'processing', 'completed' ];

		if (
			!empty( $stored_charge_id ) &&
			in_array( $current_status, $success_status, true ) &&
			in_array( $target_status, $success_status, true ) &&
			$stored_charge_id != $new_charge_id
		) {

			LoggerHelper::log_callback_event(
				'WARNING : Possible double payment detected',
				[
					'order_id'         => $order->get_id(),
					'stored_charge_id' => $stored_charge_id,
					'new_charge_id'    => $new_charge_id,
					'current_status'   => $current_status,
					'target_status'    => $target_status,
					'message'          => 'Order has a success status and a new success status, this is a possible double payment',
				],
				'warning'
			);

		}
	}

	/**
	 * Check if the given status is a final state
	 *
	 * @param string $status WooCommerce order status
	 * @return bool True if it's a final state
	 */
	private function is_final_state( string $status ): bool {
		return in_array( $status, self::FINAL_STATES, true );
	}

	/**
	 * Handle conflict when order is in final state but IPN suggests different status
	 *
	 * @param WC_Order $order WooCommerce order object
	 * @param string $charge_id PowerBoard charge ID
	 * @param string $current_status Current order status
	 * @param string $target_status Target status from IPN
	 * @param string $ipn_status Original IPN status
	 * @return array Processing result
	 */
	private function handle_final_state_conflict(
		WC_Order $order,
		string $charge_id,
		string $current_status,
		string $target_status,
		string $ipn_status
	): array {
		LoggerHelper::log_callback_event(
			'Final state conflict detected - triggering API verification',
			[
				'order_id' => $order->get_id(),
				'charge_id' => $charge_id,
				'current_final_status' => $current_status,
				'ipn_target_status' => $target_status,
				'ipn_status' => $ipn_status,
			],
			'warning'
		);

		try {

			// Get definitive status from PowerBoard API
			$api_response = $this->sdk_adapter->get_charge( $charge_id );
			$api_status = $this->extract_status_from_api_response( $api_response );
			$definitive_wc_status = $this->map_powerboard_status_to_wc( $api_status );

			LoggerHelper::log_callback_event(
				'API verification completed',
				[
					'order_id' => $order->get_id(),
					'charge_id' => $charge_id,
					'api_status' => $api_status,
					'definitive_wc_status' => $definitive_wc_status,
					'current_status' => $current_status,
				],
				'info'
			);

			// Check if API status differs from current order status
			if ( $definitive_wc_status !== $current_status ) {
				// Determine if the API status has higher priority
				if ( $this->should_update_status( $current_status, $definitive_wc_status ) ) {
					return [
						'status' => 'update_status',
					];
				} else {
					LoggerHelper::log_callback_event(
						'API status has lower priority - no update',
						[
							'order_id' => $order->get_id(),
							'charge_id' => $charge_id,
							'current_status' => $current_status,
							'api_status' => $api_status,
							'definitive_wc_status' => $definitive_wc_status,
							'action' => 'ignored',
						],
						'info'
					);

					return [
						'status' => 'ignored',
						'message' => 'API status has lower priority than current status',
						'http_code' => 200,
					];
				}
			} else {
				LoggerHelper::log_callback_event(
					'API confirms current status - no change needed',
					[
						'order_id' => $order->get_id(),
						'charge_id' => $charge_id,
						'current_status' => $current_status,
						'api_status' => $api_status,
						'action' => 'confirmed',
					],
					'info'
				);

				return [
					'status' => 'ignored',
					'message' => 'API confirms current order status',
					'http_code' => 200,
				];
			}
		} catch ( Exception $e ) {
			LoggerHelper::log_callback_event(
				'API verification failed',
				[
					'order_id' => $order->get_id(),
					'charge_id' => $charge_id,
					'error' => $e->getMessage(),
					'fallback_action' => 'maintain_current_status',
				],
				'error'
			);

			// On API failure, maintain current status but still return 200
			return [
				'status' => 'api_error',
				'message' => 'API verification failed - maintaining current status',
				'http_code' => 200,
			];
		}
	}

	/**
	 * Determine if status should be updated based on priority
	 *
	 * @param string $current_status Current WooCommerce status
	 * @param string $new_status Proposed new WooCommerce status
	 * @return bool True if status should be updated
	 */
	private function should_update_status( string $current_status, string $new_status ): bool {
		$current_priority = self::STATUS_PRIORITY[ $current_status ] ?? 0;
		$new_priority = self::STATUS_PRIORITY[ $new_status ] ?? 0;

		return $new_priority > $current_priority;
	}

	/**
	 * Extract charge ID from IPN data
	 *
	 * @param array $ipn_data IPN payload
	 * @return string|null Charge ID or null if not found
	 */
	private function extract_charge_id( array $ipn_data ): ?string {
		// Common locations for charge ID in IPN data
		$possible_keys = [ 'charge_id', 'id', 'charge', 'transaction_id' ];
		foreach ( $possible_keys as $key ) {
			if ( ! empty( $ipn_data[ $key ] ) ) {
				return sanitize_text_field( (string) $ipn_data[ $key ] );
			}
		}

		// Check nested data structures
		if ( ! empty( $ipn_data['data']['id'] ) ) {
			return sanitize_text_field( (string) $ipn_data['data']['id'] );
		}

		if ( ! empty( $ipn_data['resource']['data']['id'] ) ) {
			return sanitize_text_field( (string) $ipn_data['resource']['data']['id'] );
		}

		return null;
	}

	/**
	 * Extract order ID from IPN data
	 *
	 * @param array $ipn_data IPN payload
	 * @return int|null Order ID or null if not found
	 */
	private function extract_order_id( array $ipn_data ): ?int {
		// Common locations for order ID in IPN data
		$possible_keys = [ 'order_id', 'reference', 'external_id', 'merchant_reference' ];
		foreach ( $possible_keys as $key ) {
			if ( ! empty( $ipn_data[ $key ] ) ) {
				return absint( $ipn_data[ $key ] );
			}
		}

		// Check nested data structures
		if ( ! empty( $ipn_data['data']['reference'] ) ) {
			return absint( $ipn_data['data']['reference'] );
		}

		if ( ! empty( $ipn_data['resource']['data']['reference'] ) ) {
			return absint( $ipn_data['resource']['data']['reference'] );
		}

		return null;
	}

	/**
	 * Extract payment status from IPN data
	 *
	 * @param array $ipn_data IPN payload
	 * @return string|null Payment status or null if not found
	 */
	private function extract_payment_status( array $ipn_data ): ?string {
		// Common locations for status in IPN data
		$possible_keys = [ 'status', 'state', 'payment_status' ];

		foreach ( $possible_keys as $key ) {
			if ( ! empty( $ipn_data[ $key ] ) ) {
				return strtolower( sanitize_text_field( (string) $ipn_data[ $key ] ) );
			}
		}

		// Check nested data structures
		if ( ! empty( $ipn_data['data']['status'] ) ) {
			return strtolower( sanitize_text_field( (string) $ipn_data['data']['status'] ) );
		}

		if ( ! empty( $ipn_data['resource']['data']['status'] ) ) {
			return strtolower( sanitize_text_field( (string) $ipn_data['resource']['data']['status'] ) );
		}

		return null;
	}

	/**
	 * Extract status from PowerBoard API response
	 *
	 * @param array $api_response API response from get_charge
	 * @return string|null Status or null if not found
	 */
	private function extract_status_from_api_response( array $api_response ): ?string {
		// Check common response structures
		if ( !empty( $api_response[ 'resource' ][ 'data' ][ 'status' ] ) ) {
			return strtolower( sanitize_text_field( (string) $api_response[ 'resource' ][ 'data' ][ 'status' ] ) );
		}

		if ( !empty( $api_response[ 'data' ][ 'status' ] ) ) {
			return strtolower( sanitize_text_field( (string) $api_response[ 'data' ][ 'status' ] ) );
		}

		if ( !empty( $api_response[ 'status' ] ) ) {
			return strtolower( sanitize_text_field( (string) $api_response[ 'status' ] ) );
		}

		return null;
	}

	/**
	 * Map PowerBoard status to WooCommerce order status
	 *
	 * @param string|null $powerboard_status PowerBoard payment status
	 * @return string WooCommerce order status
	 */
	private function map_powerboard_status_to_wc( ?string $powerboard_status ): string {
		if ( empty( $powerboard_status ) ) {
			return 'pending';
		}

		$status = strtolower( trim( $powerboard_status ) );
		return self::POWERBOARD_TO_WC_STATUS_MAP[ $status ] ?? 'pending';
	}

}
