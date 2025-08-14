<?php

namespace PowerBoard\Tests\Unit\Helpers\MasterWidgetSettingsHelper;

use PHPUnit\Framework\TestCase;
use PowerBoard\Helpers\MasterWidgetSettingsHelper as MWHelper;

final class GatewayLabelsTest extends TestCase
{
	public function testNormalizeSettingStringTrimsAndLimits(): void
	{
		$settings = ['title' => '   Hello World   '];

		$out = MWHelper::normalize_setting_string($settings, 'title', 5);
		$this->assertSame('Hello', $out);

		$out = MWHelper::normalize_setting_string($settings, 'title', null);
		$this->assertSame('Hello World', $out);
	}

	public function testNormalizeSettingStringMissingKeyReturnsEmptyString(): void
	{
		$out = MWHelper::normalize_setting_string([], 'missing', 100);
		$this->assertSame('', $out);
	}

	public function testGetGatewayTitleUsesNormalizationAndLimit(): void
	{
		$settings = ['title' => '   PowerBoard — Custom Title   '];

		$out = MWHelper::get_gateway_title($settings, 9);
		$this->assertSame('PowerBoard', $out);
	}

	public function testGetGatewayTitleFallsBackToDefaultIfEmpty(): void
	{
		$settings = ['title' => '   '];

		$out = MWHelper::get_gateway_title($settings, 50, 'PowerBoard');
		$this->assertSame('PowerBoard', $out);
	}

	public function testGetGatewayDescriptionUsesNormalizationAndLimit(): void
	{
		$settings = ['description' => '   Pay securely via PowerBoard.   '];

		$out = MWHelper::get_gateway_description($settings, 7);
		$this->assertSame('Pay sec', $out);
	}

	public function testGetGatewayDescriptionFallsBackToDefaultIfEmpty(): void
	{
		$settings = ['description' => ''];

		$out = MWHelper::get_gateway_description($settings, 500, 'Pay securely via PowerBoard.');
		$this->assertSame('Pay securely via PowerBoard.', $out);
	}
}
