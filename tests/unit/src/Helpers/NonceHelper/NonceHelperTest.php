<?php
declare(strict_types=1);

namespace unit\src\Helpers\NonceHelper;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use PowerBoard\Helpers\Util\NonceHelper;

/**
 * Unit tests for NonceHelper::is_valid_nonce method
 *
 * @coversDefaultClass PowerBoard\Helpers\NonceHelper
 */
class NonceHelperTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// ========================================
	// VALID NONCE SCENARIOS
	// ========================================

	/**
	 * Test that valid nonces are accepted
	 *
	 * @test
	 * @covers ::is_valid_nonce
	 * @dataProvider validNonceDataProvider
	 */
	public function it_validates_nonce_successfully( $nonce_data ) {
		// Arrange
		$nonce            = $nonce_data['nonce'];
		$action           = $nonce_data['action'];
		$wp_verify_result = $nonce_data['wp_verify_result'];

		Functions\expect( 'wp_unslash' )
			->once()
			->with( $nonce )
			->andReturn( $nonce );

		Functions\expect( 'sanitize_text_field' )
			->once()
			->with( $nonce )
			->andReturn( $nonce );

		Functions\expect( 'wp_verify_nonce' )
			->once()
			->with( $nonce, $action )
			->andReturn( $wp_verify_result );

		// Act
		$result = NonceHelper::is_valid_nonce( $nonce, $action );

		// Assert
		$this->assertTrue( $result, 'Valid scenario: ' . $nonce_data['description'] );
	}

	/**
	 * Data provider for valid nonce scenarios
	 */
	public static function validNonceDataProvider(): array {
		return [
			'standard_valid_nonce' => [
				[
					'nonce'            => 'abc123def',
					'action'           => 'test_action',
					'wp_verify_result' => 1,
					'description'      => 'Standard valid nonce',
				],
			],
			'time_period_2_nonce'  => [
				[
					'nonce'            => 'xyz789uvw',
					'action'           => 'another_action',
					'wp_verify_result' => 2,
					'description'      => 'Valid nonce in time period 2',
				],
			],
		];
	}

	// ========================================
	// INVALID NONCE SCENARIOS
	// ========================================

	/**
	 * Test that invalid nonces are rejected
	 *
	 * @test
	 * @covers ::is_valid_nonce
	 * @dataProvider invalidNonceDataProvider
	 */
	public function it_rejects_invalid_nonces( $scenario ) {
		// Arrange
		$nonce           = $scenario['nonce'];
		$action          = $scenario['action'];
		$sanitized_nonce = $scenario['sanitized_nonce'] ?? $nonce;

		// Only set up wp_unslash and sanitize_text_field if nonce is set
		if ( isset( $nonce ) ) {
			Functions\expect( 'wp_unslash' )
				->once()
				->with( $nonce )
				->andReturn( $nonce );

			Functions\expect( 'sanitize_text_field' )
				->once()
				->with( $nonce )
				->andReturn( $sanitized_nonce );

			$wp_verify_input = $sanitized_nonce;
		} else {
			$wp_verify_input = null;
		}

		Functions\expect( 'wp_verify_nonce' )
			->once()
			->with( $wp_verify_input, $action )
			->andReturn( false );

		Functions\expect( '__' )
			->once()
			->with( 'Error: Security check', 'power-board' )
			->andReturn( 'Error: Security check' );

		Functions\expect( 'wp_send_json_error' )
			->once()
			->with( [ 'message' => 'Error: Security check' ] );

		// Act
		$result = NonceHelper::is_valid_nonce( $nonce, $action );

		// Assert
		$this->assertFalse( $result, 'Should reject scenario: ' . $scenario['description'] );
	}

	/**
	 * Data provider for invalid nonce scenarios
	 */
	public static function invalidNonceDataProvider(): array {
		return [
			'invalid_nonce_string'   => [
				[
					'nonce'       => 'invalid_nonce',
					'action'      => 'test_action',
					'description' => 'Invalid nonce string should be rejected',
				],
			],
			'null_nonce'             => [
				[
					'nonce'       => null,
					'action'      => 'test_action',
					'description' => 'Null nonce should be handled gracefully',
				],
			],
			'empty_string_nonce'     => [
				[
					'nonce'       => '',
					'action'      => 'test_action',
					'description' => 'Empty string nonce should be rejected',
				],
			],
			'wp_verify_returns_zero' => [
				[
					'nonce'       => 'some_nonce',
					'action'      => 'test_action',
					'description' => 'WordPress returns 0 for invalid nonces',
				],
			],
		];
	}

	// ========================================
	// EDGE CASE SCENARIOS
	// ========================================

	/**
	 * Test edge cases for nonce validation
	 *
	 * @test
	 * @covers ::is_valid_nonce
	 * @dataProvider edgeCaseDataProvider
	 */
	public function it_handles_edge_cases( $scenario ) {
		// Arrange
		$nonce            = $scenario['nonce'];
		$action           = $scenario['action'];
		$wp_verify_result = $scenario['wp_verify_result'];

		// Handle special input/output expectations for edge cases
		if ( isset( $nonce ) ) {
			$wp_unslash_input  = $scenario['expected_wp_unslash_input'] ?? $nonce;
			$wp_unslash_output = $scenario['expected_wp_unslash_output'] ?? $nonce;
			$sanitize_input    = $scenario['expected_sanitize_input'] ?? $wp_unslash_output;
			$sanitize_output   = $scenario['expected_sanitize_output'] ?? $sanitize_input;

			Functions\expect( 'wp_unslash' )
				->once()
				->with( $wp_unslash_input )
				->andReturn( $wp_unslash_output );

			Functions\expect( 'sanitize_text_field' )
				->once()
				->with( $sanitize_input )
				->andReturn( $sanitize_output );

			$wp_verify_input = $sanitize_output;
		} else {
			$wp_verify_input = null;
		}

		Functions\expect( 'wp_verify_nonce' )
			->once()
			->with( $wp_verify_input, $action )
			->andReturn( $wp_verify_result );

		// Set up error expectations for failed scenarios
		if ( !$wp_verify_result ) {
			Functions\expect( '__' )
				->once()
				->with( 'Error: Security check', 'power-board' )
				->andReturn( 'Error: Security check' );

			Functions\expect( 'wp_send_json_error' )
				->once()
				->with( [ 'message' => 'Error: Security check' ] );
		}

		// Act
		$result = NonceHelper::is_valid_nonce( $nonce, $action );

		// Assert
		$expected_result = (bool) $wp_verify_result;
		$this->assertEquals( $expected_result, $result, 'Failed for edge case: ' . $scenario['description'] );
	}

	/**
	 * Data provider for edge cases
	 */
	public static function edgeCaseDataProvider(): array {
		return [
			'boolean_false_nonce' => [
				[
					'nonce'                      => false,
					'action'                     => 'test_action',
					'expected_wp_unslash_input'  => false,
					'expected_wp_unslash_output' => '',
					'expected_sanitize_input'    => '',
					'expected_sanitize_output'   => '',
					'wp_verify_result'           => false,
					'description'                => 'Boolean false nonce should be handled',
				],
			],
			'array_as_nonce'      => [
				[
					'nonce'                      => [ 'invalid', 'array' ],
					'action'                     => 'test_action',
					'expected_wp_unslash_input'  => [ 'invalid', 'array' ],
					'expected_wp_unslash_output' => [ 'invalid', 'array' ],
					'expected_sanitize_input'    => [ 'invalid', 'array' ],
					'expected_sanitize_output'   => '',
					'wp_verify_result'           => false,
					'description'                => 'Array as nonce should be sanitized to empty string',
				],
			],
			'numeric_nonce'       => [
				[
					'nonce'                      => 12345,
					'action'                     => 'test_action',
					'expected_wp_unslash_input'  => 12345,
					'expected_wp_unslash_output' => '12345',
					'expected_sanitize_input'    => '12345',
					'expected_sanitize_output'   => '12345',
					'wp_verify_result'           => 1,
					'description'                => 'Numeric nonce should be converted to string',
				],
			],
			'slashes_in_nonce'    => [
				[
					'nonce'                      => 'abc\\123\\def',
					'action'                     => 'test_action',
					'expected_wp_unslash_input'  => 'abc\\123\\def',
					'expected_wp_unslash_output' => 'abc123def',
					'expected_sanitize_input'    => 'abc123def',
					'expected_sanitize_output'   => 'abc123def',
					'wp_verify_result'           => 1,
					'description'                => 'Nonce with slashes should be unslashed',
				],
			],
			'xss_attempt'         => [
				[
					'nonce'                      => '<script>alert("xss")</script>abc123',
					'action'                     => 'test_action',
					'expected_wp_unslash_input'  => '<script>alert("xss")</script>abc123',
					'expected_wp_unslash_output' => '<script>alert("xss")</script>abc123',
					'expected_sanitize_input'    => '<script>alert("xss")</script>abc123',
					'expected_sanitize_output'   => 'abc123',
					'wp_verify_result'           => false,
					'description'                => 'XSS attempt should be sanitized and rejected',
				],
			],
		];
	}
}
