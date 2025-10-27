<?php

declare( strict_types=1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once 'vendor/autoload.php';

if ( ! defined( 'POWER_BOARD_PLUGIN_PREFIX' ) ) {
	define( 'POWER_BOARD_PLUGIN_PREFIX', 'power_board' );
}

$settings_option_name = 'woocommerce_' . POWER_BOARD_PLUGIN_PREFIX . '_settings';

delete_option( $settings_option_name );

if ( function_exists( 'is_multisite' ) && is_multisite() ) {
	delete_site_option( $settings_option_name );
}
