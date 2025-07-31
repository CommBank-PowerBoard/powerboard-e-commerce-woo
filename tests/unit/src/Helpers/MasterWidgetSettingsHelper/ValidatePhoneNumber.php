<?php

namespace unit\src\Helpers\MasterWidgetSettingsHelper;

use PHPUnit\Framework\TestCase;
use PowerBoard\Helpers\MasterWidgetSettingsHelper;

class ValidatePhoneNumber extends TestCase
{
    /**
     * @dataProvider validPhoneNumbersProvider
     */
    public function testValidPhoneNumbers(string $input, string $expected): void
    {
        $result = MasterWidgetSettingsHelper::validate_phone_number($input);
        $this->assertEquals($expected, $result);
    }

    /**
     * @dataProvider phoneNumbersRequiringFormattingProvider
     */
    public function testPhoneNumbersRequiringFormatting(string $input, string $expected): void
    {
        $result = MasterWidgetSettingsHelper::validate_phone_number($input);
        $this->assertEquals($expected, $result);
    }

    /**
     * @dataProvider phoneNumbersRequiringFormattingProvider
     */
    public function testPhoneNumberOutputFormatting(string $input): void
    {
        $result = MasterWidgetSettingsHelper::validate_phone_number($input);
        $this->assertMatchesRegularExpression('/^\+[1-9]{1}[0-9]{3,14}$/', $result);;
    }

    public function testLongPhoneNumberTruncation(): void
    {
        $longPhone = '12345678901234567890';
        $result = MasterWidgetSettingsHelper::validate_phone_number($longPhone);
        $this->assertEquals('+611234567890123', $result);
        $this->assertEquals(16, strlen($result)); // +61 (3 chars) + 13 digits
    }

    /**
     * testing specific number format
     */
    public function testPhoneNumberWithSpecialCharacters(): void
    {
        $phone = '(02) 9876-5432';
        $result = MasterWidgetSettingsHelper::validate_phone_number($phone);
        $this->assertEquals('+610298765432', $result);
    }

    public function validPhoneNumbersProvider(): array
    {
        return [
            'Already formatted international number' => ['+61412345678', '+61412345678'],
            'Different country code' => ['+44123456789', '+44123456789'],
            'Minimum length with country code' => ['+61123', '+61123'],
        ];
    }

    /**
     * Data mock for testing purpose
     */
    public function phoneNumbersRequiringFormattingProvider(): array
    {
        return [
            'Local number without country code' => ['412345678', '+61412345678'],
            'Number with leading zero' => ['0412345678', '+610412345678'],
            'Local number with spaces' => ['0412 345 678', '+610412345678'],
            'Only zeros' => ['000000', '+61000000'],
            'Number with leading zeros' => ['00412345678', '+6100412345678'],
            'Maximum length number' => ['1234567890123456', '+611234567890123'],
            'Number with hyphens' => ['0412-345-678', '+610412345678'],
            'Number with parentheses' => ['(0412) 345678', '+610412345678'],
            "scenario provided by product1" => ["+44 74474889862","+4474474889862"],
            "scenario provided by product2" => ["7447488986123","+617447488986123"],
            "scenario provided by product3" => ["412 345 678","+61412345678"],
            "scenario provided by product4" => ["+61 981 987 987","+61981987987"],
            "scenario provided by product5" => ["(123) 45236-7890","+61123452367890"],
            "scenario provided by product6" => ["31245236-7890","+61312452367890"],
            "scenario provided by product7" => ["01254) 895 859","+6101254895859"],
            "scenario provided by product8" => ["1254 9488571","+6112549488571"],
            "scenario provided by product9" => ["1254857585","+611254857585"],
            "scenario provided by product0" => ["412 345 67811","+6141234567811"],
            "scenario provided by product11" => ["412 345 67811","+6141234567811"],
            "scenario provided by product12" => ["(123)123411231231231231212312123123123123","+611231234112312"],
            "scenario provided by product13" => ["+612345123452123123","+616123451234521"],
            "scenario provided by product14" => ["(123) 456-7890","+611234567890"],
            "scenario provided by product15" => ["020 7946 0000","+6102079460000"]
        ];
    }

    /**
     * @dataProvider invalidCharactersProvider
     *
     * Testing phone numbers with weird characters
     */
    public function testInvalidCharacters(string $input, string $expected): void
    {
        $result = MasterWidgetSettingsHelper::validate_phone_number($input);
        $this->assertEquals($expected, $result);
    }

    public function invalidCharactersProvider(): array
    {
        return [
            'Letters in number' => ['04abc12345678', '+610412345678'],
            'Special characters' => ['04!@#$%^&*()12345678', '+6104 12345678'],
            'Mixed invalid characters' => ['04ab!@#cd12345678', '+610412345678'],
        ];
    }

    /**
     * Testing the return for null phone numbers
     */
    public function testNullInput(): void
    {
        $result = MasterWidgetSettingsHelper::validate_phone_number(null);
        $this->assertEquals('', $result);
    }

    /**
     * Testing the return for empty phone numbers
     */
    public function testEmptyStringInput(): void
    {
        $result = MasterWidgetSettingsHelper::validate_phone_number('');
        $this->assertEquals('', $result);
    }

    /**
     * @dataProvider differentCountryCodesProvider
     *
     * Test if the number comes with internation code format already
     */
    public function testPreservesExistingCountryCodes(string $input, string $expected): void
    {
        $result = MasterWidgetSettingsHelper::validate_phone_number($input);
        $this->assertEquals($expected, $result);
    }

    public function differentCountryCodesProvider(): array
    {
        return [
            'UK number' => ['+44123456789', '+44123456789'],
            'US number' => ['+1123456789', '+1123456789'],
            'NZ number' => ['+64123456789', '+64123456789'],
        ];
    }
}

