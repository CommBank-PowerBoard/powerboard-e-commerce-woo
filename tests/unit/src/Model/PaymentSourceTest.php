<?php
declare( strict_types=1 );

namespace unit\src\Model;

use PHPUnit\Framework\TestCase;
use PowerBoard\Model\PaymentSource;

/**
 * Unit tests for PaymentSource class
 */
class PaymentSourceTest extends TestCase {

	/**
	 * Valid payment source data for testing
	 *
	 * @var array
	 */
	private array $valid_payment_source_data;

	protected function setUp(): void {
		parent::setUp();

		$this->valid_payment_source_data = [
			'type'         => 'card',
			'card_scheme'  => 'visa',
			'gateway_type' => 'paydock',
			'gateway_name' => 'Paydock Gateway',
			'wallet_type'  => '',
		];
	}

	/**
	 * Test constructor with valid data
	 */
	public function test_constructor_with_valid_data() {
		$payment_source = new PaymentSource( $this->valid_payment_source_data );

		$this->assertEquals( 'Card', $payment_source->get_type() );
		$this->assertEquals( 'VISA', $payment_source->get_card_scheme() );
		$this->assertEquals( 'paydock', $payment_source->get_gateway_type() );
		$this->assertEquals( 'Paydock Gateway', $payment_source->get_gateway_name() );
		$this->assertEmpty( $payment_source->get_wallet_type() );
	}

	/**
	 * Test constructor with empty data
	 */
	public function test_constructor_with_empty_data() {
		$payment_source = new PaymentSource( [] );

		$this->assertEmpty( $payment_source->get_type() );
		$this->assertEmpty( $payment_source->get_card_scheme() );
		$this->assertEmpty( $payment_source->get_gateway_type() );
		$this->assertEmpty( $payment_source->get_gateway_name() );
		$this->assertEmpty( $payment_source->get_wallet_type() );
	}

	/**
	 * Test constructor with null data
	 */
	public function test_constructor_with_null_data() {
		$payment_source = new PaymentSource( null );

		$this->assertEmpty( $payment_source->get_type() );
		$this->assertEmpty( $payment_source->get_card_scheme() );
		$this->assertEmpty( $payment_source->get_gateway_type() );
		$this->assertEmpty( $payment_source->get_gateway_name() );
		$this->assertEmpty( $payment_source->get_wallet_type() );
	}

	/**
	 * Test wallet type mapping - Google Pay
	 */
	public function test_wallet_type_google_pay() {
		$data           = [ 'wallet_type' => 'google' ];
		$payment_source = new PaymentSource( $data );

		$this->assertEquals( 'Google Pay', $payment_source->get_wallet_type() );
	}

	/**
	 * Test wallet type mapping - Apple Pay
	 */
	public function test_wallet_type_apple_pay() {
		$data           = [ 'wallet_type' => 'apple' ];
		$payment_source = new PaymentSource( $data );

		$this->assertEquals( 'Apple Pay', $payment_source->get_wallet_type() );
	}

	/**
	 * Test wallet type mapping - PayPal
	 */
	public function test_wallet_type_paypal() {
		$data           = [ 'wallet_type' => 'paypal' ];
		$payment_source = new PaymentSource( $data );

		$this->assertEquals( 'PayPal', $payment_source->get_wallet_type() );
	}

	/**
	 * Test wallet type fallback for unknown types
	 */
	public function test_wallet_type_unknown_fallback() {
		$data           = [ 'wallet_type' => 'unknown_wallet' ];
		$payment_source = new PaymentSource( $data );

		$this->assertEquals( 'Unknown_wallet', $payment_source->get_wallet_type() );
	}

	/**
	 * Test wallet type with multiple words
	 */
	public function test_wallet_type_multiple_words() {
		$data           = [ 'wallet_type' => 'digital wallet' ];
		$payment_source = new PaymentSource( $data );

		$this->assertEquals( 'Digital Wallet', $payment_source->get_wallet_type() );
	}

	/**
	 * Test wallet type with empty string
	 */
	public function test_wallet_type_empty_string() {
		$data           = [ 'wallet_type' => '' ];
		$payment_source = new PaymentSource( $data );

		$this->assertEmpty( $payment_source->get_wallet_type() );
	}

	/**
	 * Test wallet type with null value
	 */
	public function test_wallet_type_null() {
		$data           = [ 'wallet_type' => null ];
		$payment_source = new PaymentSource( $data );

		$this->assertEmpty( $payment_source->get_wallet_type() );
	}

	/**
	 * Test card scheme with card_scheme field
	 */
	public function test_card_scheme_with_card_scheme_field() {
		$data           = [ 'card_scheme' => 'visa' ];
		$payment_source = new PaymentSource( $data );

		$this->assertEquals( 'VISA', $payment_source->get_card_scheme() );
	}

	/**
	 * Test card scheme with scheme field (fallback)
	 */
	public function test_card_scheme_with_scheme_field() {
		$data           = [ 'scheme' => 'mastercard' ];
		$payment_source = new PaymentSource( $data );

		$this->assertEquals( 'MASTERCARD', $payment_source->get_card_scheme() );
	}

