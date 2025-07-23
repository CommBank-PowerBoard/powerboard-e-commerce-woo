<?php
/**
 * This file uses classes from WooCommerce
 *
 * @noinspection PhpUndefinedClassInspection
 */

declare( strict_types=1 );

namespace PowerBoard\Services\PaymentGateway;

use PowerBoard\Enums\EnvironmentSettingsEnum;
use PowerBoard\Enums\MasterWidgetSettingsEnum;
use PowerBoard\Enums\SettingGroupsEnum;
use PowerBoard\Helpers\EnvironmentSettingsHelper;
use PowerBoard\Helpers\LoggerHelper;
use PowerBoard\Helpers\MasterWidgetSettingsHelper;
use PowerBoard\Helpers\OrderHelper;
use PowerBoard\Helpers\SettingGroupsHelper;
use PowerBoard\Helpers\SettingsHelper;
use PowerBoard\Services\Assets\AdminAssetsService;
use PowerBoard\Services\HashService;
use PowerBoard\Services\SDKAdapterService;
use PowerBoard\Services\SettingsService;
use PowerBoard\Services\TemplateService;
use PowerBoard\Services\Validation\ConnectionValidationService;
use PowerBoard\Controllers\Admin\WidgetController;
use Exception;
use WC_Admin_Settings;
use WC_Order;
use WC_Payment_Gateway;
use WC_Validation;

/**
 * Some properties used comes from the extension WC_Payment_Gateway from WooCommerce
 *
 * @property string $id
 * @property string $title
 * @property string $description
 * @property string $method_title
 * @property string $method_description
 * @property string $icon
 * @property bool $has_fields
 * @property array $supports
 * @property array $settings
 * @property array $form_fields
 * @property string $enabled
 * @property string $plugin_id
 */
class MasterWidgetPaymentService extends WC_Payment_Gateway {
	private static ?MasterWidgetPaymentService $instance = null;
	protected TemplateService $template_service;
	protected const  NOT_AVAILABLE_TEMPLATE_ERROR     = 'The selected template is no longer available.';
	protected const  INVALID_TOKEN_ERROR              = 'The previously saved access token is no longer valid.';
	private $configuration_id_options                 = [];
	protected static bool $admin_settings_load_logged = false;

