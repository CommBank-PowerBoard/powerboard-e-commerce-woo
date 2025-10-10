<?php
declare( strict_types=1 );

namespace unit\src\Model;

use PHPUnit\Framework\TestCase;
use PowerBoard\Model\IPN;
use PowerBoard\Model\Charge;
use PowerBoard\Enums\AvailablePaymentMethods\PBAvailablePaymentMethodsEnum;
use PowerBoard\Enums\PaymentNotification\PBPaymentNotificationEnum;
use PowerBoard\Enums\PaymentNotification\APIPaymentNotificationEnum;
use PowerBoard\Enums\AvailablePaymentMethods\APIAvailablePaymentMethodsEnum;

/**
 * Unit tests for IPN class
 */
class IPNTest extends TestCase {

	/**
	 * Valid IPN data for testing.
	 *
	 * @var array
	 */
	private array $valid_ipn_data;

	protected function setUp(): void {
		parent::setUp();

		// Mock WordPress function - use static variable to ensure single declaration
		static $function_declared = false;
		if ( ! $function_declared && ! function_exists( 'wp_send_json_error' ) ) {
            // phpcs:ignore Squiz.PHP.Eval.Discouraged
			eval(
				'function wp_send_json_error( $data = null, $status_code = 400 ) {
				throw new \Exception( "wp_send_json_error called with: " . json_encode( $data ) );
			}'
				);
			$function_declared = true;
		}