	/**
	 * Test card scheme priority (card_scheme over scheme)
	 */
	public function test_card_scheme_priority() {
		$data           = [
			'card_scheme' => 'visa',
			'scheme'      => 'mastercard',
		];
		$payment_source = new PaymentSource( $data );

		$this->assertEquals( 'VISA', $payment_source->get_card_scheme() );
	}

	/**
	 * Test card scheme with different cases
	 */
	public function test_card_scheme_case_insensitive() {
		$schemes = [ 'visa', 'VISA', 'Visa', 'vIsA' ];

		foreach ( $schemes as $scheme ) {
			$data           = [ 'card_scheme' => $scheme ];
			$payment_source = new PaymentSource( $data );
			$this->assertEquals( 'VISA', $payment_source->get_card_scheme() );
		}
	}

	/**
	 * Test card scheme with empty string
	 */
	public function test_card_scheme_empty_string() {
		$data           = [ 'card_scheme' => '' ];
		$payment_source = new PaymentSource( $data );

		$this->assertEmpty( $payment_source->get_card_scheme() );
	}

	/**
	 * Test card scheme with null value
	 */
	public function test_card_scheme_null() {
		$data           = [ 'card_scheme' => null ];
		$payment_source = new PaymentSource( $data );

		$this->assertEmpty( $payment_source->get_card_scheme() );
	}

	/**
	 * Test gateway type with valid data
	 */
	public function test_gateway_type_valid() {
		$data           = [ 'gateway_type' => 'paydock' ];
		$payment_source = new PaymentSource( $data );

		$this->assertEquals( 'paydock', $payment_source->get_gateway_type() );
	}

	/**
	 * Test gateway type with empty string
	 */
	public function test_gateway_type_empty_string() {
		$data           = [ 'gateway_type' => '' ];
		$payment_source = new PaymentSource( $data );

		$this->assertEmpty( $payment_source->get_gateway_type() );
	}

	/**
	 * Test gateway type with null value
	 */
	public function test_gateway_type_null() {
		$data           = [ 'gateway_type' => null ];
		$payment_source = new PaymentSource( $data );

		$this->assertEmpty( $payment_source->get_gateway_type() );
	}

	/**
	 * Test gateway name with valid data
	 */
	public function test_gateway_name_valid() {
		$data           = [ 'gateway_name' => 'Paydock Gateway' ];
		$payment_source = new PaymentSource( $data );

		$this->assertEquals( 'Paydock Gateway', $payment_source->get_gateway_name() );
	}

	/**
	 * Test gateway name with empty string
	 */
	public function test_gateway_name_empty_string() {
		$data           = [ 'gateway_name' => '' ];
		$payment_source = new PaymentSource( $data );

		$this->assertEmpty( $payment_source->get_gateway_name() );
	}

	/**
	 * Test gateway name with null value
	 */
	public function test_gateway_name_null() {
		$data           = [ 'gateway_name' => null ];
		$payment_source = new PaymentSource( $data );

		$this->assertEmpty( $payment_source->get_gateway_name() );
	}

	/**
	 * Test type with valid data
	 */
	public function test_type_valid() {
		$data           = [ 'type' => 'card' ];
		$payment_source = new PaymentSource( $data );

		$this->assertEquals( 'Card', $payment_source->get_type() );
	}

	/**
	 * Test type with multiple words
	 */
	public function test_type_multiple_words() {
		$data           = [ 'type' => 'bank transfer' ];
		$payment_source = new PaymentSource( $data );

		$this->assertEquals( 'Bank Transfer', $payment_source->get_type() );
	}

	/**
	 * Test type with empty string
	 */
	public function test_type_empty_string() {
		$data           = [ 'type' => '' ];
		$payment_source = new PaymentSource( $data );

		$this->assertEmpty( $payment_source->get_type() );
	}

	/**
	 * Test type with null value
	 */
	public function test_type_null() {
		$data           = [ 'type' => null ];
		$payment_source = new PaymentSource( $data );

		$this->assertEmpty( $payment_source->get_type() );
	}

	/**
	 * Test all getter methods return correct types
	 */
	public function test_getter_return_types() {
		$payment_source = new PaymentSource( $this->valid_payment_source_data );

		$this->assertIsString( $payment_source->get_type() );
		$this->assertIsString( $payment_source->get_card_scheme() );
		$this->assertIsString( $payment_source->get_gateway_type() );
		$this->assertIsString( $payment_source->get_gateway_name() );
		$this->assertIsString( $payment_source->get_wallet_type() );
	}

