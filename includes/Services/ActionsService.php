<?php
/**
 * This file uses classes from WooCommerce
 *
 * @noinspection PhpUndefinedClassInspection
 * @noinspection PhpUndefinedNamespaceInspection
 */

declare( strict_types=1 );

namespace PowerBoard\Services;

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use Automattic\WooCommerce\Utilities\FeaturesUtil;
use PowerBoard\Controllers\Admin\WidgetController;
use PowerBoard\Controllers\Integrations\PaymentController;
use PowerBoard\Helpers\OrderHelper;
use PowerBoard\Services\PaymentGateway\MasterWidgetPaymentService;
use PowerBoard\Enums\SettingsSectionEnum;
use PowerBoard\Util\MasterWidgetBlock;
use WC_Data_Exception;
use WC_Order;

class ActionsService {
	protected static ?ActionsService $instance = null;
	protected string $last_shipping_id         = '';

	protected const SECTION_HOOK = 'woocommerce_get_sections';

	public static function get_instance(): ActionsService {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Uses a function (add_action) from WordPress
	 */
	protected function __construct() {
		/* @noinspection PhpUndefinedFunctionInspection */
		add_action( 'before_woocommerce_init', [ $this, 'init_before_woocommerce' ] );

		/* @noinspection PhpUndefinedFunctionInspection */
		add_action( 'woocommerce_blocks_loaded', [ $this, 'register_payment_method' ] );

		/* @noinspection PhpUndefinedFunctionInspection */
		add_action( 'admin_init', [ $this, 'powerboard_refund_messages' ] );
	}

	public function init_before_woocommerce() {
		$this->add_compatibility_with_woocommerce();
		$this->add_cart_hooks();
		$this->add_settings_actions();
		$this->add_order_actions();
		$this->add_edit_order_actions();
	}

	protected function add_compatibility_with_woocommerce(): void {
		if ( class_exists( FeaturesUtil::class ) ) {
			FeaturesUtil::declare_compatibility( 'custom_order_tables', POWER_BOARD_PLUGIN_FILE );
			FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', POWER_BOARD_PLUGIN_FILE );
		}
	}

	/**
	 * Uses a function (add_action) from WordPress
	 */
	public function add_cart_hooks(): void {
		/* @noinspection PhpUndefinedFunctionInspection */
		add_action( 'woocommerce_after_cart_item_quantity_update', [ $this, 'cart_item_quantity_changed' ] );
		/* @noinspection PhpUndefinedFunctionInspection */
		add_action( 'woocommerce_add_to_cart', [ $this, 'add_to_cart' ] );
		/* @noinspection PhpUndefinedFunctionInspection */
		add_action( 'woocommerce_cart_item_removed', [ $this, 'cart_item_removed' ] );
		/* @noinspection PhpUndefinedFunctionInspection */
		add_action( 'woocommerce_removed_coupon', [ $this, 'remove_coupon' ] );
		/* @noinspection PhpUndefinedFunctionInspection */
		add_action( 'woocommerce_applied_coupon', [ $this, 'add_coupon' ] );
		/* @noinspection PhpUndefinedFunctionInspection */
		add_action( 'wc_ajax_power-board-update-shipping', [ $this, 'classic_order_update_shipping' ] );
		/* @noinspection PhpUndefinedFunctionInspection */
		add_action( 'wc_ajax_nopriv_power-board-update-shipping', [ $this, 'classic_order_update_shipping' ] );
		/* @noinspection PhpUndefinedFunctionInspection */
		add_action( 'wc_ajax_power-board-update-order-notes', [ $this, 'classic_order_update_notes' ] );
		/* @noinspection PhpUndefinedFunctionInspection */
		add_action( 'wc_ajax_nopriv_power-board-update-order-notes', [ $this, 'classic_order_update_notes' ] );
		/* @noinspection PhpUndefinedFunctionInspection */
		add_action( 'woocommerce_update_order_item', [ $this, 'handle_order_update_shipping' ], 10, 3 );
	}

	public function register_master_widget_block( PaymentMethodRegistry $registry ) {
		$registry->register( new MasterWidgetBlock() );
	}

	public function cart_item_quantity_changed() {
		$this->calculate_totals_and_save_cookie();
	}

	public function add_to_cart() {
		$this->calculate_totals_and_save_cookie();
	}

	public function cart_item_removed() {
		$this->calculate_totals_and_save_cookie();
	}

	public function remove_coupon() {
		$this->calculate_totals_and_save_cookie();
	}

	public function add_coupon() {
		$this->calculate_totals_and_save_cookie();
	}

	/**
	 * Hook woocommerce_update_order_item sends these arguments, but are not needed for this use case
	 *
	 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function handle_order_update_shipping( $order_item_id, $order_item, $order_id ) {
		$this->order_update_shipping();
	}
	// phpcs:enable

	public function classic_order_update_shipping() {
		/* @noinspection PhpUndefinedFunctionInspection */
		$wp_nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : null;

		/* @noinspection PhpUndefinedFunctionInspection */
		if ( ! wp_verify_nonce( $wp_nonce, 'power-board-update-shipping' ) ) {
			/* @noinspection PhpUndefinedFunctionInspection */
			wp_send_json_error( [ 'message' => __( 'Error: Security check', 'power-board' ) ] );

			return;
		}

		$this->order_update_shipping();
	}

