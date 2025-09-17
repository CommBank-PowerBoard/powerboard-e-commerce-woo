<?php

namespace PowerBoard\Tests\Unit\Helpers\MasterWidgetSettingsHelper;

use PHPUnit\Framework\TestCase;
use PowerBoard\Helpers\AdminPanelHelpers\MasterWidgetSettingsHelper as MWHelper;

final class GatewayLabelsTest extends TestCase {

	public function testNormalizeSettingStringTrimsAndLimits(): void {
		$settings = [ 'title' => '   Hello World   ' ];

		$out = MWHelper::normalize_setting_string( $settings, 'title', 5 );
		$this->assertSame( 'Hello', $out );

		$out = MWHelper::normalize_setting_string( $settings, 'title', null );
		$this->assertSame( 'Hello World', $out );
	}

	public function testNormalizeSettingStringMissingKeyReturnsEmptyString(): void {
		$out = MWHelper::normalize_setting_string( [], 'missing', 100 );
		$this->assertSame( '', $out );
	}

	public function testGetGatewayTitleUsesNormalizationAndLimit(): void {
		$settings = [ 'title' => '   PowerBoard — Custom Title   ' ];

		$out = MWHelper::get_gateway_title( $settings, 50 );
		$this->assertSame( 'PowerBoard — Custom Title', $out );
	}

	public function testGetGatewayTitleFallsBackToDefaultIfEmpty(): void {
		$settings = [ 'title' => '   ' ];

		$out = MWHelper::get_gateway_title( $settings, 50, 'PowerBoard' );
		$this->assertSame( 'PowerBoard', $out );
	}

	public function testGetGatewayDescriptionUsesNormalizationAndLimit(): void {
		$settings = [ 'description' => '   Click \'Place Order\' to securely complete your payment.   ' ];

		$out = MWHelper::get_gateway_description( $settings, 500 );
		$this->assertSame( 'Click \'Place Order\' to securely complete your payment.', $out );
	}

	public function testGetGatewayDescriptionFallsBackToDefaultIfEmpty(): void {
		$settings = [ 'description' => '' ];

		$out = MWHelper::get_gateway_description( $settings, 500, 'Click \'Place Order\' to securely complete your payment.' );
		$this->assertSame( 'Click \'Place Order\' to securely complete your payment.', $out );
	}
}
