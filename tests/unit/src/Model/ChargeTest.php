<?php
declare( strict_types=1 );

namespace unit\src\Model;

use PHPUnit\Framework\TestCase;
use PowerBoard\Model\Charge;
use PowerBoard\Model\PaymentSource;

/**
 * Unit tests for Charge class
 */
class ChargeTest extends TestCase {

	/**
	 * Valid charge data for testing
	 *
	 * @var array
	 */
	private array $valid_charge_data;

	protected function setUp(): void {
		parent::setUp();

		$this->valid_charge_data = [
			'charge_id' => 'ch_test_123456789',
			'charge'    => [
				'customer' => [
					'payment_source' => [
						'type'         => 'card',
						'card_scheme'  => 'visa',
						'gateway_type' => 'paydock',
						'gateway_name' => 'Paydock Gateway',
						'wallet_type'  => '',
					],
				],
			],
		];
	}

	/**
	 * Test constructor with valid data structure (customer.payment_source)
	 */
	public function test_constructor_with_customer_payment_source() {
		$charge = new Charge( $this->valid_charge_data );

		$this->assertEquals( 'ch_test_123456789', $charge->get_charge_id() );
		$this->assertInstanceOf( PaymentSource::class, $charge->get_payment_source() );
		$this->assertEquals( 'VISA', $charge->get_payment_source()->get_card_scheme() );
		$this->assertEquals( 'paydock', $charge->get_payment_source()->get_gateway_type() );
		$this->assertEquals( 'Paydock Gateway', $charge->get_payment_source()->get_gateway_name() );
		$this->assertEquals( 'Card', $charge->get_payment_source()->get_type() );
	}

	/**
	 * Test constructor with alternative data structure (charge.payment_source)
	 */
	public function test_constructor_with_charge_payment_source() {
		$data = [
			'charge_id' => 'ch_test_123456789',
			'charge'    => [
				'payment_source' => [
					'type'         => 'card',
					'card_scheme'  => 'mastercard',
					'gateway_type' => 'paypal',
					'gateway_name' => 'PayPal Gateway',
					'wallet_type'  => '',
				],
			],
		];

		$charge = new Charge( $data );

		$this->assertEquals( 'ch_test_123456789', $charge->get_charge_id() );
		$this->assertInstanceOf( PaymentSource::class, $charge->get_payment_source() );
		$this->assertEquals( 'MASTERCARD', $charge->get_payment_source()->get_card_scheme() );
		$this->assertEquals( 'paypal', $charge->get_payment_source()->get_gateway_type() );
		$this->assertEquals( 'PayPal Gateway', $charge->get_payment_source()->get_gateway_name() );
		$this->assertEquals( 'Card', $charge->get_payment_source()->get_type() );
	}

	/**
	 * Test constructor with empty payment source data
	 */
	public function test_constructor_with_empty_payment_source() {
		$data = [
			'charge_id' => 'ch_test_123456789',
			'charge'    => [],
		];

		$charge = new Charge( $data );

		$this->assertEquals( 'ch_test_123456789', $charge->get_charge_id() );
		$this->assertInstanceOf( PaymentSource::class, $charge->get_payment_source() );
		$this->assertEmpty( $charge->get_payment_source()->get_card_scheme() );
		$this->assertEmpty( $charge->get_payment_source()->get_gateway_type() );
		$this->assertEmpty( $charge->get_payment_source()->get_gateway_name() );
		$this->assertEmpty( $charge->get_payment_source()->get_type() );
		$this->assertEmpty( $charge->get_payment_source()->get_wallet_type() );
	}

	/**
	 * Test constructor with missing charge data
	 */
	public function test_constructor_with_missing_charge_data() {
		$data = [
			'charge_id' => 'ch_test_123456789',
		];

		$charge = new Charge( $data );

		$this->assertEquals( 'ch_test_123456789', $charge->get_charge_id() );
		$this->assertInstanceOf( PaymentSource::class, $charge->get_payment_source() );
	}

	/**
	 * Test getChargeLabel with wallet type (highest priority)
	 */
	public function test_get_charge_label_with_wallet_type() {
		$data = [
			'charge_id' => 'ch_test_123456789',
			'charge'    => [
				'customer' => [
					'payment_source' => [
						'wallet_type'  => 'apple',
						'type'         => 'card',
						'card_scheme'  => 'visa',
						'gateway_type' => 'paydock',
						'gateway_name' => 'Paydock Gateway',
					],
				],
			],
		];

		$charge = new Charge( $data );
		$this->assertEquals( 'Apple Pay', $charge->get_charge_label() );
	}

