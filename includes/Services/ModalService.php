<?php
declare( strict_types=1 );

namespace PowerBoard\Services;

use PowerBoard\Helpers\DBSettingsHelper;
use PowerBoard\Helpers\MasterWidgetHelper;
use PowerBoard\Helpers\Util\LoggerHelper;
use PowerBoard\Helpers\Util\NonceHelper;

class ModalService {

	private static ?ModalService $instance = null;

	public static function get_instance(): self {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		// Register AJAX hooks
		add_action( 'wc_ajax_power-board-create-charge-intent', [ $this, 'create_checkout_intent' ] );
		add_action( 'wc_ajax_nopriv_power-board-create-charge-intent', [ $this, 'create_checkout_intent' ] );
		add_action( 'wc_ajax_nopriv_power-board-payment-successful', [ $this, 'handle_payment_successful' ] );
		add_action( 'wc_ajax_power-board-payment-successful', [ $this, 'handle_payment_successful' ] );
		add_action( 'wc_ajax_nopriv_power-board-payment-failure', [ $this, 'handle_payment_failure' ] );
		add_action( 'wc_ajax_power-board-payment-failure', [ $this, 'handle_payment_failure' ] );
		add_action( 'wc_ajax_nopriv_power-board-payment-expired', [ $this, 'handle_payment_expired' ] );
		add_action( 'wc_ajax_power-board-payment-expired', [ $this, 'handle_payment_expired' ] );
		add_action( 'wc_ajax_nopriv_power-board-payment-cancelled', [ $this, 'handle_payment_cancelled' ] );
		add_action( 'wc_ajax_power-board-payment-cancelled', [ $this, 'handle_payment_cancelled' ] );
	}

