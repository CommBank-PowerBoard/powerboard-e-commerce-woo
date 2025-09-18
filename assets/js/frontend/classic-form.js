/**
 * PowerBoard Classic Checkout Form Handler
 * Handles modal display, and payment processing for WooCommerce checkout
 */

// Constants
const CONSTANTS = {
	PAYMENT_METHOD: 'power_board',
	AJAX_ENDPOINTS: {
		CHECKOUT: '/?wc-ajax=checkout',
		CREATE_INTENT: '/?wc-ajax=power-board-create-charge-intent',
		PAYMENT_SUCCESS: '/?wc-ajax=power-board-payment-successful',
		PAYMENT_FAILURE: '/?wc-ajax=power-board-payment-failure',
		PAYMENT_EXPIRED: '/?wc-ajax=power-board-payment-expired',
		PAYMENT_CANCELLED: '/?wc-ajax=power-board-payment-cancelled',
		ORDER_SUCCESS: '/?wc-ajax=power-board-process-successful-order'
	},
	SELECTORS: {
		CHECKOUT_FORM: 'form[name="checkout"]',
		SHIP_TO_DIFFERENT: '[name="ship_to_different_address"]',
		PAYMENT_METHOD_RADIO: 'input[name="payment_method"]:checked',
		SETTINGS_INPUT: '#classic-power_board-settings',
		BILLING_EMAIL_INPUT: 'billing_email',
		BILLING_FIRST_NAME_INPUT: 'billing_first_name',
		BILLING_LAST_NAME_INPUT: 'billing_last_name',
		BILLING_ADDRESS_1_INPUT: 'billing_address_1',
		BILLING_CITY_INPUT: 'billing_city',
		BILLING_STATE_INPUT: 'billing_state',
		BILLING_COUNTRY_INPUT: 'billing_country',
		BILLING_POSTCODE_INPUT: 'billing_postcode',
		BILLING_PHONE_INPUT: 'billing_phone',
		MODAL_CLOSE_BUTTON: '.powerboard-modal-close',
		WOOCOMMERCE_CHECKOUT: 'form.woocommerce-checkout',
		PLACE_ORDER_BUTTON: '#place_order',
		BLOCK_UI_OVERLAY: '.blockUI.blockOverlay',
		PROCESSING_MESSAGES: '.woocommerce-checkout-processing',
		WIDGET_WRAPPER: '#powerboard-modal-widget-wrapper'
	},
	MODAL_IDS: {
		MODAL: 'powerboard-payment-modal',
		LOADING: 'powerboard-modal-loading',
		WIDGET: 'powerboard-modal-widget-wrapper',
		ERROR: 'powerboard-modal-error'
	},
	CSS_CLASSES: {
		WC_INVALID: 'woocommerce-invalid woocommerce-invalid-required-field',
		PROCESSING: 'processing',
		BLOCK_UI: 'blockUI',
		BLOCK_OVERLAY: 'blockOverlay',
		ACTIVE: 'active'
	},
	ERROR_MESSAGES: {
		POWERBOARD_REQUIRED_FIELDS:
			'To complete your checkout with PowerBoard, ' +
			'please provide the following required fields:',
		MODAL_NOT_FOUND: 'PowerBoard: Modal element not found!',
		PAYMENT_FAILED: 'Payment failed. Please try again.',
		PAYMENT_PROCESSING_FAILED: 'Payment processing failed.',
		PAYMENT_PROCESSING_RETRY: 'Payment processing failed. Please try again.',
		PAYMENT_EXPIRED: 'Your payment session has expired. Please retry your payment.',
		MISSING_REQUIRED_FIELDS: 'PowerBoard: Missing required fields: ',
		FAILED_CREATE_CHARGE_INTENT: 'Failed to create charge intent.',
		AJAX_ERROR_PREFIX: 'AJAX error: ',
		PAYMENT_FAILURE_PROCESSING_FAILED: 'Payment failure processing failed.',
		PAYMENT_EXPIRED_PROCESSING_FAILED: 'Payment expired processing failed.',
		PAYMENT_CANCELLED_PROCESSING_FAILED: 'Payment cancelled processing failed.',
		FAILED_INITIALIZE_WIDGET: 'Failed to initialize widget:',
		POWERBOARD_FAILED_PARSE_SETTINGS: 'PowerBoard: Failed to parse settings.',
		PAYMENT_DATA_NOT_SET: 'Payment data not set.',
		USER_CANCELLED: 'Payment window closed by user',
		PAYMENT_CANCELLED_ERROR: 'An unexpected error occurred while rendering payment options.',
		MODAL_CLOSE_CONFIRMATION_MESSAGE:
			'If you’ve submitted your payment, ' +
			'please wait for a confirmation message before closing the window. ' +
			'Do you still wish to close?'
	},
	RESULTS: {
		SUCCESS: 'success'
	},
	VALUES: {
		DISPLAY_BLOCK: 'block',
		DISPLAY_NONE: 'none',
		PLACE_ORDER_TEXT: 'Place Order',
		POWERBOARD_REDIRECT: 'powerboard_show_modal',
		OVERFLOW_HIDDEN: 'hidden',
		OVERFLOW_EMPTY: ''
	},
	CSS_VALUES: {
		POWERBOARD_MODAL_OPENED: 'powerboard-modal-opened'
	},
	FORM_FIELDS: [
		'first_name', 'last_name', 'country', 'address_1', 'address_2',
		'city', 'state', 'postcode', 'email', 'company', 'phone'
	],
	PREFIXES: {
		BILLING: 'billing_',
		SHIPPING: 'shipping_'
	},
	TIMEOUTS: {
		AUTO_CLOSE: 3000,
		WIDGET_PROCESSING: 500
	},
	EVENTS: {
		BEFOREUNLOAD: 'beforeunload'
	},
	ATTRIBUTES: {
		DATA_VALUE: 'data-value'
	},
	WIDGET_PROPERTIES: {
		POWERBOARD_WIDGET: 'widgetPowerBoard'
	},
	HTTP_METHODS: {
		POST: 'POST'
	},
	NOTICE_TYPES: {
		ERROR: 'error'
	},
	ADDRESS_TYPES: {
		SHIPPING: 'shipping_address',
		BILLING: 'address'
	},
	REQUIRED_FIELDS: [
		'order_id', '_wpnonce_intent', '_wpnonce_widget_event', '_wpnonce_success_order'
	],
	SEPARATORS: {
		COMMA_SPACE: ', '
	},
	DEFAULT_VALUES: {
		EMPTY_STRING: '',
		UNDEFINED: 'undefined'
	},
	FALLBACK_ERRORS: {
		WIDGET_INITIALIZATION_FAILED: 'widget_initialization_failed'
	}
};

