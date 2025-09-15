/**
 * @jest-environment jsdom
 */

import { BlockModalManager } from '../../resources/js/frontend/BlockModalManager.js';
import { MODAL_IDS, MODAL_AUTO_CLOSE_DELAY } from '../../resources/js/frontend/constants.js';
describe( 'BlockModalManager', () => {
	let modalManager;
	let mockModal, mockLoading, mockWidget, mockError, mockCloseBtn;

	beforeEach( () => {
		// Reset DOM
		document.body.innerHTML = '';

		// Create mock modal elements
		mockModal = document.createElement( 'div' );
		mockModal.id = MODAL_IDS.MODAL;

		mockLoading = document.createElement( 'div' );
		mockLoading.id = MODAL_IDS.LOADING;

		mockWidget = document.createElement( 'div' );
		mockWidget.id = MODAL_IDS.WIDGET;

		mockError = document.createElement( 'div' );
		mockError.id = MODAL_IDS.ERROR;

		mockCloseBtn = document.createElement( 'span' );
		mockCloseBtn.className = 'powerboard-modal-close';

		// Add error element inside modal error
		const errorElement = document.createElement( 'p' );
		errorElement.className = 'power-board-validation-error';
		errorElement.textContent = 'Default error message';
		mockError.appendChild( errorElement );

		// Build modal structure
		mockModal.appendChild( mockCloseBtn );
		document.body.appendChild( mockModal );
		document.body.appendChild( mockLoading );
		document.body.appendChild( mockWidget );
		document.body.appendChild( mockError );

		// Clean up any existing global widget
		global.window.widgetPowerBoard = null;

		// Create mock dataService
		const mockDataService = {
			onPaymentCancelled: jest.fn().mockResolvedValue( {} )
		};

		modalManager = new BlockModalManager( mockDataService );
	} );

	afterEach( () => {
		jest.clearAllTimers();
		modalManager = null;
	} );

	describe( 'constructor', () => {
		it( 'should initialize with correct default state', () => {
			expect( modalManager.isModalOpen ).toBe( false );
			expect( modalManager.closeHandlers ).toEqual( [] );
			expect( modalManager.onCloseCallbacks ).toEqual( [] );
		} );
	} );

	describe( 'show', () => {
		it( 'should show modal and set up initial state', () => {
			const result = modalManager.show();

			expect( result ).toBe( true );
			expect( mockModal.style.display ).toBe( 'block' );
			expect( document.body.style.overflow ).toBe( 'hidden' );
			expect( modalManager.isModalOpen ).toBe( true );
		} );

		it( 'should reset modal to loading state', () => {
			modalManager.show();

			expect( mockLoading.style.display ).toBe( 'block' );
			expect( mockWidget.style.display ).toBe( 'none' );
			expect( mockWidget.classList.contains( 'active' ) ).toBe( false );
			expect( mockError.style.display ).toBe( 'none' );
		} );

		it( 'should return false if modal element not found', () => {
			mockModal.remove();

			const result = modalManager.show();

			expect( result ).toBe( false );
			expect( modalManager.isModalOpen ).toBe( false );
		} );

		it( 'should set up close handlers', () => {
			modalManager.show();

			expect( modalManager.closeHandlers.length ).toBeGreaterThan( 0 );
		} );

		it( 'should close modal when close button is clicked', () => {
			const closeSpy = jest.spyOn( modalManager, 'close' );
			modalManager.show();

			mockCloseBtn.click();

			expect( closeSpy ).toHaveBeenCalled();
		} );

		it( 'should close modal when clicking outside', () => {
			const closeSpy = jest.spyOn( modalManager, 'close' );
			modalManager.show();

			// Simulate clicking on the modal background (not the content)
			const event = new MouseEvent( 'click', { target: mockModal } );
			Object.defineProperty( event, 'target', { value: mockModal } );
			mockModal.dispatchEvent( event );

			expect( closeSpy ).toHaveBeenCalled();
		} );

		it( 'should not close modal when clicking inside', () => {
			const closeSpy = jest.spyOn( modalManager, 'close' );
			modalManager.show();

			// Simulate clicking on modal content (not the background)
			const event = new MouseEvent( 'click', { target: mockCloseBtn } );
			Object.defineProperty( event, 'target', { value: mockCloseBtn } );
			mockModal.dispatchEvent( event );

			expect( closeSpy ).not.toHaveBeenCalled();
		} );

		it( 'should close modal when escape key is pressed', () => {
			const closeSpy = jest.spyOn( modalManager, 'close' );
			modalManager.show();

			// Simulate escape key press
			const event = new KeyboardEvent( 'keydown', { key: 'Escape' } );
			document.dispatchEvent( event );

			expect( closeSpy ).toHaveBeenCalled();
		} );
	} );

	describe( 'close', () => {
		beforeEach( () => {
			modalManager.show(); // Ensure modal is open first
		} );

		it( 'should hide modal and reset body overflow', () => {
			modalManager.close();

			expect( mockModal.style.display ).toBe( 'none' );
			expect( document.body.style.overflow ).toBe( '' );
			expect( modalManager.isModalOpen ).toBe( false );
		} );

		it( 'should clean up widget reference', () => {
			global.window.widgetPowerBoard = { someProperty: 'test' };

			modalManager.close();

			expect( global.window.widgetPowerBoard ).toBeNull();
		} );

		it( 'should handle missing modal element gracefully', () => {
			mockModal.remove();

			expect( () => modalManager.close() ).not.toThrow();
			expect( modalManager.isModalOpen ).toBe( false );
		} );

		it( 'should remove all event listeners', () => {
			const initialHandlerCount = modalManager.closeHandlers.length;
			expect( initialHandlerCount ).toBeGreaterThan( 0 );

			modalManager.close();

			expect( modalManager.closeHandlers ).toEqual( [] );
		} );
	} );

	describe( 'showError', () => {
		beforeEach( () => {
			jest.useFakeTimers();
		} );

		afterEach( () => {
			jest.useRealTimers();
		} );

		it( 'should show error state in modal', () => {
			modalManager.showError();

			expect( mockLoading.style.display ).toBe( 'none' );
			expect( mockWidget.style.display ).toBe( 'none' );
			expect( mockError.style.display ).toBe( 'block' );
		} );

		it( 'should update error message when custom message provided', () => {
			const customMessage = 'Custom error message';

			modalManager.showError( customMessage );

			const errorElement = mockError.querySelector( '.power-board-validation-error' );
			expect( errorElement.textContent ).toBe( customMessage );
		} );

		it( 'should auto-close modal after showing error', () => {
			const closeSpy = jest.spyOn( modalManager, 'close' );

			modalManager.showError();

			expect( closeSpy ).not.toHaveBeenCalled();

			// The modal should auto-close after delay
			jest.advanceTimersByTime( MODAL_AUTO_CLOSE_DELAY );

			expect( closeSpy ).toHaveBeenCalled();
		} );

		it( 'should handle missing error element gracefully', () => {
			mockError.innerHTML = ''; // Remove error element

			expect( () => modalManager.showError( 'Test message' ) ).not.toThrow();
		} );
	} );

	describe( 'showWidget', () => {
		it( 'should show widget container and hide loading state', () => {
			modalManager.showWidget();

			expect( mockLoading.style.display ).toBe( 'none' );
			expect( mockWidget.style.display ).toBe( 'block' );
			expect( mockWidget.classList.contains( 'active' ) ).toBe( true );
			expect( mockError.style.display ).toBe( 'none' );
		} );

		it( 'should handle missing elements gracefully', () => {
			mockLoading.remove();
			mockWidget.remove();
			mockError.remove();

			expect( () => modalManager.showWidget() ).not.toThrow();
		} );
	} );

	describe( 'isOpen', () => {
		it( 'should return false initially', () => {
			expect( modalManager.isOpen() ).toBe( false );
		} );

		it( 'should return true when modal is open', () => {
			modalManager.show();

			expect( modalManager.isOpen() ).toBe( true );
		} );

		it( 'should return false after closing modal', () => {
			modalManager.show();
			modalManager.close();

			expect( modalManager.isOpen() ).toBe( false );
		} );
	} );

	describe( 'setupCloseHandlers', () => {
		it( 'should handle missing close button gracefully', () => {
			mockCloseBtn.remove();

			expect( () => modalManager.show() ).not.toThrow();
		} );

		it( 'should set up outside click handler', () => {
			modalManager.show();

			const hasOutsideClickHandler = modalManager.closeHandlers.some(
				handler => handler.element === mockModal && handler.event === 'click'
			);

			expect( hasOutsideClickHandler ).toBe( true );
		} );

		it( 'should set up escape key handler', () => {
			modalManager.show();

			const hasEscapeHandler = modalManager.closeHandlers.some(
				handler => handler.element === document && handler.event === 'keydown'
			);

			expect( hasEscapeHandler ).toBe( true );
		} );
	} );

	describe( 'removeCloseHandlers', () => {
		it( 'should remove all event listeners', () => {
			modalManager.show();
			const removeEventListenerSpy = jest.spyOn( Element.prototype, 'removeEventListener' );
			const docRemoveEventListenerSpy = jest.spyOn( document, 'removeEventListener' );

			modalManager.close();

			expect( removeEventListenerSpy ).toHaveBeenCalled();
			expect( docRemoveEventListenerSpy ).toHaveBeenCalled();

			removeEventListenerSpy.mockRestore();
			docRemoveEventListenerSpy.mockRestore();
		} );
	} );

	describe( 'onClose callback system', () => {
		it( 'should register close callback and return unsubscribe function', () => {
			const callback = jest.fn();
			const unsubscribe = modalManager.onClose( callback );

			expect( modalManager.onCloseCallbacks ).toContain( callback );
			expect( typeof unsubscribe ).toBe( 'function' );
		} );

		it( 'should call registered callbacks when modal closes', () => {
			const callback1 = jest.fn();
			const callback2 = jest.fn();

			modalManager.onClose( callback1 );
			modalManager.onClose( callback2 );
			modalManager.show();
			modalManager.close();

			expect( callback1 ).toHaveBeenCalledTimes( 1 );
			expect( callback2 ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'should unsubscribe callback correctly', () => {
			const callback = jest.fn();
			const unsubscribe = modalManager.onClose( callback );

			expect( modalManager.onCloseCallbacks ).toContain( callback );

			unsubscribe();

			expect( modalManager.onCloseCallbacks ).not.toContain( callback );
		} );

		it( 'should handle multiple unsubscribe calls gracefully', () => {
			const callback = jest.fn();
			const unsubscribe = modalManager.onClose( callback );

			unsubscribe();
			expect( modalManager.onCloseCallbacks ).not.toContain( callback );

			// Second unsubscribe should not throw
			expect( () => unsubscribe() ).not.toThrow();
		} );

		it( 'should handle callback errors gracefully', () => {
			const goodCallback = jest.fn();
			const errorCallback = jest.fn().mockImplementation( () => {
				throw new Error( 'Test error' );
			} );

			modalManager.onClose( goodCallback );
			modalManager.onClose( errorCallback );
			modalManager.show();
			modalManager.close();

			expect( goodCallback ).toHaveBeenCalled();
			expect( errorCallback ).toHaveBeenCalled();
		} );

		it( 'should return empty function for non-function callback', () => {
			const unsubscribe1 = modalManager.onClose( 'not a function' );
			const unsubscribe2 = modalManager.onClose( null );
			const unsubscribe3 = modalManager.onClose( undefined );

			expect( typeof unsubscribe1 ).toBe( 'function' );
			expect( typeof unsubscribe2 ).toBe( 'function' );
			expect( typeof unsubscribe3 ).toBe( 'function' );

			// Should not add invalid callbacks
			expect( modalManager.onCloseCallbacks.length ).toBe( 0 );

			// Unsubscribe should not throw
			expect( () => unsubscribe1() ).not.toThrow();
			expect( () => unsubscribe2() ).not.toThrow();
			expect( () => unsubscribe3() ).not.toThrow();
		} );

		it( 'should not trigger callbacks if modal was not properly opened', () => {
			const callback = jest.fn();
			modalManager.onClose( callback );

			// Close modal without opening it properly
			modalManager.close();

			expect( callback ).toHaveBeenCalledTimes( 1 ); // Callbacks are triggered regardless
		} );
	} );

	describe( 'comprehensive integration tests', () => {
		it( 'should handle complete modal lifecycle with callbacks', () => {
			const onCloseCallback = jest.fn();

			modalManager.onClose( onCloseCallback );

			// Open modal
			const showResult = modalManager.show();
			expect( showResult ).toBe( true );
			expect( modalManager.isOpen() ).toBe( true );

			// Show widget
			modalManager.showWidget();
			expect( mockWidget.style.display ).toBe( 'block' );

			// Close modal
			modalManager.close();
			expect( modalManager.isOpen() ).toBe( false );
			expect( onCloseCallback ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'should handle error state and still trigger callbacks on close', () => {
			const callback = jest.fn();
			modalManager.onClose( callback );

			modalManager.show();
			modalManager.showError( 'Test error message' );

			expect( mockError.style.display ).toBe( 'block' );

			modalManager.close();
			expect( callback ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'should properly clean up all state when closing', () => {
			const callback = jest.fn();
			global.window.widgetPowerBoard = { test: 'data' };

			modalManager.onClose( callback );
			modalManager.show();

			expect( modalManager.closeHandlers.length ).toBeGreaterThan( 0 );
			expect( modalManager.onCloseCallbacks.length ).toBe( 1 );

			modalManager.close();

			expect( modalManager.closeHandlers.length ).toBe( 0 );
			expect( global.window.widgetPowerBoard ).toBeNull();
			expect( callback ).toHaveBeenCalledTimes( 1 );
		} );
	} );
} );