	/**
	 * Test getChargeLabel with card scheme (second priority)
	 */
	public function test_get_charge_label_with_card_scheme() {
		$data = [
			'charge_id' => 'ch_test_123456789',
			'charge'    => [
				'customer' => [
					'payment_source' => [
						'wallet_type'  => '',
						'type'         => 'card',
						'card_scheme'  => 'visa',
						'gateway_type' => 'paydock',
						'gateway_name' => 'Paydock Gateway',
					],
				],
			],
		];

		$charge = new Charge( $data );
		$this->assertEquals( 'VISA', $charge->get_charge_label() );
	}

	/**
	 * Test getChargeLabel with gateway type (third priority)
	 */
	public function test_get_charge_label_with_gateway_type() {
		$data = [
			'charge_id' => 'ch_test_123456789',
			'charge'    => [
				'customer' => [
					'payment_source' => [
						'wallet_type'  => '',
						'type'         => 'card',
						'card_scheme'  => '',
						'gateway_type' => 'paydock',
						'gateway_name' => 'Paydock Gateway',
					],
				],
			],
		];

		$charge = new Charge( $data );
		$this->assertEquals( 'paydock', $charge->get_charge_label() );
	}

	/**
	 * Test getChargeLabel with gateway name (fourth priority)
	 */
	public function test_get_charge_label_with_gateway_name() {
		$data = [
			'charge_id' => 'ch_test_123456789',
			'charge'    => [
				'customer' => [
					'payment_source' => [
						'wallet_type'  => '',
						'type'         => 'card',
						'card_scheme'  => '',
						'gateway_type' => '',
						'gateway_name' => 'Paydock Gateway',
					],
				],
			],
		];

		$charge = new Charge( $data );
		$this->assertEquals( 'Paydock Gateway', $charge->get_charge_label() );
	}

	/**
	 * Test getChargeLabel with type (fifth priority)
	 */
	public function test_get_charge_label_with_type() {
		$data = [
			'charge_id' => 'ch_test_123456789',
			'charge'    => [
				'customer' => [
					'payment_source' => [
						'wallet_type'  => '',
						'type'         => 'card',
						'card_scheme'  => '',
						'gateway_type' => '',
						'gateway_name' => '',
					],
				],
			],
		];

		$charge = new Charge( $data );
		$this->assertEquals( 'Card', $charge->get_charge_label() );
	}

	/**
	 * Test getChargeLabel with no payment source data (returns empty string)
	 */
	public function test_get_charge_label_with_no_data() {
		$data = [
			'charge_id' => 'ch_test_123456789',
			'charge'    => [],
		];

		$charge = new Charge( $data );
		$this->assertEquals( '', $charge->get_charge_label() );
	}

	/**
	 * Test getChargeLabel priority order
	 */
	public function test_get_charge_label_priority_order() {
		$data = [
			'charge_id' => 'ch_test_123456789',
			'charge'    => [
				'customer' => [
					'payment_source' => [
						'wallet_type'  => 'google', // Should be highest priority
						'type'         => 'card',
						'card_scheme'  => 'visa',
						'gateway_type' => 'paydock',
						'gateway_name' => 'Paydock Gateway',
					],
				],
			],
		];

		$charge = new Charge( $data );
		$this->assertEquals( 'Google Pay', $charge->get_charge_label() );
	}

	/**
	 * Test setChargeId method
	 */
	public function test_set_charge_id() {
		$charge = new Charge( $this->valid_charge_data );

		$charge->set_charge_id( 'new_charge_id_123' );
		$this->assertEquals( 'new_charge_id_123', $charge->get_charge_id() );
	}

	/**
	 * Test setPaymentSource method
	 */
	public function test_set_payment_source() {
		$charge = new Charge( $this->valid_charge_data );

		$new_payment_source_data = [
			'type'         => 'paypal',
			'wallet_type'  => 'paypal',
			'gateway_name' => 'PayPal Gateway',
		];

		$charge->set_payment_source( $new_payment_source_data );

		$this->assertInstanceOf( PaymentSource::class, $charge->get_payment_source() );
		$this->assertEquals( 'PayPal', $charge->get_payment_source()->get_wallet_type() );
		$this->assertEquals( 'Paypal', $charge->get_payment_source()->get_type() );
		$this->assertEquals( 'PayPal Gateway', $charge->get_payment_source()->get_gateway_name() );
	}

	/**
	 * Test $this->get_charge_id() method
	 */
	public function test_get_charge_id() {
		$charge = new Charge( $this->valid_charge_data );
		$this->assertEquals( 'ch_test_123456789', $charge->get_charge_id() );
	}