const GET_BILLING_FIELD_NICE_NAME = {
	[ CONSTANTS.SELECTORS.BILLING_EMAIL_INPUT ]: 'Email',
	[ CONSTANTS.SELECTORS.BILLING_FIRST_NAME_INPUT ]: 'First name',
	[ CONSTANTS.SELECTORS.BILLING_LAST_NAME_INPUT ]: 'Last name',
	[ CONSTANTS.SELECTORS.BILLING_ADDRESS_1_INPUT ]: 'Street address',
	[ CONSTANTS.SELECTORS.BILLING_CITY_INPUT ]: 'City',
	[ CONSTANTS.SELECTORS.BILLING_STATE_INPUT ]: 'State',
	[ CONSTANTS.SELECTORS.BILLING_COUNTRY_INPUT ]: 'Country',
	[ CONSTANTS.SELECTORS.BILLING_POSTCODE_INPUT ]: 'Postcode'
};

/**
 * Modal Manager
 * Handles PowerBoard payment modal display and interactions
 */
class ModalManager {
	constructor( stateManager, dataService ) {
		this.stateManager = stateManager;
		this.dataService  = dataService;
		this.escapeKeyHandler = null;
	}

	/**
	 * Shows the payment modal
	 */
	show() {
		const modal        = document.getElementById( CONSTANTS.MODAL_IDS.MODAL );
		const modalLoading = document.getElementById( CONSTANTS.MODAL_IDS.LOADING );
		const modalWidget  = document.getElementById( CONSTANTS.MODAL_IDS.WIDGET );
		const modalError   = document.getElementById( CONSTANTS.MODAL_IDS.ERROR );

		if ( !modal ) {
			return false;
		}

		// Reset modal state
		if ( modalLoading ) {
			modalLoading.style.display = CONSTANTS.VALUES.DISPLAY_BLOCK;
		}
		if ( modalWidget ) {
			modalWidget.style.display = CONSTANTS.VALUES.DISPLAY_NONE;
			modalWidget.classList.remove( CONSTANTS.CSS_CLASSES.ACTIVE );
		}
		if ( modalError ) {
			modalError.style.display = CONSTANTS.VALUES.DISPLAY_NONE;
		}

		// Show modal
		modal.style.display          = CONSTANTS.VALUES.DISPLAY_BLOCK;
		document.body.style.overflow = CONSTANTS.VALUES.OVERFLOW_HIDDEN;

		// Remove WooCommerce beforeunload handler that causes Chrome popup
		if ( window.wc_checkout_form && window.wc_checkout_form.detachUnloadEventsOnSubmit ) {
			window.wc_checkout_form.detachUnloadEventsOnSubmit();
		}

		// Remove all beforeunload handlers to prevent Chrome popup
		window.onbeforeunload = null;

		// Also remove any jQuery-bound beforeunload handlers
		if ( window.jQuery ) {
			window.jQuery( window ).off( CONSTANTS.EVENTS.BEFOREUNLOAD );
		}

		const woocommerceCheckoutForm = document.querySelector(
			CONSTANTS.SELECTORS.WOOCOMMERCE_CHECKOUT
		);
		if ( woocommerceCheckoutForm ) {
			woocommerceCheckoutForm.classList.add( CONSTANTS.CSS_VALUES.POWERBOARD_MODAL_OPENED );
		}

		this.setupCloseHandlers( modal );

		return true;
	}

