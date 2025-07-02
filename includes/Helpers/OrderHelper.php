<?php
/**
 * This file uses classes from WooCommerce
 *
 * @noinspection PhpUndefinedClassInspection
 */

declare( strict_types=1 );

namespace PowerBoard\Helpers;

use WC_Checkout;
use WC_Geolocation;
use WC_Data_Exception;
use WC_Order;

class OrderHelper {
	public static function update_order( &$order, $billing_address = null, $shipping_address = null ) {
		if ( ! ( $order instanceof WC_Order ) ) {
			return;
		}

		self::LogOrderInfo( $order, 'Updating order - initial state' );

		$order->remove_order_items();

		/* @noinspection PhpUndefinedFunctionInspection */
		$cart = WC()->cart;
		/* @noinspection PhpUndefinedFunctionInspection */
		$session     = WC()->session;
		$order_notes = $session->get( 'order_comments' );

		/* @noinspection PhpUndefinedFunctionInspection */
		$billing_email = WC()->customer->get_billing_email( false );
		if ( isset( $billing_email ) ) {
			$order->hold_applied_coupons( $billing_email );
		}

		$order->set_created_via( 'checkout' );
		$order->set_cart_hash( $cart->get_cart_hash() );

		/* @noinspection PhpUndefinedFunctionInspection */
		$order->set_customer_id( apply_filters( 'woocommerce_checkout_customer_id', get_current_user_id() ) );
		/* @noinspection PhpUndefinedFunctionInspection */
		$order->set_currency( get_woocommerce_currency() );
		/* @noinspection PhpUndefinedFunctionInspection */
		$order->set_prices_include_tax( get_option( 'woocommerce_prices_include_tax' ) === 'yes' );
		$order->set_customer_ip_address( WC_Geolocation::get_ip_address() );
		/* @noinspection PhpUndefinedFunctionInspection */
		$order->set_customer_user_agent( wc_get_user_agent() );
		$order->set_customer_note( $order_notes ?? '' );
		$order->set_payment_method( POWER_BOARD_PLUGIN_PREFIX );

		/* @noinspection PhpUndefinedFunctionInspection */
		$order->set_address( ! empty( $billing_address ) ? $billing_address : WC()->customer->get_billing(), 'billing' );
		/* @noinspection PhpUndefinedFunctionInspection */
		$order->set_address( ! empty( $shipping_address ) ? $shipping_address : WC()->customer->get_shipping(), 'shipping' );
		$order_vat_exempt = $cart->get_customer()->get_is_vat_exempt() ? 'yes' : 'no';
		$order->add_meta_data( 'is_vat_exempt', $order_vat_exempt, true );
		$order->set_shipping_total( $cart->get_shipping_total() );
		$order->set_discount_total( $cart->get_discount_total() );
		$order->set_discount_tax( $cart->get_discount_tax() );
		$order->set_cart_tax( $cart->get_cart_contents_tax() + $cart->get_fee_tax() );
		$order->set_shipping_tax( $cart->get_shipping_tax() );
		$order->set_total( $cart->get_total( 'edit' ) );

		$checkout = new WC_Checkout();
		$checkout->create_order_line_items( $order, $cart );
		$checkout->create_order_fee_lines( $order, $cart );
		/* @noinspection PhpUndefinedFunctionInspection */
		$checkout->create_order_shipping_lines( $order, WC()->session->get( 'chosen_shipping_methods' ), WC()->shipping()->get_packages() );
		$checkout->create_order_tax_lines( $order, $cart );
		$checkout->create_order_coupon_lines( $order, $cart );

		$order->calculate_totals();
		$order->save();

		self::LogOrderInfo( $order, 'Updating order - updated state' );
	}

	/**
	 * Updates customer notes from an order.
	 *
	 * @throws WC_Data_Exception If trying to save invalid data to customer notes.
	 */
	public static function update_order_customer_notes( $order_id, $order_notes ) {
		/* @noinspection PhpUndefinedFunctionInspection */
		$order = wc_get_order( $order_id );
		$order->set_customer_note( $order_notes ?? '' );
		$order->save();
	}

	public static function LogOrderInfo( &$order, $message ) {
		$order_items = [];
		foreach ( $order->get_items() as $initial_item ) {
			$order_items[] = $initial_item->get_data();
		}

		$coupon_items = [];
		foreach ( $order->get_coupons() as $initial_coupon ) {
			$coupon_items[] = $initial_coupon->get_data();
		}

		LoggerHelper::log(
			$message,
			'info',
			[
				'order' => [
					'items'             => $order_items,
					'total'             => $order->get_total( false ),
					'discounts'         => [
						'applied_coupons' => $coupon_items,
						'discounts_total' => $order->get_discount_total(),
						'tax'             => $order->get_discount_tax(),
					],
					'shipping_total'    => $order->get_shipping_total(),
					'selected_shipping' => $order->get_shipping_method(),
					'shipping_address'  => $order->get_formatted_shipping_address(),
					'billing_address'   => $order->get_formatted_billing_address(),
				],
			]
		);
	}
}
