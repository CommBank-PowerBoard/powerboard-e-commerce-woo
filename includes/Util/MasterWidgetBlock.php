<?php
/**
 * This file uses classes from WooCommerce
 *
 * @noinspection PhpUndefinedClassInspection
 * @noinspection PhpUndefinedNamespaceInspection
 */

declare( strict_types=1 );

namespace PowerBoard\Util;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use PowerBoard\Helpers\AdminPanelHelpers\MasterWidgetSettingsHelper;
use PowerBoard\Helpers\DBSettingsHelper;
use PowerBoard\Helpers\MasterWidgetHelper;
use PowerBoard\Services\PaymentGateway\MasterWidgetPaymentService;
use PowerBoard\Services\SDKAdapterService;

/**
 * Settings property used comes from the extension AbstractPaymentMethodType from WooCommerce
 *
 * @property array $settings
 */
final class MasterWidgetBlock extends AbstractPaymentMethodType {
	private static bool $is_load = false;
	/**
	 * Variable from AbstractPaymentMethodType
	 *
	 * @var string $name
	 * @noinspection PhpMissingFieldTypeInspection
	 * @noinspection PhpUnused
	 */
	protected $name          = POWER_BOARD_PLUGIN_PREFIX;
	protected string $script = 'blocks';

	/**
	 * This function is used on AbstractPaymentMethodType
	 * Uses a function (get_option) from WordPress
	 *
	 * @noinspection PhpUnused
	 */
	public function initialize(): void {
		$this->settings = get_option( 'woocommerce_power_board_settings', [] );
	}

	/**
	 * This function is used on AbstractPaymentMethodType
	 * Uses a method (get_setting) from AbstractPaymentMethodType
	 *
	 * @noinspection PhpUnused
	 */
	public function is_active() {
		$payment_gateways_class = WC()->payment_gateways();
		$payment_gateways       = $payment_gateways_class->payment_gateways();

		return ! empty( $payment_gateways[ POWER_BOARD_PLUGIN_PREFIX ] ) ? $payment_gateways[ POWER_BOARD_PLUGIN_PREFIX ]->is_available() : false;
	}

	/**
	 * Check if the current checkout page uses WooCommerce checkout blocks
	 * instead of the classic checkout shortcode
	 *
	 * @return bool
	 */
	private function is_checkout_block_page(): bool {
		if ( ! function_exists( 'has_block' ) ) {
			return false;
		}

		// Check if the checkout page contains the checkout block
		if ( has_block( 'woocommerce/checkout' ) ) {
			return true;
		}

		// Additional check: if WooCommerce checkout block is rendered
		global $post;
		if ( $post && has_block( 'woocommerce/checkout', $post ) ) {
			return true;
		}

		return false;
	}