	public static function get_instance(): self {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Uses functions (__, _x, add_action) from WordPress
	 * Uses a method (init_settings) from WC_Payment_Gateway
	 * Uses a property (method_description) from WC_Payment_Gateway
	 */
	public function __construct() {
		$this->id         = POWER_BOARD_PLUGIN_PREFIX;
		$this->has_fields = true;
		$this->supports   = [ 'products', 'default_credit_card_form' ];
		/* @noinspection PhpUndefinedFunctionInspection */
		$this->method_title = _x( 'PowerBoard payment', 'PowerBoard payment method', 'power-board' );
		/* @noinspection PhpUndefinedFunctionInspection */
		$this->title = __( 'PowerBoard', 'power-board' );
		/* @noinspection PhpUndefinedFunctionInspection */
		$this->method_description = __(
			'PowerBoard simplify how you manage your payments. Reduce costs, technical headaches & streamline compliance using PowerBoard\'s payment orchestration.',
			'power-board'
		);
		$this->description        = '';
		$this->icon               = POWER_BOARD_PLUGIN_URL . 'assets/images/logo.png';
		// Load the settings
		$this->init_form_fields();
		/* @noinspection PhpUndefinedMethodInspection */
		$this->init_settings();
		/* @noinspection PhpUndefinedFunctionInspection */
		if ( is_admin() ) {
			$this->title            = $this->method_title;
			$this->template_service = new TemplateService( $this );

			$key = SettingsHelper::get_option_name(
				$this->id,
				[ SettingGroupsEnum::CREDENTIALS, 'ACCESS_KEY' ]
			);

			if ( ! empty( $this->settings[ $key ] ) ) {
				try {
					$decrypted_key = HashService::decrypt( $this->settings[ $key ] );
				} catch ( Exception $error ) {
					$decrypted_key = null;
				}

				$this->settings[ $key ] = $decrypted_key;
			}
		}

		// woo hooks
		/* @noinspection PhpUndefinedFunctionInspection */
		add_action( 'wp_ajax_nopriv_power_board_create_error_notice', [ $this, 'power_board_create_error_notice' ], 20 );
		/* @noinspection PhpUndefinedFunctionInspection */
		add_action( 'wp_ajax_power_board_create_error_notice', [ $this, 'power_board_create_error_notice' ], 20 );
		/* @noinspection PhpUndefinedFunctionInspection */
		add_action( 'woocommerce_checkout_fields', [ $this, 'setup_phone_fields_settings' ] );
		/* @noinspection PhpUndefinedFunctionInspection */
		add_filter( 'woocommerce_create_order', [ $this, 'get_order_id' ] );
		/* @noinspection PhpUndefinedFunctionInspection */
		add_action( 'woocommerce_settings_page_init', [ $this, 'log_admin_settings_load' ] );
		/* @noinspection PhpUndefinedFunctionInspection */
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options_with_logging' ] );
	}

	/**
	 * This function is used on WC_Payment_Gateway
	 *
	 * @noinspection PhpUnused
	 */
	public function get_title(): string {
		return trim( $this->title ) ? $this->title : $this->method_title;
	}
	/**
	 * This function is used on WC_Payment_Gateway
	 *
	 * @noinspection PhpUnused
	 */
	public function is_available(): bool {
		if ( $this->enabled === 'yes' ) {
			// Prevent using this gateway on frontend if there are any configuration errors.
			return $this->has_valid_required_fields();
		}

		return parent::is_available();
	}

	public function init_form_fields(): void {
		foreach ( SettingGroupsEnum::cases() as $setting_group ) {
			$key = SettingsHelper::get_option_name(
					$this->id,
					[
						$setting_group,
						'label',
					]
				);

			$this->form_fields[ $key ] = [
				'type'  => 'big_label',
				'title' => SettingGroupsHelper::get_label( $setting_group ),
			];

			switch ( $setting_group ) {
				case SettingGroupsEnum::ENVIRONMENT:
					$merged_options = $this->get_environment_options();
					break;
				case SettingGroupsEnum::CREDENTIALS:
					$merged_options = $this->get_credential_options();
					break;
				case SettingGroupsEnum::CHECKOUT:
					$merged_options = $this->get_checkout_options();
					break;
				default:
					$merged_options = [];
					break;
			}

			$this->form_fields = array_merge( $this->form_fields, $merged_options );
		}
	}

	public function get_version(): ?string {
		$version_key = SettingsHelper::get_option_name(
			$this->id,
			[
				SettingGroupsEnum::CHECKOUT,
				MasterWidgetSettingsEnum::VERSION,
			]
		);
		$settings    = $this->get_db_settings();
		if ( array_key_exists( $version_key, $settings ) ) {
			return $settings[ $version_key ];
		}
		return null;
	}

	public function get_access_token(): ?string {
		$token_key = SettingsHelper::get_option_name(
			$this->id,
			[
				SettingGroupsEnum::CREDENTIALS,
				'ACCESS_KEY',
			]
		);
		$settings  = $this->get_db_settings();
		if ( array_key_exists( $token_key, $settings ) ) {
			try {
				$decrypted_key = HashService::decrypt( $settings[ $token_key ] );
			} catch ( Exception $error ) {
				$decrypted_key = null;
			}
			return $decrypted_key;
		}
		return null;
	}

	public function get_configuration_id(): ?string {
		$configuration_id = SettingsHelper::get_option_name(
			$this->id,
			[
				SettingGroupsEnum::CHECKOUT,
				MasterWidgetSettingsEnum::CONFIGURATION_ID,
			]
		);
		if ( array_key_exists( $configuration_id, $this->settings ) ) {
			return $this->settings[ $configuration_id ];
		}
		return null;
	}

	public function get_customisation_id(): ?string {
		$customisation_id = SettingsHelper::get_option_name(
			$this->id,
			[
				SettingGroupsEnum::CHECKOUT,
				MasterWidgetSettingsEnum::CUSTOMISATION_ID,
			]
		);
		if ( array_key_exists( $customisation_id, $this->settings ) ) {
			return $this->settings[ $customisation_id ];
		}
		return null;
	}

	public function get_environment(): ?string {
		$environment_key = SettingsHelper::get_option_name(
			$this->id,
			[
				SettingGroupsEnum::ENVIRONMENT,
				EnvironmentSettingsEnum::ENVIRONMENT,
			]
		);
		$settings        = $this->get_db_settings();
		if ( array_key_exists( $environment_key, $settings ) ) {
			return $settings[ $environment_key ];
		}
		return null;
	}

	/**
	 * Process the payment and return the result.
	 * This function is used on WC_Payment_Gateway
	 * Uses a function (sanitize_text_field) from WordPress
	 * Uses functions (wc_get_order, WC and get_return_url) from WooCommerce
	 * Uses method (get_return_url) from WC_Payment_Gateway
	 * phpcs:disable WordPress.Security.NonceVerification -- processed through the WooCommerce form handler
	 *
	 * @noinspection PhpUnused
	 * @throws Exception If intent status is not completed
	 * @since 1.0.0
	 */
	public function process_payment( $order_id ): array {
		/* @noinspection PhpUndefinedFunctionInspection */
		$order         = wc_get_order( $order_id );
		$initial_order = $order;

		OrderHelper::log_order_info( $initial_order, 'Order received to complete payment' );

		/* @noinspection PhpUndefinedFunctionInspection */
		$session = WC()->session;

		if ( $order->get_status() !== 'pending' && ! empty( $session->get( 'power_board_draft_order' ) ) ) {
			$order->set_status( 'pending' );
		}

		/* @noinspection PhpUndefinedFunctionInspection */
		$checkout_order_identifier = 'power_board_checkout_cart_' . wp_create_nonce( 'power-board-checkout-cart' );
		$checkout_order            = $session->get( $checkout_order_identifier );
		$order                     = $this->get_order_to_process_payment( $order, $checkout_order );

		$current_active_intent_ids = $session->get( 'power_board_active_checkout_intent_ids' ) ?? [];

		/* @noinspection PhpUndefinedFunctionInspection */
		$charge_id = isset( $_POST['chargeid'] ) ? sanitize_text_field( wp_unslash( $_POST['chargeid'] ) ) : '';

		/* @noinspection PhpUndefinedFunctionInspection */
		$intent_id = isset( $_POST['intentid'] ) ? sanitize_text_field( wp_unslash( $_POST['intentid'] ) ) : '';

		if ( ! empty( $charge_id ) && ! empty( $intent_id ) && in_array( $intent_id, $current_active_intent_ids, true ) ) {
			$valid_payment = WidgetController::check_intent_status( $intent_id, $charge_id, $order_id, $order );
		} else {
			$valid_payment = false;
		}

		if ( ! $valid_payment ) {
			$failed_message = 'Payment could not be processed due to an error.';

			$initial_order_items = [];
			foreach ( $initial_order->get_items() as $initial_item ) {
				$initial_order_items[] = $initial_item->get_data();
			}

			$initial_coupon_items = [];
			foreach ( $initial_order->get_coupons() as $initial_coupon ) {
				$initial_coupon_items[] = $initial_coupon->get_data();
			}

			$current_order_items = [];
			foreach ( $order->get_items() as $current_item ) {
				$current_order_items[] = $current_item->get_data();
			}

			$current_coupon_items = [];
			foreach ( $order->get_coupons() as $current_coupon ) {
				$current_coupon_items[] = $current_coupon->get_data();
			}

			LoggerHelper::log(
				'Payment processing failed in process_payment()',
				'error',
				[
					'intent_id'                 => $intent_id,
					'current_active_intent_ids' => $current_active_intent_ids,
					'charge_id'                 => $charge_id,
					'order_id'                  => $order_id,
					'order_total'               => $order->get_total( false ),
					'valid_payment'             => $valid_payment,
					'error_message'             => $failed_message,
					'initial_order'             => [
						'items'             => $initial_order_items,
						'total'             => $initial_order->get_total( false ),
						'discounts'         => [
							'applied_coupons' => $initial_coupon_items,
							'discounts_total' => $initial_order->get_discount_total(),
							'tax'             => $initial_order->get_discount_tax(),
						],
						'shipping_total'    => $initial_order->get_shipping_total(),
						'selected_shipping' => $initial_order->get_shipping_method(),
						'shipping_address'  => $initial_order->get_formatted_shipping_address(),
						'billing_address'   => $initial_order->get_formatted_billing_address(),
					],
					'checkout_order'            => $checkout_order,
					'current_order'             => [
						'items'             => $current_order_items,
						'total'             => $order->get_total( false ),
						'discounts'         => [
							'applied_coupons' => $current_coupon_items,
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

			if ( ! empty( $charge_id ) ) {
				if ( empty( $checkout_order ) ) {
					$this->refund_charge( $charge_id, $order->get_total() );
				} else {
					$this->refund_charge( $charge_id, $checkout_order['total'] );
				}
				$order_note_failed_message = $failed_message . ' The charge with id ' . $charge_id . ' has been refunded.';
			}

			$order->update_status( 'failed', $order_note_failed_message ?? $failed_message );
			$order->save();
			/* @noinspection PhpUndefinedFunctionInspection */
			throw new Exception( esc_html( $failed_message ) );
		}

		$order->add_order_note( 'Payment succeeded. Charge ID: ' . $charge_id );
		$order->payment_complete();

		$order->update_meta_data( '_power_board_charge_id', $charge_id );
		/* @noinspection PhpUndefinedFunctionInspection */
		WC()->cart->empty_cart();
		$order->save();

		$session->set( 'order_awaiting_payment', null );
		$session->set( 'store_api_draft_order', null );
		$session->set( 'power_board_draft_order', null );
		$session->set( 'order_comments', '' );
		$session->set( 'power_board_active_checkout_intent_ids', [] );
		$session->set( $checkout_order_identifier, null );
		$session->save_data();

		/* @noinspection PhpUndefinedMethodInspection */
		return [
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		];
	}
	// phpcs:enable

	/**
	 * Processing the payment result from the widget: error / success.
	 * Called on the "Place Order" button or via AJAX.
	 */
	public function process_payment_result() {
		/* @noinspection PhpUndefinedFunctionInspection */
		$wp_nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : null;

		/* @noinspection PhpUndefinedFunctionInspection */
		if ( ! wp_verify_nonce( $wp_nonce, 'power-board-process-payment-result' ) ) {
			/* @noinspection PhpUndefinedFunctionInspection */
			wp_send_json_error( [ 'message' => __( 'Error: Security check', 'power-board' ) ] );

			return;
		}

		if ( ! empty( $_REQUEST['order_id'] ) ) {
			/* @noinspection PhpUndefinedFunctionInspection */
			$order_id = absint( $_REQUEST['order_id'] );
		} else {
			/* @noinspection PhpUndefinedFunctionInspection */
			$order_id = (string) WC()->session->get( 'power_board_draft_order' );
		}

		/* @noinspection PhpUndefinedFunctionInspection */
		$payment_data = ! empty( $_REQUEST['payment_response'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_REQUEST['payment_response'] ) ) : [];

		LoggerHelper::log_callback_event(
			'Received callback from Checkout',
			[
				'order_id'      => $order_id ?? null,
				'charge_id'     => $payment_data['charge_id'] ?? null,
				'status'        => $payment_data['status'] ?? null,
				'error_message' => $payment_data['errorMessage'] ?? null,
				'raw_data'      => $payment_data,
			]
		);

		/* @noinspection PhpUndefinedFunctionInspection */
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			/* @noinspection PhpUndefinedFunctionInspection */
			wp_send_json_error( [ 'message' => 'Order not found' ] );
		}

		/* @noinspection PhpUndefinedFunctionInspection */
		$charge_id = ! empty( $payment_data['charge_id'] ) ? sanitize_text_field( $payment_data['charge_id'] ) : '';

		if ( ! empty( $payment_data['errorMessage'] ) ) {
			/* @noinspection PhpUndefinedFunctionInspection */
			$error_message = sanitize_text_field( $payment_data['errorMessage'] );
			$order->set_payment_method( POWER_BOARD_PLUGIN_PREFIX );
			$order->update_status( 'failed' );
			$order->add_order_note( 'Payment failed: ' . $error_message . '. Charge ID: ' . $charge_id );
			$order->save();

			LoggerHelper::log_callback_event(
				'Payment error',
				[
					'order_id'      => $order_id ?? null,
					'charge_id'     => $charge_id ?? null,
					'error_message' => $error_message ?? null,
				],
				'error'
			);

			/* @noinspection PhpUndefinedFunctionInspection */
			$session = WC()->session;
			$session->set( 'order_awaiting_payment', (string) $order_id );
			$session->set( 'store_api_draft_order', (string) $order_id );

			/* @noinspection PhpUndefinedFunctionInspection */
			wp_send_json_success(
				[
					'order_status' => 'failed',
					'message'      => $error_message,
				],
				200
			);
		}

		$order->update_meta_data( '_power_board_charge_id', $charge_id );
		$order->save();

		/* @noinspection PhpUndefinedFunctionInspection */
		$create_account = ! empty( $_REQUEST['create_account'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['create_account'] ) ) : false;
		if ( $create_account === 'true' ) {
			$email = $order->get_billing_email( false );
			if ( ! $this->check_email( $email ) ) {
				$this->refund_charge( $charge_id, $order->get_total( false ) );
				$order->add_order_note( 'Attempted account creation with ' . $email . ', but this email is already registered. The charge has been refunded.' );

				/* @noinspection PhpUndefinedFunctionInspection */
				wp_send_json_error(
					[
						'message' => sprintf(
						// Translators: %s Email address.
							esc_html__( 'An account is already registered with %s. Please log in or use a different email address. The associated charge has been refunded, and you will need to complete the payment again.', 'power-board' ),
							esc_html( $email )
						),
					]
				);
			}
		}

		/* @noinspection PhpUndefinedFunctionInspection */
		$session = WC()->session;
		$session->set( 'order_awaiting_payment', (string) $order_id );
		$session->set( 'store_api_draft_order', (string) $order_id );

		LoggerHelper::log_callback_event(
			'Payment completed',
			[
				'order_id'  => $order_id ?? null,
				'charge_id' => $charge_id ?? null,
			]
		);

		/* @noinspection PhpUndefinedFunctionInspection */
		wp_send_json_success( [], 200 );
	}

	public function refund_charge( $charge_id, $amount_to_refund ) {
		$sdk_adapter = SDKAdapterService::get_instance();
		$sdk_adapter->refunds(
			[
				'charge_id' => $charge_id,
				'amount'    => $amount_to_refund,
			]
			);
	}

	/**
	 * Returns order id if order was created previously on PowerBoard
	 * phpcs:disable WordPress.Security.NonceVerification -- processed through the WooCommerce form handler
	 *
	 * @return string
	 */
	public function get_order_id(): ?string {
		/* @noinspection PhpUndefinedFunctionInspection */
		$payment_method = isset( $_POST['payment_method'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_method'] ) ) : '';
		if ( $payment_method === POWER_BOARD_PLUGIN_PREFIX ) {
			/* @noinspection PhpUndefinedFunctionInspection */
			$custom_order_id = (string) WC()->session->get( 'power_board_draft_order' );
			/* @noinspection PhpUndefinedFunctionInspection */
			$order_awaiting_payment = (string) WC()->session->get( 'order_awaiting_payment' );
			return ! empty( $custom_order_id ) ? $custom_order_id : $order_awaiting_payment;
		}

		return null;
	}
	// phpcs:enable

	public function check_email( $email ): bool {
		/* @noinspection PhpUndefinedFunctionInspection */
		if ( ! is_user_logged_in() && email_exists( $email ) ) {
			return false;
		}

		return true;
	}

	public function check_postcode() {
		/* @noinspection PhpUndefinedFunctionInspection */
		$wp_nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : null;

		/* @noinspection PhpUndefinedFunctionInspection */
		if ( ! wp_verify_nonce( $wp_nonce, 'power-board-check-postcode' ) ) {
			/* @noinspection PhpUndefinedFunctionInspection */
			wp_send_json_error( [ 'message' => __( 'Error: Security check', 'power-board' ) ] );
		}

		/**
		 * Disable ValidatedSanitizedInput.MissingUnslash warning to be able to compare original string with unslashed one
		 * @phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		 * @phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		 *
		 * @noinspection PhpUndefinedFunctionInspection
		 */
		$original_postcode = $_POST['postcode'] ?? '';
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		/* @noinspection PhpUndefinedFunctionInspection */
		$sanitized_postcode = isset( $_POST['postcode'] ) ? sanitize_text_field( wp_unslash( $_POST['postcode'] ) ) : '';

		if ( $original_postcode !== $sanitized_postcode ) {
			/* @noinspection PhpUndefinedFunctionInspection */
			wp_send_json_error( [ 'message' => __( 'Please enter a valid postcode/ZIP.', 'power-board' ) ] );
		}

		/* @noinspection PhpUndefinedFunctionInspection */
		$country  = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '';
		$postcode = $sanitized_postcode;
		/* @noinspection PhpUndefinedFunctionInspection */
		if ( $postcode && ! WC_Validation::is_postcode( $postcode, $country ) ) {
			/* @noinspection PhpUndefinedFunctionInspection */
			wp_send_json_error( [ 'message' => __( 'Please enter a valid postcode/ZIP.', 'power-board' ) ] );
		}

		/* @noinspection PhpUndefinedFunctionInspection */
		wp_send_json_success( [], 200 );
	}

	public function check_is_valid_email() {
		/* @noinspection PhpUndefinedFunctionInspection */
		$wp_nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : null;

		/* @noinspection PhpUndefinedFunctionInspection */
		if ( ! wp_verify_nonce( $wp_nonce, 'power-board-check-email' ) ) {
			/* @noinspection PhpUndefinedFunctionInspection */
			wp_send_json_error( [ 'message' => __( 'Error: Security check', 'power-board' ) ] );
		}

		/* @noinspection PhpUndefinedFunctionInspection */
		$email = isset( $_POST['email'] ) ? sanitize_text_field( wp_unslash( $_POST['email'] ) ) : '';
		/* @noinspection PhpUndefinedFunctionInspection */
		if ( ! is_email( $email ) ) {
			/* @noinspection PhpUndefinedFunctionInspection */
			wp_send_json_error( [ 'message' => __( 'Please enter a valid email address.', 'power-board' ) ] );
		}

		/* @noinspection PhpUndefinedFunctionInspection */
		wp_send_json_success( [], 200 );
	}

	/**
	 * Uses functions (WC and get_woocommerce_currency) from WooCommerce
	 */
	public function get_settings(): array {
		$settings_service = SettingsService::get_instance();

		/* @noinspection PhpUndefinedFunctionInspection */
		return [
			// Wordpress data.
			'environment'             => $settings_service->get_environment(),
			// Woocommerce data.
			'amount'                  => WC()->cart->get_total(),
			'currency'                => strtoupper( get_woocommerce_currency() ),
			// Widget.
			'title'                   => 'PowerBoard',
			// Master Widget Checkout.
			'checkoutTemplateVersion' => $settings_service->get_checkout_template_version(),
			'checkoutCustomisationId' => $settings_service->get_checkout_customisation_id(),
			'checkoutConfigurationId' => $settings_service->get_checkout_configuration_id(),
		];
	}

	/**
	 * Ajax function
	 * Uses functions (sanitize_text_field, wp_verify_nonce, __ and wp_json_error) from WordPress
	 * Uses functions (wc_add_notice and wc_print_notices) from WooCommerce
	 */
	public function power_board_create_error_notice(): ?array {
		/* @noinspection PhpUndefinedFunctionInspection */
		$wp_nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : null;

		/* @noinspection PhpUndefinedFunctionInspection */
		if ( ! wp_verify_nonce( $wp_nonce, 'power-board-create-error-notice' ) ) {
			/* @noinspection PhpUndefinedFunctionInspection */
			wp_send_json_error( [ 'message' => __( 'Error: Security check', 'power-board' ) ] );

			return null;
		}

		/* @noinspection PhpUndefinedFunctionInspection */
		$message = isset( $_POST['message'] ) ? sanitize_text_field( wp_unslash( $_POST['message'] ) ) : '';
		/* @noinspection PhpUndefinedFunctionInspection */
		$notice_type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : 'error';
		if ( $message ) {
			/* @noinspection PhpUndefinedFunctionInspection */
			wc_clear_notices();
			/* @noinspection PhpUndefinedFunctionInspection */
			wc_add_notice( esc_html( $message ), $notice_type );
		}

		/* @noinspection PhpUndefinedFunctionInspection */
		$response['data'] = wc_print_notices();

		return $response;
	}

	public function setup_phone_fields_settings( $address_fields ): array {
		$address_fields['billing']['billing_phone']['required'] = false;
		$address_fields['shipping']['shipping_phone']           = [
			'label'        => 'Phone',
			'type'         => 'tel',
			'required'     => false,
			'class'        => [ 'form-row-wide' ],
			'validate'     => [ 'phone' ],
			'autocomplete' => 'tel',
			'priority'     => 95,
		];
		return $address_fields;
	}

	/**
	 * This function is used on WC_Payment_Gateway
	 * Uses functions (wp_create_nonce and wp_json_encode) from WordPress
	 *
	 * @noinspection PhpUnused
	 */
	public function payment_fields(): void {
		$template = new TemplateService( $this );
		SDKAdapterService::get_instance();

		$settings = $this->get_settings();
		/* @noinspection PhpUndefinedFunctionInspection */
		$nonce = wp_create_nonce( 'power-board-create-charge-intent' );

		/* @noinspection PhpUndefinedFunctionInspection */
		$data = [
			'description' => $this->description,
			'id'          => $this->id,
			'nonce'       => $nonce,
			'settings'    => wp_json_encode( $settings ),
		];
		$template->include_checkout_html(
			'method-form',
			$data
		);
	}

	public function process_admin_options_with_logging(): bool {
		$processed = $this->process_admin_options();
		$this->log_admin_settings( 'Admin settings saved' );
		return $processed;
	}

	/**
	 * Processes the admin options for the payment gateway
	 * This function is used on WC_Payment_Gateway
	 * Uses functions (wp_unslash, do_action, update_option and apply_filters) from WordPress
	 * Uses a function (wc_clean) from WooCommerce
	 * Uses methods (init_settings, get_form_fields, get_field_type, validate_text_field, get_option, add_error and get_option_key) from WC_Payment_Gateway
	 * phpcs:disable WordPress.Security.NonceVerification -- processed through the WooCommerce form handler
	 *
	 * @noinspection PhpUnused
	 */
	public function process_admin_options(): bool {
		/* @noinspection PhpUndefinedFunctionInspection */
		set_transient( 'power_board_selected_CONFIGURATION_ID_template_not_available', false );
		/* @noinspection PhpUndefinedFunctionInspection */
		set_transient( 'power_board_selected_CUSTOMISATION_ID_template_not_available', false );
		/* @noinspection PhpUndefinedMethodInspection */
		$this->init_settings();

		$validation_service = new ConnectionValidationService( $this );

		$errors = $validation_service->get_errors();
		if ( ! empty( $errors ) ) {
			foreach ( $errors as $error ) {
				if ( strpos( $error, 'Invalid credentials' ) !== false ) {
					/* @noinspection PhpUndefinedFunctionInspection */
					WC_Admin_Settings::add_error( __( 'You have entered an invalid access token. Your changes have not been saved.', 'power-board' ) );
				} else {
					WC_Admin_Settings::add_error( $error );
				}
				break;
			}
			return false;
		}

		$hashed_credential_keys = [];
		$settings_keys          = [];

		$access_key = SettingsHelper::get_option_name(
			$this->id,
			[
				SettingGroupsEnum::CREDENTIALS,
				'ACCESS_KEY',
			]
		);

		$hashed_credential_keys[ $access_key ] = 'ACCESS_KEY';
		$settings_keys[ $access_key ]          = 'ACCESS_KEY';

		foreach ( EnvironmentSettingsEnum::cases() as $environment_settings ) {
			$key                   = SettingsHelper::get_option_name(
				$this->id,
				[
					SettingGroupsEnum::ENVIRONMENT,
					$environment_settings,
				]
			);
			$settings_keys[ $key ] = $environment_settings;
		}

		foreach ( MasterWidgetSettingsEnum::cases() as $master_widget_settings ) {
			$key                   = SettingsHelper::get_option_name(
				$this->id,
				[
					SettingGroupsEnum::CHECKOUT,
					$master_widget_settings,
				]
			);
			$settings_keys[ $key ] = $master_widget_settings;
		}

		$empty_template_fields = false;
		/* @noinspection PhpUndefinedMethodInspection */
		foreach ( $this->get_form_fields() as $key => $field ) {
			/* @noinspection PhpUndefinedMethodInspection */
			$type = $this->get_field_type( $field );

			$option_key = $this->plugin_id . $this->id . '_' . $key;

			/* @noinspection PhpUndefinedFunctionInspection */
			$value = ! empty( $_POST[ $option_key ] ) ? wc_clean( wp_unslash( $_POST[ $option_key ] ) ) : null;

			if ( method_exists( $this, 'validate_' . $type . '_field' ) ) {
				$value = $this->{'validate_' . $type . '_field'}( $key, $value );
			} else {
				/* @noinspection PhpUndefinedMethodInspection */
				$value = $this->validate_text_field( $key, $value );
			}

			if ( array_key_exists( $key, $settings_keys ) ) {
				if ( $key === 'power_board_CREDENTIALS_ACCESS_KEY' && !$validation_service->has_errors() &&
					$value !== '********************' ) {
					$empty_template_fields = true;
				}

				if ( $validation_service->has_errors() || $value === '********************' ) {
					/* @noinspection PhpUndefinedMethodInspection */
					$value = $this->get_option( $key );
				}
			}

			if ( $empty_template_fields &&
				( $key === 'power_board_CHECKOUT_CONFIGURATION_ID' ||
					$key === 'power_board_CHECKOUT_CUSTOMISATION_ID' ) ) {
				$this->settings[ $key ] = '';
			} else {
				$this->settings[ $key ] = $value;
			}
		}

		foreach ( $hashed_credential_keys as $key => $credential_settings ) {
			try {
				$decrypted_key = HashService::decrypt( $this->settings[ $key ] );
			} catch ( Exception $error ) {
				$decrypted_key = null;
				/* @noinspection PhpUndefinedMethodInspection */
				$this->add_error( $error );
				WC_Admin_Settings::add_error( $error );
			}
			$is_encrypted = $decrypted_key !== $this->settings[ $key ];

			if ( ! empty( $this->settings[ $key ] ) && ! $is_encrypted ) {
				try {
					$encrypted_key = HashService::encrypt( $this->settings[ $key ] );
				} catch ( Exception $error ) {
					$encrypted_key = null;
					/* @noinspection PhpUndefinedMethodInspection */
					$this->add_error( $error );
					WC_Admin_Settings::add_error( $error );
				}
				$this->settings[ $key ] = $encrypted_key;
			}
		}

		foreach ( $validation_service->get_errors() as $error ) {
			/* @noinspection PhpUndefinedMethodInspection */
			$this->add_error( $error );
			WC_Admin_Settings::add_error( $error );
		}

		/* @noinspection PhpUndefinedMethodInspection */
		$option_key = $this->get_option_key();
		/* @noinspection PhpUndefinedFunctionInspection */
		do_action( 'woocommerce_update_option', [ 'id' => $option_key ] );

		/* @noinspection PhpUndefinedFunctionInspection */
		return update_option(
			$option_key,
			apply_filters( 'woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings ),
			'yes'
		);
	}
	// phpcs:enable

	/**
	 * This function is used on admin.php template
	 *
	 * @noinspection PhpUnused
	 */
	public function parent_generate_settings_html( $form_fields = [], $should_echo = true ): ?string {
		return parent::generate_settings_html( $form_fields, $should_echo );
	}

	/**
	 * This function is used on WC_Payment_Gateway
	 * Uses a method (get_form_fields) from WC_Payment_Gateway
	 *
	 * @noinspection PhpUnused
	 */
	public function generate_settings_html( $form_fields = [], $should_echo = true ): ?string {
		if ( empty( $form_fields ) ) {
			/* @noinspection PhpUndefinedMethodInspection */
			$form_fields = $this->get_form_fields();
		}

		$credential_key                    = SettingsHelper::get_option_name( $this->id, [ SettingGroupsEnum::CREDENTIALS, 'ACCESS_KEY' ] );
		$this->settings[ $credential_key ] = ! empty( $this->settings[ $credential_key ] ) ? '********************' : '';
		$form_fields                       = compact( 'form_fields' );

		if ( $should_echo ) {
			$this->template_service->include_admin_html( 'admin', $form_fields );
		} else {
			return $this->template_service->get_admin_html( 'admin', $form_fields );
		}

		return null;
	}

	/**
	 * This function is used on WC_Payment_Gateway
	 *
	 * @noinspection PhpUnused
	 */
	public function generate_big_label_html( $key, $value ): string {
		return $this->template_service->get_admin_html( 'big-label', compact( 'key', 'value' ) );
	}

	private function get_credential_options(): array {
		$access_token = $this->get_access_token();
		$environment  = $this->get_environment();
		$version      = $this->get_version();

		$this->configuration_id_options = MasterWidgetSettingsHelper::get_options_for_ui( MasterWidgetSettingsEnum::CONFIGURATION_ID, $environment, $access_token, $version );

		$key = SettingsHelper::get_option_name(
			$this->id,
			[
				SettingGroupsEnum::CREDENTIALS,
				'ACCESS_KEY',
			]
		);

		/* @noinspection PhpUndefinedFunctionInspection */
		$invalid_access_token = get_transient( 'invalid_access_token' );
		$add_error            = false;

		if ( isset( $invalid_access_token ) && $invalid_access_token === '1' ) {
			$add_error = true;
		}

		return [
			$key => [
				'type'        => 'password',
				'title'       => 'API Access Token',
				'description' => $add_error ? self::INVALID_TOKEN_ERROR : '',
				'desc_tip'    => 'Enter your API Access Token. This token is used to securely authenticate your payment operations. It is also used to retrieve the values for the Checkout Template ID fields shown below.',
			],
		];
	}

	private function get_checkout_options(): array {
		$fields       = [];
		$access_token = $this->get_access_token();
		$environment  = $this->get_environment();
		$version      = $this->get_version();

		foreach ( MasterWidgetSettingsEnum::cases() as $checkout_settings ) {
			$add_error = false;
			$key       = SettingsHelper::get_option_name(
				$this->id,
				[
					SettingGroupsEnum::CHECKOUT,
					$checkout_settings,
				]
			);

			/* @noinspection PhpUndefinedFunctionInspection */
			$selected_template_not_available = get_transient( 'power_board_selected_' . $checkout_settings . '_template_not_available' );

			if ( isset( $selected_template_not_available ) && $selected_template_not_available === '1' ) {
				$add_error = true;
			}

			$description = null;
			if ( $add_error ) {
				$description = self::NOT_AVAILABLE_TEMPLATE_ERROR;

				if ( MasterWidgetSettingsEnum::CONFIGURATION_ID === $checkout_settings ) {
					$description = $description . ' Please select a new template and save your configuration.';
				}
			}

			$fields[ $key ] = [
				'type'        => MasterWidgetSettingsHelper::get_input_type( $checkout_settings ),
				'title'       => preg_replace( [ '/ Id/', '/ id/' ], ' ID', MasterWidgetSettingsHelper::get_label( $checkout_settings ) ),
				'description' => $description,
			];

			if ( MasterWidgetSettingsEnum::VERSION === $checkout_settings || ! empty( $environment ) ) {
				if ( $checkout_settings === MasterWidgetSettingsEnum::CONFIGURATION_ID ) {
					$options = $this->configuration_id_options;
				} else {
					$options = MasterWidgetSettingsHelper::get_options_for_ui( $checkout_settings, $environment, $access_token, $version );
				}

				if ( ! empty( $options ) && ( MasterWidgetSettingsHelper::get_input_type( $checkout_settings ) ) === 'select' ) {
					$fields[ $key ]['options'] = $options;
					$fields[ $key ]['class']   = POWER_BOARD_PLUGIN_PREFIX . '-settings' . ( MasterWidgetSettingsEnum::CUSTOMISATION_ID === $checkout_settings ? ' grey-description' : '' );
					$fields[ $key ]['default'] = '';
				}
			}
		}

		return $fields;
	}

	private function get_environment_options(): array {
		$fields = [];
		foreach ( EnvironmentSettingsEnum::cases() as $environment_settings ) {
			$key = SettingsHelper::get_option_name(
				$this->id,
				[
					SettingGroupsEnum::ENVIRONMENT,
					$environment_settings,
				]
			);

			$fields[ $key ] = [
				'type'  => EnvironmentSettingsHelper::get_input_type( $environment_settings ),
				'title' => preg_replace( [ '/ Id/', '/ id/' ], ' ID', EnvironmentSettingsHelper::get_label( $environment_settings ) ),
			];

			$options = EnvironmentSettingsHelper::get_options_for_ui( $environment_settings );

			if ( ! empty( $options ) && ( EnvironmentSettingsHelper::get_input_type( $environment_settings ) ) === 'select' ) {
				$fields[ $key ]['options'] = $options;
				$fields[ $key ]['class']   = POWER_BOARD_PLUGIN_PREFIX . '-settings';
				$fields[ $key ]['default'] = EnvironmentSettingsHelper::get_default( $environment_settings );
			}
		}

		return $fields;
	}

	private function get_order_to_process_payment( WC_Order $current_order, ?array $checkout_order ): WC_Order {
		if ( empty( $checkout_order ) ) {
			LoggerHelper::log(
				'Checkout order is empty'
			);
			return $current_order;
		}

		$current_order_total  = $current_order->get_total( false );
		$checkout_order_total = $checkout_order['total'];

		$checkout_customer_shipping = $checkout_order['shipping_address'];
		$checkout_customer_billing  = $checkout_order['billing_address'];

		if ( ! empty( $checkout_customer_shipping ) && ! empty( $checkout_customer_billing ) ) {
			LoggerHelper::log(
				'Updating Woo order with Checkout shipping and billing address'
			);

			$current_order->set_shipping_address( $checkout_customer_shipping );
			$current_order->set_billing_address( $checkout_customer_billing );

			$current_order->calculate_totals();
		}

		if ( $current_order_total !== $checkout_order_total ) {
			LoggerHelper::log(
				'Updating Woo order with Checkout products, shipping method and coupons'
			);

			$order_items = $current_order->get_items();
			foreach ( $order_items as $item_id => $item ) {
				$current_order->remove_item( $item_id );
			}

			$checkout_order_items = $checkout_order['items'];
			foreach ( $checkout_order_items as $checkout_order_item ) {
				$product_id = $checkout_order_item['product_id'];
				$quantity   = $checkout_order_item['quantity'];
				/* @noinspection PhpUndefinedFunctionInspection */
				$product = wc_get_product( $product_id );

				if ( $product && $product->exists() && $quantity > 0 ) {
					$current_order->add_product( $product, $quantity );
				}
			}

			$checkout_shipping = $checkout_order['shipping_total'];
			$current_shipping  = $current_order->get_shipping_total( false );
			if ( $current_shipping !== $checkout_shipping ) {
				$shipping_lines       = $current_order->get_items( 'shipping' );
				$shipping_id          = explode( ':', $checkout_order['selected_shipping_id'] );
				$shipping_method_id   = $shipping_id[0];
				$shipping_instance_id = $shipping_id[1];
				$selected_shipping    = $checkout_order['selected_shipping'];
				foreach ( $shipping_lines as $shipping_line ) {
					if ( $shipping_method_id !== $shipping_line->get_method_id() && $shipping_instance_id !== $shipping_line->get_instance_id() ) {
						$shipping_line->set_meta_data( $selected_shipping );
						$shipping_line->set_method_id( $shipping_method_id );
						$shipping_line->set_instance_id( $shipping_instance_id );
						$shipping_line->set_method_title( $selected_shipping->get_label() );
						$shipping_line->set_total( $selected_shipping->get_cost() );
						$shipping_line->save();
					}
				}
			}

			$current_order->calculate_totals();

			$current_order_discount  = $current_order->get_discount_total( false );
			$checkout_order_discount = $checkout_order['discounts']['discounts_total'];

			if ( $current_order_discount !== $checkout_order_discount ) {
				$coupons = $checkout_order['discounts']['applied_coupons'];

				foreach ( $coupons as $coupon ) {
					$current_order->apply_coupon( $coupon );
				}
				$current_order->set_discount_total( $checkout_order_discount );
				$current_order->set_discount_tax( $checkout_order['discounts']['tax'] );
			}
			$current_order->set_total( $checkout_order_total );
			$current_order->save();
		}

		return $current_order;
	}

	/**
	 * Uses a method (get_option) from WordPress
	 */
	private function get_db_settings() {
		if ( empty( $this->settings ) ) {
			/* @noinspection PhpUndefinedFunctionInspection */
			$this->settings = get_option( $this->plugin_id . $this->id . '_settings', [] );
		}
		return $this->settings;
	}

	private function has_valid_required_fields(): bool {
		$version          = $this->get_version();
		$environment      = $this->get_environment();
		$access_token     = $this->get_access_token();
		$configuration_id = $this->get_configuration_id();

		return isset( $version ) && isset( $environment ) && isset( $access_token ) && isset( $configuration_id );
	}

	public function log_admin_settings_load(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just checking admin page section parameter
		if ( isset( $_GET['section'] ) && $_GET['section'] === $this->id ) {
			$this->log_powerboard_admin_load();
		}
	}

	protected function log_powerboard_admin_load(): void {
		if ( self::$admin_settings_load_logged ) {
			return;
		}
		self::$admin_settings_load_logged = true;

		$this->log_admin_settings( 'Admin settings page refreshed' );
	}

	protected function log_admin_settings( $log_title ): void {
		$environment = $this->get_environment();
		$raw_token   = $this->get_access_token();
		$token_valid = ! empty( $raw_token );

		$masked = false;

		if ( $raw_token ) {
			$masked = '...' . substr( $raw_token, -4 );
		}

		$version = $this->get_version();
		$config  = $this->get_configuration_id();
		$custom  = $this->get_customisation_id();

		LoggerHelper::log(
			$log_title,
			'info',
			[
				'environment'            => $environment,
				'access_token_valid'     => $token_valid,
				'access_token_masked'    => $masked,
				'checkout_version'       => $version,
				'configuration_template' => $config,
				'customisation_template' => $custom,
			]
		);
	}
}
