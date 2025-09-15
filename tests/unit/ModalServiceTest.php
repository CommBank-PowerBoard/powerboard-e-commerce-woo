<?php
declare( strict_types=1 );

namespace unit;

require_once __DIR__ . '/bootstrap.php';

use PHPUnit\Framework\TestCase;
use PowerBoard\Services\ModalService;

/**
 * Unit tests for ModalService
 */
class ModalServiceTest extends TestCase {

	public function test_get_instance_returns_singleton() {
		// Test that get_instance returns the same instance
		$instance1 = ModalService::get_instance();
		$instance2 = ModalService::get_instance();

		$this->assertInstanceOf( ModalService::class, $instance1, 'Should return ModalService instance' );
		$this->assertSame( $instance1, $instance2, 'Should return same instance (singleton pattern)' );
	}

	public function test_is_complete_address_with_complete_address() {
		$modal_service = ModalService::get_instance();
		$reflection    = new \ReflectionClass( $modal_service );
		$method        = $reflection->getMethod( 'is_complete_address' );
		$method->setAccessible( true );

		$complete_address = [
			'email'      => 'test@example.com',
			'first_name' => 'John',
			'last_name'  => 'Doe',
			'address_1'  => '123 Main St',
			'city'       => 'Anytown',
			'state'      => 'CA',
			'country'    => 'US',
			'postcode'   => '12345',
		];

		$result = $method->invoke( $modal_service, $complete_address );
		$this->assertTrue( $result, 'Should return true for complete address' );
	}

	public function test_is_complete_address_with_missing_email() {
		$modal_service = ModalService::get_instance();
		$reflection    = new \ReflectionClass( $modal_service );
		$method        = $reflection->getMethod( 'is_complete_address' );
		$method->setAccessible( true );

		$incomplete_address = [
			'first_name' => 'John',
			'last_name'  => 'Doe',
			'address_1'  => '123 Main St',
			'city'       => 'Anytown',
			'state'      => 'CA',
			'country'    => 'US',
			'postcode'   => '12345',
		];

		$result = $method->invoke( $modal_service, $incomplete_address );
		$this->assertFalse( $result, 'Should return false when email is missing' );
	}

	public function test_is_complete_address_with_empty_fields() {
		$modal_service = ModalService::get_instance();
		$reflection    = new \ReflectionClass( $modal_service );
		$method        = $reflection->getMethod( 'is_complete_address' );
		$method->setAccessible( true );

		$address_with_empty_field = [
			'email'      => '',
			'first_name' => 'John',
			'last_name'  => 'Doe',
			'address_1'  => '123 Main St',
			'city'       => 'Anytown',
			'state'      => 'CA',
			'country'    => 'US',
			'postcode'   => '12345',
		];

		$result = $method->invoke( $modal_service, $address_with_empty_field );
		$this->assertFalse( $result, 'Should return false when email is empty' );
	}

	public function test_is_complete_address_with_empty_array() {
		$modal_service = ModalService::get_instance();
		$reflection    = new \ReflectionClass( $modal_service );
		$method        = $reflection->getMethod( 'is_complete_address' );
		$method->setAccessible( true );

		$result = $method->invoke( $modal_service, [] );
		$this->assertFalse( $result, 'Should return false for empty array' );
	}

	/**
	 * Authorization tests for can_user_modify_order method
	 */
	public function test_can_user_modify_order_guest_order() {
		$modal_service = ModalService::get_instance();
		$reflection    = new \ReflectionClass( $modal_service );
		$method        = $reflection->getMethod( 'can_user_modify_order' );
		$method->setAccessible( true );

		// Mock guest order (no user ID)
		$order = \Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_user_id' )->andReturn( 0 );

		// With default bootstrap mocks (guest user, no permissions)
		$result = $method->invoke( $modal_service, $order );
		$this->assertTrue( $result, 'Guest users should be able to modify guest orders' );
	}

	public function test_can_user_modify_order_null_order() {
		$modal_service = ModalService::get_instance();
		$reflection    = new \ReflectionClass( $modal_service );
		$method        = $reflection->getMethod( 'can_user_modify_order' );
		$method->setAccessible( true );

		$result = $method->invoke( $modal_service, null );
		$this->assertFalse( $result, 'Should return false for null order' );
	}

	public function test_can_user_modify_order_registered_order_different_user() {
		$modal_service = ModalService::get_instance();
		$reflection    = new \ReflectionClass( $modal_service );
		$method        = $reflection->getMethod( 'can_user_modify_order' );
		$method->setAccessible( true );

		// Mock order belonging to user ID 123, but current user is 0 (guest)
		$order = \Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_user_id' )->andReturn( 123 );

		// With default bootstrap mocks (guest user, no permissions)
		$result = $method->invoke( $modal_service, $order );
		$this->assertFalse( $result, 'Guest users should not be able to modify orders belonging to registered users' );
	}

	protected function tearDown(): void {
		\Mockery::close();
		parent::tearDown();
	}
}
