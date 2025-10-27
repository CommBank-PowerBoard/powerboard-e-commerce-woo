/**
 * PowerBoard Block Checkout - Main Entry Point
 * Registers the PowerBoard payment method for WooCommerce blocks
 */

import { __ } from '@wordpress/i18n';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { decodeEntities } from '@wordpress/html-entities';
import { getSetting } from '@woocommerce/settings';
import { createElement } from 'react';
import { select } from '@wordpress/data';
import { CART_STORE_KEY, CHECKOUT_STORE_KEY } from '@woocommerce/block-data';

import { BlockPaymentComponent } from './BlockPaymentComponent.js';
import { TEXT_DOMAIN } from './constants.js';

// Initialize WooCommerce store and cart selectors
const store    		= select( CHECKOUT_STORE_KEY );
const cart     		= select( CART_STORE_KEY );
const settings 		= getSetting( 'power_board_data', {} );
const defaultLabel  = __( 'PowerBoard', TEXT_DOMAIN );
const label  = decodeEntities( settings.title ) || defaultLabel;

/**
 * Creates the PowerBoard payment method label with logo
 *
 * @returns {ReactElement} Payment method label component
 */
const createPaymentMethodLabel = () => {
	return createElement(
		() => createElement(
			'div',
			{ className: 'power-board-payment-method-label-wrapper' },
			createElement(
				'div',
				{ className: 'power-board-payment-method-label' },
				createElement(
					'span',
					{ className: 'power-board-payment-method-label-text' },
					label
				),
				createElement(
					'img',
					{
						src: window.powerBoardWidgetSettings.pluginUrlPrefix +
							'assets/images/logo.svg',
						alt: label,
						className: 'power-board-payment-method-label-logo'
					}
				)
			)
		)
	);
};

/**
 * PowerBoard content component - rendered in the checkout block when PowerBoard is selected
 */
const PowerBoardContent = ( props ) => {
	return createElement(
		BlockPaymentComponent,
		{
			...props,
			store,
			cart,
			settings
		}
	);
};

/**
 * PowerBoard payment method configuration object
 */
const PowerBoardPaymentMethod = {
	name: 'power_board',
	label: createPaymentMethodLabel(),
	content: createElement( PowerBoardContent ),
	edit: createElement( PowerBoardContent ),
	canMakePayment: () => true,
	ariaLabel: label,
	supports: {
		features: settings.supports
	}
};

// Register the payment method with WooCommerce Blocks
registerPaymentMethod( PowerBoardPaymentMethod );

// Export for testing purposes
export {
	PowerBoardPaymentMethod,
	createPaymentMethodLabel,
	PowerBoardContent
};
