<?php
declare( strict_types=1 );

namespace PowerBoard\Services;

use PowerBoard\Helpers\Util\LoggerHelper;
use PowerBoard\Services\SDKAdapterService;
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
	 * Final states that should trigger conflict resolution
	 */
	const FINAL_STATES = [
		'completed',
		'failed',
		'cancelled',
		'refunded',
	];

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

	/**
	 * Status priority for determining which status should take precedence
	 * Higher number = higher priority
	 */
	const STATUS_PRIORITY = [
		'pending'    => 1,
		'processing' => 2,
		'cancelled'  => 3,
		'failed'     => 4,
		'refunded'   => 5,
		'completed'  => 6,
	];

	private SDKAdapterService $sdk_adapter;

	public function __construct() {
		$this->sdk_adapter = SDKAdapterService::get_instance();
	}

	/**
	 * Process IPN notification and apply duplicate checking logic
	 *
	 * @param array $ipn_data The IPN payload data
	 * @return array Processing result with status and messages
	 */
	public function process_ipn_notification( array $ipn_data ): array {
		try {
			// Extract essential data from IPN
			$charge_id  = $this->extract_charge_id( $ipn_data );
			$order_id   = $this->extract_order_id( $ipn_data );
			$ipn_status = $this->extract_payment_status( $ipn_data );

			if ( empty( $charge_id ) || empty( $order_id ) ) {
				$this->log_ipn_event(
					'IPN validation failed: Missing charge_id or order_id',
					[
						'ipn_data'  => $ipn_data,
						'charge_id' => $charge_id,
						'order_id'  => $order_id,
					],
					'error'
					);

				return [
					'status'    => 'error',
					'message'   => 'Missing required IPN data',
					'http_code' => 400,
				];
			}

			// Get WooCommerce order
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				$this->log_ipn_event(
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

			// Validate that this order belongs to PowerBoard
			if ( strpos( $order->get_payment_method(), POWER_BOARD_PLUGIN_PREFIX ) === false ) {
				$this->log_ipn_event(
					'IPN rejected: Order not processed by PowerBoard',
					[
						'order_id'       => $order_id,
						'payment_method' => $order->get_payment_method(),
						'charge_id'      => $charge_id,
					],
					'warning'
					);

				return [
					'status'    => 'ignored',
					'message'   => 'Order not processed by PowerBoard',
					'http_code' => 200,
				];
			}

			$current_order_status = $order->get_status();
			$target_wc_status     = $this->map_powerboard_status_to_wc( $ipn_status );

			$this->log_ipn_event(
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

			// Check for duplicate status
			if ( $this->is_duplicate_status( $current_order_status, $target_wc_status ) ) {
				$this->log_ipn_event(
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
					'message'   => 'Duplicate IPN - status unchanged',
					'http_code' => 200,
				];
			}

			// Check if current order status is final
			if ( $this->is_final_state( $current_order_status ) ) {
				return $this->handle_final_state_conflict( $order, $charge_id, $current_order_status, $target_wc_status, $ipn_status );
			}

			// Normal status update - current status is not final
			return $this->update_order_status( $order, $target_wc_status, $charge_id, $ipn_status, $current_order_status );

		} catch ( Exception $e ) {
			$this->log_ipn_event(
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
	private function handle_final_state_conflict( WC_Order $order, string $charge_id, string $current_status, string $target_status, string $ipn_status ): array {
		$this->log_ipn_event(
			'Final state conflict detected - triggering API verification',
			[
				'order_id'             => $order->get_id(),
				'charge_id'            => $charge_id,
				'current_final_status' => $current_status,
				'ipn_target_status'    => $target_status,
				'ipn_status'           => $ipn_status,
			],
			'warning'
			);

		try {
			// Get definitive status from PowerBoard API
			$api_response         = $this->sdk_adapter->get_charge( $charge_id );
			$api_status           = $this->extract_status_from_api_response( $api_response );
			$definitive_wc_status = $this->map_powerboard_status_to_wc( $api_status );

			$this->log_ipn_event(
				'API verification completed',
				[
					'order_id'             => $order->get_id(),
					'charge_id'            => $charge_id,
					'api_status'           => $api_status,
					'definitive_wc_status' => $definitive_wc_status,
					'current_status'       => $current_status,
				],
				'info'
				);

			// Check if API status differs from current order status
			if ( $definitive_wc_status !== $current_status ) {
				// Determine if the API status has higher priority
				if ( $this->should_update_status( $current_status, $definitive_wc_status ) ) {
					return $this->update_order_status( $order, $definitive_wc_status, $charge_id, $api_status, $current_status, true );
				} else {
					$this->log_ipn_event(
						'API status has lower priority - no update',
						[
							'order_id'             => $order->get_id(),
							'charge_id'            => $charge_id,
							'current_status'       => $current_status,
							'api_status'           => $api_status,
							'definitive_wc_status' => $definitive_wc_status,
							'action'               => 'ignored',
						],
						'info'
						);

					return [
						'status'    => 'no_update',
						'message'   => 'API status has lower priority than current status',
						'http_code' => 200,
					];
				}
			} else {
				$this->log_ipn_event(
					'API confirms current status - no change needed',
					[
						'order_id'       => $order->get_id(),
						'charge_id'      => $charge_id,
						'current_status' => $current_status,
						'api_status'     => $api_status,
						'action'         => 'confirmed',
					],
					'info'
					);

				return [
					'status'    => 'confirmed',
					'message'   => 'API confirms current order status',
					'http_code' => 200,
				];
			}
		} catch ( Exception $e ) {
			$this->log_ipn_event(
				'API verification failed',
				[
					'order_id'        => $order->get_id(),
					'charge_id'       => $charge_id,
					'error'           => $e->getMessage(),
					'fallback_action' => 'maintain_current_status',
				],
						'error'
				);

			// On API failure, maintain current status but still return 200
			return [
				'status'    => 'api_error',
				'message'   => 'API verification failed - maintaining current status',
				'http_code' => 200,
			];
		}
	}

	/**
	 * Update order status with comprehensive logging
	 *
	 * @param WC_Order $order WooCommerce order object
	 * @param string $new_status New WooCommerce status
	 * @param string $charge_id PowerBoard charge ID
	 * @param string $original_status Original PowerBoard status
	 * @param string $old_status Previous WooCommerce status
	 * @param bool $is_api_resolved Whether this update came from API resolution
	 * @return array Processing result
	 */
	private function update_order_status( WC_Order $order, string $new_status, string $charge_id, string $original_status, string $old_status, bool $is_api_resolved = false ): array {
		$order_id = $order->get_id();

		try {
			// Build order note
			$source = $is_api_resolved ? 'API verification' : 'IPN notification';
			$note   = sprintf(
				'Payment status updated via %s. Status: %s (Charge ID: %s)',
				$source,
				$original_status,
				$charge_id
			);

			// Update order status
			$order->update_status( $new_status, $note );
			$order->save();

			$this->log_ipn_event(
				'Order status updated successfully',
				[
					'order_id'                   => $order_id,
					'charge_id'                  => $charge_id,
					'old_status'                 => $old_status,
					'new_status'                 => $new_status,
					'original_powerboard_status' => $original_status,
					'source'                     => $source,
					'note_added'                 => $note,
				],
				'info'
				);

			return [
				'status'    => 'updated',
				'message'   => "Order status updated from {$old_status} to {$new_status}",
				'http_code' => 200,
			];

		} catch ( Exception $e ) {
			$this->log_ipn_event(
				'Order status update failed',
				[
					'order_id'            => $order_id,
					'charge_id'           => $charge_id,
					'intended_new_status' => $new_status,
					'error'               => $e->getMessage(),
				],
				'error'
				);

			return [
				'status'    => 'update_failed',
				'message'   => 'Failed to update order status',
				'http_code' => 500,
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
		$new_priority     = self::STATUS_PRIORITY[ $new_status ] ?? 0;

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
		if ( ! empty( $api_response['resource']['data']['status'] ) ) {
			return strtolower( sanitize_text_field( (string) $api_response['resource']['data']['status'] ) );
		}

		if ( ! empty( $api_response['data']['status'] ) ) {
			return strtolower( sanitize_text_field( (string) $api_response['data']['status'] ) );
		}

		if ( ! empty( $api_response['status'] ) ) {
			return strtolower( sanitize_text_field( (string) $api_response['status'] ) );
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

	/**
	 * Log IPN-related events with consistent format
	 *
	 * @param string $message Log message
	 * @param array $context Log context data
	 * @param string $level Log level (info, warning, error)
	 */
	private function log_ipn_event( string $message, array $context = [], string $level = 'info' ): void {
		LoggerHelper::log_callback_event( $message, $context, $level );
	}

	/**
	 * Parse JSON IPN payload safely
	 *
	 * @param string $json_payload Raw JSON payload
	 * @return array Parsed data or empty array on failure
	 */
	public function parse_ipn_payload( string $json_payload ): array {
		try {
			$data = json_decode( $json_payload, true );
			return is_array( $data ) ? $data : [];
		} catch ( Exception $e ) {
			$this->log_ipn_event(
				'IPN payload parsing failed',
				[
					'payload' => substr( $json_payload, 0, 500 ), // Log only first 500 chars for security
					'error'   => $e->getMessage(),
				],
				'error'
				);
			return [];
		}
	}
}
