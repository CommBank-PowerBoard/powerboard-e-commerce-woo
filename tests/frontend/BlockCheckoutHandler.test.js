/**
 * @jest-environment jsdom
 *
 * Unit tests for `BlockCheckoutHandler`
 */

import { BlockCheckoutHandler } from '../../resources/js/frontend/BlockCheckoutHandler.js';
import { BlockModalManager } from '../../resources/js/frontend/BlockModalManager.js';
import { BlockDataService } from '../../resources/js/frontend/BlockDataService.js';
import { BlockValidationService } from '../../resources/js/frontend/BlockValidationService.js';
import { BlockWidgetManager } from '../../resources/js/frontend/BlockWidgetManager.js';
import {
	ERROR_MESSAGES,
	MODAL_REDIRECT,
	TEXT_DOMAIN
} from '../../resources/js/frontend/constants.js';

// Mock WordPress i18n
jest.mock(
	'@wordpress/i18n',
	() => ( {
		__: jest.fn( ( text ) => text )
	} )
);

// Mock dependencies
jest.mock( '../../resources/js/frontend/BlockModalManager.js' );
jest.mock( '../../resources/js/frontend/BlockDataService.js' );
jest.mock( '../../resources/js/frontend/BlockValidationService.js' );
jest.mock( '../../resources/js/frontend/BlockWidgetManager.js' );

// Import the mocked function for direct access
import { __ as mockTranslate } from '@wordpress/i18n';

