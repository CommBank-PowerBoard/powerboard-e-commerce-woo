<?php
declare( strict_types=1 );

namespace PowerBoard\Helpers;

use PowerBoard\Enums\AvailablePaymentMethods\APIAvailablePaymentMethodsEnum;
use PowerBoard\Enums\AvailablePaymentMethods\PBAvailablePaymentMethodsEnum;
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
			$payment_method_options         = !empty( $configuration_template_content['payment_method_options'] ) ?
				$configuration_template_content['payment_method_options'] : (
				!empty( $configuration_template_content['payment_methods'] ) ? $configuration_template_content['payment_methods'] :[]
			);

			if ( !empty( $payment_method_options ) ) {
				$payment_methods_keys = array_keys( $payment_method_options );

				foreach ( $payment_methods_keys as $key ) {
					if ( $configuration_template_version > 1 && $payment_method_options[ $key ]['enabled'] === false ) {
						continue;
					}

					switch ( $key ) {
						case APIAvailablePaymentMethodsEnum::CARD:
							$payment_methods = array_merge( $payment_methods, PBAvailablePaymentMethodsEnum::CARD );
							break;
						case APIAvailablePaymentMethodsEnum::AFTERPAY:
							$payment_methods = array_merge( $payment_methods, PBAvailablePaymentMethodsEnum::AFTERPAY );
							break;
						case APIAvailablePaymentMethodsEnum::APPLEPAY:
							$payment_methods = array_merge( $payment_methods, PBAvailablePaymentMethodsEnum::APPLE_PAY );
							break;
						case APIAvailablePaymentMethodsEnum::GOOGLEPAY:
							$payment_methods = array_merge( $payment_methods, PBAvailablePaymentMethodsEnum::GOOGLE_PAY );
							break;
						case APIAvailablePaymentMethodsEnum::PAYPAL:
							$payment_methods = array_merge( $payment_methods, PBAvailablePaymentMethodsEnum::PAYPAL );
							break;
						case APIAvailablePaymentMethodsEnum::ZIP:
							$payment_methods = array_merge( $payment_methods, PBAvailablePaymentMethodsEnum::ZIP );
							break;
					}
				}
			}
		}

		return $payment_methods;
	}
}
