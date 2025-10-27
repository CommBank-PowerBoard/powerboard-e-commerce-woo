<?php
declare( strict_types=1 );
/**
 * PowerBoard settings page
 *
 * @var array $data
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

\settings_fields( 'powerboard_settings' );
\settings_errors( 'powerboard_checkout_version' );

if ( isset( $data['template_service'] ) ) {
	$template_service = $data['template_service'];

	$template_service->setting_service->parent_generate_settings_html( $data['form_fields'], true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped  --  the following require is safe it is not a user input.
}
