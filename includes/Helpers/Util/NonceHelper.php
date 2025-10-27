<?php
declare( strict_types=1 );

namespace PowerBoard\Helpers\Util;

class NonceHelper {
	public static function is_valid_nonce( $nonce, $nonce_action ): bool {
		$wp_nonce = isset( $nonce ) ? sanitize_text_field( wp_unslash( $nonce ) ) : null;

		if ( ! wp_verify_nonce( $wp_nonce, $nonce_action ) ) {
			wp_send_json_error( [ 'message' => __( 'Error: Security check', 'power-board' ) ] );

			return false;
		}

		return true;
	}
}