describe(
	'BlockCheckoutHandler',
	() => {
		let checkoutHandler;
		let mockStore,
			mockCart,
			mockSettings,
			mockResponseTypes,
			mockNoticeContexts;
		let mockModalManager,
			mockDataService,
			mockValidationService,
			mockWidgetManager;

		beforeEach(
			() => {
				// Reset all mocks
				jest.clearAllMocks();
				mockTranslate.mockImplementation( ( text ) => text );

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
					)
				};

				// Mock settings
				mockSettings = {
					environment: 'sandbox',
					pluginUrlPrefix: '/wp-content/plugins/power-board/'
				};

				// Mock response types
				mockResponseTypes = {
					SUCCESS: 'success',
					ERROR: 'error',
					FAILED: 'failed'
				};

				// Mock notice contexts
				mockNoticeContexts = {
					PAYMENTS: 'wc/checkout/payments'
				};

				// Mock service instances
				mockModalManager      = {
					show: jest.fn().mockReturnValue( true ),
					close: jest.fn(),
					showWidget: jest.fn(),
					showError: jest.fn(),
					isOpen: jest.fn().mockReturnValue( false ),
					onClose: jest.fn()
				};
				mockDataService       = {
					setOrderId: jest.fn(),
					getOrderId: jest.fn().mockReturnValue( 123 ),
					setPaymentData: jest.fn(),
					createChargeIntent: jest.fn(),
					processPaymentResult: jest.fn(),
					processSuccessfulOrder: jest.fn()
				};
				mockValidationService = {
					validatePowerBoardRequirements: jest.fn().mockReturnValue( {
						isValid: false,
						error: 'test error'
					} )
				};
				mockWidgetManager = {
					setPaymentPromiseResolver: jest.fn(),
					initializeMasterWidget: jest.fn(),
					handlePromiseResolveFailure: jest.fn(),
					cleanup: jest.fn()
				};

				// Mock constructors
				BlockModalManager.mockImplementation( () => mockModalManager );
				BlockDataService.mockImplementation( () => mockDataService );
				BlockValidationService.mockImplementation( () => mockValidationService );
				BlockWidgetManager.mockImplementation( () => mockWidgetManager );

				// Create instance
				checkoutHandler = new BlockCheckoutHandler(
					mockStore,
					mockCart,
					mockSettings,
					mockResponseTypes,
					mockNoticeContexts
				);

				// Mock timers
				jest.useFakeTimers();
			}
		);

		afterEach(
			() => {
				jest.useRealTimers();
			}
		);

		describe(
			'constructor',
			() => {
				it(
					'should initialize with correct dependencies',
					() => {
						expect( checkoutHandler.store ).toBe( mockStore );
						expect( checkoutHandler.cart ).toBe( mockCart );
						expect( checkoutHandler.settings ).toBe( mockSettings );
						expect( checkoutHandler.responseTypes ).toBe( mockResponseTypes );
						expect( checkoutHandler.noticeContexts ).toBe( mockNoticeContexts );
					}
				);

				it(
					'should initialize service instances',
					() => {
						expect( BlockModalManager ).toHaveBeenCalledTimes( 1 );
						expect( BlockDataService ).toHaveBeenCalledWith( mockStore, mockCart, mockSettings );
						expect( BlockValidationService ).toHaveBeenCalledWith( mockCart );
						expect( BlockWidgetManager ).toHaveBeenCalledWith( mockModalManager, mockDataService, mockSettings );
						expect( checkoutHandler.eventHandlers ).toBeInstanceOf( Map );
					}
				);
			}
		);

		describe(
			'processPaymentSetup',
			() => {
				it(
					'should return success when form is valid',
					() => {
						mockValidationService.validatePowerBoardRequirements.mockReturnValue( {
							isValid: true,
							error: ''
						} );
						const result = checkoutHandler.processPaymentSetup();
						expect( mockValidationService.validatePowerBoardRequirements ).toHaveBeenCalled();
						expect( result ).toEqual(
							{
								type: mockResponseTypes.SUCCESS,
								meta: {
									paymentMethodData: {
										powerboard_redirect: MODAL_REDIRECT
									}
								}
							}
						);
					}
				);

				it(
					'should return failed when form is invalid',
					() => {
						mockValidationService.validatePowerBoardRequirements.mockReturnValue( {
							isValid: false,
							error: 'test error'
						} );
						const result = checkoutHandler.processPaymentSetup();
						expect( mockTranslate ).toHaveBeenCalledWith( 'test error', TEXT_DOMAIN );
						expect( result ).toEqual(
							{
								type: mockResponseTypes.FAILED,
								message: 'test error',
								messageContext: mockNoticeContexts.PAYMENTS
							}
						);
					}
				);
			}
		);

		describe(
			'handleCheckoutSuccess',
			() => {
				const baseCheckoutData = {
					processingResponse: {
						paymentDetails: {}
					}
				};

				it(
					'should return true when not PowerBoard payment method',
					async() => {
						const checkoutDataWithoutPowerBoard = {
							orderId: 456,
							processingResponse: {
								paymentDetails: {
									someOtherMethod: 'data'
								}
							}
						};
						const result = await checkoutHandler.handleCheckoutSuccess( checkoutDataWithoutPowerBoard );
						expect( result ).toBe( true );
					}
				);

				it(
					'should show modal and process payment for PowerBoard method',
					async() => {
						const checkoutDataWithPowerBoard = {
							...baseCheckoutData,
							processingResponse: {
								paymentDetails: {
									powerboard_redirect: MODAL_REDIRECT,
									order_id: 456,
									_wpnonce_intent: 'intent-nonce',
									_wpnonce_widget_event: 'payment-result-nonce'
								}
							},
							orderId: 456
						};
						const mockModalResult = { success: true, payment_data: { charge_id: 'ch_123' } };
						const mockProcessResult = { success: true, data: { redirect_url: 'http://example.com/success' } };
						jest.spyOn( checkoutHandler, 'showModalAndProcessPayment' ).mockResolvedValue( mockModalResult );
						mockDataService.processSuccessfulOrder.mockResolvedValue( mockProcessResult );
						const result = await checkoutHandler.handleCheckoutSuccess( checkoutDataWithPowerBoard );
						expect( result ).toEqual(
							{
								type: mockResponseTypes.SUCCESS,
								redirectUrl: 'http://example.com/success'
							}
						);
					}
				);

				it(
					'should handle modal payment failure',
					async() => {
						const checkoutDataWithPowerBoard = {
							...baseCheckoutData,
							processingResponse: {
								paymentDetails: {
									powerboard_redirect: MODAL_REDIRECT,
									order_id: 456,
									_wpnonce_intent: 'intent-nonce'
								}
							}
						};
						const mockModalResult = { success: false, message: 'Payment failed' };
						jest.spyOn( checkoutHandler, 'showModalAndProcessPayment' ).mockResolvedValue( mockModalResult );
						const result = await checkoutHandler.handleCheckoutSuccess( checkoutDataWithPowerBoard );
						expect( result ).toEqual(
							{
								type: mockResponseTypes.ERROR,
								message: 'Payment failed',
								messageContext: mockNoticeContexts.PAYMENTS,
								retry: true
							}
						);
					}
				);

				it(
					'should handle payment result processing failure',
					async() => {
						const checkoutDataWithPowerBoard = {
							...baseCheckoutData,
							processingResponse: {
								paymentDetails: {
									powerboard_redirect: MODAL_REDIRECT,
									order_id: 456,
									_wpnonce_intent: 'intent-nonce'
								}
							}
						};
						const mockModalResult = { success: true, payment_data: { charge_id: 'ch_123' } };
						const processingError = new Error( 'Processing failed' );
						jest.spyOn( checkoutHandler, 'showModalAndProcessPayment' ).mockResolvedValue( mockModalResult );
						mockDataService.processPaymentResult.mockRejectedValue( processingError );
						const result = await checkoutHandler.handleCheckoutSuccess( checkoutDataWithPowerBoard );
						expect( result ).toEqual(
							{
								type: mockResponseTypes.ERROR,
								message: ERROR_MESSAGES.SOMETHING_WRONG,
								messageContext: mockNoticeContexts.PAYMENTS,
								retry: true
							}
						);
					}
				);

				it(
					'should handle exceptions gracefully',
					async() => {
						const checkoutDataWithPowerBoard = {
							...baseCheckoutData,
							processingResponse: {
								paymentDetails: {
									powerboard_redirect: MODAL_REDIRECT,
									order_id: 456,
									_wpnonce_intent: 'intent-nonce'
								}
							}
						};
						const unexpectedError = new Error( 'Unexpected error' );
						jest.spyOn( checkoutHandler, 'showModalAndProcessPayment' ).mockRejectedValue( unexpectedError );
						const result = await checkoutHandler.handleCheckoutSuccess( checkoutDataWithPowerBoard );
						expect( result ).toEqual(
							{
								type: mockResponseTypes.ERROR,
								message: ERROR_MESSAGES.SOMETHING_WRONG,
								messageContext: mockNoticeContexts.PAYMENTS,
								retry: true
							}
						);
					}
				);
			}
		);

		describe(
			'showModalAndProcessPayment',
			() => {
				it(
					'should show modal and set up promise handlers',
					async() => {
						mockModalManager.show.mockReturnValue( true );
						jest.spyOn( checkoutHandler, 'setupOnCloseModalHandlers' ).mockImplementation();
						// Start the promise but don't await it yet
						const promise = checkoutHandler.showModalAndProcessPayment();
						// Verify setup was called
						expect( mockModalManager.show ).toHaveBeenCalled();
						expect( checkoutHandler.setupOnCloseModalHandlers ).toHaveBeenCalled();
						expect( mockWidgetManager.setPaymentPromiseResolver ).toHaveBeenCalled();
						expect( mockWidgetManager.initializeMasterWidget ).toHaveBeenCalled();
						// Since promise resolution is now handled by widget manager,
						// we just verify that the setup was called correctly
						expect( promise ).toBeInstanceOf( Promise );
					}
				);

				it(
					'should reject when modal fails to show',
					async() => {
						mockModalManager.show.mockReturnValue( false );
						await expect( checkoutHandler.showModalAndProcessPayment( 123, 'nonce' ) ).rejects.toThrow(
							ERROR_MESSAGES.MODAL_LOAD_FAILED
						);
						expect( mockWidgetManager.setPaymentPromiseResolver ).toHaveBeenCalledWith( null );
					}
				);
			}
		);

		describe(
			'setupOnCloseModalHandlers',
			() => {
				it(
					'should set up modal close handler',
					() => {
						checkoutHandler.setupOnCloseModalHandlers();
						expect( mockModalManager.onClose ).toHaveBeenCalled();
						// Simulate modal close
						const onCloseCallback = mockModalManager.onClose.mock.calls[ 0 ][ 0 ];
						onCloseCallback();
						// Verify that widget manager's handlePromiseResolveFailure was called
						expect( mockWidgetManager.handlePromiseResolveFailure ).toHaveBeenCalledWith(
							ERROR_MESSAGES.USER_CANCELLED
						);
					}
				);
			}
		);

		describe(
			'cleanup',
			() => {
				it(
					'should close modal and cleanup widget manager',
					() => {
						checkoutHandler.cleanup();
						expect( mockModalManager.close ).toHaveBeenCalled();
						expect( mockWidgetManager.cleanup ).toHaveBeenCalled();
					}
				);
			}
		);

		describe(
			'getState',
			() => {
				it(
					'should return current state',
					() => {
						mockModalManager.isOpen.mockReturnValue( false );
						const state = checkoutHandler.getState();
						expect( state ).toEqual(
							{
								isModalOpen: false
							}
						);
					}
				);
			}
		);

		describe(
			'createCheckoutErrorObject',
			() => {
				it(
					'should create error object with correct structure',
					() => {
						const result = checkoutHandler.createCheckoutErrorObject( 'Test error message' );
						expect( result ).toEqual(
							{
								type: mockResponseTypes.ERROR,
								message: 'Test error message',
								messageContext: mockNoticeContexts.PAYMENTS,
								retry: true
							}
						);
					}
				);

				it(
					'should handle empty message',
					() => {
						const result = checkoutHandler.createCheckoutErrorObject( '' );
						expect( result ).toEqual(
							{
								type: mockResponseTypes.ERROR,
								message: '',
								messageContext: mockNoticeContexts.PAYMENTS,
								retry: true
							}
						);
					}
				);
			}
		);

		describe(
			'error handling and integration scenarios',
			() => {
				it(
					'should handle payment processing failure gracefully',
					async() => {
						const checkoutDataWithPowerBoard = {
							orderId: 456,
							processingResponse: {
								paymentDetails: {
									powerboard_redirect: MODAL_REDIRECT,
									_wpnonce_intent: 'intent-nonce',
									_wpnonce_payment_result: 'payment-result-nonce'
								}
							}
						};

						// Mock modal success but payment processing failure
						const mockModalResult = { success: true, payment_data: { charge_id: 'charge-123' } };
						const mockProcessError = { success: false, data: { message: 'Processing failed' } };

						jest.spyOn( checkoutHandler, 'showModalAndProcessPayment' ).mockResolvedValue( mockModalResult );
						mockDataService.processSuccessfulOrder.mockResolvedValue( mockProcessError );

						const result = await checkoutHandler.handleCheckoutSuccess( checkoutDataWithPowerBoard );

						expect( result ).toEqual( {
							type: mockResponseTypes.ERROR,
							message: expect.any( String ),
							messageContext: mockNoticeContexts.PAYMENTS,
							retry: true
						} );
					}
				);

				it(
					'should handle payment processing success without redirect URL',
					async() => {
						const checkoutDataWithPowerBoard = {
							orderId: 456,
							processingResponse: {
								paymentDetails: {
									powerboard_redirect: MODAL_REDIRECT,
									_wpnonce_intent: 'intent-nonce',
									_wpnonce_payment_result: 'payment-result-nonce'
								}
							}
						};

						const mockModalResult = { success: true, payment_data: { charge_id: 'charge-123' } };
						const mockProcessResult = { success: true, data: {} }; // No redirect_url

						jest.spyOn( checkoutHandler, 'showModalAndProcessPayment' ).mockResolvedValue( mockModalResult );
						mockDataService.processSuccessfulOrder.mockResolvedValue( mockProcessResult );

						const result = await checkoutHandler.handleCheckoutSuccess( checkoutDataWithPowerBoard );

						expect( result ).toEqual( {
							type: mockResponseTypes.ERROR,
							message: expect.any( String ),
							messageContext: mockNoticeContexts.PAYMENTS,
							retry: true
						} );
					}
				);

				it(
					'should handle modal promise rejection',
					async() => {
						const checkoutDataWithPowerBoard = {
							orderId: 456,
							processingResponse: {
								paymentDetails: {
									powerboard_redirect: MODAL_REDIRECT
								}
							}
						};

						jest.spyOn( checkoutHandler, 'showModalAndProcessPayment' ).mockRejectedValue( new Error( 'Modal failed to load' ) );

						const result = await checkoutHandler.handleCheckoutSuccess( checkoutDataWithPowerBoard );

						expect( result ).toEqual( {
							type: mockResponseTypes.ERROR,
							message: expect.any( String ),
							messageContext: mockNoticeContexts.PAYMENTS,
							retry: true
						} );
					}
				);

				it(
					'should handle processing successful order rejection',
					async() => {
						const checkoutDataWithPowerBoard = {
							orderId: 456,
							processingResponse: {
								paymentDetails: {
									powerboard_redirect: MODAL_REDIRECT
								}
							}
						};

						const mockModalResult = { success: true, payment_data: { charge_id: 'charge-123' } };
						jest.spyOn( checkoutHandler, 'showModalAndProcessPayment' ).mockResolvedValue( mockModalResult );
						mockDataService.processSuccessfulOrder.mockRejectedValue( new Error( 'Network error' ) );

						const result = await checkoutHandler.handleCheckoutSuccess( checkoutDataWithPowerBoard );

						expect( result ).toEqual( {
							type: mockResponseTypes.ERROR,
							message: expect.any( String ),
							messageContext: mockNoticeContexts.PAYMENTS,
							retry: true
						} );
					}
				);


			}
		);

		describe(
			'validation and form processing',
			() => {
				it(
					'should validate form requirements before processing',
					() => {
						// Test that validation is called
						mockValidationService.validatePowerBoardRequirements.mockReturnValue( {
							isValid: true,
							error: ''
						} );

						const result = checkoutHandler.processPaymentSetup();

						expect( mockValidationService.validatePowerBoardRequirements ).toHaveBeenCalledTimes( 1 );
						expect( result.type ).toBe( mockResponseTypes.SUCCESS );
					}
				);

				it(
					'should include validation error details in response',
					() => {
						const validationError = 'Phone number is required';
						mockValidationService.validatePowerBoardRequirements.mockReturnValue( {
							isValid: false,
							error: validationError
						} );

						const result = checkoutHandler.processPaymentSetup();

						expect( result ).toEqual( {
							type: mockResponseTypes.FAILED,
							message: validationError,
							messageContext: mockNoticeContexts.PAYMENTS
						} );
					}
				);

				it(
					'should include correct payment method data in success response',
					() => {
						mockValidationService.validatePowerBoardRequirements.mockReturnValue( {
							isValid: true,
							error: ''
						} );

						const result = checkoutHandler.processPaymentSetup();

						expect( result.meta.paymentMethodData ).toEqual( {
							powerboard_redirect: MODAL_REDIRECT
						} );
					}
				);
			}
		);

		describe(
			'showModalAndProcessPayment edge cases',
			() => {
				it(
					'should handle modal setup and widget initialization',
					async() => {
						mockModalManager.show.mockReturnValue( true );
						jest.spyOn( checkoutHandler, 'setupOnCloseModalHandlers' ).mockImplementation();

						// Don't await the promise, just verify setup
						const promise = checkoutHandler.showModalAndProcessPayment();

						expect( mockWidgetManager.setPaymentPromiseResolver ).toHaveBeenCalled();
						expect( mockModalManager.show ).toHaveBeenCalled();
						expect( checkoutHandler.setupOnCloseModalHandlers ).toHaveBeenCalled();
						expect( mockWidgetManager.initializeMasterWidget ).toHaveBeenCalled();

						// Verify it returns a promise
						expect( promise ).toBeInstanceOf( Promise );
					}
				);

				it(
					'should cleanup promise resolver when modal fails to show',
					async() => {
						mockModalManager.show.mockReturnValue( false );

						await expect( checkoutHandler.showModalAndProcessPayment() ).rejects.toThrow( 'Failed to show payment modal' );

						expect( mockWidgetManager.setPaymentPromiseResolver ).toHaveBeenCalledWith( null );
					}
				);

				it(
					'should not call widget initialization if modal fails',
					async() => {
						mockModalManager.show.mockReturnValue( false );

						try {
							await checkoutHandler.showModalAndProcessPayment();
						} catch {
							// Expected to reject
						}

						expect( mockWidgetManager.initializeMasterWidget ).not.toHaveBeenCalled();
					}
				);
			}
		);

		describe(
			'data handling and state management',
			() => {
				it(
					'should set payment data and order ID from checkout data',
					async() => {
						const checkoutData = {
							processingResponse: {
								paymentDetails: {
									test: 'data',
									_wpnonce_intent: 'test-nonce',
									order_id: 789
								}
							}
						};

						const result = await checkoutHandler.handleCheckoutSuccess( checkoutData );

						expect( mockDataService.setPaymentData ).toHaveBeenCalledWith( checkoutData.processingResponse.paymentDetails );
						expect( mockDataService.setOrderId ).toHaveBeenCalledWith( 789 );
						expect( result ).toBe( true ); // Non-PowerBoard payment
					}
				);



				it(
					'should handle missing orderId gracefully',
					async() => {
						const checkoutData = {
							processingResponse: {
								paymentDetails: { test: 'data' }
							}
						};

						await expect( checkoutHandler.handleCheckoutSuccess( checkoutData ) ).resolves.toBe( true );
						expect( mockDataService.setOrderId ).toHaveBeenCalledWith( undefined );
					}
				);
			}
		);

		// Edge cases and error handling
		describe(
			'edge cases and error boundaries',
			() => {
				describe(
					'malformed data handling',
					() => {
						it(
							'should handle checkout data without processingResponse',
							async() => {
								const malformedData = { orderId: 456 };
								const result = await checkoutHandler.handleCheckoutSuccess( malformedData );
								expect( result ).toBe( true );
							}
						);

						it(
							'should handle checkout data with null paymentDetails',
							async() => {
								const malformedData = {
									orderId: 456,
									processingResponse: {
										paymentDetails: null
									}
								};
								const result = await checkoutHandler.handleCheckoutSuccess( malformedData );
								expect( result ).toBe( true );
							}
						);

						it(
							'should handle empty checkout data gracefully',
							async() => {
								const result = await checkoutHandler.handleCheckoutSuccess( {} );
								expect( result ).toBe( true );
							}
						);
					}
				);
			}
		);
	}
);
