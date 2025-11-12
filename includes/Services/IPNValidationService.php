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
class IPNValidationService {

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

	const API_TO_IPN_STATUS_MAP = [
		'complete'   => 'success',
		'completed'  => 'success',
		'success'    => 'success',
		'successful' => 'success',
		'processing' => 'success',
		'pending'    => 'pending',
		'failed'     => 'failed',
		'cancelled'  => 'cancelled',
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
			$charge_id  = $ipn->get_charge()->get_charge_id();
			$order_id   = $ipn->get_order_id();
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
			$target_wc_status     = $this->map_powerboard_status_to_wc( $ipn_status );

			$this->check_double_payment( $order, $charge_id, $current_order_status, $target_wc_status );

			// Check for duplicate status
			if ( $this->is_duplicate_status( $current_order_status, $target_wc_status ) ) {
				LoggerHelper::log_callback_event(
					' Order Updated - no action taken',
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
					'status'    => 'updated',
					'message'   => 'Updated',
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

	public function authenticate_ipn( $ipn ) {
		$api_response = $this->sdk_adapter->get_charge( $ipn->get_charge()->get_charge_id() );
		$api_status   = $this->extract_status_from_api_response( $api_response );
		$api_status   = self::API_TO_IPN_STATUS_MAP[ $api_status ] ?? null;
		$ipn_status   = $this->map_event_to_status( $ipn->get_event() );

		if ( $api_status === $ipn_status && (int) $ipn->get_order_id() === (int) $api_response['resource']['data']['reference']) {
			return true;
		}

		return false;
	}

	/**
	 * Extract status from PowerBoard API response
	 *
	 * @param array $api_response API response from get_charge
	 * @return string|null Status or null if not found
	 */
	private function extract_status_from_api_response( array $api_response ): ?string {
		// Check common response structures
		if ( !empty( $api_response['resource']['data']['status'] ) ) {
			return strtolower( sanitize_text_field( (string) $api_response['resource']['data']['status'] ) );
		}

		if ( !empty( $api_response['data']['status'] ) ) {
			return strtolower( sanitize_text_field( (string) $api_response['data']['status'] ) );
		}

		if ( !empty( $api_response['status'] ) ) {
			return strtolower( sanitize_text_field( (string) $api_response['status'] ) );
		}

		return null;
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
		$success_status   = [ 'processing', 'completed' ];

		if (
			!empty( $stored_charge_id ) &&
			in_array( $current_status, $success_status, true ) &&
			in_array( $target_status, $success_status, true ) &&
			$stored_charge_id !== $new_charge_id
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
