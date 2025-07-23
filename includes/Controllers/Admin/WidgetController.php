<?php
declare( strict_types=1 );

namespace PowerBoard\Controllers\Admin;

use PowerBoard\Helpers\JsonHelper;
use PowerBoard\Helpers\LoggerHelper;
use PowerBoard\Helpers\OrderHelper;
use PowerBoard\Helpers\PaymentMethodHelper;
use PowerBoard\Services\Settings\APIAdapterService;
use PowerBoard\Services\SettingsService;

class WidgetController {

	/**
	 * Uses functions (sanitize_text_field, wp_verify_nonce, wp_send_json_error, __ and wp_send_json_success) from WordPress
	 * Uses functions (WC, get_woocommerce_currency and wc_get_orders) from WooCommerce
	 */
	public function create_checkout_intent(): void {
		/* @noinspection PhpUndefinedFunctionInspection */
		$wp_nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : null;

		/* @noinspection PhpUndefinedFunctionInspection */
		if ( ! wp_verify_nonce( $wp_nonce, 'power-board-create-charge-intent' ) ) {
			/* @noinspection PhpUndefinedFunctionInspection */
			wp_send_json_error( [ 'message' => __( 'Error: Security check', 'power-board' ) ] );

			return;
		}

		$request  = [];
		$settings = SettingsService::get_instance();

		/* @noinspection PhpUndefinedFunctionInspection */
		$cart = WC()->cart;
		/* @noinspection PhpUndefinedFunctionInspection */
		$session = WC()->session;

		if ( is_object( $session ) && isset( $_POST['selected_shipping_id'] ) && isset( $_COOKIE['power_board_selected_shipping'] ) ) {
			/* @noinspection PhpUndefinedFunctionInspection */
			$selected_shipping_id = sanitize_text_field( wp_unslash( $_POST['selected_shipping_id'] ) );
			/* @noinspection PhpUndefinedFunctionInspection */
			$cookies_shipping_id = urldecode( sanitize_text_field( wp_unslash( $_COOKIE['power_board_selected_shipping'] ) ) );
			/* @noinspection PhpUndefinedFunctionInspection */
			$chosen_shipping_methods = $session->get( 'chosen_shipping_methods' );
			$current_shipping_id     = isset( $chosen_shipping_methods[0] ) ? $chosen_shipping_methods[0] : '';
			if ( $selected_shipping_id === $cookies_shipping_id && $selected_shipping_id !== $current_shipping_id ) {
				if ( isset( $_POST['total'] ) ) {
					if ( is_array( $_POST['total'] ) ) {
						/* @noinspection PhpUndefinedFunctionInspection */
						$cart_total = array_map( 'sanitize_text_field', wp_unslash( $_POST['total'] ) );
					} else {
						/* @noinspection PhpUndefinedFunctionInspection */
						$cart_total = sanitize_text_field( wp_unslash( $_POST['total'] ) );
					}
					$request['total'] = $cart_total;
				} else {
					return;
				}
			}
		}

		if ( is_object( $cart ) ) {
			if ( empty( $cart_total ) ) {
				$cart->calculate_totals();
				$cart_total = $cart->get_total( false );

				if ( ! empty( $cart_total ) ) {
					/* @noinspection PhpUndefinedFunctionInspection */
					$request['total']['total_price'] = $cart_total * 100;
					/* @noinspection PhpUndefinedFunctionInspection */
					$request['total']['currency_code'] = get_woocommerce_currency();
				}
			}
		} elseif ( isset( $_POST['total'] ) ) {
			if ( is_array( $_POST['total'] ) ) {
				/* @noinspection PhpUndefinedFunctionInspection */
				$request['total'] = array_map( 'sanitize_text_field', wp_unslash( $_POST['total'] ) );
			} else {
				/* @noinspection PhpUndefinedFunctionInspection */
				$request['total'] = sanitize_text_field( wp_unslash( $_POST['total'] ) );
			}
		} else {
			return;
		}

		/* @noinspection PhpUndefinedFunctionInspection */
		if ( isset( $_POST['shipping_address'] ) ) {
			$shipping_address = array_map( 'sanitize_text_field', wp_unslash( $_POST['shipping_address'] ) );
		} else {
			$customer_data    = $session->get( 'customer' );
			$shipping_address = isset( $customer_data['shipping'] ) ? $customer_data['shipping'] : [];
		}
		$billing_address = [];

		if ( ! empty( $_POST['address'] ) && is_array( $_POST['address'] ) ) {
			/* @noinspection PhpUndefinedFunctionInspection */
			$billing_address = array_map( 'sanitize_text_field', wp_unslash( $_POST['address'] ) );
		}

		$billing_country  = isset( $billing_address['country'] ) ? $billing_address['country'] : '';
		$shipping_country = isset( $shipping_address['country'] ) ? $shipping_address['country'] : '';

		if ( empty( $billing_country ) || empty( $shipping_country ) ) {
			/* @noinspection PhpUndefinedFunctionInspection */
			$countries = WC()->countries;
			if ( ! empty( $countries ) ) {
				$allowed_countries = $countries->get_allowed_countries();

				if ( count( $allowed_countries ) === 1 ) {
					$allowed_country = key( $allowed_countries );

					if ( empty( $billing_country ) ) {
						$billing_address['country'] = $allowed_country;
					}

					if ( empty( $shipping_country ) ) {
						$shipping_address['country'] = $allowed_country;
					}
				}
			}
		}

		/* @noinspection PhpUndefinedFunctionInspection */
		$billing_email = isset( $billing_address['email'] ) ? $billing_address['email'] : '';
		if ( ! is_email( $billing_email ) ) {
			/* @noinspection PhpUndefinedFunctionInspection */
			wp_send_json_error( [ 'message' => __( 'Please enter a valid email address', 'power-board' ) ] );
		}

		if ( ! empty( $_POST['order_id'] ) ) {
			/* @noinspection PhpUndefinedFunctionInspection */
			$reference = sanitize_text_field( wp_unslash( $_POST['order_id'] ) );
		} else {
			/* @noinspection PhpUndefinedFunctionInspection */
			$custom_order_id = (string) WC()->session->get( 'power_board_draft_order' );

			if ( ! empty( $custom_order_id ) ) {
				$order_id = $custom_order_id;
				/* @noinspection PhpUndefinedFunctionInspection */
				$order = wc_get_order( $order_id );

				if ( is_object( $order ) ) {
					OrderHelper::update_order( $order, $billing_address, $shipping_address );
					$order_status = $order->get_status();
				} else {
					$order_status = false;
				}

				if ( $order_status !== 'checkout-draft' && $order_status !== 'failed' ) {
					$order_id = $this->create_draft_order( $billing_address, $shipping_address );
				}
			} else {
				$order_id = $this->create_draft_order( $billing_address, $shipping_address );
			}
			/* @noinspection PhpUndefinedFunctionInspection */
			WC()->session->set( 'power_board_draft_order', $order_id );

			$reference = $order_id;
		}

		if ( ! $this->check_is_complete_address( $billing_address ) ) {
			/* @noinspection PhpUndefinedFunctionInspection */
			wp_send_json_error( [ 'message' => __( 'Incomplete billing address', 'power-board' ) ] );
		}

		$intent_request_params = [
			'amount'        => round( $request['total']['total_price'] / 100, 2 ),
			'version'       => (int) $settings->get_checkout_template_version(),
			'currency'      => $request['total']['currency_code'],
			'reference'     => $reference,
			'customer'      => [
				'email'           => $billing_address['email'],
				'billing_address' => [
					'first_name'       => $billing_address['first_name'],
					'last_name'        => $billing_address['last_name'],
					'address_line1'    => $billing_address['address_1'],
					'address_city'     => $billing_address['city'],
					'address_state'    => $billing_address['state'],
					'address_country'  => $billing_address['country'],
					'address_postcode' => $billing_address['postcode'],
				],
			],
			'configuration' => [
				'template_id' => $settings->get_checkout_configuration_id(),
			],
		];

		if ( empty( $reference ) ) {
			unset( $intent_request_params['reference'] );
		}

		if ( ! empty( $settings->get_checkout_customisation_id() ) ) {
			$intent_request_params['customisation']['template_id'] = $settings->get_checkout_customisation_id();
		}

		if ( ! empty( $billing_address['phone'] ) ) {
			$intent_request_params['customer']['phone'] = $billing_address['phone'];
		}

		if ( ! empty( $billing_address['address_2'] ) ) {
			$intent_request_params['customer']['billing_address']['address_line2'] = $billing_address['address_2'];
		}

		$api_adapter_service = APIAdapterService::get_instance();
		$api_adapter_service->initialise( $settings->get_environment(), $settings->get_access_token() );
		$result = $api_adapter_service->create_checkout_intent( $intent_request_params );

		if ( ! empty( $result['error'] ) ) {
			/* @noinspection PhpUndefinedFunctionInspection */
			wp_send_json_error( [ 'message' => __( 'Something went wrong, please refresh the page and try again.', 'power-board' ) ] );
		}

		$chosen_shipping_methods = $session->get( 'chosen_shipping_methods' );
		$selected_shipping_id    = isset( $chosen_shipping_methods[0] ) ? $chosen_shipping_methods[0] : '';
		/* @noinspection PhpUndefinedFunctionInspection */
		$shipping_package  = $session->get( 'shipping_for_package_0' );
		$selected_shipping = null;
		if ( isset( $shipping_package['rates'][ $selected_shipping_id ] ) ) {
			$selected_shipping = $shipping_package['rates'][ $selected_shipping_id ];
		}
		/* @noinspection PhpUndefinedFunctionInspection */
		$identifier = '_' . wp_create_nonce( 'power-board-checkout-cart' );

		$session->set(
			'power_board_checkout_cart' . $identifier,
			[
				'items'                => $cart->get_cart(),
				'total'                => $cart->get_total( false ),
				'discounts'            => [
					'applied_coupons' => $cart->get_applied_coupons(),
					'discounts_total' => $cart->get_discount_total(),
					'tax'             => $cart->get_discount_tax(),
				],
				'shipping_total'       => $cart->get_shipping_total(),
				'selected_shipping_id' => $selected_shipping_id,
				'selected_shipping'    => $selected_shipping,
				'shipping_address'     => $shipping_address,
				'billing_address'      => $billing_address,
			]
		);

		$current_active_intent_ids = $session->get( 'power_board_active_checkout_intent_ids' ) ?? [];
		$current_intent            = $result['resource']['data']['_id'];
		if ( !in_array( $current_intent, $current_active_intent_ids, true ) ) {
			$current_active_intent_ids[] = $current_intent;
			$session->set( 'power_board_active_checkout_intent_ids', $current_active_intent_ids );
		}

		$session->save_data();

		/* @noinspection PhpUndefinedFunctionInspection */
		wp_send_json_success(
			[
				'token'    => $result['resource']['data']['token'],
				'intentId' => $current_intent,
			],
			200
			);
	}