	/**
	 * Updates customer notes from order when order notes field is updated.
	 *
	 * @throws WC_Data_Exception If trying to save invalid data to customer notes.
	 */
	public function classic_order_update_notes() {
		/* @noinspection PhpUndefinedFunctionInspection */
		$wp_nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : null;

		/* @noinspection PhpUndefinedFunctionInspection */
		if ( ! wp_verify_nonce( $wp_nonce, 'power-board-update-order-notes' ) ) {
			/* @noinspection PhpUndefinedFunctionInspection */
			wp_send_json_error( [ 'message' => __( 'Error: Security check', 'power-board' ) ] );

			return;
		}

		/* @noinspection PhpUndefinedFunctionInspection */
		$order_notes = isset( $_REQUEST['value'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['value'] ) ) : '';

		/* @noinspection PhpUndefinedFunctionInspection */
		WC()->session->set( 'order_comments', $order_notes );
		/* @noinspection PhpUndefinedFunctionInspection */
		$custom_order_id = (string) WC()->session->get( 'power_board_draft_order' );

		if ( ! empty( $custom_order_id ) ) {
			$order_id = $custom_order_id;
			OrderHelper::update_order_customer_notes( $order_id, $order_notes );
		}
	}

	public function order_update_shipping() {
		/* @noinspection PhpUndefinedFunctionInspection */
		$session = WC()->session;

		if ( ! empty( $session ) ) {
			$chosen_methods   = $session->get( 'chosen_shipping_methods' );
			$current_shipping = is_array( $chosen_methods ) && ! empty( $chosen_methods ) ? $chosen_methods[0] : null;
			if ( $current_shipping !== null && $current_shipping !== $this->last_shipping_id ) {
				$this->last_shipping_id = $current_shipping;
				$expiry_time            = time() + 3600;
				setcookie( 'power_board_selected_shipping', $current_shipping, $expiry_time, '/' );
			}
		}
		$this->calculate_totals_and_save_cookie();
	}

	/**
	 * Uses a function (WC) from WooCommerce
	 */
	private function calculate_totals_and_save_cookie() {
		/* @noinspection PhpUndefinedFunctionInspection */
		$cart = WC()->cart;

		if ( ! empty( $cart ) ) {
			$cart->calculate_totals();
			$cart_total  = (string) $cart->get_total( false );
			$expiry_time = time() + 3600;
			/* @noinspection PhpUndefinedFunctionInspection */
			setcookie( 'power_board_cart_total', $cart_total, $expiry_time, '/' );
		}
	}

	/**
	 * Uses a function (add_action) from WordPress
	 */
	protected function add_settings_actions(): void {
		/* @noinspection PhpUndefinedFunctionInspection */
		add_action(
			self::SECTION_HOOK,
			function ( $system_tabs ) {
				return array_merge( $system_tabs, [ SettingsSectionEnum::WIDGET_CONFIGURATION => '' ] );
			}
		);
	}