	/**
	 * Uses functions (sanitize_text_field, wp_send_json_error, __ and wp_send_json_success) from WordPress
	 * Uses functions (WC, get_woocommerce_currency and wc_get_orders) from WooCommerce
	 */
	public function create_checkout_intent(): void {
		// Validate nonce for security
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Nonce verification is performed by NonceHelper which handles sanitization and validation
		$valid_nonce = NonceHelper::is_valid_nonce( $_REQUEST['_wpnonce'] ?? '', 'power-board-create-charge-intent' );
		if ( ! $valid_nonce ) {
			return;
		}

		// Check user authorization - either authenticated user or guest checkout allowed
		if ( ! is_user_logged_in() && get_option( 'woocommerce_enable_guest_checkout', 'yes' ) === 'no' ) {
			wp_send_json_error( [ 'message' => 'You must be logged in to checkout' ] );
			return;
		}

		$request = [];
		$cart    = WC()->cart;

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verification is performed above using NonceHelper
		if ( isset( $_POST['total'] ) ) {
			if ( is_array( $_POST['total'] ) ) {
				$cart_total = array_map( 'sanitize_text_field', wp_unslash( $_POST['total'] ) );
			} else {
				$cart_total = sanitize_text_field( wp_unslash( $_POST['total'] ) );
			}
			$request['total'] = $cart_total;
		} elseif ( is_object( $cart ) ) {
			$cart_total_amount                 = $cart->get_total( false );
			$request['total']['total_price']   = $cart_total_amount * 100;
			$request['total']['currency_code'] = get_woocommerce_currency();
		} else {
			return;
		}

		$billing_address = [];
		if ( ! empty( $_POST['address'] ) && is_array( $_POST['address'] ) ) {
			$billing_address = array_map( 'sanitize_text_field', wp_unslash( $_POST['address'] ) );
		}

		$billing_country = $billing_address['country'] ?? '';

		if ( empty( $billing_country ) ) {
			$countries = WC()->countries;
			if ( ! empty( $countries ) ) {
				$allowed_countries = $countries->get_allowed_countries();

				if ( count( $allowed_countries ) === 1 ) {
					$allowed_country            = key( $allowed_countries );
					$billing_address['country'] = $allowed_country;
				}
			}
		}

		if ( ! empty( $_POST['order_id'] ) ) {
			$order_id = sanitize_text_field( wp_unslash( $_POST['order_id'] ) );
		} else {
			wp_send_json_error( [ 'message' => __( 'Something went wrong on order creation. Please try again.', 'power-board' ) ] );
			return;
		}

		if ( ! $this->is_complete_address( $billing_address ) ) {
			wp_send_json_error( [ 'message' => __( 'Incomplete billing address', 'power-board' ) ] );
			return;
		}

		$intent_request_params = [
			'amount'           => round( $request['total']['total_price'] / 100, 2 ),
			'version'          => (int) DBSettingsHelper::get_version(),
			'currency'         => $request['total']['currency_code'],
			'reference'        => $order_id,
			'customer'         => [
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
			'configuration'    => [
				'template_id' => DBSettingsHelper::get_configuration_id(),
			],
			'notification_url' => esc_url_raw( WC()->api_request_url( 'powerboard_ipn' ) ),
		];

		$customisation_id = DBSettingsHelper::get_customisation_id();
		if ( ! empty( $customisation_id ) ) {
			$intent_request_params['customisation']['template_id'] = $customisation_id;
		}

		if ( ! empty( $billing_address['phone'] ) && MasterWidgetHelper::validate_phone_number( $billing_address['phone'] ) ) {
			$intent_request_params['customer']['phone'] = preg_replace( '/\s+/', '', $billing_address['phone'] );
		}

		if ( ! empty( $billing_address['address_2'] ) ) {
			$intent_request_params['customer']['billing_address']['address_line2'] = $billing_address['address_2'];
		}

		$api_adapter_service = SDKAdapterService::get_instance();
		$result              = $api_adapter_service->create_checkout_intent( $intent_request_params );

		if ( ! empty( $result['error'] ) ) {
			wp_send_json_error( [ 'message' => __( 'Something went wrong. Please try again.', 'power-board' ) ] );
			return;
		}

		$current_intent = $result['resource']['data']['_id'];

		wp_send_json_success(
			[
				'token'    => $result['resource']['data']['token'],
				'intentId' => $current_intent,
			],
			200
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	protected function is_complete_address( $address ): bool {
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

	/**
	 * Ajax function - Handle payment successful notification from widget
	 * Uses function sanitize_text_field from WordPress
	 */
	public function handle_payment_successful(): void {
		// Validate nonce for security
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Nonce verification is performed by NonceHelper which handles sanitization and validation
		$valid_nonce = NonceHelper::is_valid_nonce( $_REQUEST['_wpnonce'] ?? '', 'power-board-widget-event' );
		if ( ! $valid_nonce ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verification is performed above using NonceHelper
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

		if ( $order_id ) {
			$order = wc_get_order( $order_id );

			if ( ! $this->can_user_modify_order( $order ) ) {
				LoggerHelper::log_callback_event(
					'Error: Payment failed and user does not have permissions to modify this order',
					[
						'order_id' => $order_id ?? null,
					],
					'error'
				);
				wp_send_json_error( [ 'message' => 'Insufficient permissions to modify this order' ] );
				return;
			}
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized with wc_clean() and wp_unslash()
		$payment_data = isset( $_POST['payment_data'] ) ? wc_clean( wp_unslash( $_POST['payment_data'] ) ) : [];
		$charge_id    = isset( $payment_data['charge_id'] ) ? sanitize_text_field( wp_unslash( $payment_data['charge_id'] ) ) : '';

		if ( ! $order_id ) {
			LoggerHelper::log_callback_event(
				'Error: Payment succeeded but order ID is missing',
				[
					'order_id'     => $order_id,
					'charge_id'    => $charge_id,
					'payment_data' => $payment_data,
				],
				'error'
			);
			wp_send_json_error( [ 'message' => 'Missing order ID' ] );
			return;
		}

		if ( ! $order ) {
			LoggerHelper::log_callback_event(
				'Error: Payment succeeded but order was not found',
				[
					'order_id'     => $order_id,
					'charge_id'    => $charge_id,
					'payment_data' => $payment_data,
				],
				'error'
			);
			wp_send_json_error( [ 'message' => 'Order not found' ] );
			return;
		}

		$order->set_payment_method( POWER_BOARD_PLUGIN_PREFIX );
		$order->update_meta_data( '_power_board_charge_id', $charge_id );

		if ( ! empty( $charge_id ) ) {
			$payment_type = $this->get_payment_display_label_from_charge( $charge_id );

			if ( $payment_type ) {
				$order->set_payment_method_title( $payment_type );
				$order->update_meta_data( 'PowerBoard_payment_method', $payment_type );
			}
		}

		$order->add_order_note( 'Payment succeeded. Charge ID: ' . $charge_id );
		$order->payment_complete( $charge_id );
		$order->save();

		// Log the successful payment notification
		LoggerHelper::log_callback_event(
			'Payment succeeded',
			[
				'order_id'     => $order_id,
				'charge_id'    => $charge_id,
				'payment_data' => $payment_data,
				'order_status' => $order->get_status(),
			]
		);

		wp_send_json_success(
			[
				'message'      => 'Payment successful notification received',
				'order_id'     => $order_id,
				'order_status' => $order->get_status(),
				'success_url'  => $order->get_checkout_order_received_url(),
			],
			200
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Processing the payment error from the widget.
	 * Called via AJAX after a payment fails.
	 */
	public function handle_payment_failure(): void {
		// Validate nonce for security
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Nonce verification is performed by NonceHelper which handles sanitization and validation
		$valid_nonce = NonceHelper::is_valid_nonce( $_REQUEST['_wpnonce'] ?? '', 'power-board-widget-event' );
		if ( ! $valid_nonce ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verification is performed above using NonceHelper
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonce verification is performed above using NonceHelper

		$order_id = isset( $_REQUEST['order_id'] ) ? absint( $_REQUEST['order_id'] ) : 0;

		if ( $order_id ) {
			$order = wc_get_order( $order_id );

			if ( ! $this->can_user_modify_order( $order ) ) {
				LoggerHelper::log_callback_event(
					'Error: Payment failed and user does not have permissions to modify this order',
					[
						'order_id' => $order_id ?? null,
					],
					'error'
				);
				wp_send_json_error( [ 'message' => 'Insufficient permissions to modify this order' ] );
				return;
			}
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized with wc_clean() and wp_unslash()
		$payment_data = isset( $_REQUEST['payment_data'] ) ? wc_clean( wp_unslash( $_REQUEST['payment_data'] ) ) : [];

		$charge_id = ! empty( $payment_data['charge_id'] ) ? sanitize_text_field( $payment_data['charge_id'] ) : '';

		if ( ! $order ) {
			LoggerHelper::log_callback_event(
				'Error: Payment failed but order was not found',
				[
					'order_id'  => $order_id ?? null,
					'charge_id' => $charge_id ?? null,
				],
				'error'
			);
			wp_send_json_error( [ 'message' => 'Order not found' ] );
			return;
		}

		if ( ! empty( $payment_data['message'] ) ) {
			$error_message = sanitize_text_field( $payment_data['message'] );
			$order->set_payment_method( POWER_BOARD_PLUGIN_PREFIX );
			$order->add_order_note( 'Payment failed: ' . $error_message . '. Charge ID: ' . $charge_id );
			$order->update_status( 'failed' );
			$order->save();

			LoggerHelper::log_callback_event(
				'Payment failed',
				[
					'order_id'      => $order_id ?? null,
					'charge_id'     => $charge_id ?? null,
					'error_message' => $error_message ?? null,
				],
				'error'
			);

			wp_send_json_success(
				[
					'order_status' => 'failed',
					'message'      => $error_message,
				],
				200
			);
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Processing the payment expired from the widget.
	 * Called via AJAX after a payment expires.
	 */
	public function handle_payment_expired(): void {
		// Validate nonce for security
		// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Nonce verification is performed by NonceHelper which handles sanitization and validation
		$valid_nonce = NonceHelper::is_valid_nonce( $_REQUEST['_wpnonce'] ?? '', 'power-board-widget-event' );
		if ( ! $valid_nonce ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verification is performed above using NonceHelper
		$order_id = isset( $_REQUEST['order_id'] ) ? absint( $_REQUEST['order_id'] ) : 0;

		if ( $order_id ) {
			$order = wc_get_order( $order_id );

			if ( ! $this->can_user_modify_order( $order ) ) {
				LoggerHelper::log_callback_event(
					'Error: Payment failed and user does not have permissions to modify this order',
					[
						'order_id' => $order_id ?? null,
					],
					'error'
				);
				wp_send_json_error( [ 'message' => 'Insufficient permissions to modify this order' ] );
				return;
			}
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized with wc_clean() and wp_unslash()
		$payment_data = isset( $_REQUEST['payment_data'] ) ? wc_clean( wp_unslash( $_REQUEST['payment_data'] ) ) : [];
        // phpcs:enable

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			LoggerHelper::log_callback_event(
				'Error: Payment expired but order was not found',
				[
					'order_id' => $order_id ?? null,
				],
				'error'
			);
			wp_send_json_error( [ 'message' => 'Order not found' ] );
		}

		$order->add_order_note( 'Payment session expired.' );

		LoggerHelper::log_callback_event(
			'Payment session expired',
			[
				'order_id'     => $order_id ?? null,
				'payment_data' => $payment_data ?? null,
			],
			'error'
		);

		wp_send_json_success(
			[
				'message' => 'Payment expired notification received',
			],
			200
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Processing the payment cancelled from the widget.
	 * Called via AJAX after a payment is manually cancelled by user.
	 */
	public function handle_payment_cancelled(): void {
		// Validate nonce for security
		// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Nonce verification is performed by NonceHelper which handles sanitization and validation
		$valid_nonce = NonceHelper::is_valid_nonce( $_REQUEST['_wpnonce'] ?? '', 'power-board-widget-event' );
		if ( ! $valid_nonce ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verification is performed above using NonceHelper
		$order_id = isset( $_REQUEST['order_id'] ) ? absint( $_REQUEST['order_id'] ) : 0;

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized with wc_clean() and wp_unslash()
		$is_user_initiated = isset( $_REQUEST['user_cancelled'] ) ? wc_clean( wp_unslash( $_REQUEST['user_cancelled'] ) ) : [];
		$error_message     = isset( $_REQUEST['error_message'] ) ? wc_clean( wp_unslash( $_REQUEST['error_message'] ) ) : [];
        // phpcs:enable

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			LoggerHelper::log_callback_event(
				'Error: Payment cancelled but order was not found',
				[
					'order_id' => $order_id ?? null,
				],
				'error'
			);
			wp_send_json_error( [ 'message' => 'Order not found' ] );
		}

		// Different messages based on cancel type
		if ( $is_user_initiated === 'true' ) {
			$order->add_order_note( 'Payment window closed by user.' );
			$log_level = 'info';
		} else {
			$order->add_order_note( 'Payment cancelled due to error.' );
			$log_level = 'error';
		}

		LoggerHelper::log_callback_event(
			'Payment cancelled',
			[
				'order_id'       => $order_id ?? null,
				'user_initiated' => $is_user_initiated,
				'error_message'  => $error_message,
			],
			$log_level
		);

		wp_send_json_success(
			[
				'message' => 'Payment cancelled notification received',
			],
			200
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}


	/**
	 * Retrieves a user-friendly payment method display label from a PowerBoard charge
	 *
	 * This method fetches charge details from the PowerBoard API and extracts payment source
	 * information to determine an appropriate display label for the payment method used.
	 * It prioritizes wallet types (Google Pay, Apple Pay, PayPal), then card schemes (VISA, MC),
	 * then gateway information as fallbacks.
	 *
	 * @param string $charge_id The PowerBoard charge ID to lookup
	 * @return string User-friendly payment method label (e.g., "Google Pay", "VISA", "PayPal")
	 *                Returns empty string if unable to determine payment type or on API error
	 */
	private function get_payment_display_label_from_charge( string $charge_id ): string {
		try {
			$sdk            = SDKAdapterService::get_instance();
			$res            = $sdk->get_charge( $charge_id );
			$data           = $res['resource']['data'] ?? ( $res['data'] ?? [] );
			$payment_source = $data['customer']['payment_source'] ?? ( $data['payment_source'] ?? [] );

			if ( ! is_array( $payment_source ) ) {
				$payment_source = [];
			}

			$wallet = strtolower( (string) ( $payment_source['wallet_type'] ?? '' ) );
			if ( $wallet ) {
				$map = [
					'google' => 'Google Pay',
					'apple'  => 'Apple Pay',
					'paypal' => 'PayPal',
				];
				return $map[ $wallet ] ?? ucwords( $wallet );
			}

			$scheme = (string) ( $payment_source['card_scheme'] ?? $payment_source['scheme'] ?? '' );
			if ( $scheme ) {
				return strtoupper( $scheme );
			}

			if ( ! empty( $payment_source['gateway_type'] ) ) {
				return (string) $payment_source['gateway_type'];
			}

			if ( ! empty( $payment_source['gateway_name'] ) ) {
				return (string) $payment_source['gateway_name'];
			}

			if ( ! empty( $payment_source['type'] ) ) {
				return ucwords( (string) $payment_source['type'] );
			}
		} catch ( \Throwable $e ) {
			LoggerHelper::log_callback_event(
				'Payment type resolve failed (charge lookup)',
				[
					'charge_id' => $charge_id,
					'error'     => $e->getMessage(),
				],
				'error'
			);
		}

		return '';
	}

	/**
	 * Check if current user can modify the given order
	 * Either they are the order owner, or they have shop management capabilities
	 *
	 * @param \WC_Order|false $order The order object
	 * @return bool True if user can modify order, false otherwise
	 */
	private function can_user_modify_order( $order ): bool {
		if ( ! $order ) {
			return false;
		}

		// Admin users with shop management capabilities can modify any order
		if ( current_user_can( 'edit_shop_orders' ) ) {
			return true;
		}

		// If user is logged in, check if they own the order
		if ( is_user_logged_in() ) {
			$current_user_id = get_current_user_id();
			$order_user_id   = $order->get_user_id();

			// User owns the order
			if ( $current_user_id === $order_user_id ) {
				return true;
			}
		}

		// Is user a guest and order should then not have a user id attached
		if ( ! is_user_logged_in() && ! $order->get_user_id() ) {
			return true;
		}

		return false;
	}
}
