/**
 * BlockDataService
 * Handles API calls and data processing for PowerBoard block checkout
 */

import { AJAX_ENDPOINTS, ERROR_MESSAGES } from './constants.js';

export class BlockDataService {
	constructor( store, cart, settings ) {
		this.store    = store;
		this.cart     = cart;
		this.settings = settings;
		this.jQuery   = window.jQuery;
	}

	/**
	 * Creates a charge intent for the payment
	 *
	 * @returns {Promise<Object>} The API response
	 */
	async createChargeIntent() {
		const data = {
			_wpnonce: this.getPaymentData()._wpnonce_intent,
			order_id: this.getOrderId(),
			total: this.cart.getCartTotals(),
			address: this.cart.getCustomerData().billingAddress
		};

		return new Promise(
			( resolve, reject ) => {
				this.jQuery.ajax(
					{
						url: AJAX_ENDPOINTS.CREATE_INTENT,
						type: 'POST',
						data: data,
						success: ( response ) => {
							if ( response.success ) {
								resolve( response );
							} else {
								reject(
									new Error(
										response.data?.message || 'Failed to create charge intent'
									)
								);
							}
						},
						error: ( xhr, status, error ) => {
							reject( new Error( `Network error: ${error}` ) );
						}
					}
				);
			}
		);
	}

	/**
	 * Notify backend of successful payment via AJAX
	 *
	 * @param {Object} responseData - Response data from widget
	 * @returns {Promise<Object>} The API response
	 */
	async onPaymentSuccessful( responseData ) {
		const chargeId 		   = responseData?.charge_id;
		const widgetEventNonce = this.getPaymentData()._wpnonce_widget_event;

		const data = {
			_wpnonce: widgetEventNonce,
			order_id: this.getOrderId(),
			charge_id: chargeId,
			payment_data: responseData
		};

		return new Promise(
			( resolve, reject ) => {
				this.jQuery.ajax(
					{
						url: AJAX_ENDPOINTS.PAYMENT_SUCCESS,
						type: 'POST',
						data: data,
						success: ( response ) => {
							if ( response.success ) {
								resolve( response );
							} else {
								reject(
									new Error(
										response.data?.message || 'Backend notification failed'
									)
								);
							}
						},
						error: ( xhr, status, error ) => {
							reject( new Error( `Network error: ${error}` ) );
						}
					}
				);
			}
		);
	}

	/**
	 * Notify backend of failed payment via AJAX
	 *
	 * @param {Object} responseData - Response data from widget
	 * @returns {Promise<void>} The API response
	 */
	async onPaymentFailure( responseData ) {
		const widgetEventNonce = this.getPaymentData()._wpnonce_widget_event;

		const data = {
			_wpnonce: widgetEventNonce,
			order_id: this.getOrderId(),
			payment_data: responseData
		};

		return new Promise(
			( resolve, reject ) => {
				this.jQuery.ajax(
					{
						url: AJAX_ENDPOINTS.PAYMENT_FAILURE,
						type: 'POST',
						data: data,
						success: ( response ) => {
							if ( response.success ) {
								resolve();
							} else {
								reject();
							}
						},
						error: ( xhr, status, error ) => {
							reject( new Error( `Network error: ${error}` ) );
						}
					}
				);
			}
		);
	}

	/**
	 * Notify backend of failed session expired via AJAX
	 *
	 * @param {Object} responseData - Response data from widget
	 * @returns {Promise<void>} The API response
	 */
	async onPaymentExpired( responseData ) {
		const widgetEventNonce = this.getPaymentData()._wpnonce_widget_event;

		const data = {
			_wpnonce: widgetEventNonce,
			order_id: this.getOrderId(),
			payment_data: responseData
		};

		return new Promise(
			( resolve, reject ) => {
				this.jQuery.ajax(
					{
						url: AJAX_ENDPOINTS.PAYMENT_EXPIRED,
						type: 'POST',
						data: data,
						success: ( response ) => {
							if ( response.success ) {
								resolve();
							} else {
								reject();
							}
						},
						error: ( xhr, status, error ) => {
							reject( new Error( `Network error: ${error}` ) );
						}
					}
				);
			}
		);
	}

	/**
	 * Notify backend of payment cancellation via AJAX
	 *
	 * @param {Boolean} isUserInitiated - Is this close user initiated
	 * @param {String|null} errorMessage - Error message for cause of close
	 * @returns {Promise<void>} The API response
	 */
	async onPaymentCancelled( isUserInitiated , errorMessage = null ) {
		const widgetEventNonce = this.getPaymentData()._wpnonce_widget_event;

		const data = {
			_wpnonce: widgetEventNonce,
			order_id: this.getOrderId(),
			user_cancelled: isUserInitiated,
			error_message: isUserInitiated !== false ? ERROR_MESSAGES.USER_CANCELLED : errorMessage
		};

		return new Promise(
			( resolve, reject ) => {
				this.jQuery.ajax(
					{
						url: AJAX_ENDPOINTS.PAYMENT_CANCELLED,
						type: 'POST',
						data: data,
						success: ( response ) => {
							if ( response.success ) {
								resolve();
							} else {
								reject();
							}
						},
						error: ( xhr, status, error ) => {
							reject( new Error( `Network error: ${error}` ) );
						}
					}
				);
			}
		);
	}

	/**
	 * Processes payment result after widget completion
	 *
	 * @param {string|null} orderId - Order ID
	 * @returns {Promise<Object>} The payment processing result
	 */
	async processSuccessfulOrder( orderId = null ) {
		const data = {
			_wpnonce: this.getPaymentData()._wpnonce_success_order,
			order_id: orderId
		};

		return new Promise(
			( resolve, reject ) => {
				this.jQuery.ajax(
					{
						url: AJAX_ENDPOINTS.ORDER_SUCCESS,
						method: 'POST',
						data: data,
						success: ( response ) => {
							resolve( response );
						},
						error: ( xhr, status, error ) => {
							reject( new Error( `Payment processing failed: ${error}` ) );
						}
					}
				);
			}
		);
	}

	/**
	 * Gets the order ID from various sources
	 *
	 * @returns {number|null} The order ID if available
	 */
	getOrderId() {
		try {
			return this.orderId || this.store.getOrderId() || null;
		} catch ( error ) {
			return this.orderId || null;
		}
	}

	/**
	 * Sets the order ID for later use
	 *
	 * @param {number} orderId - The order ID to store
	 */
	setOrderId( orderId ) {
		this.orderId = orderId;
	}

	/**
	 * Sets payment data
	 *
	 * @param {Object} paymentData - Payment data to store
	 */
	setPaymentData( paymentData ) {
		this.paymentData = paymentData || {};
	}

	/**
	 * Gets stored payment data
	 *
	 * @returns {Object|null} Parsed payment data or null
	 */
	getPaymentData() {
		return this.paymentData;
	}
}
