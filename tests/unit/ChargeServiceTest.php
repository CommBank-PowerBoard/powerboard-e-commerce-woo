<?php
declare( strict_types=1 );

namespace unit;

use PHPUnit\Framework\TestCase;
use PowerBoard\API\ChargeService;

class ChargeServiceTest extends TestCase {
	protected ChargeService $charge_service;

	protected function setUp(): void {
		parent::setUp();
		$this->charge_service = new ChargeService();
	}

	public function test_build_endpoint_refunds() {
		$charge_id = 'chargeidtest';
		$params    = [
			'charge_id' => $charge_id,
		];
		$expected  = ChargeService::ENDPOINT . '/' . $charge_id . '/' . ChargeService::REFUNDS_ENDPOINT;
		$actual    = $this->charge_service->refunds( $params )->build_endpoint();

		$this->assertEquals( $expected, $actual, 'Should return valid refund endpoint.' );
	}

	public function test_build_endpoint_checkout_intent() {
		$params   = [
			'amount'    => 54.99,
			'reference' => '1234',
		];
		$expected = ChargeService::CREATE_INTENT_ENDPOINT;
		$actual   = $this->charge_service->create_checkout_intent( $params )->build_endpoint();

		$this->assertEquals( $expected, $actual, 'Should return valid create intent endpoint.' );
	}

	public function test_build_endpoint_get_configuration_templates() {
		$version  = '1';
		$type     = 'configuration';
		$expected = ChargeService::GET_TEMPLATES_ENDPOINT . '?type=' . $type . '&version=' . $version;
		$actual   = $this->charge_service->get_configuration_templates_ids( $version )->build_endpoint();

		$this->assertEquals( $expected, $actual, 'Should return valid get configuration templates endpoint.' );
	}

	public function test_build_endpoint_get_customisation_templates() {
		$version  = '1';
		$type     = 'customisation';
		$expected = ChargeService::GET_TEMPLATES_ENDPOINT . '?type=' . $type . '&version=' . $version;
		$actual   = $this->charge_service->get_customisation_templates_ids( $version )->build_endpoint();

		$this->assertEquals( $expected, $actual, 'Should return valid get customisation templates endpoint.' );
	}

	/**
	 * Test charge_id validation through refunds method
	 */
	public function test_charge_id_validation_valid() {
		$charge_service  = new ChargeService();
		$valid_charge_id = 'valid_charge_id_123';

		$params   = [ 'charge_id' => $valid_charge_id ];
		$expected = ChargeService::ENDPOINT . '/' . $valid_charge_id . '/' . ChargeService::REFUNDS_ENDPOINT;

		// Should not throw exception with valid charge_id
		$result = $charge_service->refunds( $params )->build_endpoint();
		$this->assertEquals( $expected, $result, 'Valid charge_id should be accepted' );
	}

	/**
	 * Test charge_id validation with empty charge_id
	 */
	public function test_charge_id_validation_empty() {
		$charge_service = new ChargeService();

		$params = [ 'charge_id' => '' ];

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Charge ID cannot be empty' );

		$charge_service->refunds( $params )->build_endpoint();
	}

	/**
	 * Test charge_id validation with invalid format
	 */
	public function test_charge_id_validation_invalid_format() {
		$charge_service = new ChargeService();

		// Test with charge_id that's too short
		$params = [ 'charge_id' => 'short' ];

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid charge ID format' );

		$charge_service->refunds( $params )->build_endpoint();
	}

	/**
	 * Test charge_id validation with path traversal attempt
	 */
	public function test_charge_id_validation_path_traversal() {
		$charge_service = new ChargeService();

		// Test with path traversal attempt
		$params = [ 'charge_id' => '../../../etc/passwd' ];

		$this->expectException( \InvalidArgumentException::class );
		// The format validation catches this first (which is good security design)
		$this->expectExceptionMessage( 'Invalid charge ID format' );

		$charge_service->refunds( $params )->build_endpoint();
	}

	/**
	 * Test charge_id validation with slash in ID
	 */
	public function test_charge_id_validation_with_slash() {
		$charge_service = new ChargeService();

		// Test with slash in charge_id (URL injection attempt)
		$params = [ 'charge_id' => 'charge/injection/attempt' ];

		$this->expectException( \InvalidArgumentException::class );
		// The format validation catches this first (which is good security design)
		$this->expectExceptionMessage( 'Invalid charge ID format' );

		$charge_service->refunds( $params )->build_endpoint();
	}

	/**
	 * Test charge_id validation through get_charge_by_id method
	 */
	public function test_get_charge_by_id_validation() {
		$charge_service = new ChargeService();

		// Should throw exception with invalid charge_id
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid charge ID format' );

		$charge_service->get_charge_by_id( 'bad!' );
	}


