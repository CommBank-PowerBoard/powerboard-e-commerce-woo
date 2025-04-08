<?php
declare( strict_types=1 );

namespace unit;

use PHPUnit\Framework\TestCase;
use PowerBoard\Helpers\JsonHelper;

class JsonHelperTest extends TestCase {

	public function test_decode_stringified_json() {
		$actual   = JsonHelper::decode_stringified_json(
			[
				'request'  => '{"amount": "2.00"}',
				'response' => '{"message":"The requested refund amount exceeds the available charge/transaction amount. Available amount is 1.99"}',
			]
		);
		$expected =
			[
				'request'  => [
					'amount' => '2.00',
				],
				'response' => [
					'message' => 'The requested refund amount exceeds the available charge/transaction amount. Available amount is 1.99',
				],
			];
		$this->assertSame( $expected, $actual, 'Should decode the JSON string and return a valid JSON object.' );
	}
}
