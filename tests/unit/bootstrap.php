<?php
/**
 * Bootstrap file for PHPUnit tests
 *
 * This file contains WordPress function mocks needed for testing.
 */

namespace PowerBoard\Services;

/**
 * Mock WordPress add_action function
 *
 * @param string   $hook     Hook name.
 * @param callable $callback Callback function.
 * @return void
 */
function add_action( $hook, $callback ) {
	// Mock implementation - do nothing in test environment
}

/**
 * Mock WordPress current_user_can function
 *
 * @param string $capability Capability to check.
 * @return bool Always returns false in test environment.
 */
function current_user_can( $capability ) {
	// Suppress unused parameter warning for mock function
	unset( $capability );
	return false; // Default to no permissions in tests
}

/**
 * Mock WordPress is_user_logged_in function
 *
 * @return bool Always returns false in test environment.
 */
function is_user_logged_in() {
	return false;
}

/**
 * Mock WordPress get_current_user_id function
 *
 * @return int Always returns 0 in test environment.
 */
function get_current_user_id() {
	return 0;
}

/**
 * Mock WordPress get_option function
 *
 * @param string $option Option name.
 * @param mixed  $default_value Default value.
 * @return mixed Default value.
 */
function get_option( $option, $default_value = '' ) {
	// Suppress unused parameter warning for mock function
	unset( $option );
	return $default_value;
}

/**
 * Mock WordPress wp_send_json_error function
 *
 * @param mixed $data Data to send.
 * @return void
 */
function wp_send_json_error( $data = null ) {
	// Mock implementation - do nothing in test environment
}

/**
 * Mock WordPress wp_send_json_success function
 *
 * @param mixed $data Data to send.
 * @return void
 */
function wp_send_json_success( $data = null ) {
	// Mock implementation - do nothing in test environment
}

// Add minimal WordPress function mocks for simple authorization tests
// Note: NonceHelper tests use Brain\Monkey which handles its own mocking

// phpcs:ignore Universal.Namespaces.OneDeclarationPerFile.MultipleFound -- Required for test mocking
namespace PowerBoard\Controllers\Integrations;

/**
 * Mock WordPress current_user_can function for PaymentController namespace
 *
 * @param string $capability Capability to check.
 * @return bool Always returns false in test environment.
 */
function current_user_can( $capability ) {
	// Suppress unused parameter warning for mock function
	unset( $capability );
	return false; // Default to no permissions in tests
}

/**
 * Mock WordPress __ function
 *
 * @param string $text Text to translate.
 * @return string Untranslated text.
 */
function __( $text ) {
	return $text;
}

/**
 * Mock WooCommerce wc_get_order function
 *
 * @param int $order_id Order ID.
 * @return mixed Mock order object.
 */
function wc_get_order( $order_id ) {
	// Suppress unused parameter warning for mock function
	unset( $order_id );
	return null; // Always return null in test environment
}

// Add WordPress function mocks for API namespace - use global scope
if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * Global mock WordPress esc_html function
	 * This will be available to all namespaces including PowerBoard\API
	 *
	 * @param string $text Text to escape.
	 * @return string Escaped text.
	 */
	function esc_html( $text ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

// Include the mock ConfigService and use class_alias to replace the real class
require_once __DIR__ . '/mocks/API/ConfigService.php';
// Only alias if the real class hasn't been loaded yet to avoid conflicts
if ( !class_exists( 'PowerBoard\\API\\ConfigService', false ) ) {
	class_alias( 'unit\\mocks\\API\\ConfigService', 'PowerBoard\\API\\ConfigService' );
}
