/**
 * BlockCheckoutHandler
 * Main business logic coordinator for PowerBoard block checkout
 */

import { BlockModalManager } from './BlockModalManager.js';
import { BlockDataService } from './BlockDataService.js';
import { BlockValidationService } from './BlockValidationService.js';
import { BlockWidgetManager } from './BlockWidgetManager.js';
import {
	ERROR_MESSAGES,
	MODAL_REDIRECT,
	TEXT_DOMAIN
} from './constants.js';
import { __ } from '@wordpress/i18n';

export class BlockCheckoutHandler {
	constructor( store, cart, settings, responseTypes, noticeContexts ) {
		this.store          = store;
		this.cart           = cart;
		this.settings       = settings;
		this.responseTypes  = responseTypes;
		this.noticeContexts = noticeContexts;

		// Initialize services
		this.dataService       = new BlockDataService( store, cart, settings );
		this.modalManager      = new BlockModalManager( this.dataService );
		this.validationService = new BlockValidationService( cart );
		this.widgetManager     = new BlockWidgetManager(
			this.modalManager,
			this.dataService,
			settings
		);

		// Event handlers storage
		this.eventHandlers = new Map();
	}

	/**
	 * Processes checkout validation for WooCommerce Blocks
	 *
	 * @returns {Object} Validation result for emitResponse
	 */
	processCheckoutValidation() {
		const powerBoardValidation = this.validationService.validatePowerBoardRequirements();

		if ( !powerBoardValidation.isValid ) {
			return {
				type: this.responseTypes.ERROR,
				message: __( powerBoardValidation.error, TEXT_DOMAIN )
			};
		}

		return { type: this.responseTypes.SUCCESS };
	}

	/**
	 * Processes the payment setup for block checkout
	 *
	 * @returns {Object} Payment setup result
	 */
	processPaymentSetup() {
		// Let WooCommerce handle basic field validation during payment setup phase
		// PowerBoard-specific validation will occur before showing the modal
		return {
			type: this.responseTypes.SUCCESS,
			meta: {
				paymentMethodData: {
					powerboard_redirect: MODAL_REDIRECT
				}
			}
		};
	}

	/**
	 * Handles checkout success event from WooCommerce blocks
	 *
	 * @param {Object} checkoutData - Checkout success data
	 * @returns {Promise<Object|boolean>} Success response or true to continue
	 */
	async handleCheckoutSuccess( checkoutData ) {
		try {
			const paymentDetails = checkoutData.processingResponse?.paymentDetails;
			const orderId = paymentDetails?.order_id;
			this.dataService.setPaymentData( paymentDetails );
			this.dataService.setOrderId( orderId );

			if ( paymentDetails?.powerboard_redirect === MODAL_REDIRECT ) {
				// Backend validation has already passed if we reach this point
				// Show modal and wait for payment result
				const modalResult = await this.showModalAndProcessPayment();

				if ( modalResult.success ) {
					// Call process_payment_result to complete the payment
					const response = await this.dataService.processSuccessfulOrder( orderId );

					if ( response.success && response.data?.redirect_url ) {
						// Return redirect response to let WooCommerce handle the redirect
						return {
							type: this.responseTypes.SUCCESS,
							redirectUrl: response.data.redirect_url
						};
					} else {
						return this.createCheckoutErrorObject(
							__( ERROR_MESSAGES.PAYMENT_FAILED, TEXT_DOMAIN )
						);
					}
				} else {
					return this.createCheckoutErrorObject(
						modalResult.message ||
						__( ERROR_MESSAGES.PAYMENT_FAILED, TEXT_DOMAIN )
					);
				}
			}

			// Not our payment method or no modal needed, continue normally
			return true;

		} catch ( error ) {
			return this.createCheckoutErrorObject(
				__( ERROR_MESSAGES.SOMETHING_WRONG, TEXT_DOMAIN )
			);
		}
	}

	/**
	 * Handle returning promise resolve
	 *
	 * @param {String} message - Message to be shown for error
	 * @returns {Object} - Error object for Woo to use
	 * @private
	 */
	createCheckoutErrorObject( message ) {
		return {
			type: this.responseTypes.ERROR,
			message: message,
			messageContext: this.noticeContexts.PAYMENTS,
			retry: true
		};
	}

	/**
	 * Shows modal and processes payment - returns Promise that resolves when payment completes
	 *
	 * @returns {Promise<Object>} Payment result with success/failure status
	 * @private
	 */
	async showModalAndProcessPayment() {
		return new Promise(
			( resolve, reject ) => {
				// Set the payment promise resolver in widget manager
				this.widgetManager.setPaymentPromiseResolver( resolve );

				// Show modal
				if ( !this.modalManager.show() ) {
					reject( new Error( __( ERROR_MESSAGES.MODAL_LOAD_FAILED, TEXT_DOMAIN ) ) );
					this.widgetManager.setPaymentPromiseResolver( null );
					return;
				}

				// Add modal close handlers for handling Promise on user close
				this.setupOnCloseModalHandlers();

				// Initialize payment widget through widget manager
				this.widgetManager.initializeMasterWidget();
			}
		);
	}

	/**
	 * Handle when modal is closed without promise handled
	 * This is then identified as a user cancellation
	 *
	 * @private
	 */
	setupOnCloseModalHandlers() {
		// Handle modal close (user cancellation)
		this.modalManager.onClose(
			() => {
				this.widgetManager.handlePromiseResolveFailure(
					__( ERROR_MESSAGES.USER_CANCELLED, TEXT_DOMAIN )
				);
			}
		);
	}

	/**
	 * Cleans up the handler and its resources
	 */
	cleanup() {
		this.modalManager.close();
		this.widgetManager.cleanup();
	}

	/**
	 * Gets the current state of the checkout handler
	 *
	 * @returns {Object} Current state
	 */
	getState() {
		return {
			isModalOpen: this.modalManager.isOpen()
		};
	}
}