	/**
	 * Test getPaymentSource method
	 */
	public function test_get_payment_source() {
		$charge         = new Charge( $this->valid_charge_data );
		$payment_source = $charge->get_payment_source();

		$this->assertInstanceOf( PaymentSource::class, $payment_source );
		$this->assertEquals( 'VISA', $payment_source->get_card_scheme() );
		$this->assertEquals( 'paydock', $payment_source->get_gateway_type() );
		$this->assertEquals( 'Paydock Gateway', $payment_source->get_gateway_name() );
		$this->assertEquals( 'Card', $payment_source->get_type() );
	}

	/**
	 * Test with different wallet types
	 */
	public function test_different_wallet_types() {
		$wallet_types = [
			'google'  => 'Google Pay',
			'apple'   => 'Apple Pay',
			'paypal'  => 'PayPal',
			'unknown' => 'Unknown', // Should be ucwords'd
		];

		foreach ( $wallet_types as $wallet_type => $expected_label ) {
			$data = [
				'charge_id' => 'ch_test_123456789',
				'charge'    => [
					'customer' => [
						'payment_source' => [
							'wallet_type'  => $wallet_type,
							'type'         => 'card',
							'card_scheme'  => '',
							'gateway_type' => '',
							'gateway_name' => '',
						],
					],
				],
			];

			$charge = new Charge( $data );
			$this->assertEquals( $expected_label, $charge->get_charge_label() );
		}
	}

	/**
	 * Test with null/empty values
	 */
	public function test_with_null_empty_values() {
		$data = [
			'charge_id' => 'ch_test_123456789',
			'charge'    => [
				'customer' => [
					'payment_source' => [
						'wallet_type'  => null,
						'type'         => null,
						'card_scheme'  => null,
						'gateway_type' => null,
						'gateway_name' => null,
					],
				],
			],
		];

		$charge = new Charge( $data );
		$this->assertEquals( '', $charge->get_charge_label() );
	}

	/**
	 * Test with special characters in charge ID
	 */
	public function test_with_special_characters_in_charge_id() {
		$special_ids = [
			'ch_test-123_456',
			'ch.test.123.456',
			'ch_test@123#456',
			'ch_test 123 456',
		];

		foreach ( $special_ids as $charge_id ) {
			$data              = $this->valid_charge_data;
			$data['charge_id'] = $charge_id;

			$charge = new Charge( $data );
			$this->assertEquals( $charge_id, $charge->get_charge_id() );
		}
	}

	/**
	 * Test that PaymentSource is properly instantiated
	 */
	public function test_payment_source_instantiation() {
		$charge         = new Charge( $this->valid_charge_data );
		$payment_source = $charge->get_payment_source();

		$this->assertInstanceOf( PaymentSource::class, $payment_source );
		$this->assertNotNull( $payment_source );
	}

	/**
	 * Test that all getter methods return correct types
	 */
	public function test_getter_return_types() {
		$charge = new Charge( $this->valid_charge_data );

		$this->assertIsString( $charge->get_charge_id() );
		$this->assertIsObject( $charge->get_payment_source() );
		$this->assertIsString( $charge->get_charge_label() );
	}

	/**
	 * Test with malformed payment source data
	 */
	public function test_with_malformed_payment_source_data() {
		$data = [
			'charge_id' => 'ch_test_123456789',
			'charge'    => [
				'customer' => [
					'payment_source' => 'not_an_array',
				],
			],
		];

		// This should still work as PaymentSource constructor handles non-array data
		$charge = new Charge( $data );
		$this->assertInstanceOf( PaymentSource::class, $charge->get_payment_source() );
		$this->assertEquals( '', $charge->get_charge_label() );
	}

	/**
	 * Test with missing payment source key
	 */
	public function test_with_missing_payment_source_key() {
		$data = [
			'charge_id' => 'ch_test_123456789',
			'charge'    => [
				'customer' => [],
			],
		];

		$charge = new Charge( $data );
		$this->assertInstanceOf( PaymentSource::class, $charge->get_payment_source() );
		$this->assertEquals( '', $charge->get_charge_label() );
	}

	/**
	 * Test with nested missing keys
	 */
	public function test_with_nested_missing_keys() {
		$data = [
			'charge_id' => 'ch_test_123456789',
			'charge'    => [
				'customer' => null,
			],
		];

		$charge = new Charge( $data );
		$this->assertInstanceOf( PaymentSource::class, $charge->get_payment_source() );
		$this->assertEquals( '', $charge->get_charge_label() );
	}
}
