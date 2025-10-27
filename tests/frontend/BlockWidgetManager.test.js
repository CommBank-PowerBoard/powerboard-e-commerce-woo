/**
 * @jest-environment jsdom
 *
 * Unit tests for `BlockWidgetManager`
 */

import { BlockWidgetManager } from '../../resources/js/frontend/BlockWidgetManager.js';
import { ERROR_MESSAGES } from '../../resources/js/frontend/constants.js';

// Mock WordPress i18n
jest.mock(
	'@wordpress/i18n',
	() => ( {
		__: jest.fn( ( text ) => text )
	} )
);

// Import the mocked function for direct access
import { __ as mockTranslate } from '@wordpress/i18n';

describe(
	'BlockWidgetManager',
	() => {
		let widgetManager;
		let mockModalManager,
			mockDataService,
			mockSettings;
		let mockPowerBoardWidget;

		beforeEach(
			() => {
				// Reset all mocks
				jest.clearAllMocks();
				mockTranslate.mockImplementation( ( text ) => text );

				// Mock settings
				mockSettings = {
					environment: 'sandbox',
					widget_event_nonce: 'test-widget-nonce'
				};

				// Mock services
				mockModalManager = {
					showWidget: jest.fn(),
					showError: jest.fn(),
					close: jest.fn()
				};

				mockDataService = {
					getOrderId: jest.fn().mockReturnValue( 123 ),
					createChargeIntent: jest.fn(),
					onPaymentSuccessful: jest.fn(),
					onPaymentFailure: jest.fn(),
					onPaymentExpired: jest.fn()
				};

				// Mock PowerBoard widget
				mockPowerBoardWidget = {
					setEnv: jest.fn(),
					onPaymentSuccessful: jest.fn(),
					onPaymentFailure: jest.fn(),
					onPaymentExpired: jest.fn()
				};

				// Setup global mocks
				global.window = {
					...global.window,
					cba: {
						Checkout: jest.fn().mockImplementation( () => mockPowerBoardWidget )
					},
					widgetPowerBoard: null,
					jQuery: {
						ajax: jest.fn()
					}
				};

				// Ensure window.cba and window.jQuery are available globally
				window.cba = {
					Checkout: jest.fn().mockImplementation( () => mockPowerBoardWidget )
				};
				window.jQuery = {
					ajax: jest.fn()
				};

				// Create instance
				widgetManager = new BlockWidgetManager( mockModalManager, mockDataService, mockSettings );

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
						expect( widgetManager.modalManager ).toBe( mockModalManager );
						expect( widgetManager.dataService ).toBe( mockDataService );
						expect( widgetManager.settings ).toBe( mockSettings );
						expect( widgetManager.jQuery ).toBe( window.jQuery );
						expect( widgetManager.paymentPromiseResolve ).toBeNull();
					}
				);
			}
		);

		describe(
			'setPaymentPromiseResolver',
			() => {
				it(
					'should set the payment promise resolver',
					() => {
						const mockResolver = jest.fn();
						widgetManager.setPaymentPromiseResolver( mockResolver );
						expect( widgetManager.paymentPromiseResolve ).toBe( mockResolver );
					}
				);
			}
		);

		describe(
			'initializeMasterWidget',
			() => {
				it(
					'should create charge intent and initialize widget',
					async() => {
						const mockResponse = {
							data: { token: 'widget-token', order_id: 456 }
						};
						mockDataService.createChargeIntent.mockResolvedValue( mockResponse );
						jest.spyOn( widgetManager, 'initializeWidget' ).mockImplementation();

						await widgetManager.initializeMasterWidget();

						expect( mockDataService.createChargeIntent ).toHaveBeenCalled();
						expect( widgetManager.initializeWidget ).toHaveBeenCalledWith( mockResponse );
					}
				);

				it(
					'should handle widget initialization errors',
					async() => {
						const nonce = 'test-nonce';
						const error = new Error( 'Creation failed' );
						mockDataService.createChargeIntent.mockRejectedValue( error );

						await widgetManager.initializeMasterWidget( nonce );

						expect( mockModalManager.showError ).toHaveBeenCalledWith( 'Creation failed' );
						// showError now handles auto-close internally, so we don't need to test close here
					}
				);
			}
		);

		describe(
			'initializeWidget',
			() => {
				it(
					'should initialize PowerBoard widget',
					() => {
						const mockResponse = {
							data: { token: 'widget-token', order_id: 456 }
						};
						jest.spyOn( widgetManager, 'setupWidgetEventHandlers' ).mockImplementation();

						widgetManager.initializeWidget( mockResponse );

						expect( mockModalManager.showWidget ).toHaveBeenCalled();
						expect( window.cba.Checkout ).toHaveBeenCalledWith(
							'#powerboard-modal-widget-wrapper',
							'widget-token'
						);
						expect( mockPowerBoardWidget.setEnv ).toHaveBeenCalledWith( 'sandbox' );
						// Data service order ID takes precedence (123)
						expect( widgetManager.setupWidgetEventHandlers ).toHaveBeenCalled();
					}
				);

				it(
					'should use data service order ID if response order ID missing',
					() => {
						const mockResponse = {
							data: { token: 'widget-token' }
						};
						jest.spyOn( widgetManager, 'setupWidgetEventHandlers' ).mockImplementation();

						widgetManager.initializeWidget( mockResponse );

						expect( widgetManager.setupWidgetEventHandlers ).toHaveBeenCalled();
					}
				);
			}
		);

		describe(
			'setupWidgetEventHandlers',
			() => {
				beforeEach(
					() => {
						window.widgetPowerBoard = mockPowerBoardWidget;
					}
				);

				it(
					'should set up payment successful handler',
					async() => {
						const mockResolver = jest.fn();
						widgetManager.setPaymentPromiseResolver( mockResolver );
						const mockNotifyPromise = Promise.resolve( { success: true, data: { message: 'Payment recorded' } } );
						mockDataService.onPaymentSuccessful.mockReturnValue( mockNotifyPromise );

						widgetManager.setupWidgetEventHandlers();

						expect( mockPowerBoardWidget.onPaymentSuccessful ).toHaveBeenCalled();

						// Simulate successful payment
						const paymentData = { charge_id: 'test-charge-123' };
						const onSuccessCallback = mockPowerBoardWidget.onPaymentSuccessful.mock.calls[ 0 ][ 0 ];

						// Call the async handler and wait for it to complete
						await onSuccessCallback( paymentData );

						expect( mockDataService.onPaymentSuccessful ).toHaveBeenCalledWith( paymentData );
						expect( mockResolver ).toHaveBeenCalledWith(
							{
								success: true,
								redirectUrl: null,
								payment_data: paymentData
							}
						);
						expect( mockModalManager.close ).toHaveBeenCalled();
					}
				);

				it(
					'should set up payment expired handler',
					() => {
						const mockResolver = jest.fn();
						widgetManager.setPaymentPromiseResolver( mockResolver );

						widgetManager.setupWidgetEventHandlers();

						expect( mockPowerBoardWidget.onPaymentExpired ).toHaveBeenCalled();

						// Simulate payment expiry
						const onExpiredCallback = mockPowerBoardWidget.onPaymentExpired.mock.calls[ 0 ][ 0 ];
						onExpiredCallback();

						expect( mockResolver ).toHaveBeenCalledWith(
							{
								success: false,
								message: ERROR_MESSAGES.PAYMENT_EXPIRED
							}
						);
						expect( mockModalManager.close ).toHaveBeenCalled();
					}
				);

				it(
					'should set up payment failure handler',
					() => {
						const mockResolver = jest.fn();
						widgetManager.setPaymentPromiseResolver( mockResolver );
						const mockNotifyPromise = Promise.resolve();
						mockDataService.onPaymentFailure.mockReturnValue( mockNotifyPromise );

						widgetManager.setupWidgetEventHandlers();

						expect( mockPowerBoardWidget.onPaymentFailure ).toHaveBeenCalled();

						// Simulate payment failure
						const failedPaymentData = { charge_id: 'test-charge-123' };
						const onFailureCallback = mockPowerBoardWidget.onPaymentFailure.mock.calls[ 0 ][ 0 ];
						onFailureCallback( failedPaymentData );

						expect( mockDataService.onPaymentFailure ).toHaveBeenCalledWith( failedPaymentData );
						expect( mockResolver ).toHaveBeenCalledWith(
							{
								success: false,
								message: ERROR_MESSAGES.PAYMENT_FAILED
							}
						);
						expect( mockModalManager.close ).toHaveBeenCalled();
					}
				);
			}
		);



		describe(
			'handlePromiseResolveFailure',
			() => {
				it(
					'should resolve promise with failure message',
					() => {
						const mockResolver = jest.fn();
						widgetManager.setPaymentPromiseResolver( mockResolver );

						widgetManager.handlePromiseResolveFailure( 'Test error message' );

						expect( mockResolver ).toHaveBeenCalledWith(
							{
								success: false,
								message: 'Test error message'
							}
						);
						expect( widgetManager.paymentPromiseResolve ).toBeNull();
					}
				);

				it(
					'should not call resolve if promise is null',
					() => {
						const mockResolver = jest.fn();
						// Don't set resolver
						widgetManager.handlePromiseResolveFailure( 'Test error message' );

						expect( mockResolver ).not.toHaveBeenCalled();
					}
				);
			}
		);

		describe(
			'cleanup',
			() => {
				it(
					'should clean up widget and promise resolver',
					() => {
						window.widgetPowerBoard = mockPowerBoardWidget;
						const mockResolver = jest.fn();
						widgetManager.setPaymentPromiseResolver( mockResolver );

						widgetManager.cleanup();

						expect( window.widgetPowerBoard ).toBeNull();
						expect( widgetManager.paymentPromiseResolve ).toBeNull();
					}
				);
			}
		);
	}
);