	/**
	 * Closes the payment modal
	 *
	 * @param {boolean} userInitiated - Whether the close was initiated by user action
	 * @param {String|null} errorReason - Error message to report for cause of close
	 */
	close( userInitiated= false, errorReason = null ) {
		const modal = document.getElementById( CONSTANTS.MODAL_IDS.MODAL );
		if ( modal ) {
			modal.style.display = CONSTANTS.VALUES.DISPLAY_NONE;
		}
		document.body.style.overflow = CONSTANTS.VALUES.OVERFLOW_EMPTY;

		// Remove all beforeunload handlers to prevent Chrome popup
		window.onbeforeunload = null;

		// Also remove any jQuery-bound beforeunload handlers
		if ( window.jQuery ) {
			window.jQuery( window ).off( CONSTANTS.EVENTS.BEFOREUNLOAD );
		}

		// Clean up widget
		if ( window[ CONSTANTS.WIDGET_PROPERTIES.POWERBOARD_WIDGET ] ) {
			window[ CONSTANTS.WIDGET_PROPERTIES.POWERBOARD_WIDGET ] = null;
		}

		const woocommerceCheckoutForm = document.querySelector(
			CONSTANTS.SELECTORS.WOOCOMMERCE_CHECKOUT
		);
		if ( woocommerceCheckoutForm ) {
			const modalOpenedClass = CONSTANTS.CSS_VALUES.POWERBOARD_MODAL_OPENED;
			woocommerceCheckoutForm.classList.remove( modalOpenedClass );
		}

		// Clean up escape key handler
		if ( this.escapeKeyHandler ) {
			document.removeEventListener( 'keydown', this.escapeKeyHandler );
			this.escapeKeyHandler = null;
		}

		// Reset checkout state to prevent form being stuck in loading state
		if ( this.stateManager ) {
			this.stateManager.reset();
		}

		// If not default values, process as cancellation
		if ( userInitiated !== false || errorReason !== null ) {
			this.dataService.onPaymentCancelled(
				userInitiated,
				errorReason
			).then( () => {
				if ( userInitiated ) {
					this.stateManager.showError( CONSTANTS.ERROR_MESSAGES.USER_CANCELLED );
				} else {
					this.stateManager.showError( CONSTANTS.ERROR_MESSAGES.PAYMENT_CANCELLED_ERROR );
				}
			} ).catch( () => {
				this.stateManager.showError(
					CONSTANTS.ERROR_MESSAGES.PAYMENT_CANCELLED_PROCESSING_FAILED
				);
			} );
		}
	}

	/**
	 * Shows confirmation dialog before closing modal
	 *
	 * @returns {boolean} True if user confirmed close, false otherwise
	 */
	showCloseConfirmation() {
		return window.confirm( CONSTANTS.ERROR_MESSAGES.MODAL_CLOSE_CONFIRMATION_MESSAGE );
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
	 * Shows error state in modal
	 *
	 * @param {string} errorReason - Reason for the error
	 */
	showError( errorReason ) {
		const modalLoading = document.getElementById( CONSTANTS.MODAL_IDS.LOADING );
		const modalWidget  = document.getElementById( CONSTANTS.MODAL_IDS.WIDGET );
		const modalError   = document.getElementById( CONSTANTS.MODAL_IDS.ERROR );

		if ( modalLoading ) {
			modalLoading.style.display = CONSTANTS.VALUES.DISPLAY_NONE;
		}
		if ( modalWidget ) {
			modalWidget.style.display = CONSTANTS.VALUES.DISPLAY_NONE;
		}
		if ( modalError ) {
			modalError.style.display = CONSTANTS.VALUES.DISPLAY_BLOCK;
		}

		// Auto-close modal after showing error
		setTimeout(
			() => {
				this.close( false, errorReason );
			},
			CONSTANTS.TIMEOUTS.AUTO_CLOSE
		);
	}

	/**
	 * Sets up modal close event handlers
	 *
	 * @param {HTMLElement} modal - The modal element
	 */
	setupCloseHandlers( modal ) {
		const closeBtn = modal.querySelector( CONSTANTS.SELECTORS.MODAL_CLOSE_BUTTON );
		if ( closeBtn ) {
			// user-initiated close with confirmation
			closeBtn.onclick = () => this.handleUserClose();
		}

		// Close on outside click with confirmation
		modal.onclick = ( e ) => {
			if ( e.target === modal ) {
				// user-initiated close with confirmation
				this.handleUserClose();
			}
		};

		// Escape key handler with confirmation
		this.escapeKeyHandler = ( e ) => {
			if ( e.key === 'Escape' ) {
				this.handleUserClose();
			}
		};

		document.addEventListener( 'keydown', this.escapeKeyHandler );
	}
}

/**
 * Checkout State Manager
 * Manages checkout form state and UI updates
 */
class CheckoutStateManager {
	constructor( $ ) {
		this.$    = $;
		this.form = null;
	}

	/**
	 * Initializes the checkout form reference
	 */
	init() {
		this.form = document.querySelector( CONSTANTS.SELECTORS.CHECKOUT_FORM );
	}

