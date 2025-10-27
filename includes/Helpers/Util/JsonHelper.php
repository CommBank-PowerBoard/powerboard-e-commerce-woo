<?php
declare( strict_types=1 );

namespace PowerBoard\Helpers\Util;

class JsonHelper {
	public static function decode_stringified_json( $data ) {
		if ( is_array( $data ) || is_object( $data ) ) {
			foreach ( $data as &$value ) {
				$value = self::decode_stringified_json( $value );
			}
		} elseif ( is_string( $data ) ) {
			$decoded = json_decode( $data, true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				return $decoded;
			} else {
				$data = str_replace( '\\', '\\\\', $data );
				$data = str_replace( '"', '\'', $data );
			}
		}

		return $data;
	}
}