		$this->valid_ipn_data = [
			'charge_id'       => 'ch_test_123456789',
			'intent_id'       => 'intent_test_123456789',
			'order_id'        => 12345,
			'failure_message' => '',
			'amount'          => 25.99,
			'currency'        => 'USD',
			'timestamp'       => 1640995200,
			'payment_method'  => APIAvailablePaymentMethodsEnum::CARD,
			'event_type'      => 'payment',
			'event_id'        => 'evt_test_123456789',
			'event'           => APIPaymentNotificationEnum::PAYMENT_SUCCEEDED,
			'is_paid'         => true,
			'charge'          => [
				'customer' => [
					'payment_source' => [
						'type'         => 'card',
						'card_scheme'  => 'visa',
						'gateway_type' => 'paydock',
						'gateway_name' => 'Paydock Gateway',
					],
				],
			],
		];
	}

	/**
	 * Test constructor with valid data
	 */
	public function test_constructor_with_valid_data() {
		$ipn = new IPN( $this->valid_ipn_data );

		$this->assertInstanceOf( Charge::class, $ipn->get_charge() );
		$this->assertEquals( 'intent_test_123456789', $ipn->get_intent_id() );
		$this->assertEquals( 12345, $ipn->get_order_id() );
		$this->assertEquals( '', $ipn->get_failure_message() );
		$this->assertEquals( 25.99, $ipn->get_amount() );
		$this->assertEquals( 'USD', $ipn->get_currency() );
		$this->assertEquals( 1640995200, $ipn->get_timestamp() );
		$this->assertEquals( PBAvailablePaymentMethodsEnum::CARD_KEY, $ipn->get_payment_method() );
		$this->assertEquals( 'payment', $ipn->get_object_type() );
		$this->assertEquals( 'evt_test_123456789', $ipn->get_event_id() );
		$this->assertEquals( PBPaymentNotificationEnum::PAYMENT_SUCCEEDED, $ipn->get_event() );
		$this->assertTrue( $ipn->get_is_paid() );
	}

	/**
	 * Test constructor with invalid data throws exception
	 */
	public function test_constructor_with_invalid_data() {
		$invalid_data = [
			'charge_id'      => '', // Empty charge_id should fail validation
			'intent_id'      => 'intent_test_123456789',
			'order_id'       => 12345,
			'amount'         => 25.99,
			'currency'       => 'USD',
			'timestamp'      => 1640995200,
			'payment_method' => APIAvailablePaymentMethodsEnum::CARD,
			'event_type'     => 'payment',
			'event_id'       => 'evt_test_123456789',
			'event'          => APIPaymentNotificationEnum::PAYMENT_SUCCEEDED,
		];

		$this->expectException( \Exception::class );

		new IPN( $invalid_data );
	}

	/**
	 * Test validation with different payment methods
	 */
	public function test_validation_with_different_payment_methods() {
		$payment_methods = [
			APIAvailablePaymentMethodsEnum::CARD      => PBAvailablePaymentMethodsEnum::CARD_KEY,
			APIAvailablePaymentMethodsEnum::APPLEPAY  => PBAvailablePaymentMethodsEnum::APPLE_PAY_KEY,
			APIAvailablePaymentMethodsEnum::GOOGLEPAY => PBAvailablePaymentMethodsEnum::GOOGLE_PAY_KEY,
			APIAvailablePaymentMethodsEnum::PAYPAL    => PBAvailablePaymentMethodsEnum::PAYPAL_KEY,
			APIAvailablePaymentMethodsEnum::AFTERPAY  => PBAvailablePaymentMethodsEnum::AFTERPAY_KEY,
			APIAvailablePaymentMethodsEnum::ZIP       => PBAvailablePaymentMethodsEnum::ZIP_KEY,
		];

		foreach ( $payment_methods as $api_method => $expected_method ) {
			$data                   = $this->valid_ipn_data;
			$data['payment_method'] = $api_method;

			$ipn = new IPN( $data );
			$this->assertEquals( $expected_method, $ipn->get_payment_method() );
		}
	}

	/**
	 * Test validation with different event types
	 */
	public function test_validation_with_different_events() {
		$events = [
			APIPaymentNotificationEnum::PAYMENT_SUCCEEDED  => PBPaymentNotificationEnum::PAYMENT_SUCCEEDED,
			APIPaymentNotificationEnum::PAYMENT_FAILED     => PBPaymentNotificationEnum::PAYMENT_FAILED,
			APIPaymentNotificationEnum::PAYMENT_CREATED    => PBPaymentNotificationEnum::PAYMENT_CREATED,
			APIPaymentNotificationEnum::PAYMENT_CAPTURED   => PBPaymentNotificationEnum::PAYMENT_CAPTURED,
			APIPaymentNotificationEnum::PAYMENT_VOIDED     => PBPaymentNotificationEnum::PAYMENT_VOIDED,
			APIPaymentNotificationEnum::CHECKOUT_CREATED   => PBPaymentNotificationEnum::CHECKOUT_CREATED,
			APIPaymentNotificationEnum::CHECKOUT_CANCELLED => PBPaymentNotificationEnum::CHECKOUT_CANCELLED,
			APIPaymentNotificationEnum::CHECKOUT_EXPIRED   => PBPaymentNotificationEnum::CHECKOUT_EXPIRED,
			APIPaymentNotificationEnum::CHECKOUT_FAILED    => PBPaymentNotificationEnum::CHECKOUT_FAILED,
			APIPaymentNotificationEnum::CHECKOUT_COMPLETED => PBPaymentNotificationEnum::CHECKOUT_COMPLETED,
		];

		foreach ( $events as $api_event => $expected_event ) {
			$data          = $this->valid_ipn_data;
			$data['event'] = $api_event;

			$ipn = new IPN( $data );
			$this->assertEquals( $expected_event, $ipn->get_event() );
		}
	}

	/**
	 * Test validation with different object types
	 */
	public function test_validation_with_different_object_types() {
		$object_types = [ 'payment', 'refund' ];

		foreach ( $object_types as $object_type ) {
			$data               = $this->valid_ipn_data;
			$data['event_type'] = $object_type;

			$ipn = new IPN( $data );
			$this->assertEquals( $object_type, $ipn->get_object_type() );
		}
	}

	/**
	 * Test validation with invalid payment method
	 */
	public function test_validation_with_invalid_payment_method() {
		$data                   = $this->valid_ipn_data;
		$data['payment_method'] = 'invalid_payment_method';

		$this->expectException( \Exception::class );

		new IPN( $data );
	}

	/**
	 * Test validation with invalid event
	 */
	public function test_validation_with_invalid_event() {
		$data          = $this->valid_ipn_data;
		$data['event'] = 'invalid_event';

		$this->expectException( \Exception::class );

		new IPN( $data );
	}

	/**
	 * Test validation with invalid object type
	 */
	public function test_validation_with_invalid_object_type() {
		$data               = $this->valid_ipn_data;
		$data['event_type'] = 'invalid_type';

		$this->expectException( \Exception::class );

		new IPN( $data );
	}

	/**
	 * Test validation with invalid amount (negative)
	 */
	public function test_validation_with_negative_amount() {
		$data           = $this->valid_ipn_data;
		$data['amount'] = -10.00;

		$this->expectException( \Exception::class );

		new IPN( $data );
	}

	/**
	 * Test validation with zero amount
	 */
	public function test_validation_with_zero_amount() {
		$data           = $this->valid_ipn_data;
		$data['amount'] = 0;

		$this->expectException( \Exception::class );

		new IPN( $data );
	}

	/**
	 * Test validation with invalid order_id (non-integer)
	 */
	public function test_validation_with_invalid_order_id() {
		$data             = $this->valid_ipn_data;
		$data['order_id'] = 'not_an_integer';

		$this->expectException( \Exception::class );

		new IPN( $data );
	}

	/**
	 * Test validation with invalid timestamp (non-integer)
	 */
	public function test_validation_with_invalid_timestamp() {
		$data              = $this->valid_ipn_data;
		$data['timestamp'] = 'not_a_timestamp';

		$this->expectException( \Exception::class );

		new IPN( $data );
	}

	/**
	 * Test validation with empty currency
	 */
	public function test_validation_with_empty_currency() {
		$data             = $this->valid_ipn_data;
		$data['currency'] = '';

		$this->expectException( \Exception::class );

		new IPN( $data );
	}

	/**
	 * Test validation with empty strings for required fields
	 */
	public function test_validation_with_empty_strings() {
		$empty_string_fields = [ 'charge_id', 'intent_id', 'event_id' ];

		foreach ( $empty_string_fields as $field ) {
			$data           = $this->valid_ipn_data;
			$data[ $field ] = '';

			$this->expectException( \Exception::class );

			new IPN( $data );
		}
	}

	/**
	 * Test validation with null values
	 */
	public function test_validation_with_null_values() {
		$data              = $this->valid_ipn_data;
		$data['charge_id'] = null;

		$this->expectException( \Exception::class );

		new IPN( $data );
	}

	/**
	 * Test validation with different numeric amounts
	 */
	public function test_validation_with_different_amounts() {
		$amounts = [ 0.01, 1.00, 100.50, 999.99, 1000.00 ];

		foreach ( $amounts as $amount ) {
			$data           = $this->valid_ipn_data;
			$data['amount'] = $amount;

			$ipn = new IPN( $data );
			$this->assertEquals( $amount, $ipn->get_amount() );
		}
	}

	/**
	 * Test validation with different currencies
	 */
	public function test_validation_with_different_currencies() {
		$currencies = [ 'USD', 'EUR', 'GBP', 'CAD', 'AUD' ];

		foreach ( $currencies as $currency ) {
			$data             = $this->valid_ipn_data;
			$data['currency'] = $currency;

			$ipn = new IPN( $data );
			$this->assertEquals( $currency, $ipn->get_currency() );
		}
	}

	/**
	 * Test validation with different timestamps
	 */
	public function test_validation_with_different_timestamps() {
		$timestamps = [ 1640995200, 1609459200, 1577836800 ];

		foreach ( $timestamps as $timestamp ) {
			$data              = $this->valid_ipn_data;
			$data['timestamp'] = $timestamp;

			$ipn = new IPN( $data );
			$this->assertEquals( $timestamp, $ipn->get_timestamp() );
		}
	}

	/**
	 * Test validation with different order IDs
	 */
	public function test_validation_with_different_order_ids() {
		$order_ids = [ 1, 100, 1000, 99999 ];

		foreach ( $order_ids as $order_id ) {
			$data             = $this->valid_ipn_data;
			$data['order_id'] = $order_id;

			$ipn = new IPN( $data );
			$this->assertEquals( $order_id, $ipn->get_order_id() );
		}
	}

	/**
	 * Test validation with failure message
	 */
	public function test_validation_with_failure_message() {
		$data                    = $this->valid_ipn_data;
		$data['failure_message'] = 'Payment failed due to insufficient funds';
		$data['is_paid']         = false;

		$ipn = new IPN( $data );
		$this->assertEquals( 'Payment failed due to insufficient funds', $ipn->get_failure_message() );
		$this->assertFalse( $ipn->get_is_paid() );
	}


	/**
	 * Test edge case with special characters in strings
	 */
	public function test_validation_with_special_characters() {
		$data              = $this->valid_ipn_data;
		$data['charge_id'] = 'ch_test_123-456_789';
		$data['intent_id'] = 'intent_test_123-456_789';
		$data['event_id']  = 'evt_test_123-456_789';

		$ipn = new IPN( $data );
		$this->assertEquals( 'ch_test_123-456_789', $ipn->get_charge()->get_charge_id() );
		$this->assertEquals( 'intent_test_123-456_789', $ipn->get_intent_id() );
		$this->assertEquals( 'evt_test_123-456_789', $ipn->get_event_id() );
	}

	/**
	 * Test that all getters return correct types
	 */
	public function test_getter_return_types() {
		$ipn = new IPN( $this->valid_ipn_data );

		$this->assertIsObject( $ipn->get_charge() );
		$this->assertIsString( $ipn->get_intent_id() );
		$this->assertIsInt( $ipn->get_order_id() );
		$this->assertIsString( $ipn->get_failure_message() );
		$this->assertIsFloat( $ipn->get_amount() );
		$this->assertIsString( $ipn->get_currency() );
		$this->assertIsInt( $ipn->get_timestamp() );
		$this->assertIsString( $ipn->get_payment_method() );
		$this->assertIsString( $ipn->get_object_type() );
		$this->assertIsString( $ipn->get_event_id() );
		$this->assertIsString( $ipn->get_event() );
		$this->assertIsBool( $ipn->get_is_paid() );
	}

	/**
	 * Test validation with missing charge data
	 */
	public function test_validation_with_missing_charge_data() {
		$data = $this->valid_ipn_data;
		unset( $data['charge'] );

		// This should still work as Charge constructor handles missing data only change_id is required
		$ipn = new IPN( $data );
		$this->assertInstanceOf( Charge::class, $ipn->get_charge() );
	}

	/**
	 * Test validation with empty charge data
	 */
	public function test_validation_with_empty_charge_data() {
		$data           = $this->valid_ipn_data;
		$data['charge'] = [];

		$ipn = new IPN( $data );
		$this->assertInstanceOf( Charge::class, $ipn->get_charge() );
	}
}
