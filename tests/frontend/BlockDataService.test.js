/**
 * @jest-environment jsdom
 */

import { BlockDataService } from '../../resources/js/frontend/BlockDataService.js';
import { PAYMENT_METHOD, AJAX_ENDPOINTS } from '../../resources/js/frontend/constants.js';

// Mock jQuery
const mockJQuery = {
	ajax: jest.fn()
};

describe(
	'BlockDataService',
	() => {
		let dataService;
		let mockStore,
			mockCart,
			mockSettings;

		beforeEach(
			() => {
			// Reset mocks
				jest.clearAllMocks();
				// Setup global mocks FIRST before creating instances
				global.window = {
					...global.window,
					jQuery: mockJQuery,
					PowerBoardAjaxCheckout: {
						wpnonce_intent: 'intent-nonce',
						wpnonce_process_payment: 'payment-nonce',
						wpnonce_update_shipping: 'shipping-nonce'
					}
				};

				// Ensure window.jQuery is available globally for the service
				window.jQuery = mockJQuery;
				// Mock store
				mockStore = {
					getOrderId: jest.fn().mockReturnValue( 123 )
				};
				// Mock cart
				mockCart = {
					getCartTotals: jest.fn().mockReturnValue(
						{
							total_price: 100.00,
							currency: 'USD'
						}
					),
					getCustomerData: jest.fn().mockReturnValue(
						{
							billingAddress: {
								first_name: 'John',
								last_name: 'Doe',
								email: 'john@example.com',
								address_1: '123 Main St',
								city: 'Anytown',
								state: 'CA',
								postcode: '12345',
								country: 'US',
								phone: '555-1234'
							},
							shippingAddress: {
								first_name: 'John',
								last_name: 'Doe',
								address_1: '123 Main St',
								city: 'Anytown',
								state: 'CA',
								postcode: '12345',
								country: 'US'
							}
						}
					),
					getShippingRates: jest.fn().mockReturnValue(
						[
							{
								shipping_rates: [
									{
										rate_id: 'flat_rate:1',
										name: 'Flat Rate',
										cost: '10.00',
										selected: true
									}
								]
							}
						]
					)
				};
				// Mock settings
				mockSettings = {
					test_mode: true,
					payment_method: PAYMENT_METHOD
				};
				// Setup DOM elements
				document.body.innerHTML = `
			<div id                 ="powerboard-checkout-error" style="display: none;"></div>
			<input type             ="hidden" id="payment_response" value="" />
			<input type             ="hidden" id="chargeId" value="" />
			<input type             ="hidden" id="intentId" value="" />
			<input type             ="hidden" id="orderId" value="" />
			<div class              ="shipping-calculator-form">
				<select name        ="shipping_method[0]">
					<option value   ="flat_rate:1" selected>Flat Rate</option>
				</select>
			</div>
			`;
				// Mock scrollIntoView
				Element.prototype.scrollIntoView = jest.fn();
				// Create the service instance
				dataService = new BlockDataService( mockStore, mockCart, mockSettings );
			}
		);
		afterEach(
			() => {
				document.body.innerHTML = '';
				delete Element.prototype.scrollIntoView;
			}
		);
		describe(
			'getOrderId',
			() => {
				it(
					'should return order ID from store',
					() => {
						const result = dataService.getOrderId();
						expect( result ).toBe( 123 );
						expect( mockStore.getOrderId ).toHaveBeenCalled();
					}
				);

				it(
					'should handle missing store gracefully',
					() => {
						dataService.store = null;
						const result      = dataService.getOrderId();
						expect( result ).toBeNull();
					}
				);

				it(
					'should return stored orderId when store fails',
					() => {
						mockStore.getOrderId.mockImplementation( () => {
							throw new Error( 'Store error' );
						} );
						dataService.setOrderId( 456 );
						const result = dataService.getOrderId();
						expect( result ).toBe( 456 );
					}
				);

				it(
					'should return store order id when no orderId is set',
					() => {
						const result = dataService.getOrderId();
						expect( result ).toBe( 123 ); // Store value should take priority
						expect( mockStore.getOrderId ).toHaveBeenCalled();
					}
				);
			}
		);

		describe(
			'setOrderId',
			() => {
				it(
					'should store order ID correctly',
					() => {
						dataService.setOrderId( 789 );
						expect( dataService.orderId ).toBe( 789 );
					}
				);

				it(
					'should handle null order ID',
					() => {
						dataService.setOrderId( null );
						expect( dataService.orderId ).toBeNull();
					}
				);
			}
		);

		describe(
			'setPaymentData',
			() => {
				it(
					'should store payment data correctly',
					() => {
						const testData = { test: 'value', _wpnonce_intent: 'test-nonce' };
						dataService.setPaymentData( testData );
						expect( dataService.paymentData ).toEqual( testData );
					}
				);

				it(
					'should handle null payment data',
					() => {
						dataService.setPaymentData( null );
						expect( dataService.paymentData ).toEqual( {} );
					}
				);

				it(
					'should handle undefined payment data',
					() => {
						dataService.setPaymentData( undefined );
						expect( dataService.paymentData ).toEqual( {} );
					}
				);
			}
		);

		describe(
			'getPaymentData',
			() => {
				it(
					'should return stored payment data',
					() => {
						const testData = { test: 'value', _wpnonce_intent: 'test-nonce' };
						dataService.setPaymentData( testData );
						const result = dataService.getPaymentData();
						expect( result ).toEqual( testData );
					}
				);

				it(
					'should return null when no payment data stored',
					() => {
						const result = dataService.getPaymentData();
						expect( result ).toBeUndefined();
					}
				);
			}
		);

		describe(
			'createChargeIntent',
			() => {
				beforeEach( () => {
					dataService.setPaymentData( { _wpnonce_intent: 'intent-nonce-123' } );
				} );

				it(
					'should create charge intent successfully',
					async() => {
						const mockResponse = {
							success: true,
							data: { token: 'widget-token', order_id: 789 }
						};

						mockJQuery.ajax.mockImplementation( ( options ) => {
							options.success( mockResponse );
						} );

						const result = await dataService.createChargeIntent();

						expect( mockJQuery.ajax ).toHaveBeenCalledWith( {
							url: AJAX_ENDPOINTS.CREATE_INTENT,
							type: 'POST',
							data: {
								_wpnonce: 'intent-nonce-123',
								order_id: 123,
								total: { total_price: 100.00, currency: 'USD' },
								address: {
									first_name: 'John',
									last_name: 'Doe',
									email: 'john@example.com',
									address_1: '123 Main St',
									city: 'Anytown',
									state: 'CA',
									postcode: '12345',
									country: 'US',
									phone: '555-1234'
								}
							},
							success: expect.any( Function ),
							error: expect.any( Function )
						} );
						expect( result ).toEqual( mockResponse );
					}
				);

				it(
					'should reject when API returns failure',
					async() => {
						const mockResponse = {
							success: false,
							data: { message: 'Intent creation failed' }
						};

						mockJQuery.ajax.mockImplementation( ( options ) => {
							options.success( mockResponse );
						} );

						await expect( dataService.createChargeIntent() ).rejects.toThrow( 'Intent creation failed' );
					}
				);

				it(
					'should reject when API returns failure without message',
					async() => {
						const mockResponse = {
							success: false,
							data: {}
						};

						mockJQuery.ajax.mockImplementation( ( options ) => {
							options.success( mockResponse );
						} );

						await expect( dataService.createChargeIntent() ).rejects.toThrow( 'Failed to create charge intent' );
					}
				);

				it(
					'should handle network errors',
					async() => {
						mockJQuery.ajax.mockImplementation( ( options ) => {
							options.error( {}, 'error', 'Network timeout' );
						} );

						await expect( dataService.createChargeIntent() ).rejects.toThrow( 'Network error: Network timeout' );
					}
				);
			}
		);

		describe(
			'onPaymentSuccessful',
			() => {
				beforeEach( () => {
					dataService.setPaymentData( { _wpnonce_widget_event: 'widget-nonce-123' } );
				} );

				it(
					'should notify backend of successful payment',
					async() => {
						const responseData = { charge_id: 'charge-123', status: 'completed' };
						const mockResponse = {
							success: true,
							data: { message: 'Payment recorded successfully' }
						};

						mockJQuery.ajax.mockImplementation( ( options ) => {
							options.success( mockResponse );
						} );

						const result = await dataService.onPaymentSuccessful( responseData );

						expect( mockJQuery.ajax ).toHaveBeenCalledWith( {
							url: AJAX_ENDPOINTS.PAYMENT_SUCCESS,
							type: 'POST',
							data: {
								_wpnonce: 'widget-nonce-123',
								order_id: 123,
								charge_id: 'charge-123',
								payment_data: responseData
							},
							success: expect.any( Function ),
							error: expect.any( Function )
						} );
						expect( result ).toEqual( mockResponse );
					}
				);

				it(
					'should handle missing widget event nonce gracefully',
					async() => {
						dataService.setPaymentData( {} ); // No nonce
						const responseData = { charge_id: 'charge-123' };

						// Should still make AJAX call even without nonce (backend will handle it)
						await dataService.onPaymentSuccessful( responseData );
						expect( mockJQuery.ajax ).toHaveBeenCalled();
					}
				);

				it(
					'should reject when backend returns failure',
					async() => {
						const responseData = { charge_id: 'charge-123' };
						const mockResponse = {
							success: false,
							data: { message: 'Backend error occurred' }
						};

						mockJQuery.ajax.mockImplementation( ( options ) => {
							options.success( mockResponse );
						} );

						await expect( dataService.onPaymentSuccessful( responseData ) ).rejects.toThrow( 'Backend error occurred' );
					}
				);

				it(
					'should reject when backend returns failure without message',
					async() => {
						const responseData = { charge_id: 'charge-123' };
						const mockResponse = {
							success: false,
							data: {}
						};

						mockJQuery.ajax.mockImplementation( ( options ) => {
							options.success( mockResponse );
						} );

						await expect( dataService.onPaymentSuccessful( responseData ) ).rejects.toThrow( 'Backend notification failed' );
					}
				);

				it(
					'should handle network errors',
					async() => {
						const responseData = { charge_id: 'charge-123' };

						mockJQuery.ajax.mockImplementation( ( options ) => {
							options.error( {}, 'error', 'Connection failed' );
						} );

						await expect( dataService.onPaymentSuccessful( responseData ) ).rejects.toThrow( 'Network error: Connection failed' );
					}
				);

				it(
					'should handle missing charge_id in response data',
					async() => {
						const responseData = { status: 'completed' }; // No charge_id
						const mockResponse = {
							success: true,
							data: { message: 'Payment recorded successfully' }
						};

						mockJQuery.ajax.mockImplementation( ( options ) => {
							options.success( mockResponse );
						} );

						const result = await dataService.onPaymentSuccessful( responseData );

						expect( mockJQuery.ajax ).toHaveBeenCalledWith(
							expect.objectContaining( {
								data: expect.objectContaining( {
									charge_id: undefined,
									payment_data: responseData
								} )
							} )
						);
						expect( result ).toEqual( mockResponse );
					}
				);
			}
		);

		describe(
			'processSuccessfulOrder',
			() => {
				beforeEach( () => {
					dataService.setPaymentData( { _wpnonce_success_order: 'success-nonce-123' } );
				} );

				it(
					'should process successful order with provided order ID',
					async() => {
						const mockResponse = {
							success: true,
							data: { redirect_url: 'https://example.com/success' }
						};

						mockJQuery.ajax.mockImplementation( ( options ) => {
							options.success( mockResponse );
						} );

						const result = await dataService.processSuccessfulOrder( 456 );

						expect( mockJQuery.ajax ).toHaveBeenCalledWith( {
							url: AJAX_ENDPOINTS.ORDER_SUCCESS,
							method: 'POST',
							data: {
								_wpnonce: 'success-nonce-123',
								order_id: 456
							},
							success: expect.any( Function ),
							error: expect.any( Function )
						} );
						expect( result ).toEqual( mockResponse );
					}
				);

				it(
					'should process successful order without order ID (uses null)',
					async() => {
						const mockResponse = {
							success: true,
							data: { redirect_url: 'https://example.com/success' }
						};

						mockJQuery.ajax.mockImplementation( ( options ) => {
							options.success( mockResponse );
						} );

						const result = await dataService.processSuccessfulOrder();

						expect( mockJQuery.ajax ).toHaveBeenCalledWith( {
							url: AJAX_ENDPOINTS.ORDER_SUCCESS,
							method: 'POST',
							data: {
								_wpnonce: 'success-nonce-123',
								order_id: null
							},
							success: expect.any( Function ),
							error: expect.any( Function )
						} );
						expect( result ).toEqual( mockResponse );
					}
				);

				it(
					'should handle processing errors',
					async() => {
						mockJQuery.ajax.mockImplementation( ( options ) => {
							options.error( {}, 'error', 'Processing failed' );
						} );

						await expect( dataService.processSuccessfulOrder( 456 ) ).rejects.toThrow( 'Payment processing failed: Processing failed' );
					}
				);

				it(
					'should handle successful response even when success is false',
					async() => {
						const mockResponse = {
							success: false,
							data: { error: 'Order already processed' }
						};

						mockJQuery.ajax.mockImplementation( ( options ) => {
							options.success( mockResponse );
						} );

						const result = await dataService.processSuccessfulOrder( 456 );
						expect( result ).toEqual( mockResponse ); // Method resolves even with success: false
					}
				);
			}
		);

	}
);
