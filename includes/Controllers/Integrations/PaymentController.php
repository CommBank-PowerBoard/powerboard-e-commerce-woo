<?php
declare( strict_types=1 );

namespace PowerBoard\Controllers\Integrations;

use Exception;
use PowerBoard\Services\OrderService;
use PowerBoard\Services\SDKAdapterService;

class PaymentController {
	/**
	 * Handles refund process on PowerBoard
	 * Uses functions (sanitize_text_field, __, remove_action and esc_html) from WordPress
	 * Uses a function (wc_get_order) from WooCommerce
	 * phpcs:disable WordPress.Security.NonceVerification -- processed through the WooCommerce form handler
	 *
	 * @throws Exception If is refund has failed
	 */
	public function refund_process( $refund, $args ): void {
		// Check user authorization - only users who can manage shop orders can process refunds
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			throw new Exception( esc_html( __( 'Insufficient permissions to process refunds', 'power-board' ) ) );
		}

		// Validate order_id parameter
		if ( ! isset( $args['order_id'] ) || ! is_numeric( $args['order_id'] ) || $args['order_id'] <= 0 ) {
			throw new Exception( esc_html( __( 'Invalid order ID provided for refund processing', 'power-board' ) ) );
		}

		$order_id = absint( $args['order_id'] );
		$order    = wc_get_order( $order_id );

		if ( ! $order || strpos( $order->get_payment_method(), POWER_BOARD_PLUGIN_PREFIX ) === false ) {
			return;
		}

		$power_board_charge_id = $order->get_meta( '_power_board_charge_id' );
		if ( empty( $power_board_charge_id ) ) {
			$error = __( 'Unable to process refund. The payment for this order was not successfully completed.', 'power-board' );
			throw new Exception( esc_html( $error ) );
		}

		// Validate amount parameter with comprehensive checks
		if ( isset( $args['amount'] ) ) {
			// Ensure amount is numeric and properly formatted
			if ( ! is_numeric( $args['amount'] ) || $args['amount'] <= 0 ) {
				$error = __( 'Please enter a valid refund amount greater than zero', 'power-board' );
				throw new Exception( esc_html( $error ) );
			}

			$amount = floatval( $args['amount'] );

			// Check upper bounds - amount cannot exceed order total
			$order_total       = floatval( $order->get_total() );
			$already_refunded  = floatval( $order->get_total_refunded() );
			$max_refund_amount = $order_total - $already_refunded;

			if ( $amount > $max_refund_amount ) {
				$error = sprintf(
					/* translators: 1: Requested amount, 2: Maximum refundable amount */
					__( 'Refund amount %1$s exceeds maximum refundable amount of %2$s', 'power-board' ),
					wc_price( $amount ),
					wc_price( $max_refund_amount )
				);
				throw new Exception( esc_html( wp_strip_all_tags( $error ) ) );
			}
		} else {
			// Use full remaining refundable amount if no amount specified
			$amount = floatval( $order->get_total() ) - floatval( $order->get_total_refunded() );

			if ( $amount <= 0 ) {
				$error = __( 'No refundable amount remaining for this order', 'power-board' );
				throw new Exception( esc_html( $error ) );
			}
		}

		if ( ! in_array(
			$order->get_status(),
			[
				'processing',
				'refunded',
				'completed',
				'cancelled',
			],
			true
		) ) {
			return;
		}

		$action = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';

		$amount_to_refund = round( (float) $amount, 2 );
		if ( ( $action === 'edit_order' || $action === 'editpost' ) ) {
			if ( $order->get_meta( '_status_change_verification_failed' ) === '1' ) {
				$refund->set_amount( 0 );
				$refund->set_total( 0 );
				$refund->set_parent_id( 0 );
				return;
			}

			$refund->set_amount( $amount_to_refund );
			$refund->set_total( $amount_to_refund * -1 );
		}

		$result = SDKAdapterService::get_instance()->refunds(
			[
				'charge_id' => $power_board_charge_id,
				'amount'    => $amount_to_refund,
			]
		);

		if ( ! empty( $result['resource']['data']['status'] ) && in_array(
			$result['resource']['data']['status'],
			[ 'refunded', 'refund_requested' ],
			true
		) ) {
			$status = 'refunded';

			$status_note = __( 'The refund of', 'power-board' )
							. " $amount_to_refund "
							. __( 'has been successfully processed.', 'power-board' );

			remove_action( 'woocommerce_order_status_refunded', 'wc_order_fully_refunded' );
			if ( $order->get_status() === $status ) {
				$order->add_order_note( $status_note );
			} else {
				OrderService::update_status( $order_id, $status, $status_note );
			}

			$order->save();
		} elseif ( ! empty( $result['error'] ) ) {
			if ( is_array( $result['error'] ) ) {
				if (
					$result['error']['code'] === 'UnfulfilledCondition'
					&& $result['error']['details']['path'] === 'status'
					&& strpos( $result['error']['message'], 'refund request' ) !== false
				) {
					$result['error'] = __( 'The previous refund is not yet finished, please try again later', 'power-board' );
				} else {
					$result['error'] = implode( '; ', $result['error'] );
				}
			}
			$order->add_order_note( 'PowerBoard refund failed: ' . $result['error'] );
			throw new Exception( esc_html( $result['error'] ) );
		} else {
			$error = __( 'The refund process has failed; please try again.', 'power-board' );

			throw new Exception( esc_html( $error ) );
		}
	}
	// phpcs:enable

	/**
	 * Uses a function (remove_action) from WordPress
	 * Uses a function (wc_get_order) from WooCommerce
	 */
	public function after_refund_process( $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( ! $order || strpos( $order->get_payment_method(), POWER_BOARD_PLUGIN_PREFIX ) === false ) {
			return;
		}

		$order_status = $order->get_status();

		if ( is_object( $order ) && $order_status !== 'refunded' ) {
			remove_action( 'woocommerce_order_status_refunded', 'wc_order_fully_refunded' );
			OrderService::update_status( $order_id, 'refunded' );
			$order->save();
		}
	}
}
