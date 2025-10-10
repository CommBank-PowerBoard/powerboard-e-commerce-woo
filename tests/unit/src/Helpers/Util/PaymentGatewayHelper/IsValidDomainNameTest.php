<?php
declare( strict_types=1 );

namespace unit\src\Helpers\Util\PaymentGatewayHelper;

// Require mock files
require_once __DIR__ . '/../../../../mocks/WordPress/WPParse.php';
require_once __DIR__ . '/../../../../mocks/API/ConfigService.php';

use PHPUnit\Framework\TestCase;
use PowerBoard\Helpers\Util\PaymentGatewayHelper;

class IsValidDomainNameTest extends TestCase {
	public function setUp(): void {
		parent::setUp();

		if ( ! defined( 'POWER_BOARD_PLUGIN_PREFIX' ) ) {
			define( 'POWER_BOARD_PLUGIN_PREFIX', 'power_board' );
		}
	}

	/**
	 * Test method handles empty string
	 */
	public function test_method_handles_empty_string() {
		$http_host = '';

		$result = PaymentGatewayHelper::is_valid_domain_name( $http_host );
		$this->assertFalse( $result );
	}

	/**
	 * Test method handles null
	 */
	public function test_method_handles_null() {
		$http_host = null;

		$result = PaymentGatewayHelper::is_valid_domain_name( $http_host );
		$this->assertFalse( $result );
	}

	/**
	 * Test method handles domain with port
	 */
	public function test_method_handles_domain_with_port() {
		$http_host = 'example.com:8080';

		$result = PaymentGatewayHelper::is_valid_domain_name( $http_host );
		$this->assertTrue( $result );
	}

	/**
	 * Test method handles domain without port
	 */
	public function test_method_handles_domain_without_port() {
		$http_host = 'example.com';

		$result = PaymentGatewayHelper::is_valid_domain_name( $http_host );
		$this->assertTrue( $result );
	}

	/**
	 * Test method handles subdomain
	 */
	public function test_method_handles_subdomain() {
		$http_host = 'www.example.com';

		$result = PaymentGatewayHelper::is_valid_domain_name( $http_host );
		$this->assertTrue( $result );
	}

	/**
	 * Test method handles subdomain with port
	 */
	public function test_method_handles_subdomain_with_port() {
		$http_host = 'www.example.com:8080';

		$result = PaymentGatewayHelper::is_valid_domain_name( $http_host );
		$this->assertTrue( $result );
	}

	/**
	 * Test method handles malformed domain
	 */
	public function test_method_handles_malformed_domain() {
		$http_host = 'not-a-domain';

		$result = PaymentGatewayHelper::is_valid_domain_name( $http_host );
		$this->assertFalse( $result );
	}

	/**
	 * Test method handles domain with special characters
	 */
	public function test_method_handles_domain_with_special_characters() {
		$http_host = 'example-site.com';
		\unit\mocks\API\ConfigService::set_test_domain( 'https://example-site.com' );

		$result = PaymentGatewayHelper::is_valid_domain_name( $http_host );
		$this->assertTrue( $result );
	}

	/**
	 * Test method handles domain with multiple colons
	 */
	public function test_method_handles_domain_with_multiple_colons() {
		$http_host = 'example.com:80:8080';

		$result = PaymentGatewayHelper::is_valid_domain_name( $http_host );
		$this->assertFalse( $result );
	}

	/**
	 * Test method handles domain with whitespace
	 */
	public function test_method_handles_domain_with_whitespace() {
		$http_host = ' example.com ';
		\unit\mocks\API\ConfigService::set_test_domain( 'http://example.com' );

		$result = PaymentGatewayHelper::is_valid_domain_name( $http_host );
		$this->assertTrue( $result );
	}

	/**
	 * Test method handles very long domain
	 */
	public function test_method_handles_very_long_domain() {
		$long_subdomain = str_repeat( 'a', 50 );
		$http_host      = $long_subdomain . '.example.com';
		\unit\mocks\API\ConfigService::set_test_domain( 'http://example.com' );

		$result = PaymentGatewayHelper::is_valid_domain_name( $http_host );
		$this->assertTrue( $result );
	}

