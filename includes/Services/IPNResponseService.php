<?php
declare( strict_types=1 );

namespace PowerBoard\Services;

use PowerBoard\Helpers\Util\LoggerHelper;
use PowerBoard\Helpers\Util\PaymentGatewayHelper;
use PowerBoard\Helpers\Util\PaymentProcessingHelper;
use PowerBoard\Model\IPN;
use WC_Order;

class IPNResponseService {
	protected IPNValidationService $ipn_validation_service;

	/**
	 * IPNResponseService constructor with comprehensive duplicate checking
	 */
	public function __construct() {
		$this->ipn_validation_service = new IPNValidationService();
		add_action( 'woocommerce_api_powerboard_ipn', [ $this, 'handle_ipn_response' ] );
	}

	/**
	 * Handles IPN response with comprehensive duplicate checking
	 */
	public function handle_ipn_response() {

		if ( !is_ssl() ) {
			LoggerHelper::log_callback_event( 'Invalid IPN Request HTTPS', [], 'error' );
			return false;
		}

		$http_host    = !empty( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$valid_domain = PaymentGatewayHelper::is_valid_domain_name( $http_host );
		if ( !$valid_domain ) {
			wp_send_json_error(
				[
					'message' => 'Unauthorized IPN origin: invalid domain.',
					'host'    => $http_host,
				],
				401
			);
		}

		// Get raw IPN payload
		$raw_input = file_get_contents( 'php://input' );
		$ipn_data  = json_decode( $raw_input, true );
		$ipn       = new IPN( $ipn_data );

		// Process IPN with comprehensive duplicate checking
		$result = $this->ipn_validation_service->process_ipn_notification( $ipn );

		if ( $result['status'] === 'error' ) {
			wp_send_json_error(
				[
					'message' => $result['message'],
				],
				$result['http_code'] ?? 400
			);
		} elseif ( in_array( $result['status'], [ 'ignored', 'duplicate' ], true ) ) {
			wp_send_json_success(
				[
					'message' => $result['message'],
				],
				200
			);
		}

		$this->process_payment_resource( $ipn );

		wp_send_json_error(
			[
				'message' => 'Invalid IPN payload',
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
		$order    = wc_get_order( $order_id );

		$auth = $this->ipn_validation_service->authenticate_ipn( $ipn );

		if ( !$auth ) {
			LoggerHelper::log_callback_event(
				'Invalid IPN Request',
				[
					'status'    => 'error',
					'message'   => 'Cannot authenticate the IPN',
					'http_code' => 200,
				],
				'error'
			);

			wp_send_json_error(
				[
					'message' => 'Cannot authenticate the IPN',
				],
				500
			);

			return false;
		}

		if ( $order->is_paid() ) {
			wp_send_json_success(
				[
					'message' => 'IPN acknowledged',
				],
				200
			);
		}

		if ( $ipn->get_is_paid() ) {
			$this->process_successful_payment( $ipn, $order );
		}

		if ( !empty( $ipn->get_failure_message() ) ) {
			$this->process_failed_payment( $ipn, $order );
		}
	}

	/**
	 * Processes payment successful
	 *
	 * @param IPN $ipn
	 * @param WC_Order $order
	 */
	private function process_successful_payment( IPN $ipn, WC_Order $order ) {

		PaymentProcessingHelper::process_payment_successful(
			$order,
			$ipn->get_charge()->get_charge_id(),
			PaymentProcessingHelper::SOURCE_IPN,
			$ipn->get_charge()->get_charge_label()
		);
		wp_send_json_success(
			[
				'message' => 'IPN processed',
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
		PaymentProcessingHelper::process_payment_failed(
			$order,
			$ipn->get_charge()->get_charge_id(),
			PaymentProcessingHelper::SOURCE_IPN,
			$ipn->get_failure_message()
		);
		wp_send_json_success(
			[
				'message' => $ipn->get_failure_message(),
			],
			200
		);
	}
}