	/**
	 * Resets checkout form to editable state
	 */
	reset() {
		// Re-enable the place order button
		const placeOrderButton = document.querySelector( CONSTANTS.SELECTORS.PLACE_ORDER_BUTTON );
		if ( placeOrderButton ) {
			placeOrderButton.disabled      = false;
			placeOrderButton.style.opacity = CONSTANTS.DEFAULT_VALUES.EMPTY_STRING;
			const originalText = placeOrderButton.getAttribute( CONSTANTS.ATTRIBUTES.DATA_VALUE );
			placeOrderButton.textContent = originalText || CONSTANTS.VALUES.PLACE_ORDER_TEXT;
		}

		// Remove loading/processing classes from form
		if ( this.form ) {
			this.form.classList.remove( CONSTANTS.CSS_CLASSES.PROCESSING );
			this.form.classList.remove( CONSTANTS.CSS_CLASSES.BLOCK_UI );
			this.form.classList.remove( CONSTANTS.CSS_CLASSES.BLOCK_OVERLAY );
		}

		// Remove any WooCommerce loading overlays
		const blockOverlays = document.querySelectorAll( CONSTANTS.SELECTORS.BLOCK_UI_OVERLAY );
		blockOverlays.forEach( overlay => overlay.remove() );

		// Clear any loading messages
		const processingMessages = document.querySelectorAll(
			CONSTANTS.SELECTORS.PROCESSING_MESSAGES
		);
		processingMessages.forEach( msg => msg.remove() );
	}

	/**
	 * Shows error message at top of checkout
	 *
	 * @param {string} errorMessage - The error message to display
	 */
	showError( errorMessage ) {
		window.showNotice( [ errorMessage ], CONSTANTS.NOTICE_TYPES.ERROR );
	}

	/**
	 * Shows error message at top of checkout
	 *
	 * @param {string[]} errorMessages - The error message to display
	 */
	showErrorsList( errorMessages ) {
		window.showNotice( errorMessages, CONSTANTS.NOTICE_TYPES.ERROR );
	}
}

/**
 * Data Service
 * Handles data extraction and formatting for checkout
 */
class DataService {
	constructor( $ ) {
		this.$           = $;
		this.paymentData = null;
		this.orderId     = null;
	}

	/**
	 * Sets the payment data for the widget
	 *
	 * @param {PaymentSubmissionData} paymentData - Payment data object
	 */
	setPaymentData( paymentData ) {
		this.paymentData = paymentData;
		this.orderId     = paymentData.orderId;
	}

	/**
	 * Creates a charge intent
	 *
	 * @param billingAddress
	 * @returns {Promise} Promise that resolves with response data
	 */
	createChargeIntent( billingAddress ) {
		const data = {
			_wpnonce: this.paymentData.createIntentNonce,
			order_id: this.orderId,
			address: billingAddress
		};

		return new Promise( ( resolve, reject ) => {
			this.$.ajax( {
				url: CONSTANTS.AJAX_ENDPOINTS.CREATE_INTENT,
				type: CONSTANTS.HTTP_METHODS.POST,
				data: data,
				success: ( response ) => {
					if ( response.success ) {
						resolve( response );
					} else {
						const errorMessage = response.data?.message ||
							CONSTANTS.ERROR_MESSAGES.FAILED_CREATE_CHARGE_INTENT;
						reject( new Error( errorMessage ) );
					}
				},
				error: ( xhr, status, error ) => {
					reject( new Error( `${CONSTANTS.ERROR_MESSAGES.AJAX_ERROR_PREFIX}${error}` ) );
				}
			} );
		} );
	}

	/**
	 * Handles successful payment notification to backend
	 *
	 * @param {Object} responseData - Response data from widget
	 * @returns {Promise} Promise that resolves with response data
	 */
	onPaymentSuccessful( responseData ) {
		const data = {
			_wpnonce: this.paymentData.widgetEventNonce,
			payment_data: responseData,
			order_id: this.orderId
		};

		return new Promise( ( resolve, reject ) => {
			this.$.ajax( {
				url: CONSTANTS.AJAX_ENDPOINTS.PAYMENT_SUCCESS,
				method: CONSTANTS.HTTP_METHODS.POST,
				data: data,
				success: ( response ) => {
					if ( response.success ) {
						resolve( response );
					} else {
						const errorMessage = response.data?.message ||
							CONSTANTS.ERROR_MESSAGES.PAYMENT_PROCESSING_FAILED;
						reject( new Error( errorMessage ) );
					}
				},
				error: ( xhr, status, error ) => {
					reject( new Error( `${CONSTANTS.ERROR_MESSAGES.AJAX_ERROR_PREFIX}${error}` ) );
				}
			} );
		} );
	}

