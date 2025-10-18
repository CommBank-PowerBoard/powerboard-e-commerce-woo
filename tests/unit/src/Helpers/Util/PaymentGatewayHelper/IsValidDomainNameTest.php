<?php
declare( strict_types=1 );

namespace unit\src\Helpers\Util\PaymentGatewayHelper;

// Require mock files
require_once __DIR__ . '/../../../../mocks/WordPress/WPParse.php';

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
		$http_host = 'https://api.preproduction.powerboard.commbank.com.au:8080';

		$result = PaymentGatewayHelper::is_valid_domain_name( $http_host );
		$this->assertTrue( $result );
	}

	/**
	 * Test method handles domain without port
	 */
	public function test_method_handles_domain_without_port() {
		$http_host = 'api.preproduction.powerboard.commbank.com.au/';

		$result = PaymentGatewayHelper::is_valid_domain_name( $http_host );
		$this->assertTrue( $result );
	}

	/**
	 * Test method handles subdomain
	 */
	public function test_method_handles_subdomain() {
		$http_host = 'http://api.preproduction.powerboard.commbank.com.au/';

		$result = PaymentGatewayHelper::is_valid_domain_name( $http_host );
		$this->assertTrue( $result );
	}

	/**
	 * Test method handles subdomain with port
	 */
	public function test_method_handles_subdomain_with_port() {
		$http_host = 'https://www.api.preproduction.powerboard.commbank.com.au/:8080';

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

		$result = PaymentGatewayHelper::is_valid_domain_name( $http_host );
		$this->assertFalse( $result );
	}

	/**
	 * Test method handles international domain
	 */
	public function test_method_handles_multiple_sub_domains() {
		$http_host = 'https://api.staging.powerboard.commbank.com.au/v1/';

		$result = PaymentGatewayHelper::is_valid_domain_name( $http_host );
		$this->assertTrue( $result );
	}

	/**
	 * Test method handles domain with protocol
	 */
	public function test_method_handles_domain_with_protocol() {
		$http_host = 'https://api.staging.powerboard.commbank.com.au/v1/';
		$result = PaymentGatewayHelper::is_valid_domain_name( $http_host );
		$this->assertTrue( $result );
	}

	/**
	 * Test method handles domain with protocol and port
	 */
	public function test_method_handles_domain_with_protocol_and_port() {
		$http_host = 'https://api.staging.powerboard.commbank.com.au/v1/:8080';

		$result = PaymentGatewayHelper::is_valid_domain_name( $http_host );
		$this->assertTrue( $result );
	}

	/**
	 * Test method handles case sensitivity
	 */
	public function test_method_handles_case_sensitivity() {
		$http_host = 'HTTPS://API:STAGING:POWERBOARD:COMMBANK:COM:AU/V1/';

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
			$http_host = 'https://api.staging.powerboard.commbank.com.au/v1/:' . $port;
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
			$http_host = $subdomain . '.powerboard.commbank.com.au';
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
}
