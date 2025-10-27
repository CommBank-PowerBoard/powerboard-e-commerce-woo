<?php
declare( strict_types=1 );

namespace PowerBoard\Services;

use PowerBoard\Services\PaymentGateway\MasterWidgetPaymentService;

class FiltersService {
	protected static ?FiltersService $instance = null;

	public static function get_instance(): FiltersService {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	protected function __construct() {
		$this->add_woocommerce_filters();
		$this->add_settings_link();
	}

	/**
	 * Uses a function (add_filter) from WordPress
	 */
	protected function add_woocommerce_filters(): void {
		add_filter( 'plugins_loaded', [ $this, 'plugins_loaded' ] );
		add_filter( 'admin_notices', [ $this, 'order_status_bulk_update' ] );
		add_filter( 'woocommerce_available_payment_gateways', [ $this, 'my_account_pay_for_order' ] );
	}

	public function plugins_loaded() {
		$this->init_payment_gateway();
	}

	public function init_payment_gateway(): void {
		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			return;
		}

		require_once plugin_dir_path( POWER_BOARD_PLUGIN_FILE ) . 'includes/Services/PaymentGateway/MasterWidgetPaymentService.php';
		add_filter( 'woocommerce_payment_gateways', [ $this, 'register_in_woocommerce_payment_class' ] );
		require_once plugin_dir_path( POWER_BOARD_PLUGIN_FILE ) . 'includes/Util/MasterWidgetBlock.php';
	}

	public function register_in_woocommerce_payment_class( array $methods ): array {
		$methods[] = MasterWidgetPaymentService::class;

		return $methods;
	}

	/**
	 * Handles orders status bulk-update (admin side, WC orders page)
	 * phpcs:disable WordPress.Security.NonceVerification -- processed through the WooCommerce form handler
	 *
	 * @return void
	 */
	public function order_status_bulk_update() {
		$is_wc_orders_page  = isset( $_GET['page'] ) && sanitize_text_field( wp_unslash( $_GET['page'] ) ) === 'wc-orders';
		$is_shop_order_page = isset( $_GET['post_type'] ) && sanitize_text_field( wp_unslash( $_GET['post_type'] ) ) === 'shop_order';

		if (
			( $is_wc_orders_page || $is_shop_order_page ) &&
			isset( $_GET['bulk_action'] ) &&
			$_GET['bulk_action'] !== ''
		) {
			echo "<script>
				jQuery(document).ready(function($) {
					$('div.updated').hide();
				});
			</script>";
		}
	}
	// phpcs:enable

	/**
	 * Uses functions (add_filter, plugin_basename) from WordPress
	 */
	protected function add_settings_link(): void {
		add_filter( 'plugin_action_links_' . plugin_basename( POWER_BOARD_PLUGIN_FILE ), [ $this, 'get_setting_link' ] );
	}

	/**
	 * Uses functions (admin_url and __) from WordPress
	 */
	public function get_setting_link( array $links ): array {
		array_unshift(
			$links,
			sprintf(
				'<a href="%1$s">%2$s</a>',
				admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . POWER_BOARD_PLUGIN_PREFIX ),
				__( 'Settings', 'power-board' )
			)
		);

		return $links;
	}

	public function my_account_pay_for_order( $gateways ) {
		if ( is_wc_endpoint_url( 'order-pay' ) ) {
			if ( ! empty( $gateways['power_board'] ) ) {
				unset( $gateways['power_board'] );
			}
		}
		return $gateways;
	}
}
