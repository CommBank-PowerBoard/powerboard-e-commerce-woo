/**
 * Unit tests for DataService class
 */

const { DataService, CONSTANTS } = require( '../../assets/js/frontend/classic-form.js' );

describe( 'DataService', () => {
	let dataService;
	let mockJQuery;
	let mockPaymentData;

	beforeEach( () => {
		// Mock jQuery
		mockJQuery = jest.fn( () => ( {
			ajax: jest.fn()
		} ) );
		mockJQuery.ajax = jest.fn();

		dataService = new DataService( mockJQuery );

		// Mock payment data
		mockPaymentData = {
			orderId: 'test-order-123',
			createIntentNonce: 'test-intent-nonce',
			widgetEventNonce: 'test-widget-nonce',
			orderSuccessNonce: 'test-success-nonce'
		};
	} );

	describe( 'setPaymentData', () => {
		it( 'should set payment data and order ID', () => {
			dataService.setPaymentData( mockPaymentData );

			expect( dataService.paymentData ).toBe( mockPaymentData );
			expect( dataService.orderId ).toBe( 'test-order-123' );
		} );
	} );

	describe( 'createChargeIntent', () => {
		beforeEach( () => {
			dataService.setPaymentData( mockPaymentData );
		} );

		it( 'should make AJAX call to create charge intent with billing address', async() => {
			const billingAddress = { first_name: 'John', last_name: 'Doe' };
			const mockResponse = { success: true, data: { token: 'test-token' } };

			mockJQuery.ajax.mockImplementation( ( options ) => {
				// Simulate successful AJAX call
				setTimeout( () => options.success( mockResponse ), 0 );
			} );

			const result = await dataService.createChargeIntent( billingAddress );

			expect( mockJQuery.ajax ).toHaveBeenCalledWith( {
				url: CONSTANTS.AJAX_ENDPOINTS.CREATE_INTENT,
				type: 'POST',
				data: {
					_wpnonce: 'test-intent-nonce',
					order_id: 'test-order-123',
					address: billingAddress
				},
				success: expect.any( Function ),
				error: expect.any( Function )
			} );
			expect( result ).toBe( mockResponse );
		} );

		it( 'should handle charge intent creation failure', async() => {
			const billingAddress = { first_name: 'John', last_name: 'Doe' };
			const mockResponse = { success: false, data: { message: 'Invalid order' } };

			mockJQuery.ajax.mockImplementation( ( options ) => {
				// Simulate AJAX success but response failure
				setTimeout( () => options.success( mockResponse ), 0 );
			} );

			await expect( dataService.createChargeIntent( billingAddress ) ).rejects.toThrow( 'Invalid order' );
		} );

		it( 'should handle AJAX error during charge intent creation', async() => {
			const billingAddress = { first_name: 'John', last_name: 'Doe' };

			mockJQuery.ajax.mockImplementation( ( options ) => {
				// Simulate AJAX error
				setTimeout( () => options.error( {}, 'error', 'Server error' ), 0 );
			} );

			await expect( dataService.createChargeIntent( billingAddress ) ).rejects.toThrow( 'AJAX error: Server error' );
		} );
	} );

	describe( 'onPaymentSuccessful', () => {
		beforeEach( () => {
			dataService.setPaymentData( mockPaymentData );
		} );

		it( 'should make AJAX call for successful payment with payment data', async() => {
			const responseData = { charge_id: 'test-charge-123' };
			const mockResponse = { success: true, data: { message: 'Payment recorded' } };

			mockJQuery.ajax.mockImplementation( ( options ) => {
				// Simulate successful AJAX call
				setTimeout( () => options.success( mockResponse ), 0 );
			} );

			const result = await dataService.onPaymentSuccessful( responseData );

			expect( mockJQuery.ajax ).toHaveBeenCalledWith( {
				url: CONSTANTS.AJAX_ENDPOINTS.PAYMENT_SUCCESS,
				method: 'POST',
				data: {
					_wpnonce: 'test-widget-nonce',
					payment_data: responseData,
					order_id: 'test-order-123'
				},
				success: expect.any( Function ),
				error: expect.any( Function )
			} );
			expect( result ).toBe( mockResponse );
		} );

		it( 'should handle AJAX success but response failure', async() => {
			const responseData = { charge_id: 'test-charge-123' };
			const mockResponse = { success: false, data: { message: 'Payment failed' } };

			mockJQuery.ajax.mockImplementation( ( options ) => {
				// Simulate AJAX success but response failure
				setTimeout( () => options.success( mockResponse ), 0 );
			} );

			await expect( dataService.onPaymentSuccessful( responseData ) ).rejects.toThrow( 'Payment failed' );
		} );

		it( 'should handle AJAX error', async() => {
			const responseData = { charge_id: 'test-charge-123' };

			mockJQuery.ajax.mockImplementation( ( options ) => {
				// Simulate AJAX error
				setTimeout( () => options.error( {}, 'error', 'Network error' ), 0 );
			} );

			await expect( dataService.onPaymentSuccessful( responseData ) ).rejects.toThrow( 'AJAX error: Network error' );
		} );
	} );

	describe( 'onPaymentFailure', () => {
		beforeEach( () => {
			dataService.setPaymentData( mockPaymentData );
		} );

		it( 'should make AJAX call for failed payment with payment data', async() => {
			const responseData = { error: 'Card declined' };
			const mockResponse = { success: true };

			mockJQuery.ajax.mockImplementation( ( options ) => {
				// Simulate successful AJAX call
				setTimeout( () => options.success( mockResponse ), 0 );
			} );

			const result = await dataService.onPaymentFailure( responseData );

			expect( mockJQuery.ajax ).toHaveBeenCalledWith( {
				url: CONSTANTS.AJAX_ENDPOINTS.PAYMENT_FAILURE,
				method: 'POST',
				data: {
					_wpnonce: 'test-widget-nonce',
					payment_data: responseData,
					order_id: 'test-order-123'
				},
				success: expect.any( Function ),
				error: expect.any( Function )
			} );
			expect( result ).toBe( mockResponse );
		} );

		it( 'should handle payment failure processing error', async() => {
			const responseData = { error: 'Card declined' };
			const mockResponse = { success: false, data: { message: 'Failed to record failure' } };

			mockJQuery.ajax.mockImplementation( ( options ) => {
				// Simulate AJAX success but response failure
				setTimeout( () => options.success( mockResponse ), 0 );
			} );

			await expect( dataService.onPaymentFailure( responseData ) ).rejects.toThrow( 'Failed to record failure' );
		} );

		it( 'should handle AJAX error', async() => {
			const responseData = { error: 'Card declined' };

			mockJQuery.ajax.mockImplementation( ( options ) => {
				// Simulate AJAX error
				setTimeout( () => options.error( {}, 'error', 'Network error' ), 0 );
			} );

			await expect( dataService.onPaymentFailure( responseData ) ).rejects.toThrow( 'AJAX error: Network error' );
		} );
	} );

	describe( 'processSuccessfulOrder', () => {
		beforeEach( () => {
			dataService.setPaymentData( mockPaymentData );
		} );

		it( 'should make AJAX call to process successful order', async() => {
			const mockResponse = { success: true, data: { redirect_url: 'https://example.com/success' } };

			mockJQuery.ajax.mockImplementation( ( options ) => {
				// Simulate successful AJAX call
				setTimeout( () => options.success( mockResponse ), 0 );
			} );

			const result = await dataService.processSuccessfulOrder();

			expect( mockJQuery.ajax ).toHaveBeenCalledWith( {
				url: CONSTANTS.AJAX_ENDPOINTS.ORDER_SUCCESS,
				method: 'POST',
				data: {
					_wpnonce: 'test-success-nonce',
					order_id: 'test-order-123'
				},
				success: expect.any( Function ),
				error: expect.any( Function )
			} );
			expect( result ).toBe( mockResponse );
		} );

		it( 'should handle order processing failure', async() => {
			const mockResponse = { success: false, data: { message: 'Order processing failed' } };

			mockJQuery.ajax.mockImplementation( ( options ) => {
				// Simulate AJAX success but response failure
				setTimeout( () => options.success( mockResponse ), 0 );
			} );

			await expect( dataService.processSuccessfulOrder() ).rejects.toThrow( 'Order processing failed' );
		} );

		it( 'should handle AJAX error during order processing', async() => {
			mockJQuery.ajax.mockImplementation( ( options ) => {
				// Simulate AJAX error
				setTimeout( () => options.error( {}, 'error', 'Server error' ), 0 );
			} );

			await expect( dataService.processSuccessfulOrder() ).rejects.toThrow( 'AJAX error: Server error' );
		} );
	} );
} );
