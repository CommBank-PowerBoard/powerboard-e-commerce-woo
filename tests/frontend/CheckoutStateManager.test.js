/**
 * Unit tests for CheckoutStateManager class
 */

const { CheckoutStateManager, CONSTANTS } = require( '../../assets/js/frontend/classic-form.js' );

describe(
	'CheckoutStateManager',
	() => {
		let stateManager;
		let mockJQuery;
		let mockForm,
			mockPlaceOrderButton;
		beforeEach(
			() => {
			// Mock jQuery
				const mockEmptyAppend = jest.fn();
				const mockEmpty       = jest.fn( () => ( { append: mockEmptyAppend } ) );
				mockJQuery            = jest.fn(
					() => ( {
						first: jest.fn(
							() => ( {
								length: 1,
								empty: mockEmpty
							} )
						),
						prepend: jest.fn(
							() => ( {
								find: jest.fn(
									() => ( {
										empty: mockEmpty
									} )
								)
							} )
						),
						empty: mockEmpty,
						ajax: jest.fn()
					} )
				);
				mockJQuery.ajax           = jest.fn();
				stateManager              = new CheckoutStateManager( mockJQuery );
				// Ensure global setup
				global.window.PowerBoardAjaxCheckout = {
					wpnonce_process_payment: 'test-nonce'
				};
				// Mock DOM elements
				mockForm             = {
					classList: {
						remove: jest.fn()
					}
				};
				mockPlaceOrderButton = {
					disabled: true,
					style: { opacity: '0.5' },
					textContent: 'Processing...',
					getAttribute: jest.fn().mockReturnValue( 'Place Order' )
				};
				// Mock document methods
				global.document.querySelector    = jest.fn( ( selector ) => {
					if ( selector === CONSTANTS.SELECTORS.CHECKOUT_FORM ) {
						return mockForm;
					}
					if ( selector === CONSTANTS.SELECTORS.PLACE_ORDER_BUTTON ) {
						return mockPlaceOrderButton;
					}
					return null;
				} );
				global.document.getElementById   = jest.fn( () => mockPlaceOrderButton );
				global.document.querySelectorAll = jest.fn(
					() => [
						{ remove: jest.fn() },
						{ remove: jest.fn() }
					]
				);
				// Mock window.scrollTo
				global.window.scrollTo = jest.fn();
			}
		);
		describe(
			'init',
			() => {
				it(
					'should initialize form reference',
					() => {
						stateManager.init();
						expect( global.document.querySelector ).toHaveBeenCalledWith( CONSTANTS.SELECTORS.CHECKOUT_FORM );
						expect( stateManager.form ).toBe( mockForm );
					}
				);
			}
		);
		describe(
			'reset',
			() => {
				beforeEach(
					() => {
						stateManager.init();
					}
				);
				it(
					'should re-enable place order button',
					() => {
						stateManager.reset();
						expect( mockPlaceOrderButton.disabled ).toBe( false );
						expect( mockPlaceOrderButton.style.opacity ).toBe( '' );
						expect( mockPlaceOrderButton.getAttribute ).toHaveBeenCalledWith( 'data-value' );
						expect( mockPlaceOrderButton.textContent ).toBe( 'Place Order' );
					}
				);
				it(
					'should remove processing classes from form',
					() => {
						stateManager.reset();
						expect( mockForm.classList.remove ).toHaveBeenCalledWith( CONSTANTS.CSS_CLASSES.PROCESSING );
						expect( mockForm.classList.remove ).toHaveBeenCalledWith( CONSTANTS.CSS_CLASSES.BLOCK_UI );
						expect( mockForm.classList.remove ).toHaveBeenCalledWith( CONSTANTS.CSS_CLASSES.BLOCK_OVERLAY );
					}
				);
				it(
					'should remove loading overlays',
					() => {
						const mockOverlays               = [
							{ remove: jest.fn() },
							{ remove: jest.fn() }
						];
						global.document.querySelectorAll = jest.fn( () => mockOverlays );
						stateManager.reset();
						mockOverlays.forEach(
							overlay => {
								expect( overlay.remove ).toHaveBeenCalled();
							}
						);
					}
				);
				it(
					'should remove processing messages',
					() => {
						const mockMessages               = [
							{ remove: jest.fn() },
							{ remove: jest.fn() }
						];
						global.document.querySelectorAll = jest.fn()
							.mockReturnValueOnce( [] ) // First call for block overlays
							.mockReturnValueOnce( mockMessages ); // Second call for processing messages
						stateManager.reset();
						mockMessages.forEach(
							msg => {
								expect( msg.remove ).toHaveBeenCalled();
							}
						);
					}
				);
				it(
					'should handle missing place order button gracefully',
					() => {
						global.document.querySelector = jest.fn( ( selector ) => {
							if ( selector === CONSTANTS.SELECTORS.CHECKOUT_FORM ) {
								return mockForm;
							}
							return null; // Return null for place order button
						} );
						expect( () => stateManager.reset() ).not.toThrow();
					}
				);
				it(
					'should handle missing form gracefully',
					() => {
						stateManager.form = null;
						expect( () => stateManager.reset() ).not.toThrow();
					}
				);
			}
		);
	}
);
