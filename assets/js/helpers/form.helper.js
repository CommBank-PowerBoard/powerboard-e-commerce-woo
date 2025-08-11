window.getValidationResults = function (phoneInputs, validatePhone) {
	return Object.entries( phoneInputs ).reduce(
		(acc, [key, input]) => {
			acc[key] = validatePhone( input );
			return acc;
		},
		{}
	);
};

window.showWarning = function (message, type = 'error') {
	// noinspection JSUnresolvedReference
	const classicCheckoutSelector = jQuery( '.woocommerce-notices-wrapper:first' );
	// noinspection JSUnresolvedReference
	const normalCheckoutSelector = jQuery( '.wc-block-components-notices:first' );
	const noticesWrapper         = classicCheckoutSelector.length > 0 ? classicCheckoutSelector : normalCheckoutSelector;
	noticesWrapper.html( '' );
	// noinspection JSUnresolvedReference
	jQuery.post(
		PowerBoardAjaxError.url,
		{
			_wpnonce: PowerBoardAjaxError.wpnonce_error,
			dataType: 'html',
			action: 'power_board_create_error_notice',
			message: message,
			type: type,
		}
	).then(
		( message ) => {
			const doc          = new DOMParser().parseFromString( message.toString(), 'text/html' );
			const noticeBanner = doc.querySelectorAll( 'div.wc-block-components-notice-banner' )[0];
			noticesWrapper.append( noticeBanner );
			// noinspection JSUnresolvedReference
			jQuery( 'html, body' ).animate(
				{
					scrollTop: noticesWrapper.offset().top - 100,
				},
				800
			);
		}
	);
}
