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

	public function test_build_endpoint_get_intent_by_id() {
		$intent_id = 'intentidtest';
		$params    = [
			'intent_id' => $intent_id,
		];
		$expected  = ChargeService::GET_INTENT_BY_ID_ENDPOINT . '/' . $intent_id;
		$actual    = $this->charge_service->get_checkout_intent_by_id( $params )->build_endpoint();

		$this->assertEquals( $expected, $actual, 'Should return valid get intent by id endpoint.' );
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
}
