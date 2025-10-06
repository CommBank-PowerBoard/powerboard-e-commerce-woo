<?php
declare( strict_types=1 );

namespace PowerBoard\Helpers\Util;

use PowerBoard\API\ConfigService;
use PowerBoard\Enums\AvailablePaymentMethods\PBAvailablePaymentMethodsEnum;
use PowerBoard\Enums\PaymentNotification\PBPaymentNotificationEnum;
use PowerBoard\Model\IPN;

class PaymentNotificationHelper {
	public static function is_valid_domain_name( $http_host ): bool {
		// Check if the host includes a port
		if ( strpos( $http_host, ':' ) !== false ) {
			// If it includes a port, remove it
			$domain_name = wp_parse_url( 'http://' . $http_host, PHP_URL_HOST );
		} else {
			// Otherwise, use the host as is
			$domain_name = $http_host;
		}
		// TODO:: sync with @jack about getting the IPN from the same server we post the intent
		return $domain_name === wp_parse_url( ConfigService::get_domain() )['host'];
	}

	/**
	 * Validate IPN response data
	 *
	 * @param IPN $ipn
	 */
	public static function validate_ipn_response_data( $ipn ): bool {
		return self::is_valid_string( $ipn->get_charge_id() )
			&& self::is_valid_string( $ipn->get_intent_id() )
			&& self::is_valid_int( $ipn->get_order_id() )
			&& is_string( $ipn->get_failure_message() )
			&& self::is_valid_amount( $ipn->get_amount() )
			&& !empty( $ipn->get_currency() )
			&& self::is_valid_amount( $ipn->get_amount_refunded(), true )
			&& self::is_valid_int( $ipn->get_timestamp() )
			&& self::validate_payment_method( $ipn->get_payment_method() )
			&& self::validate_ipn_object_type( $ipn->get_object_type() )
			&& self::is_valid_string( $ipn->get_event_id() )
			&& self::validate_ipn_event( $ipn->get_event() );
	}

	private static function is_valid_string( $value ): bool {
		return !empty( $value ) && is_string( $value );
	}

	private static function is_valid_int( $value ): bool {
		return !empty( $value ) && is_int( $value );
	}

	private static function is_valid_amount( $value, $allow_zero = false ): bool {
		return isset( $value ) && is_numeric( $value ) && ( $value > 0 || ( $allow_zero && (int) $value === 0 ) );
	}

	private static function validate_ipn_object_type( $obj_type ): bool {
		return in_array( $obj_type, [ 'payment', 'refund' ], true );
	}

	private static function validate_ipn_event( $event ): bool {
		return in_array( $event, PBPaymentNotificationEnum::EVENTS, true );
	}

	private static function validate_payment_method( $payment_method ): bool {
		return in_array( $payment_method, PBAvailablePaymentMethodsEnum::PAYMENT_METHODS, true );
	}
}
