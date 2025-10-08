<?php

namespace unit\src\Helpers\MasterWidgetHelper;

use PHPUnit\Framework\TestCase;
use PowerBoard\Helpers\MasterWidgetHelper;

class ValidatePhoneNumberTest extends TestCase {

	/**
	 * Test valid phone numbers
	 *
	 * @dataProvider validPhoneNumbersProvider
	 */
	public function testValidPhoneNumbers( string $input, bool $expected ): void {
		$result = MasterWidgetHelper::validate_phone_number( $input );
		$this->assertEquals( $expected, $result );
	}

	/**
	 * Testing specific number format
	 */
	public function testPhoneNumberWithSpecialCharacters(): void {
		$phone  = '(02) 9876-5432';
		$result = MasterWidgetHelper::validate_phone_number( $phone );
		$this->assertEquals( false, $result );
	}

	public static function validPhoneNumbersProvider(): array {
		return [
			'Already formatted international number' => [ '+61412345678', true ],
			'Different country code'                 => [ '+44123456789', true ],
			'Minimum length with country code'       => [ '+61123', true ],
			'Number with white-spaces'               => [ '+611 2323', true ],
			'Number with white-spaces 2'             => [ '+6 2323', true ],
		];
	}



	/**
	 * Test invalid phone numbers with weird characters
	 *
	 * @dataProvider invalidNumbersProvider
	 */
	public function testInvalidCharacters( string $input, bool $expected ): void {
		$result = MasterWidgetHelper::validate_phone_number( $input );
		$this->assertEquals( $expected, $result );
	}

	public static function invalidNumbersProvider(): array {
		return [
			'Letters in number'             => [ '04abc12345678', false ],
			'Special characters'            => [ '04!@#$%^&*()12345678', false ],
			'Mixed invalid characters'      => [ '04ab!@#cd12345678', false ],
			'Mixed invalid number format'   => [ '04', false ],
			'Mixed invalid number format 2' => [ '04123123123123123', false ],
			'Mixed invalid number format 3' => [ '+0123', false ],
			'Mixed invalid number format 4' => [ '+0', false ],
		];
	}

	/**
	 * Testing the return for null phone numbers
	 */
	public function testNullInput(): void {
		$result = MasterWidgetHelper::validate_phone_number( null );
		$this->assertEquals( false, $result );
	}

	/**
	 * Testing the return for empty phone numbers
	 */
	public function testEmptyStringInput(): void {
		$result = MasterWidgetHelper::validate_phone_number( '' );
		$this->assertEquals( false, $result );
	}

	/**
	 * Test phone numbers with existing international country codes
	 *
	 * @dataProvider differentCountryCodesProvider
	 */
	public function testPreservesExistingCountryCodes( string $input, bool $expected ): void {
		$result = MasterWidgetHelper::validate_phone_number( $input );
		$this->assertEquals( $expected, $result );
	}

	public static function differentCountryCodesProvider(): array {
		return [
			'UK number' => [ '+44123456789', true ],
			'US number' => [ '+1123456789', true ],
			'NZ number' => [ '+64123456789', true ],
		];
	}
}