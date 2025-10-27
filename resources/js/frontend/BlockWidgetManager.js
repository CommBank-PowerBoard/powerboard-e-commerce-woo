/**
 * BlockWidgetManager
 * Handles PowerBoard widget initialization, lifecycle, and event management
 */

import {
	ERROR_MESSAGES,
	TEXT_DOMAIN
} from './constants.js';
import { __ } from '@wordpress/i18n';

export class BlockWidgetManager {
	constructor( modalManager, dataService, settings ) {
		this.modalManager = modalManager;
		this.dataService  = dataService;
		this.settings     = settings;
		this.jQuery       = window.jQuery;

		// Promise resolver for payment completion
		this.paymentPromiseResolve = null;
	}

	/**
	 * Sets the payment promise resolver
	 *
	 * @param {Function} resolver - Promise resolver function
	 */
	setPaymentPromiseResolver( resolver ) {
		this.paymentPromiseResolve = resolver;
	}

	/**
	 * Initializes the PowerBoard widget in the modal
	 *
	 * @returns {Promise<void>}
	 */
	async initializeMasterWidget() {
		try {
			const response = await this.dataService.createChargeIntent();
			this.initializeWidget( response );
		} catch ( error ) {
			this.modalManager.showError( error.message );

			// Handle promise resolve failure
			this.handlePromiseResolveFailure(
				__( ERROR_MESSAGES.WIDGET_FAILURE, TEXT_DOMAIN )
			);
		}
	}

	/**
	 * Initializes the PowerBoard widget with response data
	 *
	 * @param {Object} response - API response with widget data
	 */
	initializeWidget( response ) {
		this.modalManager.showWidget();

		window.widgetPowerBoard = new window.cba.Checkout(
			'#powerboard-modal-widget-wrapper',
			response.data.token
		);
		window.widgetPowerBoard.setEnv( this.settings.environment );

		this.setupWidgetEventHandlers();
	}

	/**
	 * Sets up PowerBoard widget event handlers
	 */
	setupWidgetEventHandlers() {
		// Handle payment success
		window.widgetPowerBoard.onPaymentSuccessful(
			async( data ) => {
				let paymentSuccessResponse = await this.dataService.onPaymentSuccessful( data );

				if ( this.paymentPromiseResolve ) {
					this.paymentPromiseResolve(
						{
							success: true,
							redirectUrl: data.success_url ?? null,
							payment_data: data
						}
					);
					this.paymentPromiseResolve = null;
				} else {
					if ( paymentSuccessResponse.data.success_url ){
						window.location.href = paymentSuccessResponse.data.success_url;
					}
				}


				this.modalManager.close();
			}
		);

		// Handle payment expiry
		window.widgetPowerBoard.onPaymentExpired(
			( data ) => {
				this.dataService.onPaymentExpired( data );
				this.handlePromiseResolveFailure(
					__( ERROR_MESSAGES.PAYMENT_EXPIRED, TEXT_DOMAIN )
				);
				this.modalManager.close();
			}
		);

		// Handle payment failure
		window.widgetPowerBoard.onPaymentFailure(
			( data ) => {
				this.dataService.onPaymentFailure( data );
				this.handlePromiseResolveFailure(
					__( ERROR_MESSAGES.PAYMENT_FAILED, TEXT_DOMAIN )
				);

				this.modalManager.close();
			}
		);
	}

	/**
	 * Handle promise resolve failure
	 *
	 * @param {string} message - Error message
	 */
	handlePromiseResolveFailure( message ) {
		if ( this.paymentPromiseResolve ) {
			this.paymentPromiseResolve(
				{
					success: false,
					message: message
				}
			);
			this.paymentPromiseResolve = null;
		}
	}

	/**
	 * Clean up widget resources
	 */
	cleanup() {
		if ( window.widgetPowerBoard ) {
			window.widgetPowerBoard = null;
		}
		this.paymentPromiseResolve = null;
	}
}
