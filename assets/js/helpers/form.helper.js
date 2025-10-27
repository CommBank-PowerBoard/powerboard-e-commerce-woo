const FORM_HELPER_CONSTANTS = {
	SELECTORS: {
		CLASSIC_CHECKOUT: '.woocommerce-notices-wrapper:first',
		BLOCKS_CHECKOUT: '.wc-block-components-notices:first',
		CHECKOUT_FORM: 'form.woocommerce-checkout'
	},
	AJAX_ENDPOINTS: {
		CREATE_ERROR_NOTICE: '/?wc-ajax=power-board-create-error-notice'
	},
	CSS: {
		NOTICE_GROUP: 'woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout'
	}
};

/**
 * Shows error message at top of checkout
 *
 * @param {string[]} messages - The error messages to display
 * @param {string} type - The name of the notice type - either error, success or notice.
 */
window.showNotice = function( messages, type ) {
	// noinspection JSUnresolvedReference
	const classicCheckoutSelector = jQuery( FORM_HELPER_CONSTANTS.SELECTORS.CLASSIC_CHECKOUT );
	// noinspection JSUnresolvedReference
	const normalCheckoutSelector = jQuery( FORM_HELPER_CONSTANTS.SELECTORS.BLOCKS_CHECKOUT );
	const hasClassicCheckout = classicCheckoutSelector.length > 0;
	const noticesWrapper = hasClassicCheckout ? classicCheckoutSelector : normalCheckoutSelector;

	// noinspection JSUnresolvedReference
	jQuery.ajax(
		{
			url: FORM_HELPER_CONSTANTS.AJAX_ENDPOINTS.CREATE_ERROR_NOTICE,
			type: 'POST',
			data: {
				_wpnonce: window.PowerBoardAjaxError?.wpnonce_error,
				messages: messages,
				type: type
			}
		}
	).then(
		( message ) => {
			if ( !message ) {
				return;
			}

			// Remove notices from all sources
			jQuery( '.woocommerce-error, .woocommerce-message, .is-error, .is-success' ).remove();

			jQuery( '<div></div>' )
				.addClass( FORM_HELPER_CONSTANTS.CSS.NOTICE_GROUP )
				.html( message )
				.prependTo( FORM_HELPER_CONSTANTS.SELECTORS.CHECKOUT_FORM );

			// noinspection JSUnresolvedReference
			jQuery( 'html, body' ).animate(
				{
					scrollTop: noticesWrapper.offset().top - 100
				},
				800
			);
		}
	);
};

/**
 * Formats an array of strings into a human-readable list.
 *
 * @param {string[]} array - The array of strings to format.
 * @returns {string} - The formatted string, with commas and "and" before the last item.
 */
window.formatList = function( array ) {
	if ( !array || array.length === 0 ) {
		return '';
	}

	if ( array.length === 1 ) {
		return array[ 0 ];
	}

	if ( array.length === 2 ) {
		return array.join( ' and ' );
	}

	const lastItem    = array[ array.length - 1 ];
	const restOfArray = array.slice( 0, -1 );
	return `${restOfArray.join( ', ' )}, and ${lastItem}`;
};
