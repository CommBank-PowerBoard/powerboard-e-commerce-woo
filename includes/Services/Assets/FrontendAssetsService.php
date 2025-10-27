<?php
declare( strict_types=1 );

namespace PowerBoard\Services\Assets;

class FrontendAssetsService {
	private static ?self $instance = null;

	public function __construct() {
		/**
		 * Use hook wp_enqueue_scripts for frontend scripts
		 */
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_scripts' ] );
	}

	public static function get_instance(): self {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function enqueue_frontend_scripts(): void {
		// Load on WooCommerce cart, checkout pages, and any page with mini cart
		if ( ! function_exists( 'is_checkout' ) && ! function_exists( 'is_cart' ) ) {
			return;
		}

		$is_checkout   = function_exists( 'is_checkout' ) && is_checkout();
		$is_cart       = function_exists( 'is_cart' ) && is_cart();
		$has_mini_cart = $this->has_mini_cart_support();

		// Load on cart, checkout pages, or any page with mini cart functionality
		if ( ! $is_checkout && ! $is_cart && ! $has_mini_cart ) {
			return;
		}

		// For checkout pages, check if PowerBoard is available
		// For cart pages and mini cart pages, always load (so they can notify checkout pages)
		if ( $is_checkout && ! $this->is_powerboard_available() ) {
			return;
		}

		// Ensure constants are defined
		if ( ! defined( 'POWER_BOARD_PLUGIN_URL' ) || ! defined( 'POWER_BOARD_PLUGIN_VERSION' ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[PowerBoard] Plugin constants not defined when trying to enqueue cart sync script' );
			}
			return;
		}

		$script_url = POWER_BOARD_PLUGIN_URL . 'assets/js/helpers/cart-changes.helper.js';

		// Verify the script file exists
		$script_path = plugin_dir_path( POWER_BOARD_PLUGIN_FILE ) . 'assets/js/helpers/cart-changes.helper.js';
		if ( ! file_exists( $script_path ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[PowerBoard] Cart sync script file not found: ' . $script_path );
			}
			return;
		}

		// Enqueue cart changes helper for cross-tab synchronization
		wp_enqueue_script(
			'power-board-cart-changes-helper',
			$script_url,
			[ 'jquery' ],
			POWER_BOARD_PLUGIN_VERSION,
			true
		);

		// Localize script with PowerBoard settings
		wp_localize_script(
			'power-board-cart-changes-helper',
			'powerBoardCartSyncSettings',
			[
				'pluginUrl'       => POWER_BOARD_PLUGIN_URL,
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'debug'           => defined( 'WP_DEBUG' ) && WP_DEBUG,
				'paymentMethodId' => POWER_BOARD_PLUGIN_PREFIX,
				'isCheckout'      => $is_checkout,
				'isCart'          => $is_cart,
				'hasMiniCart'     => $has_mini_cart,
			]
		);
	}

	/**
	 * Check if the current page/theme has mini cart support
	 */
	private function has_mini_cart_support(): bool {
		// Check if WooCommerce is active
		if ( ! function_exists( 'WC' ) ) {
			return false;
		}

		// Most themes with mini cart show it on all pages
		// Check for common mini cart indicators:

		// 2. Check for common mini cart widgets/blocks in active widgets
		$active_widgets = wp_get_sidebars_widgets();
		if ( is_array( $active_widgets ) ) {
			foreach ( $active_widgets as $sidebar => $widgets ) {
				if ( is_array( $widgets ) ) {
					foreach ( $widgets as $widget ) {
						if ( strpos( $widget, 'woocommerce_widget_cart' ) !== false ||
							strpos( $widget, 'shopping_cart' ) !== false ||
							strpos( $widget, 'mini_cart' ) !== false ) {
							return true;
						}
					}
				}
			}
		}

		// 3. Check for WooCommerce mini cart block in block themes
		if ( function_exists( 'has_block' ) ) {
			$post = get_post();
			if ( $post && ( has_block( 'woocommerce/mini-cart', $post ) ||
							has_block( 'woocommerce/cart', $post ) ) ) {
				return true;
			}
		}

		// 4. Check if we're on a WooCommerce page (shop, product, etc.) where mini cart is commonly shown
		if ( function_exists( 'is_woocommerce' ) && is_woocommerce() ) {
			return true;
		}

		// 5. Check if we're on any page that commonly has mini cart (header/footer)
		// For most themes, if WooCommerce is supported, mini cart is available site-wide
		if ( function_exists( 'is_shop' ) || function_exists( 'is_product' ) ) {
			// If WooCommerce functions exist and theme supports it, likely has mini cart
			return true;
		}

		return false;
	}

	/**
	 * Check if PowerBoard payment gateway is available
	 */
	private function is_powerboard_available(): bool {
		if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
			return false;
		}

		if ( ! defined( 'POWER_BOARD_PLUGIN_PREFIX' ) ) {
			return false;
		}

		$payment_gateways   = WC()->payment_gateways()->payment_gateways();
		$powerboard_gateway = $payment_gateways[ POWER_BOARD_PLUGIN_PREFIX ] ?? null;

		return $powerboard_gateway && $powerboard_gateway->is_available();
	}
}