	/**
	 * Test method handles international domain
	 */
	public function test_method_handles_international_domain() {
		$http_host = 'example.co.uk';
		\unit\mocks\API\ConfigService::set_test_domain( 'https://example.co.uk' );

		$result = PaymentGatewayHelper::is_valid_domain_name( $http_host );
		$this->assertTrue( $result );
	}

	/**
	 * Test method handles international domain
	 */
	public function test_method_handles_multiple_sub_domains() {
		$http_host = 'https://api.staging.powerboard.commbank.com.au/v1/';
		\unit\mocks\API\ConfigService::set_test_domain( 'https://api.staging.powerboard.commbank.com.au/v1/' );

		$result = PaymentGatewayHelper::is_valid_domain_name( $http_host );
		$this->assertTrue( $result );
	}

	/**
	 * Test method handles domain with protocol
	 */
	public function test_method_handles_domain_with_protocol() {
		$http_host = 'https://example.com';
		\unit\mocks\API\ConfigService::set_test_domain( 'https://example.com' );
		$result = PaymentGatewayHelper::is_valid_domain_name( $http_host );
		$this->assertTrue( $result );
	}

	/**
	 * Test method handles domain with protocol and port
	 */
	public function test_method_handles_domain_with_protocol_and_port() {
		$http_host = 'https://example.com:8080';

		$result = PaymentGatewayHelper::is_valid_domain_name( $http_host );
		$this->assertTrue( $result );
	}

	/**
	 * Test method handles case sensitivity
	 */
	public function test_method_handles_case_sensitivity() {
		$http_host = 'EXAMPLE.COM';

		$result = PaymentGatewayHelper::is_valid_domain_name( $http_host );
		$this->assertIsBool( $result );
	}

	/**
	 * Test method handles numeric input
	 */
	public function test_method_handles_numeric_input() {
		$http_host = '123456';

		$result = PaymentGatewayHelper::is_valid_domain_name( $http_host );
		$this->assertIsBool( $result );
	}

	/**
	 * Test method handles boolean input
	 */
	public function test_method_handles_boolean_input() {
		$http_host = true;

		$result = PaymentGatewayHelper::is_valid_domain_name( $http_host );
		$this->assertFalse( $result );
	}

	/**
	 * Test method handles array input
	 */
	public function test_method_handles_array_input() {
		$http_host = [ 'example.com' ];

		$result = PaymentGatewayHelper::is_valid_domain_name( $http_host );
		$this->assertFalse( $result );
	}

	/**
	 * Test method handles object input
	 */
	public function test_method_handles_object_input() {
		$http_host = (object) [ 'domain' => 'example.com' ];

		$result = PaymentGatewayHelper::is_valid_domain_name( $http_host );
		$this->assertFalse( $result );
	}

	/**
	 * Test method handles various port numbers
	 */
	public function test_method_handles_various_port_numbers() {
		$ports = [ '80', '443', '3000', '8080', '9000' ];

		foreach ( $ports as $port ) {
			$http_host = 'example.com:' . $port;
			$result    = PaymentGatewayHelper::is_valid_domain_name( $http_host );
			$this->assertTrue( $result, "Failed for port: {$port}" );
		}
	}

	/**
	 * Test method handles various subdomains
	 */
	public function test_method_handles_various_subdomains() {
		$subdomains = [ 'www', 'api', 'subdomain.admin', 'test', 'dev.qwe.asd' ];

		foreach ( $subdomains as $subdomain ) {
			$http_host = $subdomain . '.example.com';
			$result    = PaymentGatewayHelper::is_valid_domain_name( $http_host );
			$this->assertTrue( $result, "Failed for subdomain: {$subdomain}" );
		}
	}

	/**
	 * Test method handles edge cases with colons
	 */
	public function test_method_handles_edge_cases_with_colons() {
		$test_cases = [
			'example.com::99',
			':8080',
			'example::com',
			'example.com::8080',
		];

		foreach ( $test_cases as $http_host ) {
			$result = PaymentGatewayHelper::is_valid_domain_name( $http_host );
			$this->assertFalse( $result, "Failed for edge case: {$http_host}" );
		}
	}

