<?php

declare( strict_types=1 );

namespace PowerBoard;

use PowerBoard\Services\ActionsService;
use PowerBoard\Services\FiltersService;
use PowerBoard\Services\ModalService;
use PowerBoard\Services\Assets\AdminAssetsService;
use PowerBoard\Services\Assets\FrontendAssetsService;

if ( ! class_exists( '\PowerBoard\PowerBoardPlugin' ) ) {

	final class PowerBoardPlugin {
		protected static ?PowerBoardPlugin $instance = null;

		/**
		 * Uses a function (add_filter) from WordPress
		 */
		protected function __construct() {
			ActionsService::get_instance();
			FiltersService::get_instance();
			ModalService::get_instance();

			// Initialize admin assets only in admin area
			if ( is_admin() ) {
				global $pagenow;

				if (
					$pagenow === 'plugins.php' ||
					(
						$pagenow === 'admin.php' &&
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just checking page parameters, not processing form data
						isset( $_GET['page'], $_GET['tab'], $_GET['section'] ) &&
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just checking page parameters, not processing form data
						$_GET['page'] === 'wc-settings' &&
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just checking page parameters, not processing form data
						$_GET['tab'] === 'checkout' &&
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just checking page parameters, not processing form data
						$_GET['section'] === 'power_board'
					)
				) {
					AdminAssetsService::get_instance();
				}
			} else {
				// Initialize frontend assets on frontend pages
				FrontendAssetsService::get_instance();
			}

			// Reset button styles inside the widget
			add_action( 'wp_footer', [ $this, 'register_style_fixes' ], 9999 );
		}

		public static function get_instance(): PowerBoardPlugin {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		public function register_style_fixes(): void {
			?>
			<style>
				#standaloneWidget #gpay-button-online-api-id {
					background-color: #000 !important;
				}
				#standaloneWidget #gpay-button-online-api-id:hover {
					background-color: #3c4043 !important;
				}
			</style>
			<?php
		}
	}
}
