<?php
declare( strict_types=1 );

namespace PowerBoard\Services;

use PowerBoard\Helpers\Util\PaymentGatewayHelper;
use PowerBoard\Helpers\Util\PaymentNotificationHelper;
use PowerBoard\Helpers\Util\PaymentNotificationValidation;
use PowerBoard\Helpers\Util\PaymentProcessingHelper;
use PowerBoard\Model\IPN;
use WC_Order;

class IPNResponseService {
	/**
	 * IPNResponseService constructor
	 */
	public function __construct() {
		add_action( 'woocommerce_api_powerboard_ipn', [ $this, 'handle_ipn_response' ] );
	}

	public function handle_ipn_response() {
		$http_host    = !empty( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$valid_domain = PaymentGatewayHelper::is_valid_domain_name( $http_host );
		if ( ! $valid_domain ) {
			wp_send_json_error(
				[
					'message' => 'Unauthorized IPN origin: invalid domain.',
					'host'    => $http_host,
				],
				401
			);
		}

		$ipn_data = file_get_contents( 'php://input' );
		$ipn_data = json_decode( $ipn_data, true );
		$ipn      = new IPN( $ipn_data );
		$this->process_payment_resource( $ipn );

		wp_send_json_error(
			[
				'message'  => 'Invalid IPN payload: missing or invalid fields.',
				'order_id' => $ipn->get_order_id(),
				'event_id' => $ipn->get_event_id(),
				'event'    => $ipn->get_event(),
			],
			500
		);
	}

	/**
	 * Processes payment resource
	 *
	 * @param IPN $ipn
	 */
	public function process_payment_resource( $ipn ) {
		$order_id = $ipn->get_order_id();
		$event_id = $ipn->get_event_id();
		$order    = wc_get_order( $order_id );

		if ( empty( $order ) ) {
			wp_send_json_error(
				[
					'message'  => 'Order not found for IPN.',
					'order_id' => $order_id,
					'event_id' => $event_id,
				],
				400
			);
		}

		if ( $order->get_payment_method() === POWER_BOARD_PLUGIN_PREFIX ) {
			if ( $order->is_paid() ) {
				wp_send_json_success(
					[
						'message'      => 'IPN acknowledged: order already completed. No action taken.',
						'order_id'     => $order_id,
						'event_id'     => $event_id,
						'order_status' => $order->get_status(),
					],
					200
				);
			}

			if ( $ipn->get_is_paid() ) {
				$this->process_successful_payment( $ipn, $order );
			}

			$failure_message = $ipn->get_failure_message();
			if ( !empty( $failure_message ) ) {
				$this->process_failed_payment( $ipn, $order );
			}
		}
	}

	/**
	 * Processes payment successful
	 *
	 * @param IPN $ipn
	 * @param WC_Order $order
	 */
	private function process_successful_payment( IPN $ipn, WC_Order $order ) {

		PaymentProcessingHelper::process_payment_successful( $order, $ipn->get_charge()->get_charge_id(), PaymentProcessingHelper::SOURCE_IPN, $ipn->get_charge()->get_charge_label() );
		wp_send_json_success(
			[
				'message'      => 'IPN processed: payment marked as complete.',
				'order_id'     => $ipn->get_order_id(),
				'event_id'     => $ipn->get_event_id(),
				'charge_id'    => $ipn->get_charge()->get_charge_id(),
				'order_status' => $order->get_status(),
			],
			200
		);
	}

	/**
	 * Processes payment failed
	 *
	 * @param IPN $ipn
	 * @param WC_Order $order
	 */
	private function process_failed_payment( $ipn, $order ) {
		PaymentProcessingHelper::process_payment_failed( $order, $ipn->get_charge()->get_charge_id(), PaymentProcessingHelper::SOURCE_IPN, $ipn->get_failure_message() );
		wp_send_json_success(
			[
				'message'         => 'IPN processed: payment marked as failed.',
				'order_id'        => $ipn->get_order_id(),
				'event_id'        => $ipn->get_event_id(),
				'charge_id'       => $ipn->get_charge()->get_charge_id(),
				'failure_message' => $ipn->get_failure_message(),
				'order_status'    => $order->get_status(),
			],
			200
		);
	}
}
