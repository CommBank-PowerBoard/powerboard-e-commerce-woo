/**
 * BlockModalManager
 * Handles PowerBoard payment modal display and interactions for block checkout
 */

import { MODAL_IDS, SELECTORS, MODAL_AUTO_CLOSE_DELAY, ERROR_MESSAGES } from './constants.js';

export class BlockModalManager {
	constructor( dataService ) {
		this.isModalOpen      = false;
		this.closeHandlers    = [];
		this.onCloseCallbacks = []; // Store callbacks for when modal closes
		this.dataService      = dataService;
	}

	/**
	 * Shows the payment modal with loading state
	 *
	 * @returns {boolean} True if modal was shown successfully
	 */
	show() {
		const modal = document.getElementById( MODAL_IDS.MODAL );
		if ( !modal ) {
			return false;
		}

		// Reset modal to loading state
		this.resetToLoadingState();

		// Show modal
		modal.style.display          = 'block';
		document.body.style.overflow = 'hidden';
		this.isModalOpen             = true;

		// Setup close handlers
		this.setupCloseHandlers( modal );

		return true;
	}

	/**
	 * Closes the payment modal and cleans up
	 *
	 * @param {boolean} userInitiated - Whether the close was initiated by user action
	 * @param {String|null} errorReason - Error message to report for cause of close
	 */
	close( userInitiated= false, errorReason = null ) {
		const modal = document.getElementById( MODAL_IDS.MODAL );
		if ( modal ) {
			modal.style.display = 'none';
		}

		document.body.style.overflow = '';
		this.isModalOpen             = false;

		// Clean up widget
		if ( window.widgetPowerBoard ) {
			window.widgetPowerBoard = null;
		}

		// If not default values, process as cancellation
		if ( userInitiated !== false || errorReason !== null ) {
			this.dataService.onPaymentCancelled(
				userInitiated,
				errorReason
			);
		}

		// Trigger close callbacks
		this.triggerCloseCallbacks();

		// Remove event listeners
		this.removeCloseHandlers();
	}

	/**
	 * Shows error state in modal
	 *
	 * @param {?string} errorMessage - Optional custom error message to show in modal
	 */
	showError( errorMessage = null ) {
		const modalLoading = document.getElementById( MODAL_IDS.LOADING );
		const modalWidget = document.getElementById( MODAL_IDS.WIDGET );
		const modalError = document.getElementById( MODAL_IDS.ERROR );

		if ( modalLoading ) {modalLoading.style.display = 'none';}
		if ( modalWidget ) {modalWidget.style.display = 'none';}
		if ( modalError ) {
			modalError.style.display = 'block';

			// Update error message if custom message provided
			if ( errorMessage ) {
				const errorElement = modalError.querySelector( '.power-board-validation-error' );
				if ( errorElement ) {
					errorElement.textContent = errorMessage;
				}
			}
		}

		// Auto-close modal after showing error
		setTimeout(
			() => {
				this.close( false, errorMessage );
			},
			MODAL_AUTO_CLOSE_DELAY
		);
	}

	/**
	 * Shows widget container and hides loading state
	 */
	showWidget() {
		const modalLoading = document.getElementById( MODAL_IDS.LOADING );
		const modalWidget = document.getElementById( MODAL_IDS.WIDGET );
		const modalError = document.getElementById( MODAL_IDS.ERROR );

		if ( modalLoading ) {modalLoading.style.display = 'none';}
		if ( modalWidget ) {
			modalWidget.style.display = 'block';
			modalWidget.classList.add( 'active' );
		}
		if ( modalError ) {modalError.style.display = 'none';}
	}

	/**
	 * Checks if modal is currently open
	 *
	 * @returns {boolean}
	 */
	isOpen() {
		return this.isModalOpen;
	}

	/**
	 * Resets modal to loading state
	 *
	 * @private
	 */
	resetToLoadingState() {
		const modalLoading = document.getElementById( MODAL_IDS.LOADING );
		const modalWidget = document.getElementById( MODAL_IDS.WIDGET );
		const modalError = document.getElementById( MODAL_IDS.ERROR );

		if ( modalLoading ) {modalLoading.style.display = 'block';}
		if ( modalWidget ) {
			modalWidget.style.display = 'none';
			modalWidget.classList.remove( 'active' );
		}
		if ( modalError ) {modalError.style.display = 'none';}
	}

	/**
	 * Sets up close handlers for the modal
	 *
	 * @param {Element} modal - The modal element
	 * @private
	 */
	setupCloseHandlers( modal ) {
		// Close button handler
		const closeBtn = modal.querySelector( SELECTORS.MODAL_CLOSE );
		if ( closeBtn ) {
			const closeBtnHandler = () => this.handleUserClose();
			closeBtn.addEventListener( 'click', closeBtnHandler );
			this.closeHandlers.push( {
				element: closeBtn,
				event: 'click',
				handler: closeBtnHandler
			} );
		}

		// Outside click handler
		const outsideClickHandler = ( e ) => {
			if ( e.target === modal ) {
				this.handleUserClose();
			}
		};
		modal.addEventListener( 'click', outsideClickHandler );
		this.closeHandlers.push( {
			element: modal,
			event: 'click',
			handler: outsideClickHandler
		} );

		// Escape key handler
		const escapeKeyHandler = ( e ) => {
			if ( e.key === 'Escape' && this.isModalOpen ) {
				this.handleUserClose();
			}
		};
		document.addEventListener( 'keydown', escapeKeyHandler );
		this.closeHandlers.push( {
			element: document,
			event: 'keydown',
			handler: escapeKeyHandler
		} );
	}

	/**
	 * Removes all close handlers
	 *
	 * @private
	 */
	removeCloseHandlers() {
		this.closeHandlers.forEach( ( { element, event, handler } ) => {
			element.removeEventListener( event, handler );
		} );
		this.closeHandlers = [];
	}

	/**
	 * Shows confirmation dialog before closing modal
	 *
	 * @returns {boolean} True if user confirmed close, false otherwise
	 */
	showCloseConfirmation() {
		return window.confirm( ERROR_MESSAGES.MODAL_CLOSE_CONFIRMATION_MESSAGE );
	}

	/**
	 * Handles user-initiated close with confirmation
	 */
	handleUserClose() {
		if ( this.showCloseConfirmation() ) {
			this.close( true );
		}
	}

	/**
	 * Registers a callback to be called when modal closes
	 *
	 * @param {Function} callback - Function to call when modal closes
	 * @returns {Function} Unsubscribe function
	 */
	onClose( callback ) {
		if ( typeof callback === 'function' ) {
			this.onCloseCallbacks.push( callback );

			// Return unsubscribe function
			return () => {
				const index = this.onCloseCallbacks.indexOf( callback );
				if ( index > -1 ) {
					this.onCloseCallbacks.splice( index, 1 );
				}
			};
		}
		return () => {};
	}

	/**
	 * Triggers all registered close callbacks
	 *
	 * @private
	 */
	triggerCloseCallbacks() {
		this.onCloseCallbacks.forEach( callback => {
			try {
				callback();
			} catch ( error ) {
				// Error in modal close callback
			}
		} );
	}
}