	/**
	 * Test with complete payment source data
	 */
	public function test_complete_payment_source_data() {
		$data = [
			'type'         => 'card',
			'card_scheme'  => 'visa',
			'gateway_type' => 'paydock',
			'gateway_name' => 'Paydock Gateway',
			'wallet_type'  => 'apple',
		];

		$payment_source = new PaymentSource( $data );

		$this->assertEquals( 'Card', $payment_source->get_type() );
		$this->assertEquals( 'VISA', $payment_source->get_card_scheme() );
		$this->assertEquals( 'paydock', $payment_source->get_gateway_type() );
		$this->assertEquals( 'Paydock Gateway', $payment_source->get_gateway_name() );
		$this->assertEquals( 'Apple Pay', $payment_source->get_wallet_type() );
	}

	/**
	 * Test with non-array data
	 */
	public function test_with_non_array_data() {
		$payment_source = new PaymentSource( 'not_an_array' );

		$this->assertEmpty( $payment_source->get_type() );
		$this->assertEmpty( $payment_source->get_card_scheme() );
		$this->assertEmpty( $payment_source->get_gateway_type() );
		$this->assertEmpty( $payment_source->get_gateway_name() );
		$this->assertEmpty( $payment_source->get_wallet_type() );
	}

	/**
	 * Test with numeric data
	 */
	public function test_with_numeric_data() {
		$data = [
			'type'         => 123,
			'card_scheme'  => 456,
			'gateway_type' => 789,
			'gateway_name' => 101112,
			'wallet_type'  => 131415,
		];

		$payment_source = new PaymentSource( $data );

		$this->assertEquals( '123', $payment_source->get_type() );
		$this->assertEquals( '456', $payment_source->get_card_scheme() );
		$this->assertEquals( '789', $payment_source->get_gateway_type() );
		$this->assertEquals( '101112', $payment_source->get_gateway_name() );
		$this->assertEquals( '131415', $payment_source->get_wallet_type() );
	}

	/**
	 * Test with boolean data
	 */
	public function test_with_boolean_data() {
		$data = [
			'type'         => true,
			'card_scheme'  => false,
			'gateway_type' => true,
			'gateway_name' => false,
			'wallet_type'  => true,
		];

		$payment_source = new PaymentSource( $data );

		$this->assertEquals( '1', $payment_source->get_type() );
		$this->assertEmpty( $payment_source->get_card_scheme() );
		$this->assertEquals( '1', $payment_source->get_gateway_type() );
		$this->assertEmpty( $payment_source->get_gateway_name() );
		$this->assertEquals( '1', $payment_source->get_wallet_type() );
	}

	/**
	 * Test wallet type mapping with all known types
	 */
	public function test_wallet_type_mapping_all_types() {
		$wallet_mappings = [
			'google' => 'Google Pay',
			'apple'  => 'Apple Pay',
			'paypal' => 'PayPal',
		];

		foreach ( $wallet_mappings as $input => $expected ) {
			$data           = [ 'wallet_type' => $input ];
			$payment_source = new PaymentSource( $data );
			$this->assertEquals( $expected, $payment_source->get_wallet_type() );
		}
	}

	/**
	 * Test card scheme with various schemes
	 */
	public function test_card_scheme_various_schemes() {
		$schemes = [ 'visa', 'mastercard', 'amex', 'discover', 'jcb', 'diners' ];

		foreach ( $schemes as $scheme ) {
			$data           = [ 'card_scheme' => $scheme ];
			$payment_source = new PaymentSource( $data );
			$this->assertEquals( strtoupper( $scheme ), $payment_source->get_card_scheme() );
		}
	}

	/**
	 * Test type with various payment types
	 */
	public function test_type_various_payment_types() {
		$types = [ 'card', 'bank_transfer', 'digital_wallet', 'crypto', 'cash' ];

		foreach ( $types as $type ) {
			$data           = [ 'type' => $type ];
			$payment_source = new PaymentSource( $data );
			$this->assertEquals( ucwords( $type ), $payment_source->get_type() );
		}
	}

	/**
	 * Test with whitespace-only strings
	 */
	public function test_with_whitespace_strings() {
		$data = [
			'type'         => '   ',
			'card_scheme'  => "\t\n",
			'gateway_type' => ' ',
			'gateway_name' => "\r\n",
			'wallet_type'  => "\t",
		];

		$payment_source = new PaymentSource( $data );

		$this->assertEquals( '   ', $payment_source->get_type() );
		$this->assertEquals( "\t\n", $payment_source->get_card_scheme() );
		$this->assertEquals( ' ', $payment_source->get_gateway_type() );
		$this->assertEquals( "\r\n", $payment_source->get_gateway_name() );
		$this->assertEquals( "\t", $payment_source->get_wallet_type() );
	}

	/**
	 * Test with undefined array keys
	 */
	public function test_with_undefined_keys() {
		$data = [
			'undefined_key'     => 'value',
			'another_undefined' => 'another_value',
		];

		$payment_source = new PaymentSource( $data );

		$this->assertEmpty( $payment_source->get_type() );
		$this->assertEmpty( $payment_source->get_card_scheme() );
		$this->assertEmpty( $payment_source->get_gateway_type() );
		$this->assertEmpty( $payment_source->get_gateway_name() );
		$this->assertEmpty( $payment_source->get_wallet_type() );
	}
}
