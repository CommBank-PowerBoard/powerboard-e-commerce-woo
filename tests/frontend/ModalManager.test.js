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
		it( 'should handle missing close button gracefully', () => {
			mockModal.querySelector.mockReturnValue( null );

			expect( () => modalManager.setupCloseHandlers( mockModal ) ).not.toThrow();
		} );

		it( 'should set up outside click handler', () => {
			modalManager.setupCloseHandlers( mockModal );

			expect( typeof mockModal.onclick ).toBe( 'function' );
		} );
	} );
} );
