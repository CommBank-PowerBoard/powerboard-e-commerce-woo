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
use PowerBoard\Controllers\Integrations\PaymentController;
use PowerBoard\Enums\AdminPanelSettings\SettingsSectionEnum;
use PowerBoard\Helpers\Util\NonceHelper;
use PowerBoard\Helpers\Util\PaymentGatewayHelper;
use PowerBoard\Util\MasterWidgetBlock;
use WC_Order;

class ActionsService {
	protected static ?ActionsService $instance = null;
	protected const SECTION_HOOK               = 'woocommerce_get_sections';

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
		add_action( 'before_woocommerce_init', [ $this, 'init_before_woocommerce' ] );
		add_action( 'woocommerce_blocks_loaded', [ $this, 'register_payment_method' ] );
		add_action( 'admin_init', [ $this, 'powerboard_messages' ] );
	}

	public function init_before_woocommerce() {
		$this->add_compatibility_with_woocommerce();
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

	public function register_master_widget_block( PaymentMethodRegistry $registry ) {
		$registry->register( new MasterWidgetBlock() );
	}

	/**
	 * Uses a function (add_action) from WordPress
	 */
	protected function add_settings_actions(): void {
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
		$order_service      = new OrderService();
		$payment_controller = new PaymentController();

		add_action( 'woocommerce_order_item_add_action_buttons', [ $order_service, 'init_power_board_order_buttons' ] );
		add_action( 'woocommerce_order_status_changed', [ $order_service, 'status_change_verification' ], 20, 4 );

		add_action( 'woocommerce_create_refund', [ $payment_controller, 'refund_process' ], 10, 2 );
		add_action( 'woocommerce_order_refunded', [ $payment_controller, 'after_refund_process' ] );

		add_action( 'admin_init', [ $order_service, 'remove_bulk_action_message' ] );

		// Add blocks checkout payment processing (after validation)
		add_action( 'woocommerce_rest_checkout_process_payment_with_context', [ $this, 'process_blocks_checkout_payment' ], 10, 2 );

		// woo hooks
		add_action( 'wc_ajax_nopriv_power-board-create-error-notice', [ $this, 'power_board_create_error_notice' ], 20 );
		add_action( 'wc_ajax_power-board-create-error-notice', [ $this, 'power_board_create_error_notice' ], 20 );

		add_action( 'wc_ajax_power-board-process-successful-order', [ $this, 'process_successful_order' ], 10, 2 );
		add_action( 'wc_ajax_nopriv_power-board-process-successful-order', [ $this, 'process_successful_order' ] );
	}

	/**
	 * Ajax function
	 * Uses function sanitize_text_field from WordPress
	 * Uses functions (wc_add_notice and wc_print_notices) from WooCommerce
	 */
	public function power_board_create_error_notice(): ?array {
		// Validate nonce for security
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Nonce verification is performed by NonceHelper which handles sanitization and validation
		$valid_nonce = NonceHelper::is_valid_nonce( $_REQUEST['_wpnonce'] ?? '', 'power-board-create-error-notice' );
		if ( ! $valid_nonce ) {
			return null;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verification is performed above using NonceHelper
		$messages    = isset( $_POST['messages'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['messages'] ) ) : '';
		$notice_type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : 'error';
		if ( ! empty( $messages ) ) {
			wc_clear_notices();

			$messages_count = count( $messages );
			for ( $i = 0; $i < $messages_count; $i++ ) {
				wc_add_notice( esc_html( $messages[ $i ] ), $notice_type );
			}
		}

		$response['data'] = wc_print_notices();

		return $response;
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}


	/**
	 * Handles blocks checkout payment processing
	 *
	 * @param object $context Payment context
	 * @param object $result Payment result
	 */
	public function process_blocks_checkout_payment( $context, &$result ): void {
		// Only process PowerBoard payments
		if ( $context->payment_method !== POWER_BOARD_PLUGIN_PREFIX ) {
			return;
		}

		// Check if we need to show modal (no charge/intent data provided)
		$payment_data = $context->payment_data ?? [];
		$charge_id    = $payment_data['chargeid'] ?? '';
		$intent_id    = $payment_data['intentid'] ?? '';

		if ( empty( $charge_id ) || empty( $intent_id ) ) {
			// Set success status and payment details for modal flow
			$result->set_status( 'success' );
			$result->set_payment_details(
				[
					'powerboard_redirect'    => 'modal',
					'order_id'               => $context->order->get_id(),
					'_wpnonce_intent'        => wp_create_nonce( 'power-board-create-charge-intent' ),
					'_wpnonce_widget_event'  => wp_create_nonce( 'power-board-widget-event' ),
					'_wpnonce_success_order' => wp_create_nonce( 'power-board-successful-order' ),
					'message'                => 'Please complete your payment in the PowerBoard modal.',
				]
				);
		}
	}

	public function process_successful_order() {
		PaymentGatewayHelper::get_payment_gateway()->process_successful_order();
	}

	public function add_edit_order_actions() {
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
		/* @noinspection PhpUndefinedFunctionInspection */
		echo '<script type="text/javascript">
			jQuery(document).ready(function($) {
				if ( ' . esc_js( $id ) . ' !== "" ) {
					$("#meta-' . esc_attr( $id ) . '-key").prop("disabled", true);
					$("#meta-' . esc_attr( $id ) . '-value").prop("disabled", true);
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
			require_once plugin_dir_path( POWER_BOARD_PLUGIN_FILE ) . 'includes/Util/MasterWidgetBlock.php';
			add_action( 'woocommerce_blocks_payment_method_type_registration', [ $this, 'register_master_widget_block' ] );
		}
	}
	/**
	 * Handles refund messages on PowerBoard
	 * phpcs:disable WordPress.Security.NonceVerification -- processed through the WooCommerce form handler
	 */
	public function powerboard_messages() {
		register_setting( 'powerboard_settings', 'powerboard_checkout_version' );
		if ( ! wp_doing_ajax() || ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] !== 'woocommerce_refund_line_items' ) ) {
			return;
		}

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

		$order_id = ! empty( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		if ( ! $order_id ) {
			return $translation;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return $translation;
		}

		$available_to_refund = $order->get_total() - $order->get_total_refunded();

		$formatted_with_html = wc_price(
			$available_to_refund,
			[ 'currency' => $order->get_currency() ]
		);

		$formatted_plain_text = html_entity_decode( wp_strip_all_tags( $formatted_with_html ) );

		return sprintf(
		/* translators: %s: Unknown. */
			__( 'Invalid refund amount. Available amount: %s', 'power-board' ),
			$formatted_plain_text
		);
	}
	// phpcs:enable
}
