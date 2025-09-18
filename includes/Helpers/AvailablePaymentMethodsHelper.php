<?php
declare( strict_types=1 );

namespace PowerBoard\Helpers;

use PowerBoard\Enums\AvailablePaymentMethodsEnum;
use PowerBoard\Helpers\Util\JsonHelper;
use PowerBoard\Services\Settings\APIAdapterService;

class AvailablePaymentMethodsHelper {
	public static function fetch_available_payment_methods( $id ): array {
		$payment_methods = [];

		$api_adapter_service = APIAdapterService::get_instance();
		$result              = $api_adapter_service->get_configuration_template_by_id( $id );

		if ( empty( $result['error'] ) ) {
			$configuration_template_content = JsonHelper::decode_stringified_json( $result['resource']['data']['content'] );
			$configuration_template_version = $result['resource']['data']['version'];
			$payment_method_options         = $configuration_template_content['payment_method_options'];

			if ( !empty( $payment_method_options ) ) {
				$payment_methods_keys = array_keys( $payment_method_options );

				foreach ( $payment_methods_keys as $key ) {
					if ( $configuration_template_version > 1 && $payment_method_options[ $key ]['enabled'] === false ) {
						continue;
					}

					switch ( $key ) {
						case 'card':
							$payment_methods = array_merge( $payment_methods, AvailablePaymentMethodsEnum::CARD );
							break;
						case 'afterpay_checkout':
							$payment_methods = array_merge( $payment_methods, AvailablePaymentMethodsEnum::AFTERPAY );
							break;
						case 'applepay_wallet':
							$payment_methods = array_merge( $payment_methods, AvailablePaymentMethodsEnum::APPLE_PAY );
							break;
						case 'googlepay_wallet':
							$payment_methods = array_merge( $payment_methods, AvailablePaymentMethodsEnum::GOOGLE_PAY );
							break;
						case 'paypal_wallet':
							$payment_methods = array_merge( $payment_methods, AvailablePaymentMethodsEnum::PAYPAL );
							break;
						case 'zip_checkout':
							$payment_methods = array_merge( $payment_methods, AvailablePaymentMethodsEnum::ZIP );
							break;
					}
				}
			}
		}

		return $payment_methods;
	}
}
