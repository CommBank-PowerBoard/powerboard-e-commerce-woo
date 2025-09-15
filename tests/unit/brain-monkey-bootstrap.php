<?php
/**
 * Bootstrap file specifically for Brain\Monkey tests
 * This file sets up Brain\Monkey for WordPress function mocking
 */

// Set up Brain\Monkey
require_once __DIR__ . '/../../vendor/autoload.php';

// Initialize Brain\Monkey
\Brain\Monkey\setUp();

// Define the WordPress plugin prefix constant if not already defined
if ( ! defined( 'POWER_BOARD_PLUGIN_PREFIX' ) ) {
	define( 'POWER_BOARD_PLUGIN_PREFIX', 'power_board' );
}

// Add a teardown function to be called at the end of each test
register_shutdown_function(
	function () {
		\Brain\Monkey\tearDown();
	}
	);