	/**
	 * Handles failed payment notification to backend
	 *
	 * @param {Object} responseData - Response data from widget
	 * @returns {Promise} Promise that resolves with response data
	 */
	onPaymentFailure( responseData ) {
		const data = {
			_wpnonce: this.paymentData.widgetEventNonce,
			payment_data: responseData,
			order_id: this.orderId
		};

		return new Promise( ( resolve, reject ) => {
			this.$.ajax( {
				url: CONSTANTS.AJAX_ENDPOINTS.PAYMENT_FAILURE,
				method: CONSTANTS.HTTP_METHODS.POST,
				data: data,
				success: ( response ) => {
					if ( response.success ) {
						resolve( response );
					} else {
						const errorMessage = response.data?.message ||
							CONSTANTS.ERROR_MESSAGES.PAYMENT_FAILURE_PROCESSING_FAILED;
						reject( new Error( errorMessage ) );
					}
				},
				error: ( xhr, status, error ) => {
					reject( new Error( `${CONSTANTS.ERROR_MESSAGES.AJAX_ERROR_PREFIX}${error}` ) );
				}
			} );
		} );
	}

	/**
	 * Handles payment session expired notification to backend
	 *
	 * @param {Object} responseData - Response data from widget
	 * @returns {Promise} Promise that resolves with response data
	 */
	onPaymentExpired( responseData ) {
		const data = {
			_wpnonce: this.paymentData.widgetEventNonce,
			payment_data: responseData,
			order_id: this.orderId
		};

		return new Promise( ( resolve, reject ) => {
			this.$.ajax( {
				url: CONSTANTS.AJAX_ENDPOINTS.PAYMENT_EXPIRED,
				method: CONSTANTS.HTTP_METHODS.POST,
				data: data,
				success: ( response ) => {
					if ( response.success ) {
						resolve( response );
					} else {
						const errorMessage = response.data?.message ||
							CONSTANTS.ERROR_MESSAGES.PAYMENT_EXPIRED_PROCESSING_FAILED;
						reject( new Error( errorMessage ) );
					}
				},
				error: ( xhr, status, error ) => {
					reject( new Error( `${CONSTANTS.ERROR_MESSAGES.AJAX_ERROR_PREFIX}${error}` ) );
				}
			} );
		} );
	}

	/**
	 * Handles payment cancelled notification to backend
	 *
	 * @param {Boolean} isUserInitiated - Is this close user initiated
	 * @param {String|null} errorMessage - Error message for cause of close
	 * @returns {Promise} Promise that resolves with response data
	 */
	onPaymentCancelled( isUserInitiated , errorMessage = null ) {
		const data = {
			_wpnonce: this.paymentData.widgetEventNonce,
			order_id: this.orderId,
			user_cancelled: isUserInitiated,
			error_message: errorMessage
		};

		return new Promise( ( resolve, reject ) => {
			this.$.ajax( {
				url: CONSTANTS.AJAX_ENDPOINTS.PAYMENT_CANCELLED,
				method: CONSTANTS.HTTP_METHODS.POST,
				data: data,
				success: ( response ) => {
					if ( response.success ) {
						resolve( response );
					} else {
						reject( new Error(
							response.data?.message ||
							CONSTANTS.ERROR_MESSAGES.PAYMENT_CANCELLED_PROCESSING_FAILED )
						);
					}
				},
				error: ( xhr, status, error ) => {
					reject( new Error( `${CONSTANTS.ERROR_MESSAGES.AJAX_ERROR_PREFIX}${error}` ) );
				}
			} );
		} );
	}


	/**
	 * Handles successful payment notification to backend
	 *
	 * @returns {Promise} Promise that resolves with response data
	 */
	processSuccessfulOrder() {
		const data = {
			_wpnonce: this.paymentData.orderSuccessNonce,
			order_id: this.orderId
		};

		return new Promise( ( resolve, reject ) => {
			this.$.ajax( {
				url: CONSTANTS.AJAX_ENDPOINTS.ORDER_SUCCESS,
				method: CONSTANTS.HTTP_METHODS.POST,
				data: data,
				success: ( response ) => {
					if ( response.success ) {
						resolve( response );
					} else {
						const errorMessage = response.data?.message ||
							CONSTANTS.ERROR_MESSAGES.PAYMENT_PROCESSING_FAILED;
						reject( new Error( errorMessage ) );
					}
				},
				error: ( xhr, status, error ) => {
					reject( new Error( `${CONSTANTS.ERROR_MESSAGES.AJAX_ERROR_PREFIX}${error}` ) );
				}
			} );
		} );
	}
}

class PaymentSubmissionData {
	constructor( response ) {
		this.validate( response );
		this.orderId           = response.order_id;
		this.createIntentNonce = response._wpnonce_intent;
		this.widgetEventNonce  = response._wpnonce_widget_event;
		this.orderSuccessNonce = response._wpnonce_success_order;
	}

	validate( response ) {
		const required = CONSTANTS.REQUIRED_FIELDS;

		const missing  = required.filter( field => !response[ field ] );
		if ( missing.length ) {
			const missingFieldsMsg = CONSTANTS.ERROR_MESSAGES.MISSING_REQUIRED_FIELDS;
			throw new Error(
				`${missingFieldsMsg}${
					missing.join(
						CONSTANTS.SEPARATORS.COMMA_SPACE
					)
				}`
			);
		}
	}
}

/**
 * Validation Service
 * Handles validation logic for checkout
 */
class ValidationService {
	constructor( $ ) {
		this.$ = $;
	}

