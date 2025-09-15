<?php
declare( strict_types=1 );

namespace PowerBoard\Services\Assets;

class AdminAssetsService {
	private static ?self $instance = null;

	private const SCRIPTS = [
		'handle-settings-select-option',
		'handle-environment-changes',
		'handle-config-template-error-styles',
		'deactivation-confirmation',
	];

	private const PREFIX = 'admin';

	private const SCRIPT_PREFIX = 'script';

	private const URL_SCRIPT_PREFIX  = 'assets/js/admin/';
	private const URL_SCRIPT_POSTFIX = '.js';

	public function __construct() {
		/**
		 * Use hook admin_enqueue_scripts
		 */
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	public static function get_instance(): self {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function enqueue_scripts( string $hook ): void {
		$allowed_pages = [ 'woocommerce_page_wc-settings', 'plugins.php' ];

		if ( ! in_array( $hook, $allowed_pages, true ) ) {
			return;
		}

		foreach ( self::SCRIPTS as $script ) {
			$script_name = $this->get_script_name( $script );

			wp_register_script(
				$script_name,
				plugins_url( $this->get_script_path( $script ), POWER_BOARD_PLUGIN_FILE ),
				[ 'jquery' ],
				POWER_BOARD_PLUGIN_VERSION,
				true
			);

			/**
			 * WordPress.Security.NonceVerification.Recommended
			 *
             * @phpcs:disable WordPress.Security.NonceVerification.Recommended
			 */
			if ( $script === 'deactivation-confirmation' ||
				( isset( $_GET['tab'], $_GET['section'] ) && $_GET['tab'] === 'checkout' && $_GET['section'] === 'power_board' ) ) {
				/**
				 * Use hook wp_enqueue_script
				 */
				wp_enqueue_script( $script_name );

				/**
				 * Use function wp_localize_script
				 */
				wp_localize_script(
					$script_name,
					'powerBoardWidgetSettings',
					[
						'pluginUrlPrefix' => POWER_BOARD_PLUGIN_URL,
						'pluginName'      => POWER_BOARD_PLUGIN_NAME,
					]
				);
			}
		}
	}

	private function get_script_name( string $script ): string {
		return implode( '_', [ POWER_BOARD_PLUGIN_PREFIX, self::PREFIX, self::SCRIPT_PREFIX, $script ] );
	}

	private function get_script_path( string $script ): string {
		return self::URL_SCRIPT_PREFIX . $script . self::URL_SCRIPT_POSTFIX;
	}
}
