<?php
/**
 * Copyright (c) PowerBoard, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * Plugin Name: PowerBoard for WooCommerce
 * Plugin URI: https://github.com/CommBank-PowerBoard/powerboard-e-commerce-woo
 * Description: PowerBoard is a payment orchestration solution that delivers payment choice, operational efficiency and security for your business.
 * Author: PowerBoard
 * Author URI: https://www.commbank.com.au/business/payments/take-online-payments/powerboard.html#getting-started
 * Version: 2.1.0
 * Requires at least: 6.6
 * Text Domain: power-board
 * Tested up to: 6.8.3
 * Stable tag: 2.1.0
 * License: GPL-3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * WC requires at least: 6.6
 * WC tested up to: 10.2.2
 * Requires Plugins: woocommerce
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! defined( 'POWER_BOARD_PLUGIN_FILE' ) ) {
	define( 'POWER_BOARD_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'POWER_BOARD_PLUGIN_URL' ) ) {
	define( 'POWER_BOARD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'POWER_BOARD_PLUGIN_VERSION' ) ) {
	define( 'POWER_BOARD_PLUGIN_VERSION', '2.1.0' );
}

if ( ! defined( 'POWER_BOARD_PLUGIN_PREFIX' ) ) {
	define( 'POWER_BOARD_PLUGIN_PREFIX', 'power_board' );
}

if ( ! defined( 'POWER_BOARD_PLUGIN_NAME' ) ) {
	define( 'POWER_BOARD_PLUGIN_NAME', 'PowerBoard for WooCommerce' );
}

if ( ! defined( 'PLUGIN_VERSIONS_JSON_URL' ) ) {
	define( 'PLUGIN_VERSIONS_JSON_URL', 'https://widget.powerboard.commbank.com.au/sdk/platforms/compatibility-registry.json' );
}

function check_the_directory(): void {
	$current_dir = basename( __DIR__ );
	$main_file   = basename( plugin_basename( __FILE__ ) );
	$implied_dir = pathinfo( $main_file, PATHINFO_FILENAME );

	if ( $current_dir !== $implied_dir ) {
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( is_plugin_active( plugin_basename( __FILE__ ) ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
		}

		$user_id = function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;

		set_transient(
			'power_board_status_change_error_' . $user_id,
			'Error: The plugin must be installed in the "' . $implied_dir . '" directory. Current one is: "' . $current_dir . '". Please delete the plugin and install it again.',
			300
		);

		add_action( 'admin_head', 'wrong_dir_style' );
		add_action( 'admin_notices', 'wrong_dir_notice' );
	}
}

/**
 * Used on admin_head hook
 * phpcs:disable WordPress.Security.NonceVerification -- processed through the WooCommerce form handler
 *
 * @noinspection PhpUnused
 */
function wrong_dir_style(): void {
	if ( isset( $_GET['activate'] ) ) {
		$user_id = function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;
		$error   = get_transient( 'power_board_status_change_error_' . $user_id );

		if ( $error ) {
			echo '<style>#message.updated.notice{display:none;}</style>';
		}
	}
}
// phpcs:enable

/**
 * Used on admin_notices hook
 *
 * @noinspection PhpUnused
 */
function wrong_dir_notice(): void {
	$user_id = function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;
	$error   = get_transient( 'power_board_status_change_error_' . $user_id );

	if ( $error ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $error ) . '</p></div>';
		delete_transient( 'power_board_status_change_error_' . $user_id );
	}
}

register_activation_hook( __FILE__, 'check_the_directory' );
add_action( 'admin_init', 'check_the_directory' );

require_once __DIR__ . '/vendor/autoload.php';

PowerBoard\PowerBoardPlugin::get_instance();