	/**
	 * Validates the checkout form for PowerBoard payment
	 *
	 * @returns {Object} Validation result with success status and errors
	 */
	validateCheckoutForm() {
		const errors = [];

		// Check if PowerBoard payment method is selected
		const selectedPaymentMethod = this.$( CONSTANTS.SELECTORS.PAYMENT_METHOD_RADIO ).val();
		if ( selectedPaymentMethod !== CONSTANTS.PAYMENT_METHOD ) {
			return {
				success: true,
				errors: []
			};
		}

		// Validate required fields
		const invalidRequiredFields = this.getMissingRequiredFields();
		if ( invalidRequiredFields.length > 0 ) {
			const requiredFieldsMsg = CONSTANTS.ERROR_MESSAGES.POWERBOARD_REQUIRED_FIELDS;
			errors.push( `${requiredFieldsMsg} ${invalidRequiredFields}` );
		}

		return {
			success: errors.length === 0,
			errors: errors
		};
	}

	/**
	 * Checks if PowerBoard payment method is selected
	 *
	 * @returns {boolean} True if PowerBoard is selected
	 */
	isPowerBoardSelected() {
		const selectedPaymentMethod = this.$( CONSTANTS.SELECTORS.PAYMENT_METHOD_RADIO ).val();
		return selectedPaymentMethod === CONSTANTS.PAYMENT_METHOD;
	}

	/**
	 * Validates payment submission data
	 *
	 * @param {Object} response - Payment submission response
	 * @returns {Object} Validation result
	 */
	validatePaymentSubmissionData( response ) {
		try {
			new PaymentSubmissionData( response );
			return {
				success: true,
				errors: []
			};
		} catch ( error ) {
			return {
				success: false,
				errors: [ error.message ]
			};
		}
	}

	/**
	 * Gets list of form fields
	 *
	 * @returns {Array} Array of field names
	 */
	getFieldsList() {
		const fieldsNames = CONSTANTS.FORM_FIELDS;

		let result           = [];
		let shippingCheckbox = this.$( CONSTANTS.SELECTORS.SHIP_TO_DIFFERENT );
		let prefixes         = [ CONSTANTS.PREFIXES.BILLING ];

		if ( shippingCheckbox?.[ 0 ]?.checked ) {
			prefixes.push( CONSTANTS.PREFIXES.SHIPPING );
		}

		prefixes.forEach(
			prefix => {
				fieldsNames.forEach(
					field => {
						result.push( `${prefix}${field}` );
					}
				);
			}
		);

		return result;
	}

	/**
	 * Gets address data from form
	 *
	 * @param {boolean} returnJson - Whether to return JSON string
	 * @returns {Object|string} Address data object or JSON string
	 */
	getAddressData( returnJson = true ) {
		let fieldList = this.getFieldsList();
		let result    = {
			shipping_address: {},
			address: {}
		};

		fieldList.forEach(
			fieldName => {
				let element    = document.getElementById( fieldName );
				let value      = element ? element.value :
					CONSTANTS.DEFAULT_VALUES.EMPTY_STRING;
				let isShipping = fieldName.startsWith( CONSTANTS.PREFIXES.SHIPPING );
				const addressType = isShipping ? CONSTANTS.ADDRESS_TYPES.SHIPPING :
					CONSTANTS.ADDRESS_TYPES.BILLING;
				const cleanFieldName = fieldName
					.replace( CONSTANTS.PREFIXES.SHIPPING, CONSTANTS.DEFAULT_VALUES.EMPTY_STRING )
					.replace( CONSTANTS.PREFIXES.BILLING, CONSTANTS.DEFAULT_VALUES.EMPTY_STRING );
				result[ addressType ][ cleanFieldName ] = value;
			}
		);

		return returnJson ? JSON.stringify( result ) : result;
	}

	/**
	 * Gets PowerBoard configuration settings
	 *
	 * @returns {Object} Configuration object
	 */
	getConfigs() {
		try {
			let settings = this.$( CONSTANTS.SELECTORS.SETTINGS_INPUT ).val();
			return JSON.parse( settings );
		} catch ( error ) {
			return {};
		}
	}

	/**
	 * Gets PowerBoard required fields that are not filled
	 *
	 * @returns {string} - String with a list of missing required fields label
	 */
	getMissingRequiredFields() {
		let missingFields             = [];
		const requiredFieldsSelectors = [
			CONSTANTS.SELECTORS.BILLING_EMAIL_INPUT,
			CONSTANTS.SELECTORS.BILLING_FIRST_NAME_INPUT,
			CONSTANTS.SELECTORS.BILLING_LAST_NAME_INPUT,
			CONSTANTS.SELECTORS.BILLING_ADDRESS_1_INPUT,
			CONSTANTS.SELECTORS.BILLING_CITY_INPUT,
			CONSTANTS.SELECTORS.BILLING_STATE_INPUT,
			CONSTANTS.SELECTORS.BILLING_COUNTRY_INPUT,
			CONSTANTS.SELECTORS.BILLING_POSTCODE_INPUT
		];

		requiredFieldsSelectors.forEach(
			( fieldName ) => {
				const el = document.getElementById( fieldName );
				if ( el && !el.value ) {
					missingFields.push( GET_BILLING_FIELD_NICE_NAME[ fieldName ] );
				}
			}
		);

		return window.formatList( missingFields );
	}
}

/**
 * Widget Manager
 * Handles PowerBoard widget lifecycle and events for checkout
 */
class WidgetManager {
	constructor( $, dataService, modalManager, stateManager, validationService ) {
		this.$                 = $;
		this.dataService       = dataService;
		this.modalManager      = modalManager;
		this.stateManager      = stateManager;
		this.validationService = validationService;
	}

