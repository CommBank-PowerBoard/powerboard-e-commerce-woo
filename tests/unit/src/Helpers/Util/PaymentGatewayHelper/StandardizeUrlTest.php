<?php

declare( strict_types = 1 );

namespace unit\src\Helpers\Util\PaymentGatewayHelper;

// Require mock files
require_once __DIR__ . '/../../../../mocks/WordPress/WPParse.php';

use PHPUnit\Framework\TestCase;
use PowerBoard\Enums\ConfigAPIEnum;
use PowerBoard\Helpers\Util\PaymentGatewayHelper;
use ReflectionClass;
use ReflectionMethod;

class StandardizeUrlTest extends TestCase {
	private ReflectionMethod $standardize_url_method;

	public function setUp(): void {
		parent::setUp();

		// Use reflection to access the private method
		$reflection = new ReflectionClass( PaymentGatewayHelper::class );
		$this->standardize_url_method = $reflection->getMethod( 'standardize_url' );
		$this->standardize_url_method->setAccessible( true );
	}

	/**
	 * Test HTTPS URL with simple domain
	 * Based on comment: //https://local.host.com
	 */
	public function test_standardize_url_with_https_simple_domain() {
		$input    = 'https://local.host.com';
		$expected = 'local.host.com'; // resolve_subdomain_url returns last 2 parts for non-country-code TLD
		$result   = $this->standardize_url_method->invoke( null, $input );

		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test HTTP URL with simple domain
	 * Based on comment: //http://local.host.com
	 */
	public function test_standardize_url_with_http_simple_domain() {
		$input    = 'http://www.local.host.com';
		$expected = 'www.local.host.com'; // resolve_subdomain_url returns last 2 parts for non-country-code TLD
		$result   = $this->standardize_url_method->invoke( null, $input );

		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test HTTPS URL with complex PowerBoard domain and path
	 * Based on comment: //https://api.staging.powerboard.commbank.com.au/v1/
	 */
	public function test_standardize_url_with_https_powerboard_domain_with_path() {
		$input    = 'https://api.staging.powerboard.commbank.com.au/v1/';
		$expected = 'api.staging.powerboard.commbank.com.au'; // resolve_subdomain_url returns last 3 parts for country-code TLD 'au'

		$result   = $this->standardize_url_method->invoke( null, $input );

		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test HTTP URL with complex PowerBoard domain and path
	 * Based on comment: //http://api.staging.powerboard.commbank.com.au/v1/
	 */
	public function test_standardize_url_with_http_powerboard_domain_with_path() {
		$input    = 'http://api.staging.powerboard.commbank.com.au/v1/';
		$expected = 'api.staging.powerboard.commbank.com.au'; // resolve_subdomain_url returns last 3 parts for country-code TLD 'au'

		$result   = $this->standardize_url_method->invoke( null, $input );

		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test HTTPS URL with www subdomain and complex PowerBoard domain
	 * Based on comment: //https://www.api.staging.powerboard.commbank.com.au/v1/
	 */
	public function test_standardize_url_with_https_www_powerboard_domain_with_path() {
		$input    = 'https://www.api.staging.powerboard.commbank.com.au/v1/';
		$expected = 'www.api.staging.powerboard.commbank.com.au'; // resolve_subdomain_url returns last 3 parts for country-code TLD 'au'

		$result   = $this->standardize_url_method->invoke( null, $input );

		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test HTTP URL with www subdomain and complex PowerBoard domain
	 * Based on comment: //http://www.api.staging.powerboard.commbank.com.au/v1/
	 */
	public function test_standardize_url_with_http_www_powerboard_domain_with_path() {
		$input    = 'http://www.api.staging.powerboard.commbank.com.au/v1/';
		$expected = 'www.api.staging.powerboard.commbank.com.au'; // resolve_subdomain_url returns last 3 parts for country-code TLD 'au'

		$result   = $this->standardize_url_method->invoke( null, $input );

		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test URL without protocol (should work since method adds http://)
	 */
	public function test_standardize_url_without_protocol() {
		$input    = 'example.com';
		$expected = 'example.com'; // Simple domain, returns as-is

		$result   = $this->standardize_url_method->invoke( null, $input );

		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test URL with port number
	 */
	public function test_standardize_url_with_port() {
		$input    = 'https://api.example.com:8080/path';
		$expected = 'api.example.com'; // resolve_subdomain_url returns last 2 parts

		$result   = $this->standardize_url_method->invoke( null, $input );

		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test empty string input
	 */
	public function test_standardize_url_with_empty_string() {
		$input  = '';
		$result = $this->standardize_url_method->invoke( null, $input );

		$this->assertNull( $result );
	}

	/**
	 * Test null input
	 */
	public function test_standardize_url_with_null() {
		$input  = null;

		$result = $this->standardize_url_method->invoke( null, $input );

		$this->assertNull( $result );
	}

	/**
	 * Test malformed URL
	 */
	public function test_standardize_url_with_malformed_url() {
		$input    = 'not-a-valid-url';
		$expected = 'not-a-valid-url'; // Should still process through resolve_subdomain_url

		$result   = $this->standardize_url_method->invoke( null, $input );

		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test domain with multiple protocols (edge case)
	 */
	public function test_standardize_url_with_multiple_protocols() {
		$input    = 'https://http://example.com';
		$expected = 'example.com'; // Both protocols should be removed

		$result = $this->standardize_url_method->invoke( null, $input );

		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test UK domain (another country code TLD)
	 */
	public function test_standardize_url_with_uk_tld() {
		$input    = 'https://api.staging.service.co.uk';
		$expected = 'api.staging.service.co.uk'; // Should return last 3 parts for UK TLD

		$result = $this->standardize_url_method->invoke( null, $input );

		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test simple two-part domain
	 */
	public function test_standardize_url_with_simple_two_part_domain() {
		$input    = 'https://google.com';
		$expected = 'google.com'; // Should return both parts

		$result = $this->standardize_url_method->invoke( null, $input );

		$this->assertEquals( $expected, $result );
	}
}
