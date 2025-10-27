/**
 * Unit tests for ModalManager class
 */

const { ModalManager, CONSTANTS } = require( '../../assets/js/frontend/classic-form.js' );

describe( 'ModalManager', () => {
	let modalManager;
	let mockModal, mockLoading, mockWidget, mockError, mockCloseBtn;
	let mockStateManager, mockDataService;

	beforeEach( () => {
		mockStateManager = {
			reset: jest.fn(),
			showError: jest.fn()
		};

		mockDataService = {
			onPaymentCancelled: jest.fn().mockResolvedValue()
		};

		modalManager = new ModalManager( mockStateManager, mockDataService );

		// Initialize escapeKeyHandler property for testing
		modalManager.escapeKeyHandler = null;

		// Mock DOM elements
		mockModal = {
			style: { display: 'none' },
			querySelector: jest.fn(),
			onclick: null
		};

		mockLoading = {
			style: { display: 'none' }
		};

		mockWidget = {
			style: { display: 'none' },
			classList: {
				remove: jest.fn(),
				add: jest.fn()
			}
		};

		mockError = {
			style: { display: 'none' }
		};

		mockCloseBtn = {
			onclick: null
		};

		// Mock document.getElementById
		global.document.getElementById = jest.fn( ( id ) => {
			switch ( id ) {
				case CONSTANTS.MODAL_IDS.MODAL:
					return mockModal;
				case CONSTANTS.MODAL_IDS.LOADING:
					return mockLoading;
				case CONSTANTS.MODAL_IDS.WIDGET:
					return mockWidget;
				case CONSTANTS.MODAL_IDS.ERROR:
					return mockError;
				default:
					return null;
			}
		} );

		mockModal.querySelector.mockReturnValue( mockCloseBtn );

		// Mock window.widgetPowerBoard
		global.window.widgetPowerBoard = {};
	} );

	describe( 'show', () => {
		it( 'should show modal and set up initial state', () => {
			const result = modalManager.show();

			expect( result ).toBe( true );
			expect( mockLoading.style.display ).toBe( 'block' );
			expect( mockWidget.style.display ).toBe( 'none' );
			expect( mockWidget.classList.remove ).toHaveBeenCalledWith( 'active' );
			expect( mockError.style.display ).toBe( 'none' );
			expect( mockModal.style.display ).toBe( 'block' );
			expect( global.document.body.style.overflow ).toBe( 'hidden' );
		} );

		it( 'should return false if modal element not found', () => {
			global.document.getElementById = jest.fn( () => null );

			const result = modalManager.show();

			expect( result ).toBe( false );
		} );

		it( 'should set up close handlers', () => {
			modalManager.show();

			expect( mockModal.querySelector ).toHaveBeenCalledWith( '.powerboard-modal-close' );
			expect( typeof mockCloseBtn.onclick ).toBe( 'function' );
			expect( typeof mockModal.onclick ).toBe( 'function' );
		} );

		it( 'should close modal when close button is clicked', () => {
			const closeSpy = jest.spyOn( modalManager, 'close' );
			modalManager.show();

			mockCloseBtn.onclick();

			expect( closeSpy ).toHaveBeenCalled();
		} );

		it( 'should close modal when clicking outside', () => {
			const closeSpy = jest.spyOn( modalManager, 'close' );
			modalManager.show();

			// Simulate click on modal background (event.target === modal)
			const mockEvent = { target: mockModal };
			mockModal.onclick( mockEvent );

			expect( closeSpy ).toHaveBeenCalled();
		} );

		it( 'should not close modal when clicking inside', () => {
			const closeSpy = jest.spyOn( modalManager, 'close' );
			modalManager.show();

			// Simulate click on modal content (event.target !== modal)
			const mockEvent = { target: mockWidget };
			mockModal.onclick( mockEvent );

			expect( closeSpy ).not.toHaveBeenCalled();
		} );
	} );

	describe( 'close', () => {
		it( 'should hide modal and reset body overflow', () => {
			modalManager.close();

			expect( mockModal.style.display ).toBe( 'none' );
			expect( global.document.body.style.overflow ).toBe( '' );
		} );

		it( 'should clean up widget reference', () => {
			global.window.widgetPowerBoard = { someMethod: jest.fn() };

			modalManager.close();

			expect( global.window.widgetPowerBoard ).toBe( null );
		} );

		it( 'should handle missing modal element gracefully', () => {
			global.document.getElementById = jest.fn( () => null );

			expect( () => modalManager.close() ).not.toThrow();
		} );

		it( 'should reset checkout state when modal is closed', () => {
			modalManager.close();

			expect( mockStateManager.reset ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'should handle missing stateManager gracefully', () => {
			const modalManagerWithoutState = new ModalManager( null, mockDataService );

			expect( () => modalManagerWithoutState.close() ).not.toThrow();
		} );
	} );

	describe( 'showError', () => {
		it( 'should show error state in modal', () => {
			modalManager.showError();

			expect( mockLoading.style.display ).toBe( 'none' );
			expect( mockWidget.style.display ).toBe( 'none' );
			expect( mockError.style.display ).toBe( 'block' );
		} );

		it( 'should auto-close modal after 3 seconds', ( done ) => {
			const closeSpy = jest.spyOn( modalManager, 'close' );

			modalManager.showError();

			// Check that close is called after 3 seconds
			setTimeout( () => {
				expect( closeSpy ).toHaveBeenCalled();
				done();
			}, 3100 );
		} );
	} );

	describe( 'setupCloseHandlers', () => {
		let originalAddEventListener;
		let mockAddEventListener;

		beforeEach( () => {
			// Mock document.addEventListener for escape key testing
			originalAddEventListener = global.document.addEventListener;
			mockAddEventListener = jest.fn();
			global.document.addEventListener = mockAddEventListener;
		} );

		afterEach( () => {
			// Restore original addEventListener
			global.document.addEventListener = originalAddEventListener;
		} );


		it( 'should set up close button handler with confirmation', () => {
			const handleUserCloseSpy = jest.spyOn( modalManager, 'handleUserClose' );
			modalManager.setupCloseHandlers( mockModal );

			expect( mockModal.querySelector ).toHaveBeenCalledWith( CONSTANTS.SELECTORS.MODAL_CLOSE_BUTTON );
			expect( typeof mockCloseBtn.onclick ).toBe( 'function' );

			// Test that clicking close button calls handleUserClose
			mockCloseBtn.onclick();
			expect( handleUserCloseSpy ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'should set up outside click handler with confirmation', () => {
			const handleUserCloseSpy = jest.spyOn( modalManager, 'handleUserClose' );

			modalManager.setupCloseHandlers( mockModal );

			expect( typeof mockModal.onclick ).toBe( 'function' );

			// Test clicking outside modal calls handleUserClose
			const mockEvent = { target: mockModal };
			mockModal.onclick( mockEvent );
			expect( handleUserCloseSpy ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'should not trigger close when clicking inside modal content', () => {
			const handleUserCloseSpy = jest.spyOn( modalManager, 'handleUserClose' );

			modalManager.setupCloseHandlers( mockModal );

			// Test clicking inside modal content doesn't call handleUserClose
			const mockEvent = { target: mockWidget };
			mockModal.onclick( mockEvent );
			expect( handleUserCloseSpy ).not.toHaveBeenCalled();
		} );

		it( 'should trigger close when escape key is pressed', () => {
			const handleUserCloseSpy = jest.spyOn( modalManager, 'handleUserClose' );

			modalManager.setupCloseHandlers( mockModal );

			// Get the escape key handler function
			const escapeKeyHandler = modalManager.escapeKeyHandler;

			// Simulate escape key press
			const escapeKeyEvent = { key: 'Escape' };
			escapeKeyHandler( escapeKeyEvent );

			expect( handleUserCloseSpy ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'should not trigger close for non-escape keys', () => {
			const handleUserCloseSpy = jest.spyOn( modalManager, 'handleUserClose' );

			modalManager.setupCloseHandlers( mockModal );

			// Get the escape key handler function
			const escapeKeyHandler = modalManager.escapeKeyHandler;

			// Simulate other key press
			const otherKeyEvent = { key: 'Enter' };
			escapeKeyHandler( otherKeyEvent );

			expect( handleUserCloseSpy ).not.toHaveBeenCalled();
		} );

	} );

	describe( 'showCloseConfirmation', () => {
		it( 'should call window.confirm with correct message', () => {
			const mockConfirm = jest.fn().mockReturnValue( true );
			global.window.confirm = mockConfirm;

			const result = modalManager.showCloseConfirmation();

			expect( mockConfirm ).toHaveBeenCalledWith(
				CONSTANTS.ERROR_MESSAGES.MODAL_CLOSE_CONFIRMATION_MESSAGE
			);
			expect( result ).toBe( true );
		} );
	} );

	describe( 'handleUserClose', () => {
		it( 'should close modal when user confirms', () => {
			const showCloseConfirmationSpy = jest.spyOn( modalManager, 'showCloseConfirmation' )
				.mockReturnValue( true );
			const closeSpy = jest.spyOn( modalManager, 'close' );

			modalManager.handleUserClose();

			expect( showCloseConfirmationSpy ).toHaveBeenCalledTimes( 1 );
			expect( closeSpy ).toHaveBeenCalledWith( true );
		} );

		it( 'should not close modal when user cancels confirmation', () => {
			const showCloseConfirmationSpy = jest.spyOn( modalManager, 'showCloseConfirmation' )
				.mockReturnValue( false );
			const closeSpy = jest.spyOn( modalManager, 'close' );

			modalManager.handleUserClose();

			expect( showCloseConfirmationSpy ).toHaveBeenCalledTimes( 1 );
			expect( closeSpy ).not.toHaveBeenCalled();
		} );
	} );
} );