	/**
	 * Test template type validation with valid types
	 */
	public function test_template_type_validation_valid() {
		$charge_service = new ChargeService();

		// Test 'configuration' type
		$version = '1';
		$result  = $charge_service->get_configuration_templates_ids( $version )->build_endpoint();
		$this->assertStringContainsString( 'type=configuration', $result, 'Configuration type should be accepted' );

		// Test 'customisation' type
		$result = $charge_service->get_customisation_templates_ids( $version )->build_endpoint();
		$this->assertStringContainsString( 'type=customisation', $result, 'Customisation type should be accepted' );
	}

	/**
	 * Test version validation with invalid format
	 */
	public function test_version_validation_invalid_format() {
		$charge_service = new ChargeService();

		// Test with invalid version format
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid version format' );

		$charge_service->get_configuration_templates_ids( 'invalid.version.format!' );
	}

	/**
	 * Test version validation with excessively long version
	 */
	public function test_version_validation_too_long() {
		$charge_service = new ChargeService();

		// Test with version string that's too long
		$long_version = str_repeat( '1.', 15 ) . '1'; // Creates a very long version string

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Version string too long' );

		$charge_service->get_configuration_templates_ids( $long_version );
	}

	/**
	 * Test version validation with valid formats
	 */
	public function test_version_validation_valid_formats() {
		$charge_service = new ChargeService();

		// Test various valid version formats
		$valid_versions = [ '1', '1.0', '2.1.3', '1.0.0-beta', '3.2.1-alpha' ];

		foreach ( $valid_versions as $version ) {
			$result = $charge_service->get_configuration_templates_ids( $version )->build_endpoint();
			$this->assertStringContainsString(
				'version=' . rawurlencode( $version ),
				$result,
				"Version '{$version}' should be accepted"
			);
		}
	}

	/**
	 * Test version validation with empty version (should be allowed)
	 */
	public function test_version_validation_empty_allowed() {
		$charge_service = new ChargeService();

		// Use reflection to test with empty version
		$reflection = new \ReflectionClass( $charge_service );
		$property   = $reflection->getProperty( 'parameters' );
		$property->setAccessible( true );
		$property->setValue(
			$charge_service,
			[
				'type'    => 'configuration',
				'version' => '',
			]
			);

		$action_property = $reflection->getProperty( 'action' );
		$action_property->setAccessible( true );
		$action_property->setValue( $charge_service, 'templates' );

		$method = $reflection->getMethod( 'build_endpoint' );
		$method->setAccessible( true );

		$result = $method->invoke( $charge_service );

		// Empty version should not add version parameter to URL
		$this->assertStringNotContainsString( 'version=', $result, 'Empty version should not add version parameter' );
		$this->assertStringContainsString( 'type=configuration', $result, 'Type should still be present' );
	}

	/**
	 * Test charge_id validation with boundary cases
	 */
	public function test_charge_id_validation_boundary_cases() {
		$charge_service = new ChargeService();

		// Test minimum length (8 characters)
		$min_charge_id = 'charge12'; // 8 characters
		$params        = [ 'charge_id' => $min_charge_id ];
		$result        = $charge_service->refunds( $params )->build_endpoint();
		$this->assertStringContainsString( $min_charge_id, $result, 'Minimum length charge_id should be accepted' );

		// Test maximum length (50 characters)
		$max_charge_id = str_repeat( 'a', 50 ); // 50 characters
		$params        = [ 'charge_id' => $max_charge_id ];
		$result        = $charge_service->refunds( $params )->build_endpoint();
		$this->assertStringContainsString( $max_charge_id, $result, 'Maximum length charge_id should be accepted' );

		// Test too long (51 characters)
		$too_long_charge_id = str_repeat( 'a', 51 );
		$params             = [ 'charge_id' => $too_long_charge_id ];

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid charge ID format' );
		$charge_service->refunds( $params )->build_endpoint();
	}

	/**
	 * Test that all validation methods properly escape output
	 */
	public function test_validation_error_message_escaping() {
		$charge_service = new ChargeService();

		// Test that error messages are properly escaped (no XSS)
		try {
			$charge_service->get_configuration_templates_ids( '<script>alert("xss")</script>' );
			$this->fail( 'Should have thrown exception' );
		} catch ( \InvalidArgumentException $e ) {
			// Error message should not contain unescaped script tags
			$this->assertStringNotContainsString( '<script>', $e->getMessage(), 'Error messages should be escaped' );
			$this->assertStringContainsString( 'Invalid version format', $e->getMessage(), 'Should contain expected error message' );
		}
	}
}
