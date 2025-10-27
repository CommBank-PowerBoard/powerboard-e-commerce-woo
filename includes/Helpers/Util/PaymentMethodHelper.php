<?php
declare( strict_types=1 );

namespace PowerBoard\Helpers\Util;

use PowerBoard\Services\PaymentGateway\MasterWidgetPaymentService;

class PaymentMethodHelper {
	public static function get_payment_gateway(): ?MasterWidgetPaymentService {
		$payment_gateways = WC()->payment_gateways->get_available_payment_gateways();

		if ( isset( $payment_gateways[ POWER_BOARD_PLUGIN_PREFIX ] ) ) {
			return $payment_gateways[ POWER_BOARD_PLUGIN_PREFIX ];
		} else {
			wp_send_json_error( [ 'message' => 'PowerBoard gateway not available' ] );
			return null;
		}
	}
}