	/**
	 * This function is used on AbstractPaymentMethodType
	 * Uses functions (is_checkout, wp_enqueue_script, wp_localize_script, admin_url, plugin_url, wp_set_script_translations, wp_create_nonce and is_admin) from WordPress
	 * Uses functions (WC and get_woocommerce_currency) from WooCommerce
	 *
	 * @noinspection PhpUnused
	 */
	public function get_payment_method_script_handles(): array {
		if ( ! self::$is_load && is_checkout() ) {
			wp_enqueue_script(
				'power-board-form-helpers',
				POWER_BOARD_PLUGIN_URL . 'assets/js/helpers/form.helper.js',
				[ 'jquery' ],
				POWER_BOARD_PLUGIN_VERSION,
				true
			);

			wp_localize_script(
				'power-board-form-helpers',
				'PowerBoardAjaxError',
				[
					'url'           => admin_url( 'admin-ajax.php' ),
					'wpnonce_error' => wp_create_nonce( 'power-board-create-error-notice' ),
				]
			);

			wp_enqueue_script(
				'power-board-api',
				MasterWidgetHelper::get_widget_script_url(),
				[],
				POWER_BOARD_PLUGIN_VERSION,
				true
			);

			if ( $this->is_checkout_block_page() ) {
				wp_enqueue_script(
					'power-board-form',
					POWER_BOARD_PLUGIN_URL . 'assets/js/frontend/form.js',
					[ 'jquery' ],
					POWER_BOARD_PLUGIN_VERSION,
					true
				);
			} else {
				wp_enqueue_script(
					'power-board-classic-form',
					POWER_BOARD_PLUGIN_URL . '/assets/js/frontend/classic-form.js',
					[ 'jquery' ],
					POWER_BOARD_PLUGIN_VERSION,
					true
				);
			}

			wp_localize_script(
				'power-board-form',
				'PowerBoardAjaxCheckout',
				[
					'url' => admin_url( 'admin-ajax.php' ),
				]
			);

			wp_localize_script(
				'power-board-classic-form',
				'PowerBoardAjaxCheckout',
				[
					'url' => admin_url( 'admin-ajax.php' ),
				]
			);

			wp_enqueue_style(
				'power-board-widget-css',
				POWER_BOARD_PLUGIN_URL . 'assets/css/frontend/widget.css',
				[],
				POWER_BOARD_PLUGIN_VERSION
			);

			wp_enqueue_style(
				'power-board-modal-css',
				POWER_BOARD_PLUGIN_URL . 'assets/css/frontend/payment-modal.css',
				[],
				POWER_BOARD_PLUGIN_VERSION
			);

			self::$is_load = true;
		}

		$script_path       = 'assets/build/js/frontend/' . $this->script . '.js';
		$script_asset_path = 'assets/build/js/frontend/' . $this->script . '.asset.php';
		$script_url        = plugins_url( $script_path, POWER_BOARD_PLUGIN_FILE );
		$script_name       = POWER_BOARD_PLUGIN_PREFIX . '-' . $this->script;
		$script_asset      = file_exists( $script_asset_path ) ? require $script_asset_path : [
			'dependencies' => [],
			'version'      => POWER_BOARD_PLUGIN_VERSION,
		];

		$js_file = plugin_dir_path( POWER_BOARD_PLUGIN_FILE ) . $script_path;
		if ( file_exists( $js_file ) ) {
			$script_asset['version'] = filemtime( $js_file );
		}

		wp_register_script(
			$script_name,
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		wp_localize_script(
			$script_name,
			'powerBoardWidgetSettings',
			[
				'pluginUrlPrefix' => POWER_BOARD_PLUGIN_URL,
			]
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( $script_name );
		}

		return [ $script_name ];
	}

	/**
	 * This function is used on AbstractPaymentMethodType
	 * Uses a function (is_admin) from WordPress
	 * Uses functions (WC, get_woocommerce_currency) from WooCommerce
	 *
	 * @noinspection PhpUnused
	 */
	public function get_payment_method_data(): array {
		SDKAdapterService::get_instance();
		$powerboard_settings = DBSettingsHelper::get_powerboard_settings();

		$title = MasterWidgetSettingsHelper::get_gateway_title( $this->settings, MasterWidgetPaymentService::TITLE_MAX ?? null );
		$desc  = MasterWidgetSettingsHelper::get_gateway_description( $this->settings, MasterWidgetPaymentService::DESCRIPTION_MAX ?? null );

		if ( $title === '' ) {
			$title = MasterWidgetPaymentService::DEFAULT_TITLE;
		}

		if ( $desc === '' ) {
			$desc = MasterWidgetPaymentService::DEFAULT_DESCRIPTION;
		}

		/* @noinspection PhpUndefinedFunctionInspection */
		return [
			'title'                     => esc_html( $title ),
			'description'               => esc_html( $desc ),
			'environment'               => $powerboard_settings[ DBSettingsHelper::LOCAL_ENVIRONMENT_ID ],
			'checkout_template_version' => $powerboard_settings[ DBSettingsHelper::LOCAL_VERSION_ID ],
			'checkout_customisation_id' => $powerboard_settings[ DBSettingsHelper::LOCAL_CUSTOMISATION_TEMPLATE_ID ],
			'checkout_configuration_id' => $powerboard_settings[ DBSettingsHelper::LOCAL_CONFIGURATION_TEMPLATE_ID ],
			'available_payment_methods' => $powerboard_settings[ DBSettingsHelper::LOCAL_AVAILABLE_PAYMENT_METHODS_ID ],
		];
	}
}
