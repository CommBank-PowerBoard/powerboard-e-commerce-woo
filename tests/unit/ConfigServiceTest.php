<?php
declare( strict_types=1 );

namespace unit;

use PHPUnit\Framework\TestCase;
use PowerBoard\API\ConfigService;
use PowerBoard\Enums\ConfigAPIEnum;

class ConfigServiceTest extends TestCase {
	protected ConfigService $config_service;
	protected string $endpoint = 'checkouts/intent';

	protected function setUp(): void {
		parent::setUp();
		$this->config_service = new ConfigService();
	}

	public function test_build_api_url_production_with_endpoint() {
		$this->config_service->init( ConfigAPIEnum::PRODUCTION_ENVIRONMENT_VALUE, 'access_token' );
		$actual   = $this->config_service->build_api_url( $this->endpoint );
		$expected = ConfigAPIEnum::PRODUCTION_API_URL . $this->endpoint;

		$this->assertEquals( $expected, $actual, 'Should return valid endpoint with a the correct production url.' );
	}

	public function test_build_api_url_staging_with_endpoint() {
		$this->config_service->init( ConfigAPIEnum::STAGING_ENVIRONMENT_VALUE, 'access_token' );
		$actual   = $this->config_service->build_api_url( $this->endpoint );
		$expected = ConfigAPIEnum::STAGING_API_URL . $this->endpoint;

		$this->assertEquals( $expected, $actual, 'Should return valid endpoint with a the correct staging url.' );
	}

	public function test_build_api_url_sandbox_with_endpoint() {
		$this->config_service->init( ConfigAPIEnum::SANDBOX_ENVIRONMENT_VALUE, 'access_token' );
		$actual   = $this->config_service->build_api_url( $this->endpoint );
		$expected = ConfigAPIEnum::SANDBOX_API_URL . $this->endpoint;

		$this->assertEquals( $expected, $actual, 'Should return valid endpoint with a the correct sandbox url.' );
	}

	public function test_build_api_url_production() {
		$this->config_service->init( ConfigAPIEnum::PRODUCTION_ENVIRONMENT_VALUE, 'access_token' );
		$actual   = $this->config_service->build_api_url();
		$expected = ConfigAPIEnum::PRODUCTION_API_URL;

		$this->assertEquals( $expected, $actual, 'Should return the correct production url.' );
	}

	public function test_build_api_url_staging() {
		$this->config_service->init( ConfigAPIEnum::STAGING_ENVIRONMENT_VALUE, 'access_token' );
		$actual   = $this->config_service->build_api_url();
		$expected = ConfigAPIEnum::STAGING_API_URL;

		$this->assertEquals( $expected, $actual, 'Should return the correct staging url.' );
	}

	public function test_build_api_url_sandbox() {
		$this->config_service->init( ConfigAPIEnum::SANDBOX_ENVIRONMENT_VALUE, 'access_token' );
		$actual   = $this->config_service->build_api_url();
		$expected = ConfigAPIEnum::SANDBOX_API_URL;

		$this->assertEquals( $expected, $actual, 'Should return the correct sandbox url.' );
	}
}
