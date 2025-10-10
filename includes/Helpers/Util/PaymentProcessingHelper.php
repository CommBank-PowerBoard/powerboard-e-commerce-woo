<?php
declare( strict_types=1 );

namespace PowerBoard\Helpers\Util;

use PowerBoard\Services\OrderService;

class PaymentProcessingHelper {
	public const SOURCE_IPN             = 'IPN';
	public const SOURCE_CHECKOUT_WIDGET = 'Checkout Widget';

	/**
	 * Process payment successful from source IPN or Checkout Widget
	 */
	public static function process_payment_successful( $order, $charge_id, $source, $payment_type, $payment_data = [] ): void {
		$order->set_payment_method( POWER_BOARD_PLUGIN_PREFIX );
		$order->update_meta_data( '_power_board_charge_id', $charge_id );

		if ( $payment_type ) {
			$order->set_payment_method_title( $payment_type );
			$order->update_meta_data( 'PowerBoard_payment_method', $payment_type );
		}

		$order->add_order_note( 'Payment succeeded. Charge ID: ' . $charge_id );
		$order->payment_complete( $charge_id );
		$order->save();

		// Log the successful payment notification
		LoggerHelper::log_callback_event(
			'Payment succeeded',
			[
				'notification_source' => $source,
				'order_id'            => $order->get_id(),
				'charge_id'           => $charge_id,
				'payment_data'        => $payment_data ?? [],
				'order_status'        => $order->get_status(),
			]
		);
	}

	/**
	 * Process payment failed from source IPN or Checkout Widget
	 */
	public static function process_payment_failed( $order, $charge_id, $source, $error_message ): void {
		$error_message = sanitize_text_field( $error_message );
		if ( ! empty( $error_message ) ) {
			$order->set_payment_method( POWER_BOARD_PLUGIN_PREFIX );
			$order->add_order_note( 'Payment failed: ' . $error_message . '. Charge ID: ' . $charge_id );
			$order->update_status( 'failed' );
			$order->save();

			LoggerHelper::log_callback_event(
				'Payment failed',
				[
					'notification_source' => $source,
					'order_id'            => $order->get_id(),
					'charge_id'           => $charge_id ?? null,
					'error_message'       => $error_message,
				],
				'error'
			);
		}
	}
}
