<?php
declare( strict_types=1 );

namespace PowerBoard\Services;

use PowerBoard\Helpers\Util\LoggerHelper;
use PowerBoard\Helpers\Util\PaymentNotificationHelper;
use PowerBoard\Model\IPN;

class IPNResponseService {
	/**
	 * IPNResponseService constructor
	 */
	public function __construct() {
		add_action( 'woocommerce_api_powerboard_ipn', [ $this, 'handle_ipn_response' ] );
	}

	public function handle_ipn_response() {
		$http_host    = !empty( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$valid_domain = PaymentNotificationHelper::is_valid_domain_name( $http_host );

		if ( ! $valid_domain ) {
			LoggerHelper::log( 'ipn response - invalid domain' );
			wp_send_json_error( [ 'message' => 'Error - invalid domain' ], 401 );
		}

		$ipn      = new IPN();
		$ipn_data = file_get_contents( 'php://input' );
		$ipn->map_powerboard_ipn_response( $ipn_data );

		$valid_ipn_response = PaymentNotificationHelper::validate_ipn_response_data( $ipn );

		if ( $valid_ipn_response ) {
			LoggerHelper::log( 'ipn response success' );
			wp_send_json_success(
				[
					'message' => 'Success test',
					'input'   => $ipn_data,
				],
				200
			);
		} else {
			LoggerHelper::log( 'ipn response error - invalid data' );
			wp_send_json_error(
				[
					'message' => 'Invalid data',
					'input'   => $ipn_data,
				],
				400
			);
		}
	}
}