	/**
	 * Initializes the master widget in modal
	 *
	 * @returns {Promise} Promise that resolves when widget is initialized
	 */
	initializeMasterWidget() {
		if ( !this.dataService.paymentData ) {
			return Promise.reject( new Error( CONSTANTS.ERROR_MESSAGES.PAYMENT_DATA_NOT_SET ) );
		}

		let addressData    = this.validationService.getAddressData( false );
		let billingAddress = addressData.address;

		return this.dataService.createChargeIntent( billingAddress )
			.then( ( response ) => {
				this.initializeWidget( response );
				return response;
			} )
			.catch( ( error ) => {
				this.modalManager.showError( error.message );
				throw error;
			} );
	}

	/**
	 * Initializes the PowerBoard widget
	 *
	 * @param {Object} response - AJAX response containing widget data
	 */
	initializeWidget( response ) {
		const modalLoading = document.getElementById( CONSTANTS.MODAL_IDS.LOADING );
		const modalWidget  = document.getElementById( CONSTANTS.MODAL_IDS.WIDGET );
		const modalError   = document.getElementById( CONSTANTS.MODAL_IDS.ERROR );

		// Hide loading, show widget container
		if ( modalLoading ) {
			modalLoading.style.display = CONSTANTS.VALUES.DISPLAY_NONE;
		}
		if ( modalWidget ) {
			modalWidget.style.display = CONSTANTS.VALUES.DISPLAY_BLOCK;
			modalWidget.classList.add( CONSTANTS.CSS_CLASSES.ACTIVE );
		}
		if ( modalError ) {
			modalError.style.display = CONSTANTS.VALUES.DISPLAY_NONE;
		}

		// Initialize PowerBoard widget
		const widgetWrapper = CONSTANTS.SELECTORS.WIDGET_WRAPPER;
		const widgetToken = response.data.token;
		window[ CONSTANTS.WIDGET_PROPERTIES.POWERBOARD_WIDGET ] = new window.cba.Checkout(
			widgetWrapper,
			widgetToken
		);
		const powerBoardWidget = window[ CONSTANTS.WIDGET_PROPERTIES.POWERBOARD_WIDGET ];
		const environment = this.validationService.getConfigs().environment;
		powerBoardWidget.setEnv( environment );

		this.setupWidgetEventHandlers();
	}

	/**
	 * Sets up PowerBoard widget event handlers
	 */
	setupWidgetEventHandlers() {
		// Handle payment success
		window[ CONSTANTS.WIDGET_PROPERTIES.POWERBOARD_WIDGET ].onPaymentSuccessful(
			async( data ) => {
				await this.dataService.onPaymentSuccessful( data );
				this.dataService.processSuccessfulOrder( this.dataService.orderId )
					.then( ( response ) => {
						if ( response.success && response.data.redirect_url ) {
							// Remove all beforeunload handlers before redirect
							window.onbeforeunload = null;
							if ( window.jQuery ) {
								window.jQuery( window ).off( CONSTANTS.EVENTS.BEFOREUNLOAD );
							}
							this.modalManager.close();
							window.location.href = response.data.redirect_url;
						} else {
							const errorMessage = response.data.message ||
								CONSTANTS.ERROR_MESSAGES.PAYMENT_PROCESSING_FAILED;
							this.closeWidgetModalWithError( errorMessage );
						}
					} )
					.catch( () => {
						this.closeWidgetModalWithError(
							CONSTANTS.ERROR_MESSAGES.PAYMENT_PROCESSING_RETRY
						);
					} );
			} );

		// Handle payment errors
		window[ CONSTANTS.WIDGET_PROPERTIES.POWERBOARD_WIDGET ].onPaymentFailure( ( data ) => {
			this.dataService.onPaymentFailure( data )
				.then( () => {
					this.closeWidgetModalWithError( CONSTANTS.ERROR_MESSAGES.PAYMENT_FAILED );
				} )
				.catch( () => {
					this.closeWidgetModalWithError( CONSTANTS.ERROR_MESSAGES.PAYMENT_FAILED );
				} );
		} );

		// Handle payment expiry
		window[ CONSTANTS.WIDGET_PROPERTIES.POWERBOARD_WIDGET ].onPaymentExpired( ( data ) => {
			this.dataService.onPaymentExpired( data )
				.then( () => {
					this.closeWidgetModalWithError( CONSTANTS.ERROR_MESSAGES.PAYMENT_EXPIRED );
				} )
				.catch( () => {
					this.closeWidgetModalWithError( CONSTANTS.ERROR_MESSAGES.PAYMENT_EXPIRED );
				} );
		} );
	}

