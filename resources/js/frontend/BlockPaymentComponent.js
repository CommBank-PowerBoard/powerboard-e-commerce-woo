/**
 * BlockPaymentComponent
 * React component for PowerBoard block checkout
 */

import { createElement, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { BlockCheckoutHandler } from './BlockCheckoutHandler.js';
import { ERROR_MESSAGES, TEXT_DOMAIN } from './constants.js';
import { getSetting } from '@woocommerce/settings';

const settings                = getSetting( 'power_board_data', {} );
const availablePaymentMethods = settings.available_payment_methods || [];
const defaultInfoText         = 'Click \'Place Order\' to securely complete your payment.';
const paymentInfoText         = settings.payment_info_text || defaultInfoText;

/**
 * Clears checkout-related notices across likely contexts to avoid stacked errors
 *
 * @param {Object} emitResponse - Woo Blocks emitResponse object (to read noticeContexts)
 */
export function clearCheckoutNotices( emitResponse ) {
	try {
		if ( window.wp && window.wp.data ) {
			const noticesStore   = window.wp.data.select( 'core/notices' );
			const dispatchNotices = window.wp.data.dispatch( 'core/notices' );
			const contextsToClear = [
				emitResponse.noticeContexts && emitResponse.noticeContexts.CHECKOUT
			].filter( Boolean );

			for ( const ctx of contextsToClear ) {
				const ctxNotices = noticesStore.getNotices( ctx ) || [];
				for ( const n of ctxNotices ) {
					dispatchNotices.removeNotice( n.id, ctx );
				}
			}
		}
	} catch ( e ) {
		// no-op if notices API is unavailable
	}
}

export const BlockPaymentComponent                                   = ( props ) => {
	const { eventRegistration, emitResponse, store, cart, settings } = props;
	const { onPaymentSetup, onCheckoutSuccess, onCheckoutValidation } = eventRegistration;

	useEffect(
		() => {
			// Initialize checkout handler
			const checkoutHandler = new BlockCheckoutHandler(
				store,
				cart,
				settings,
				emitResponse.responseTypes,
				emitResponse.noticeContexts
			);

			const unsubscribeCheckoutValidation = onCheckoutValidation(
				() => {
					try {
						const result = checkoutHandler.processCheckoutValidation();
						if ( result.type === emitResponse.responseTypes.SUCCESS ) {
							return { type: emitResponse.responseTypes.SUCCESS };
						}

						// Clear existing notices from previous validations
						clearCheckoutNotices( emitResponse );

						return {
							type: emitResponse.responseTypes.ERROR,
							errorMessage: __( result.message, TEXT_DOMAIN )
						};
					} catch ( error ) {
						// Clear existing notices from previous validations
						clearCheckoutNotices( emitResponse );

						return {
							type: emitResponse.responseTypes.ERROR,
							errorMessage: __( ERROR_MESSAGES.CHECKOUT_VALIDATION, TEXT_DOMAIN )
						};
					}
				}
			);

			// Set up payment setup handler
			const unsubscribePaymentSetup = onPaymentSetup(
				() => {
					try {
						const result = checkoutHandler.processPaymentSetup();

						if ( result.type === emitResponse.responseTypes.SUCCESS ) {
							return {
								type: emitResponse.responseTypes.SUCCESS,
								meta: result.meta
							};
						} else {
							return {
								type: emitResponse.responseTypes.ERROR,
								message: __( result.message, TEXT_DOMAIN ),
								messageContext: result.messageContext ||
									emitResponse.noticeContexts.PAYMENTS
							};
						}
					} catch ( error ) {
						return {
							type: emitResponse.responseTypes.ERROR,
							message: __( ERROR_MESSAGES.PAYMENT_SETUP, TEXT_DOMAIN ),
							messageContext: emitResponse.noticeContexts.PAYMENTS
						};
					}
				}
			);

			// Set up checkout success handler (for showing modal)
			const unsubscribeCheckoutSuccess = onCheckoutSuccess(
				async( checkoutData ) => {
					try {
						return await checkoutHandler.handleCheckoutSuccess( checkoutData );
					} catch ( error ) {
						return {
							type: emitResponse.responseTypes.ERROR,
							message: ERROR_MESSAGES.PAYMENT_FAILED,
							messageContext: emitResponse.noticeContexts.PAYMENTS,
							retry: true
						};
					}
				} );
			// Cleanup function
			return () => {
				checkoutHandler.cleanup();
				unsubscribePaymentSetup();
				unsubscribeCheckoutValidation();
				unsubscribeCheckoutSuccess();
			};
		},
		[
			emitResponse.responseTypes.ERROR,
			emitResponse.responseTypes.SUCCESS,
			emitResponse.noticeContexts.PAYMENTS,
			onPaymentSetup,
			onCheckoutSuccess,
			store,
			cart,
			settings
		]
	);

	// Render the payment component UI
	return createElement(
		'div',
		{ className: 'master-widget-wrapper' },
		createAvailablePaymentMethodsList(),
		createModal(),
		createPaymentInfo(),
		createLoadingIndicator(),
		createHiddenPaymentField()
	);
};

/**
 * Creates the PowerBoard available payment methods
 *
 * @returns {ReactElement} Payment method label component
 */
const createAvailablePaymentMethodsList = () => {
	const availablePaymentMethodsElements = [];

	for ( let paymentMethodKey in availablePaymentMethods ) {
		const paymentMethodNiceName = availablePaymentMethods[ paymentMethodKey ].nice_name;
		const paymentMethodImage = availablePaymentMethods[ paymentMethodKey ].image;

		availablePaymentMethodsElements.push( createElement(
			'img',
			{
				src: window.powerBoardWidgetSettings.pluginUrlPrefix +
					`assets/images/payment-methods/${paymentMethodImage}`,
				alt: `Available payment method ${paymentMethodNiceName}`,
				id: `power-board-payment-method-${paymentMethodKey}`,
				title: paymentMethodNiceName,
				className: 'payment-method'
			}
		) );
	}

	return createElement(
		'div',
		{ className: 'powerboard-payment-methods-wrapper' },
		...availablePaymentMethodsElements
	);
};

/**
 * Creates the payment modal element
 *
 * @returns {ReactElement} Modal element
 */
const createModal = () => {
	return createElement(
		'div',
		{
			id: 'powerboard-payment-modal',
			className: 'powerboard-modal',
			style: { display: 'none' }
		},
		createElement(
			'div',
			{ className: 'powerboard-modal-content' },
			createModalHeader(),
			createModalBody()
		)
	);
};

/**
 * Creates the modal header
 *
 * @returns {ReactElement} Modal header element
 */
const createModalHeader = () => {
	return createElement(
		'div',
		{ className: 'powerboard-modal-header' },
		createElement( 'h3', null, __( 'Complete Your Payment', TEXT_DOMAIN ) ),
		createElement( 'span', { className: 'powerboard-modal-close' }, '×' )
	);
};

/**
 * Creates the modal body
 *
 * @returns {ReactElement} Modal body element
 */
const createModalBody = () => {
	return createElement(
		'div',
		{ className: 'powerboard-modal-body' },
		createModalLoading(),
		createModalWidget(),
		createModalError()
	);
};

/**
 * Creates the modal loading indicator
 *
 * @returns {ReactElement} Loading element
 */
const createModalLoading = () => {
	return createElement(
		'div',
		{ id: 'powerboard-modal-loading' },
		createElement(
			'p',
			{ className: 'loading-text' },
			__( 'Initializing payment...', TEXT_DOMAIN )
		)
	);
};

/**
 * Creates the modal widget container
 *
 * @returns {ReactElement} Widget container element
 */
const createModalWidget = () => {
	return createElement(
		'div',
		{ id: 'powerboard-modal-widget-wrapper' },
		'<!-- PowerBoard widget will be initialized here -->'
	);
};

/**
 * Creates the modal error container
 *
 * @returns {ReactElement} Error container element
 */
const createModalError = () => {
	return createElement(
		'div',
		{
			id: 'powerboard-modal-error',
			style: { display: 'none' }
		},
		createElement(
			'p',
			{ className: 'power-board-validation-error' },
			__( 'Something went wrong, please try again.', TEXT_DOMAIN )
		)
	);
};

/**
 * Creates the payment info section
 *
 * @returns {ReactElement} Payment info element
 */
const createPaymentInfo = () => {
	return createElement(
		'div',
		{ id: 'powerboard-payment-info' },
		createElement( 'p', null, paymentInfoText )
	);
};

/**
 * Creates the loading indicator
 *
 * @returns {ReactElement} Loading indicator element
 */
const createLoadingIndicator = () => {
	return createElement(
		'div',
		{
			id: 'loading',
			style: { display: 'none' }
		},
		createElement(
			'p',
			{ className: 'loading-text' },
			__( 'Loading...', TEXT_DOMAIN )
		)
	);
};

/**
 * Creates the hidden payment field for storing payment data
 *
 * @returns {ReactElement} Hidden input element
 */
const createHiddenPaymentField = () => {
	return createElement(
		'input',
		{
			type: 'hidden',
			id: 'paymentSourceToken'
		}
	);
};
