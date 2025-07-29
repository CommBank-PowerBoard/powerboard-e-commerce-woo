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
        $this->assertEquals('+61298765432', $result);
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
            'Number with leading zero' => ['0412345678', '+61412345678'],
            'Local number with spaces' => ['0412 345 678', '+61412345678'],
            'Empty number' => ['', ''],
            'Only zeros' => ['000000', '+61'],
            'Number with leading zeros' => ['00412345678', '+61412345678'],
            'Maximum length number' => ['1234567890123456', '+611234567890123'],
            'Number with hyphens' => ['0412-345-678', '+61412345678'],
            'Number with parentheses' => ['(0412) 345678', '+61412345678'],
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
            'Letters in number' => ['04abc12345678', '+61412345678'],
            'Special characters' => ['04!@#$%^&*()12345678', '+61412345678'],
            'Mixed invalid characters' => ['04ab!@#cd12345678', '+61412345678'],
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