	/**
	 * Closes the PowerBoard widget modal and shows an error message
	 *
	 * @param {string} errorMessage - Error message to show when modal is closed
	 */
	closeWidgetModalWithError( errorMessage ) {
		this.modalManager.close();
		this.stateManager.showError( errorMessage );
		this.stateManager.reset();
	}

	/**
	 * Cleanup widget resources
	 */
	cleanup() {
		if ( window[ CONSTANTS.WIDGET_PROPERTIES.POWERBOARD_WIDGET ] ) {
			window[ CONSTANTS.WIDGET_PROPERTIES.POWERBOARD_WIDGET ] = null;
		}
	}
}

/**
 * Main PowerBoard Checkout Handler
 * Coordinates all checkout functionality
 */
class PowerBoardCheckoutHandler {
	constructor( $ ) {
		this.$                = $;
		this.stateManager     = new CheckoutStateManager( $ );
		this.dataService      = new DataService( $ );
		this.modalManager     = new ModalManager( this.stateManager, this.dataService );
		this.validationService = new ValidationService( $ );
		this.widgetManager = new WidgetManager(
			$,
			this.dataService,
			this.modalManager,
			this.stateManager,
			this.validationService
		);
	}

	/**
	 * Initializes the checkout handler
	 */
	init() {
		this.stateManager.init();
		this.interceptFormSubmission();
		return this;
	}

	/**
	 * Intercepts form submission to handle PowerBoard payments
	 */
	interceptFormSubmission() {
		const self         = this;
		const originalAjax = this.$.ajax;

		this.$.ajax = function( options ) {
			// Check if this is a WooCommerce checkout AJAX call
			if ( options.url && options.url.includes( CONSTANTS.AJAX_ENDPOINTS.CHECKOUT ) ) {
				// Only proceed if PowerBoard is selected
				if ( self.validationService.isPowerBoardSelected() ) {
					// Validate checkout form
					const validation = self.validationService.validateCheckoutForm();

					if ( !validation.success ) {
						self.stateManager.showErrorsList( validation.errors );
						self.stateManager.reset();
						return self.$.Deferred().reject();
					}

					// Store original success handler
					const originalSuccess = options.success;

					// Override success handler to check for our special response
					options.success = function( response ) {
						const isSuccessResult = response &&
							response.result === CONSTANTS.RESULTS.SUCCESS;
						const redirectUrl = response.redirect;
						const isPowerBoardRedirect =
							redirectUrl === CONSTANTS.VALUES.POWERBOARD_REDIRECT;
						if ( isSuccessResult && isPowerBoardRedirect ) {
							self.handlePowerBoardSubmission( response );
						} else {
							if ( originalSuccess ) {
								originalSuccess.call( this, response );
							}
						}
					};
				}
			}

			return originalAjax.call( self.$, options );
		};
	}

	/**
	 * Handles PowerBoard payment submission
	 *
	 * @param {Object} response - Payment submission response
	 */
	handlePowerBoardSubmission( response ) {
		// Validate payment submission data
		const validation = this.validationService.validatePaymentSubmissionData( response );
		if ( !validation.success ) {
			this.stateManager.showErrorsList( validation.errors );
			this.stateManager.reset();
			return;
		}

		// Check if modal is already open
		const modal = document.getElementById( CONSTANTS.MODAL_IDS.MODAL );
		if ( modal && modal.style.display === CONSTANTS.VALUES.DISPLAY_BLOCK ) {
			return;
		}

		// Show modal and initialize widget
		if ( this.modalManager.show() ) {
			const paymentData = new PaymentSubmissionData( response );
			this.dataService.setPaymentData( paymentData );

			this.widgetManager.initializeMasterWidget()
				.catch(
					( error ) => {
						this.modalManager.showError(
							error.message ||
							CONSTANTS.FALLBACK_ERRORS.WIDGET_INITIALIZATION_FAILED
						);
					}
				);
		}
	}
}

// Initialize when document is ready
// noinspection JSUnresolvedReference
jQuery(
	function( $ ) {
		$( document ).ready(
			() => {
				const powerBoardCheckout = new PowerBoardCheckoutHandler( $ );
				powerBoardCheckout.init();
				// Expose for testing
				if ( typeof window !== CONSTANTS.DEFAULT_VALUES.UNDEFINED ) {
					window.PowerBoardCheckout = {
						PowerBoardCheckoutHandler,
						ModalManager,
						CheckoutStateManager,
						DataService,
						ValidationService,
						WidgetManager,
						PaymentSubmissionData,
						CONSTANTS
					};
				}
			}
		);
	}
);

// Export for Node.js testing environment
if ( typeof module !== CONSTANTS.DEFAULT_VALUES.UNDEFINED && module.exports ) {
	module.exports = {
		PowerBoardCheckoutHandler,
		ModalManager,
		CheckoutStateManager,
		DataService,
		ValidationService,
		WidgetManager,
		PaymentSubmissionData,
		CONSTANTS
	};
}
