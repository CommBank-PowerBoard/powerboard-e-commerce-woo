/**
 * Shared constants for PowerBoard Block Checkout
 */

export const MODAL_IDS = {
	MODAL: 'powerboard-payment-modal',
	LOADING: 'powerboard-modal-loading',
	WIDGET: 'powerboard-modal-widget-wrapper',
	ERROR: 'powerboard-modal-error'
};

export const SELECTORS = {
	MODAL_CLOSE: '.powerboard-modal-close',
	FORM: '.wc-block-components-form',
	EMAIL_INPUT: 'email',
	FIRST_NAME_INPUT: 'first_name',
	LAST_NAME_INPUT: 'last_name',
	ADDRESS_1_INPUT: 'address_1',
	CITY_INPUT: 'city',
	STATE_INPUT: 'state',
	COUNTRY_INPUT: 'country',
	POSTCODE_INPUT: 'postcode'
};

export const GET_FIELD_NICE_NAME = {
	[ SELECTORS.EMAIL_INPUT ]: 'Email',
	[ SELECTORS.FIRST_NAME_INPUT ]: 'First name',
	[ SELECTORS.LAST_NAME_INPUT ]: 'Last name',
	[ SELECTORS.ADDRESS_1_INPUT ]: 'Street address',
	[ SELECTORS.CITY_INPUT ]: 'City',
	[ SELECTORS.STATE_INPUT ]: 'State',
	[ SELECTORS.COUNTRY_INPUT ]: 'Country',
	[ SELECTORS.POSTCODE_INPUT ]: 'Postcode'
};

export const AJAX_ENDPOINTS = {
	CHECKOUT: '/?wc-ajax=checkout',
	CREATE_INTENT: '/?wc-ajax=power-board-create-charge-intent',
	PAYMENT_SUCCESS: '/?wc-ajax=power-board-payment-successful',
	PAYMENT_FAILURE: '/?wc-ajax=power-board-payment-failure',
	PAYMENT_EXPIRED: '/?wc-ajax=power-board-payment-expired',
	PAYMENT_CANCELLED: '/?wc-ajax=power-board-payment-cancelled',
	ORDER_SUCCESS: '/?wc-ajax=power-board-process-successful-order'
};

export const PAYMENT_METHOD = 'power_board';

export const ERROR_MESSAGES = {
	MODAL_LOAD_FAILED: 'Failed to show payment modal',
	CHECKOUT_VALIDATION: 'An unexpected error occurred during checkout validation.',
	PAYMENT_SETUP: 'An unexpected error occurred during payment setup.',
	WIDGET_FAILURE: 'An unexpected error occurred while rendering payment options.',
	PAYMENT_FAILED: 'Payment processing failed. Please try again.',
	PAYMENT_EXPIRED: 'Your payment session has expired. Please retry your payment',
	PAYMENT_CANCELLED_PROCESSING_FAILED: 'Payment cancelled processing failed',
	SOMETHING_WRONG: 'Something went wrong, please try again.',
	POWERBOARD_REQUIRED_FIELDS: 'To complete your checkout with PowerBoard, ' +
		'please provide the following required fields:',
	USER_CANCELLED: 'Payment window closed by user',
	MODAL_CLOSE_CONFIRMATION_MESSAGE:
		'If you’ve submitted your payment, ' +
		'please wait for a confirmation message before closing the window. ' +
		'Do you still wish to cancel?'
};

export const TEXT_DOMAIN = 'power-board';

export const MODAL_AUTO_CLOSE_DELAY = 2000;

export const MODAL_REDIRECT = 'modal';