	/**
	 * Uses a function (add_action) from WordPress
	 */
	protected function add_order_actions(): void {
		$order_service                 = new OrderService();
		$payment_controller            = new PaymentController();
		$widget_controller             = new WidgetController();
		$master_widget_payment_service = MasterWidgetPaymentService::get_instance();

		/* @noinspection PhpUndefinedFunctionInspection */
		add_action( 'woocommerce_order_item_add_action_buttons', [ $order_service, 'init_power_board_order_buttons' ] );
		/* @noinspection PhpUndefinedFunctionInspection */
		add_action( 'woocommerce_order_status_changed', [ $order_service, 'status_change_verification' ], 20, 4 );
		/* @noinspection PhpUndefinedFunctionInspection */
		add_action( 'woocommerce_create_refund', [ $payment_controller, 'refund_process' ], 10, 2 );
		/* @noinspection PhpUndefinedFunctionInspection */
		add_action( 'woocommerce_order_refunded', [ $payment_controller, 'after_refund_process' ] );
		/* @noinspection PhpUndefinedFunctionInspection */
		add_action( 'wc_ajax_power-board-create-charge-intent', [ $widget_controller, 'create_checkout_intent' ] );
		/* @noinspection PhpUndefinedFunctionInspection */
		add_action( 'wc_ajax_nopriv_power-board-create-charge-intent', [ $widget_controller, 'create_checkout_intent' ] );
		/* @noinspection PhpUndefinedFunctionInspection */
		add_action( 'admin_init', [ $order_service, 'remove_bulk_action_message' ] );
		/* @noinspection PhpUndefinedFunctionInspection */
		add_action( 'wc_ajax_power-board-process-payment-result', [ $master_widget_payment_service, 'process_payment_result' ] );
		/* @noinspection PhpUndefinedFunctionInspection */
		add_action( 'wc_ajax_nopriv_power-board-process-payment-result', [ $master_widget_payment_service, 'process_payment_result' ] );
		/* @noinspection PhpUndefinedFunctionInspection */
		add_action( 'wc_ajax_power-board-check-postcode', [ $master_widget_payment_service, 'check_postcode' ] );
		/* @noinspection PhpUndefinedFunctionInspection */
		add_action( 'wc_ajax_nopriv_power-board-check-postcode', [ $master_widget_payment_service, 'check_postcode' ] );
		/* @noinspection PhpUndefinedFunctionInspection */
		add_action( 'wc_ajax_power-board-check-email', [ $master_widget_payment_service, 'check_is_valid_email' ] );
		/* @noinspection PhpUndefinedFunctionInspection */
		add_action( 'wc_ajax_nopriv_power-board-check-email', [ $master_widget_payment_service, 'check_is_valid_email' ] );
	}
	public function add_edit_order_actions() {
		/* @noinspection PhpUndefinedFunctionInspection */
		add_action( 'woocommerce_admin_order_data_after_billing_address', [ $this, 'disable_payment_method_custom_field_on_order_page' ] );
	}

	public function disable_payment_method_custom_field_on_order_page( WC_Order $order ): void {
		$meta_data       = $order->get_meta_data();
		$meta_data_count = count( $meta_data );
		$id              = '';
		for ( $i = 0; $i < $meta_data_count; $i++ ) {
			if ( $meta_data[ $i ]->key === 'PowerBoard_payment_method' ) {
				$id = $meta_data[ $i ]->id;
				break;
			}
		}
		echo '<script type="text/javascript">
			jQuery(document).ready(function($) {
        		if ( ' . $id . ' !== "" ) {
					$("#meta-' . $id . '-key").prop("disabled", true);
					$("#meta-' . $id . '-value").prop("disabled", true);
        		}
			});
		</script>';
	}

	/**
	 * Add new payment method on checkout page
	 * Uses a function (add_action, plugin_dir_path) from WordPress
	 */
	public function register_payment_method(): void {
		if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			/* @noinspection PhpUndefinedFunctionInspection */
			require_once plugin_dir_path( POWER_BOARD_PLUGIN_FILE ) . 'includes/Util/MasterWidgetBlock.php';
			/* @noinspection PhpUndefinedFunctionInspection */
			add_action( 'woocommerce_blocks_payment_method_type_registration', [ $this, 'register_master_widget_block' ] );
		}
	}
	/**
	 * Handles refund messages on PowerBoard
     * phpcs:disable WordPress.Security.NonceVerification -- processed through the WooCommerce form handler
	 */
	public function powerboard_refund_messages() {
		/* @noinspection PhpUndefinedFunctionInspection */
		if ( ! wp_doing_ajax() || ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] !== 'woocommerce_refund_line_items' ) ) {
			return;
		}

		/* @noinspection PhpUndefinedFunctionInspection */
		add_filter( 'gettext_woocommerce', [ $this, 'powerboard_filter_refund_message' ], 10, 3 );
	}
	// phpcs:enable

	/**
	 * Hook gettext_woocommerce sends these arguments, but are not needed for this use case
	 *
     * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
     *  phpcs:disable WordPress.Security.NonceVerification -- processed through the WooCommerce form handler
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function powerboard_filter_refund_message( $translation, $text, $domain ) {
		if ( $text !== 'Invalid refund amount' ) {
			return $translation;
		}

		/* @noinspection PhpUndefinedFunctionInspection */
		$order_id = ! empty( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		if ( ! $order_id ) {
			return $translation;
		}

		/* @noinspection PhpUndefinedFunctionInspection */
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return $translation;
		}

		$available_to_refund = $order->get_total() - $order->get_total_refunded();

		/* @noinspection PhpUndefinedFunctionInspection */
		$formatted_with_html = wc_price(
			$available_to_refund,
			[ 'currency' => $order->get_currency() ]
		);

		/* @noinspection PhpUndefinedFunctionInspection */
		$formatted_plain_text = html_entity_decode( wp_strip_all_tags( $formatted_with_html ) );

		/* @noinspection PhpUndefinedFunctionInspection */
		return sprintf(
		/* translators: %s: Unknown. */
			__( 'Invalid refund amount. Available amount: %s', 'power-board' ),
			$formatted_plain_text
		);
	}
    // phpcs:enable
}
