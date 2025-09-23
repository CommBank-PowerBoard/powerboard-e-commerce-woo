/**
 * Tests for PowerBoardCheckoutHandler
 */

// Import modules
const { PowerBoardCheckoutHandler, ModalManager, CheckoutStateManager, DataService, CONSTANTS } = require( '../../assets/js/frontend/classic-form.js' );

describe( 'PowerBoardCheckoutHandler', () => {
	let checkoutHandler;
	let mockJQuery;

	beforeEach( () => {
		// Mock jQuery
		mockJQuery = {
			ajax: jest.fn(),
			Deferred: jest.fn( () => ( { reject: jest.fn() } ) )
		};
		mockJQuery.mockImplementation = jest.fn();

		// Mock global objects
		global.window = {
			showNotice: jest.fn(),
			formatList: jest.fn( ( arr ) => arr.join( ', ' ) ),
			jQuery: mockJQuery
		};

		global.document = {
			querySelector: jest.fn(),
			getElementById: jest.fn()
		};

		checkoutHandler = new PowerBoardCheckoutHandler( mockJQuery );
	} );

	afterEach( () => {
		jest.clearAllMocks();
	} );

	describe( 'constructor', () => {
		it( 'should initialize with correct dependencies', () => {
			expect( checkoutHandler.$ ).toBe( mockJQuery );
			expect( checkoutHandler.stateManager ).toBeInstanceOf( CheckoutStateManager );
			expect( checkoutHandler.modalManager ).toBeInstanceOf( ModalManager );
			expect( checkoutHandler.dataService ).toBeInstanceOf( DataService );
			expect( checkoutHandler.validationService ).toBeDefined();
			expect( checkoutHandler.widgetManager ).toBeDefined();
		} );
	} );

	describe( 'init', () => {
		it( 'should initialize state manager and intercept form submission', () => {
			const initSpy = jest.spyOn( checkoutHandler.stateManager, 'init' );
			const interceptSpy = jest.spyOn( checkoutHandler, 'interceptFormSubmission' );

			const result = checkoutHandler.init();

			expect( initSpy ).toHaveBeenCalled();
			expect( interceptSpy ).toHaveBeenCalled();
			expect( result ).toBe( checkoutHandler );
		} );
	} );

	describe( 'interceptFormSubmission', () => {
		let originalAjax;

		beforeEach( () => {
			originalAjax = mockJQuery.ajax;
			checkoutHandler.init(); // This sets up the interception
		} );

		it( 'should intercept WooCommerce checkout AJAX calls', () => {
			const ajaxOptions = {
				url: '/?wc-ajax=checkout',
				type: 'POST',
				data: {}
			};

			// Mock validation service to return PowerBoard is not selected
			jest.spyOn( checkoutHandler.validationService, 'isPowerBoardSelected' ).mockReturnValue( false );

			mockJQuery.ajax( ajaxOptions );

			// Should call original AJAX without modification for non-PowerBoard payments
			expect( originalAjax ).toHaveBeenCalledWith( ajaxOptions );
		} );

		it( 'should handle PowerBoard payment method selection with validation errors', () => {
			const ajaxOptions = {
				url: '/?wc-ajax=checkout',
				type: 'POST',
				data: {}
			};

			// Mock validation service
			jest.spyOn( checkoutHandler.validationService, 'isPowerBoardSelected' ).mockReturnValue( true );
			jest.spyOn( checkoutHandler.validationService, 'validateCheckoutForm' ).mockReturnValue( {
				success: false,
				errors: [ 'Test error message' ]
			} );

			const showErrorsListSpy = jest.spyOn( checkoutHandler.stateManager, 'showErrorsList' );
			const resetSpy = jest.spyOn( checkoutHandler.stateManager, 'reset' );

			mockJQuery.ajax( ajaxOptions );

			expect( showErrorsListSpy ).toHaveBeenCalledWith( [ 'Test error message' ] );
			expect( resetSpy ).toHaveBeenCalled();
		} );

		it( 'should handle PowerBoard success response', () => {
			const ajaxOptions = {
				url: '/?wc-ajax=checkout',
				type: 'POST',
				data: {},
				success: jest.fn()
			};

			// Mock validation service
			jest.spyOn( checkoutHandler.validationService, 'isPowerBoardSelected' ).mockReturnValue( true );
			jest.spyOn( checkoutHandler.validationService, 'validateCheckoutForm' ).mockReturnValue( {
				success: true,
				errors: []
			} );

			const handlePowerBoardSpy = jest.spyOn( checkoutHandler, 'handlePowerBoardSubmission' );

			mockJQuery.ajax( ajaxOptions );

			// Get the modified success handler
			const modifiedOptions = originalAjax.mock.calls[ 0 ][ 0 ];
			const successResponse = {
				result: 'success',
				redirect: 'powerboard_show_modal',
				order_id: 'test-order-123'
			};

			// Call the success handler
			modifiedOptions.success( successResponse );

			expect( handlePowerBoardSpy ).toHaveBeenCalledWith( successResponse );
		} );

		it( 'should call original success handler for non-PowerBoard responses', () => {
			const originalSuccess = jest.fn();
			const ajaxOptions = {
				url: '/?wc-ajax=checkout',
				type: 'POST',
				data: {},
				success: originalSuccess
			};

			// Mock validation service
			jest.spyOn( checkoutHandler.validationService, 'isPowerBoardSelected' ).mockReturnValue( true );
			jest.spyOn( checkoutHandler.validationService, 'validateCheckoutForm' ).mockReturnValue( {
				success: true,
				errors: []
			} );

			mockJQuery.ajax( ajaxOptions );

			// Get the modified success handler
			const modifiedOptions = originalAjax.mock.calls[ 0 ][ 0 ];
			const regularResponse = {
				result: 'success',
				redirect: 'http://example.com/success'
			};

			// Call the success handler
			modifiedOptions.success( regularResponse );

			expect( originalSuccess ).toHaveBeenCalledWith( regularResponse );
		} );

		it( 'should not modify AJAX calls for non-checkout URLs', () => {
			const ajaxOptions = {
				url: '/some-other-endpoint',
				type: 'POST',
				data: {}
			};

			mockJQuery.ajax( ajaxOptions );
			// Should call original AJAX without modification
			expect( originalAjax ).toHaveBeenCalledWith( ajaxOptions );
		} );
	} );

	describe( 'handlePowerBoardSubmission', () => {
		const validResponse = {
			result: 'success',
			redirect: 'powerboard_show_modal',
			order_id: 'test-order-123',
			_wpnonce_intent: 'test-intent-nonce',
			_wpnonce_widget_event: 'test-widget-nonce',
			_wpnonce_success_order: 'test-success-nonce'
		};

		it( 'should handle invalid response data', () => {
			const invalidResponse = { result: 'success' }; // Missing required fields
			const showErrorSpy = jest.spyOn( checkoutHandler.stateManager, 'showErrorsList' );
			const resetSpy = jest.spyOn( checkoutHandler.stateManager, 'reset' );

			checkoutHandler.handlePowerBoardSubmission( invalidResponse );

			expect( showErrorSpy ).toHaveBeenCalled();
			expect( resetSpy ).toHaveBeenCalled();
		} );

		it( 'should show modal and initialize widget', () => {
			const showSpy = jest.spyOn( checkoutHandler.modalManager, 'show' ).mockReturnValue( true );
			const setPaymentDataSpy = jest.spyOn( checkoutHandler.dataService, 'setPaymentData' );
			const initWidgetSpy = jest.spyOn( checkoutHandler.widgetManager, 'initializeMasterWidget' ).mockResolvedValue( {} );

			// Mock DOM element for modal visibility check
			global.document.getElementById = jest.fn(
				( id ) => {
					if ( id === CONSTANTS.MODAL_IDS.MODAL ) {
						return { style: { display: 'none' } };
					}
					return null;
				}
			);
			global.PowerBoardAjaxError = {};

			checkoutHandler.handlePowerBoardSubmission( validResponse );

			expect( showSpy ).toHaveBeenCalled();
			expect( setPaymentDataSpy ).toHaveBeenCalled();
			expect( initWidgetSpy ).toHaveBeenCalled();
		} );

		it( 'should handle widget initialization error', async() => {
			const showSpy = jest.spyOn( checkoutHandler.modalManager, 'show' ).mockReturnValue( true );
			const showErrorSpy = jest.spyOn( checkoutHandler.modalManager, 'showError' );
			const initWidgetSpy = jest.spyOn( checkoutHandler.widgetManager, 'initializeMasterWidget' ).mockRejectedValue( new Error( 'Widget error' ) );

			global.document.getElementById = jest.fn(
				( id ) => {
					if ( id === CONSTANTS.MODAL_IDS.MODAL ) {
						return { style: { display: 'none' } };
					}
					return null;
				}
			);
			global.PowerBoardAjaxError = {};

			checkoutHandler.handlePowerBoardSubmission( validResponse );

			// Wait for promise to resolve
			await Promise.resolve();
			await Promise.resolve();

			expect( showSpy ).toHaveBeenCalled();
			expect( initWidgetSpy ).toHaveBeenCalled();
			expect( showErrorSpy ).toHaveBeenCalled();
		} );
	} );
} );
