<?php
declare( strict_types=1 );

namespace PowerBoard\Helpers\Util;

use PowerBoard\API\ConfigService;
use PowerBoard\Enums\ConfigAPIEnum;
use PowerBoard\Services\PaymentGateway\MasterWidgetPaymentService;

class PaymentGatewayHelper {

	public static function get_payment_gateway(): ?MasterWidgetPaymentService {
		$payment_gateways = WC()->payment_gateways->get_available_payment_gateways();

		if ( isset( $payment_gateways[ POWER_BOARD_PLUGIN_PREFIX ] ) ) {
			return $payment_gateways[ POWER_BOARD_PLUGIN_PREFIX ];
		} else {
			wp_send_json_error( [ 'message' => 'PowerBoard gateway not available' ] );
			return null;
		}
	}


    /**
     * @param $url
     * @return void
     *
     */
    private static function standardize_url($http_host) {

        if ( !is_string( $http_host ) || empty($http_host) ) {
            return null;
        }

        $http_host = str_replace( 'http://', '', $http_host );
        $http_host = str_replace( 'https://', '', $http_host );
        $http_host =  wp_parse_url( 'https://' . $http_host, PHP_URL_HOST );

        return $http_host;

    }


    /**
     * @param $http_host
     * @return bool
     */
	public static function is_valid_domain_name( $http_host ): bool {

        $http_host = self::standardize_url($http_host);

        if(empty($http_host)) {
            return false;
        }

        return str_ends_with($http_host, ConfigAPIEnum::DOMAIN_API );

	}

}