	protected function check_is_complete_address( $address ): bool {
		return ! empty( $address )
			&& ! empty( $address['email'] )
			&& ! empty( $address['first_name'] )
			&& ! empty( $address['last_name'] )
			&& ! empty( $address['address_1'] )
			&& ! empty( $address['city'] )
			&& ! empty( $address['state'] )
			&& ! empty( $address['country'] )
			&& ! empty( $address['postcode'] );
	}

	public static function check_intent_status( $intent_id, $charge_id, $order_id, $order ): bool {
		$settings = SettingsService::get_instance();

		$intent_request_params = [ 'intent_id' => $intent_id ];
		$api_adapter_service   = APIAdapterService::get_instance();
		$api_adapter_service->initialise( $settings->get_environment(), $settings->get_access_token() );
		$result = $api_adapter_service->get_checkout_intent_by_id( $intent_request_params );

		$is_intent_valid = (float) $result['resource']['data']['amount'] === (float) $order->get_total( false )
			&& (string) $result['resource']['data']['reference'] === (string) $order_id
			&& $result['resource']['data']['status'] === 'completed'
			&& $result['resource']['data']['process_reference'] === $charge_id;

		if ( ! $is_intent_valid ) {
			LoggerHelper::log_callback_event(
				'Intent validation failed',
				[
					'intent_id'         => $intent_id,
					'charge_id'         => $charge_id,
					'order_id'          => $order_id,
					'order_total'       => $order->get_total( false ),
					'api_amount'        => $result['resource']['data']['amount'] ?? null,
					'api_reference'     => $result['resource']['data']['reference'] ?? null,
					'process_reference' => $result['resource']['data']['process_reference'] ?? null,
					'api_status'        => $result['resource']['data']['status'] ?? null,
				],
				'error'
			);
		}

		if ( $is_intent_valid ) {
			$intent_journey = $result['resource']['data']['journey'];
			for ( $i = count( $intent_journey ) - 1; $i >= 0; $i-- ) {
				if ( ! empty( $intent_journey[ $i ]['context'] ) ) {
					$decoded_context = JsonHelper::decode_stringified_json( $intent_journey[ $i ]['context'] );
					if ( ! empty( $decoded_context['payment_method'] ) ) {
						$payment_method_key = $decoded_context['payment_method'];
						$payment_method     = PaymentMethodHelper::get_payment_method( $payment_method_key );
						$order->update_meta_data( 'PowerBoard_payment_method', $payment_method );
						$order->set_payment_method_title( $payment_method );
						$order->save();
						break;
					}
				}
			}
		}

		return $is_intent_valid;
	}

	private function create_draft_order( $billing_address = null, $shipping_address = null ): string {
		/* @noinspection PhpUndefinedFunctionInspection */
		$cart = WC()->cart;
		/* @noinspection PhpUndefinedFunctionInspection */
		$order = wc_create_order(
			[
				'cart_hash' => $cart->get_cart_hash(),
			]
		);
		$order->set_status( 'checkout-draft' );
		OrderHelper::update_order( $order, $billing_address, $shipping_address );

		$order_id = $order->get_id();
		return (string) $order_id;
	}
}