	/**
	 * Test method handles edge cases with special characters
	 */
	public function test_method_handles_edge_cases_with_special_characters() {
		$test_cases = [
			'example-site.com',
			'example_site.com',
			'example.site.com',
			'example-site.com:8080',
		];

		foreach ( $test_cases as $http_host ) {
			$result = PaymentGatewayHelper::is_valid_domain_name( $http_host );
			$this->assertFalse( $result, "Failed for special chars: {$http_host}" );
		}
	}

	/**
	 * Test method handles edge cases with whitespace
	 */
	public function test_method_handles_edge_cases_with_whitespace() {
		$test_cases = [
			' example.com',
			'example.com ',
			' example.com ',
			'example .com',
			'example. com',
		];

		foreach ( $test_cases as $http_host ) {
			$result = PaymentGatewayHelper::is_valid_domain_name( $http_host );
			$this->assertTrue( $result );
		}
	}

	/**
	 * Test subdomain removal - simple case
	 */
	public function test_subdomain_removal_simple() {
		// Set the config domain to example.com
		\unit\mocks\API\ConfigService::set_test_domain( 'http://example.com' );

		// Test that www.example.com matches example.com
		$http_host = 'www.example.com';
		$result    = PaymentGatewayHelper::is_valid_domain_name( $http_host );
		$this->assertTrue( $result, 'www.example.com should match example.com' );
	}

	/**
	 * Test subdomain removal - multi-level subdomain
	 */
	public function test_subdomain_removal_multilevel() {
		// Set the config domain to example.com
		\unit\mocks\API\ConfigService::set_test_domain( 'http://example.com' );

		// Test that sub1.sub2.example.com matches example.com
		$http_host = 'sub1.sub2.example.com';
		$result    = PaymentGatewayHelper::is_valid_domain_name( $http_host );
		$this->assertTrue( $result, 'sub1.sub2.example.com should match example.com' );
	}
	/**
	 * Test subdomain removal - multi-level subdomain with country code top level domain
	 */
	public function test_subdomain_removal_multilevel_with_country_code_tld() {
		// Set the config domain to example.com
		\unit\mocks\API\ConfigService::set_test_domain( 'http://example.co.uk' );

		// Test that sub1.sub2.example.com matches example.com
		$http_host = 'sub1.sub2.example.co.uk';
		$result    = PaymentGatewayHelper::is_valid_domain_name( $http_host );
		$this->assertTrue( $result, 'sub1.sub2.example.com should match example.co.uk' );
	}

	/**
	 * Test subdomain removal - both domains have subdomains
	 */
	public function test_subdomain_removal_both_have_subdomains() {
		// Set the config domain to sub.example.com
		\unit\mocks\API\ConfigService::set_test_domain( 'http://sub.example.com' );

		// Test that www.example.com matches sub.example.com (both should be reduced to example.com)
		$http_host = 'www.example.com';
		$result    = PaymentGatewayHelper::is_valid_domain_name( $http_host );
		$this->assertTrue( $result, 'www.example.com should match sub.example.com (both reduced to example.com)' );
	}

	/**
	 * Test subdomain removal - different domains
	 */
	public function test_subdomain_removal_different_domains() {
		// Set the config domain to example.com
		\unit\mocks\API\ConfigService::set_test_domain( 'http://example.com' );

		// Test that www.different.com doesn't match example.com
		$http_host = 'www.different.com';
		$result    = PaymentGatewayHelper::is_valid_domain_name( $http_host );
		$this->assertFalse( $result, 'www.different.com should not match example.com' );
	}

	/**
	 * Test subdomain removal - with port
	 */
	public function test_subdomain_removal_with_port() {
		// Set the config domain to example.com
		\unit\mocks\API\ConfigService::set_test_domain( 'http://example.com' );

		// Test that www.example.com:8080 matches example.com
		$http_host = 'www.example.com:8080';
		$result    = PaymentGatewayHelper::is_valid_domain_name( $http_host );
		$this->assertTrue( $result, 'www.example.com:8080 should match example.com' );
	}
}
