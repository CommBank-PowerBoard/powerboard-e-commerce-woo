import {__} from '@wordpress/i18n';
// noinspection NpmUsedModulesInstalled
import {registerPaymentMethod} from '@woocommerce/blocks-registry';
import {decodeEntities} from '@wordpress/html-entities';
import {getSetting} from '@woocommerce/settings';
import {createElement, useEffect} from 'react';
// noinspection NpmUsedModulesInstalled
import {select,subscribe} from '@wordpress/data';
// noinspection NpmUsedModulesInstalled
import {CART_STORE_KEY,CHECKOUT_STORE_KEY} from '@woocommerce/block-data';
import canMakePayment from "../includes/canMakePayment";

const store                  = select( CHECKOUT_STORE_KEY );
const cart                   = select( CART_STORE_KEY );
const settings               = getSetting( 'power_board_data', {} );
const textDomain             = 'power-board';
const defaultLabel           = __( 'PowerBoard', textDomain );
const label                  = decodeEntities( settings.title ) || defaultLabel;
const description            = settings.description ? decodeEntities( settings.description ) : '';
let billingAddress           = null;
let shippingAddress          = null;
let lastMasterWidgetInit     = null;
let shippingChangedTimeout   = null;
let currentSavedShipping     = null;
let widgetVisibilityInterval = null;

// Initialize shipping change tracking
window.powerBoardLastShippingChange = 0; // Reset to clean state

function syncPlaceOrderVisibility() {
	const method = select( 'wc/store/payment' ).getActivePaymentMethod();
	const btn = document.querySelector( '.wc-block-components-checkout-place-order-button' );
	if ( btn ) {
		btn.style.visibility = method === 'power_board' ? 'hidden' : 'visible';
	}
}

window.addEventListener( 'load', syncPlaceOrderVisibility );
const unsubscribe = subscribe( syncPlaceOrderVisibility );

const validateAndRefreshCartTotals = callback => {
	if ( typeof PowerBoardAjaxCheckout === 'undefined' ) {
		callback( null );
		return;
	}
	jQuery.ajax(
		{
			url: '/?wc-ajax=power-board-update-shipping',
			type: 'POST',
			data: {
				_wpnonce: PowerBoardAjaxCheckout.wpnonce_update_shipping,
				validate_only: true,
			},
			success: response => {
				if ( response.success && response.data.cart_total ) {
					const base = select( CART_STORE_KEY ).getCartTotals() || {};
					callback({ ...base, total_price: response.data.cart_total * 100 });
				} else {
					callback( null );
				}
			},
			error: () => callback( null ),
		}
	);
};

const toggleWidgetVisibility = ( hide ) => {
	// noinspection DuplicatedCode
	let widget        = document.getElementById( 'standaloneWidget' );
	let widgetList    = document.getElementById( 'list' );
	let widgetSpinner = document.getElementById( 'spinner' );

	if (hide) {
		if (widget) {
			widget.style.display = 'none';
		}
		if (widgetList) {
			widgetList.style.display = 'none';
		}
		if (widgetSpinner) {
			widgetSpinner.style.display = 'none';
		}
	} else {
		if (widget) {
			widget.style.display = 'flex';
		}
		if (widgetList) {
			widgetList.style.display = 'flex';
		}
		if (widgetSpinner) {
			widgetSpinner.style.display = 'flex';
		}
	}
};

const getSelectedShippingValue = () => {
	// noinspection JSUnresolvedReference
	return jQuery( '.wc-block-components-radio-control__input:checked' ).val();
}

const clearCustomNotices   = () => {
	const noticesContainer = document.querySelector( '.wc-block-components-notices' );
	if ( noticesContainer ) {
		noticesContainer.innerHTML = '';
	}
}

const showErrorMessage = ( message ) => {
	const msgHtml      = '<ul class="woocommerce-error" role="alert"><li>' + message + '</li></ul>';
	let container      = document.querySelector( '.wc-block-components-notices' );
	if ( container ) {
		container.innerHTML = msgHtml;
		container.scrollIntoView( { behavior: 'smooth', block: 'start' } );
	}
}

const initMasterWidgetCheckout = ( updatedCartTotals = null, retryCount = 0 ) => {
	// Use provided cart totals or fall back to cart.getCartTotals()
	let cartTotals = updatedCartTotals || cart.getCartTotals();

	// Only apply timing logic if we don't have updated cart totals AND this is a fresh call
	if ( !updatedCartTotals && retryCount === 0 ) {
		// Check if we recently changed shipping (within last 15 seconds)
		const lastShippingChange = window.powerBoardLastShippingChange || 0;
		const currentTime        = Date.now();
		const timeSinceChange    = currentTime - lastShippingChange;

		// Check for corrupted timestamps (way in the future or impossibly large differences)
		if ( lastShippingChange > currentTime || timeSinceChange > 1000000 ) {
			window.powerBoardLastShippingChange = 0;
		} else if ( lastShippingChange > 0 && timeSinceChange < 15000 ) {
			// Wait a bit if very recent
			if ( timeSinceChange < 1000 ) {
				setTimeout(
					() => {
							initMasterWidgetCheckout( null, 1 );
				},
					500
					);
				return;
			}

			// Validate with backend if not too old
			if ( retryCount < 2 ) {
				validateAndRefreshCartTotals(
					( freshTotals ) => {
						if ( freshTotals ) {
							const frontendTotal = cartTotals?.total_price / 100 || 0;
							const backendTotal  = freshTotals.total_price / 100;

							if ( Math.abs( frontendTotal - backendTotal ) > 0.01 ) {
								initMasterWidgetCheckout( freshTotals, retryCount + 1 );
							} else {
								initMasterWidgetCheckout( cartTotals, retryCount + 1 );
							}
						} else {
							initMasterWidgetCheckout( cartTotals, retryCount + 1 );
						}
				}
					);
				return;
			}
		}

		// Clear old timestamps if they're more than 30 seconds old
		if ( lastShippingChange > 0 && timeSinceChange > 30000 ) {
			window.powerBoardLastShippingChange = 0;
		}
	}

	// noinspection JSUnresolvedReference
	if ( canMakePayment( settings.total_limitation, cartTotals?.total_price ) ) {
		clearCustomNotices();
		const initTimestamp  = ( new Date() ).getTime();
		lastMasterWidgetInit = initTimestamp;

		// noinspection JSUnresolvedReference
		const orderId = store.getOrderId();


		if ( widgetVisibilityInterval ) {
			clearInterval( widgetVisibilityInterval )
		}

		// noinspection JSUnresolvedReference
		jQuery.ajax(
		{
			url: '/?wc-ajax=power-board-create-charge-intent',
			type: 'POST',
			data: {
				_wpnonce: PowerBoardAjaxCheckout.wpnonce_intent,
				order_id: orderId,
				total: cartTotals,
				address: cart.getCustomerData().billingAddress,
				selected_shipping_id: getSelectedShippingValue(),
				create_account: document.querySelector( '.wc-block-components-checkbox.wc-block-checkout__create-account input' )?.checked ? 'true' : 'false',
			},
			success: ( response ) => {
				if ( ! checkIsFormValid() ) {
					// noinspection JSUnresolvedReference
					let error = jQuery( '#required-fields-validation-error' )[0];
					// noinspection JSUnresolvedReference
					let loading = jQuery( '#loading' )[0];
					showInvalidFormError( loading, error );
				} else {
					if (initTimestamp === lastMasterWidgetInit) {
						if (response.success) {
							const checkoutWrapper = document.getElementById( 'powerBoardCheckout_wrapper' );
							if (!checkoutWrapper?.checkVisibility()) {
								widgetVisibilityInterval = setInterval(
									() => {
										if (checkoutWrapper?.checkVisibility()) {
											loadMasterWidget( response, orderId );
											clearInterval( widgetVisibilityInterval );
										}
									},
									2000
								);
							} else {
								loadMasterWidget( response, orderId );
							}
						} else {
							if ( response.data?.code === 'invalid_account_creation' ) {
								showErrorMessage( response.data?.message || 'An account is already registered.' );
								window.widgetPowerBoard = null;
							}

							// noinspection JSUnresolvedReference
							let error = jQuery( '#intent-creation-error' )[0];
							// noinspection JSUnresolvedReference
							let loading = jQuery( '#loading' )[0];
							showInvalidFormError( loading, error );
						}
					}
				}
			}
			}
		);
	}
}

const loadMasterWidget = ( response, orderId ) => {
	// noinspection DuplicatedCode
	toggleWidgetVisibility( false );
	const widgetSelector = '#powerBoardCheckout_wrapper';
	// noinspection JSUnresolvedReference
	if (!jQuery( widgetSelector )[0]) {
		return;
	}
	// noinspection JSUnresolvedReference
	window.widgetPowerBoard = new cba.Checkout( widgetSelector, response.data.token );
	// noinspection JSUnresolvedReference
	window.widgetPowerBoard.setEnv( settings.environment )
	// noinspection JSUnresolvedReference
	const orderButton = jQuery( '.wc-block-components-checkout-place-order-button' )[0];
	// noinspection JSUnresolvedReference
	const paymentSourceElement = jQuery( '#paymentSourceToken' );

	// noinspection JSUnresolvedReference
	window.widgetPowerBoard.onPaymentSuccessful(
		function ( data ) {
			// noinspection JSUnresolvedReference
			paymentSourceElement.val( JSON.stringify( { ...data, orderId: orderId } ) );
			orderButton.click();
			window.widgetPowerBoard = null;
		}
	);
	// noinspection JSUnresolvedReference
	window.widgetPowerBoard.onPaymentFailure(
		function ( data ) {
			// noinspection JSUnresolvedReference
			paymentSourceElement.val(
				JSON.stringify(
					{
						errorMessage: 'Transaction failed. Please check your payment details or contact your bank',
					}
				)
			);
			// noinspection JSUnresolvedReference
			jQuery.ajax(
				{
					url: '/?wc-ajax=power-board-process-payment-result',
					method: 'POST',
					data: {
						_wpnonce: PowerBoardAjaxCheckout.wpnonce_process_payment,
						order_id: store.getOrderId(),
						payment_response:
							{
								...data,
								errorMessage: data.message || 'Transaction failed',
							}
					},
					success: function () {
						orderButton.click();

						window.widgetPowerBoard = null;
					}
				}
			);
		}
	);

	// noinspection JSUnresolvedReference
	window.widgetPowerBoard.onPaymentExpired(
		function ( data ) {
			// noinspection JSUnresolvedReference
			paymentSourceElement.val(
				JSON.stringify(
					{
						errorMessage: 'Your payment session has expired. Please retry your payment',
					}
				)
			);

			// noinspection JSUnresolvedReference
			if ( data.charge_id ) {
				// noinspection JSUnresolvedReference
				jQuery.ajax(
					{
						url: '/?wc-ajax=power-board-process-payment-result',
						method: 'POST',
						data: {
							_wpnonce: PowerBoardAjaxCheckout.wpnonce_process_payment,
							order_id: store.getOrderId(),
							payment_response:
								{
									...data,
									errorMessage: 'Payment session has expired',
								}
						},
						success: function () {
							orderButton.click();

							window.widgetPowerBoard = null;
						}
					}
				);
			} else {
				orderButton.click();

				window.widgetPowerBoard = null;
			}
		}
	);
}

const checkIsFormValid = () => {
	// noinspection JSUnresolvedReference
	let isFormValid = jQuery( '.wc-block-components-form' )[0].checkValidity() && isShippingFormValid() && isShippingPhoneValid();

	// noinspection JSUnresolvedReference
	let useSameBillingAndShipping = jQuery( '.wc-block-checkout__use-address-for-billing input[type="checkbox"]' ).checked;
	if ( !useSameBillingAndShipping ) {
		isFormValid = isFormValid && isBillingFormValid() && isBillingPhoneValid();
	}

	// noinspection JSUnresolvedReference
	const termsIds = ['_woo_additional_terms', 'terms-and-conditions'];
	const wooTerms = termsIds.map( id => document.getElementById( id ) ).find( el => el !== null );
	if ( wooTerms && !wooTerms.checked ) {
		isFormValid = false;
	}

	return isFormValid;
};

const showInvalidFormError = (loading, error) => {
	loading.classList.add( 'hide' );
	if ( error.classList.length > 0 ) {
		error.classList.remove( 'hide' );
	}
};

const handleWidgetDisplay = ( waitForExternalWidgetDisplay = false, updatedCartTotals = null ) => {
	if ( ! document.querySelector( '.wc-block-components-form' ) ) {
		return;
	}

	let isFormValid       = checkIsFormValid();
	// noinspection JSUnresolvedReference
	let error = jQuery( '#required-fields-validation-error' )[0];
	// noinspection JSUnresolvedReference
	let intentCreationError = jQuery( '#intent-creation-error' )[0];
	// noinspection JSUnresolvedReference
	let invalidFieldsError = jQuery( '#invalid-fields-error' )[0];
	// noinspection JSUnresolvedReference
	let loading = jQuery( '#loading' )[0];
	toggleWidgetVisibility( true );
	intentCreationError?.classList.add( 'hide' );
	invalidFieldsError?.classList.add( 'hide' );
	if ( isFormValid ) {
		if ( loading?.classList.length > 0 ) {
			loading.classList.remove( 'hide' );
		}
		error?.classList.add( 'hide' );
	} else {
		const validPostcode = document.getElementById( 'shipping-postcode' )?.checkValidity() && document.getElementById( 'billing-postcode' )?.checkValidity();
		const validEmail    = document.getElementById( 'email' ).checkValidity();
		const validPhone    = !document.getElementById( 'shipping-phone' )?.className.includes( 'power-board-invalid-phone' ) && !document.getElementById( 'billing-phone' )?.className.includes( 'power-board-invalid-phone' );
		if ( !validPostcode || !validEmail || !validPhone ) {
			error.classList.add( 'hide' );
			// noinspection JSUnresolvedReference
			error = invalidFieldsError;
		}
		showInvalidFormError( loading, error );
	}

	if ( isFormValid && !waitForExternalWidgetDisplay ) {
		clearTimeout( window.initWidgetTimer );
		window.initWidgetTimer = setTimeout(
			() => {
				initMasterWidgetCheckout( updatedCartTotals );
			},
			500
		);
	}
};

window.handleWidgetDisplay = handleWidgetDisplay;

let lastCartTotal = cart.getCartTotals()?.total_price || 0;

const unsubscribeCart = subscribe(
	() => {
		const totals = cart.getCartTotals();
		const newTotal = totals?.total_price || 0;
		if ( newTotal !== lastCartTotal ) {
			lastCartTotal = newTotal;
			validateAndRefreshCartTotals( freshTotals => {
				handleWidgetDisplay( false, freshTotals || { total_price: newTotal } );
			} );
		}
	}
);

window.addEventListener( 'beforeunload', () => unsubscribeCart() );

jQuery( document.body ).on(
	'change',
	'.wc-block-components-shipping-rates-control input[type="radio"]',
	() => {
		clearTimeout( window.initWidgetTimer );
		validateAndRefreshCartTotals( freshTotals => {
			const totals = freshTotals || select( CART_STORE_KEY ).getCartTotals();
			initMasterWidgetCheckout( totals );
		} );
	}
);

jQuery( document.body ).on(
	'change',
	'.wc-block-checkout__create-account input[type="checkbox"]',
	() => {
		handleWidgetDisplay( false );
	}
);

jQuery( document.body ).on(
	'input',
	'.wc-block-components-text-input input[type="password"]',
	() => {
		handleWidgetDisplay( false );
	}
);

const isBillingFormValid = () => {
	// noinspection JSUnresolvedReference
	const billingAddressFormData = cart.getCustomerData().billingAddress;
	// noinspection JSUnresolvedReference
	return !!billingAddressFormData
	&& !!billingAddressFormData.address_1
	&& !!billingAddressFormData.city
	&& !!billingAddressFormData.country
	&& !!billingAddressFormData.email
	&& !!billingAddressFormData.first_name
	&& !!billingAddressFormData.last_name
	&& !!billingAddressFormData.postcode
	&& !!billingAddressFormData.state
}

const isShippingFormValid = () => {
	// noinspection JSUnresolvedReference
	const shippingAddressFormData = cart.getCustomerData().shippingAddress
	// noinspection JSUnresolvedReference
	return !!shippingAddressFormData
	&& !!shippingAddressFormData.address_1
	&& !!shippingAddressFormData.city
	&& !!shippingAddressFormData.country
	&& !!shippingAddressFormData.first_name
	&& !!shippingAddressFormData.last_name
	&& !!shippingAddressFormData.postcode
	&& !!shippingAddressFormData.state
}

const isShippingPhoneValid = () => {
	return !document.getElementById( 'shipping-phone' )?.classList?.contains( 'power-board-invalid-phone' );
}

const isBillingPhoneValid = () => {
	return !document.getElementById( 'billing-phone' )?.classList?.contains( 'power-board-invalid-phone' );
}

const handleCartTotalChanged = (event) => {
	toggleWidgetVisibility( true );
	// Use the updated cart totals from the event detail if available
	const updatedCartTotals = event?.detail?.updatedCartTotals || null;
	handleWidgetDisplay( false, updatedCartTotals );
};

const handleShippingChanged = () => {
	if (shippingChangedTimeout) {
		clearTimeout( shippingChangedTimeout );
	}
	const selectedShippingMethodId = getSelectedShippingValue();

	if (currentSavedShipping !== selectedShippingMethodId) {
		// Mark timestamp of shipping change for staleness detection (only if not already set recently)
		const currentTime         = Date.now();
		const lastChange          = window.powerBoardLastShippingChange || 0;
		const timeSinceLastChange = currentTime - lastChange;

		// Only update timestamp if this is a new shipping change (not a rapid repeat)
		if ( timeSinceLastChange > 500 || lastChange === 0 ) {
			window.powerBoardLastShippingChange = currentTime;
		}

		shippingChangedTimeout   = setTimeout(
		() => {
			currentSavedShipping = selectedShippingMethodId;
			// noinspection JSUnresolvedReference
			jQuery.ajax(
				{
					url: '/?wc-ajax=power-board-update-shipping',
					type: 'POST',
					data: {
						_wpnonce: PowerBoardAjaxCheckout.wpnonce_update_shipping,
					},
					success: function (response) {
						if (response.success && response.data.trigger_event === 'power_board_cart_total_changed') {
							// Create updated cart totals with the backend value
							const currentCartTotals = cart.getCartTotals() || {};
							const updatedCartTotals = {
								...currentCartTotals,
								total_price: response.data.cart_total * 100 // Convert to cents for WooCommerce
							};

							// Dispatch the custom event with updated cart totals in detail
							const event = new CustomEvent(
								'power_board_cart_total_changed',
								{
									detail: {
										updatedCartTotals: updatedCartTotals
									}
								}
								);
							document.dispatchEvent( event );
						}
					},
					error: function (xhr, status, error) {
						console.error( 'PowerBoard: Error updating shipping:', error );
					}
					}
			);
		},
		500
		);
	} else {
		handleWidgetDisplay( true );
	}
}

const handleFormChanged = ( event ) => {
	setTimeout(
		() => {
			// noinspection JSUnresolvedReference
			const billingAddressFormData = cart.getCustomerData().billingAddress;
			// noinspection JSUnresolvedReference
			const shippingAddressFormData = cart.getCustomerData().shippingAddress;
			// noinspection JSUnresolvedReference
			const isShippingRateBeingSelected = cart.isShippingRateBeingSelected();
			if (
				billingAddress !== billingAddressFormData ||
				shippingAddress !== shippingAddressFormData ||
				event.target.id.includes( '_woo_additional_terms' ) ||
				event.target.id.includes( 'terms-and-conditions' ) ||
				event.target.closest( 'div' ).className.includes( 'create-account' )
			) {
				billingAddress  = billingAddressFormData;
				shippingAddress = shippingAddressFormData;
				handleWidgetDisplay();
			} else if ( isShippingRateBeingSelected ) {
				handleShippingChanged();
			}
	},
		0
		)
}

const handleWidgetError = () => {
	let loading         = document.getElementById( 'loading' );
	if ( loading.classList.length > 0 ) {
		loading.classList.remove( 'hide' );
	}
	toggleWidgetVisibility( true );
	initMasterWidgetCheckout();

	const checkoutContainer       = document.querySelectorAll( '.wc-block-checkout' )[0];
	const topNotices              = checkoutContainer.querySelectorAll( '.wc-block-components-notices' )[0];
	const paymentMethodsContainer = document.querySelectorAll( '.wc-block-checkout__payment-method' )[0];
	const checkoutPaymentStep     = paymentMethodsContainer?.querySelectorAll( '.wc-block-components-checkout-step__content' )?.[0];
	const checkoutPaymentNotices  = checkoutPaymentStep?.querySelectorAll( '.wc-block-components-notices' )?.[0];
	const removeNoticeInterval    = setInterval(
		() => {
			if ( checkoutPaymentNotices?.children.length > 0 || topNotices.children.length > 0 ) {
				clearInterval( removeNoticeInterval );

				const removeErrorTimeout = setTimeout(
					() => {
						// noinspection JSUnresolvedReference
						clearTimeout( removeErrorTimeout );
						const noticesToCheck = checkoutPaymentNotices?.children.length > 0 ? checkoutPaymentNotices : topNotices;
						for ( const notice of noticesToCheck.children ) {
							if ( notice.classList.contains( 'is-error' ) ) {
								notice.classList.add( 'hide' );
							}
						}
					},
					10000
				);
			}
		},
		200
	);
};

// eslint-disable-next-line no-unused-vars
const Content                               = ( props ) => {
	const {eventRegistration, emitResponse} = props;
	const {onPaymentSetup}                  = eventRegistration;

	// noinspection JSUnresolvedReference
	useEffect(
		() => {
			if ( !window.unsubscribeFromFormChanges ) {
				// noinspection JSUnresolvedReference
				window.unsubscribeFromFormChanges = jQuery( '.wc-block-components-form' )[0].addEventListener( "change", event => handleFormChanged( event ) );
			}
			if ( !window.cartChangesEventListenerSetup ) {
				document.addEventListener( "power_board_cart_total_changed", handleCartTotalChanged );
				window.cartChangesEventListenerSetup = true;
			}
			const unsubscribe               = onPaymentSetup(
				async() => {
					const paymentData       = document.getElementById( 'paymentSourceToken' )?.value
					const paymentDataParsed = JSON.parse( paymentData )
					if ( !!paymentData && !paymentDataParsed.errorMessage ) {
						// noinspection JSUnresolvedReference
						return {
							type: emitResponse.responseTypes.SUCCESS, meta: {
								paymentMethodData: {
									payment_response: paymentData,
									chargeId: paymentDataParsed['charge_id'],
									intentId: paymentDataParsed['intent_id'],
									orderId: paymentDataParsed['order_id'],
									_wpnonce: settings._wpnonce
								}
							},
						};
					}

					handleWidgetError();
					// noinspection JSUnresolvedReference
					return {
						type: emitResponse.responseTypes.ERROR, message: __( paymentDataParsed.errorMessage, textDomain ),
					}
				}
			);
			return () => {
				// noinspection JSUnresolvedReference
				const form = jQuery( '.wc-block-components-form' )[0];
				if ( form ) {
					form.removeEventListener( "change", handleFormChanged );
				}
				document.removeEventListener( "power_board_cart_total_changed", handleCartTotalChanged );
				window.cartChangesEventListenerSetup = false;
				unsubscribe();
			};
		},
		[emitResponse.responseTypes.ERROR, emitResponse.responseTypes.SUCCESS, onPaymentSetup]
	);

	const input = createElement(
		"input",
		{
			type: 'hidden', id: 'paymentSourceToken'
		}
	);

	return createElement(
		'div',
		{className: 'master-widget-wrapper'},
		createElement(
			"div",
			{id: 'loading'},
			createElement(
				"p",
				{className: 'loading-text'},
				'Loading...',
			),
		),
		createElement(
			"div",
			{id: 'required-fields-validation-error', className: 'hide'},
			createElement(
				"p",
				{className: 'power-board-validation-error'},
				'Please fill in the required fields of the form to display payment methods.',
			),
		),
		createElement(
			"div",
			{id: 'intent-creation-error', className: 'hide'},
			createElement(
				"p",
				{className: 'power-board-validation-error'},
				'Something went wrong, please refresh the page and try again.',
			),
		),
		createElement(
			"div",
			{id: 'invalid-fields-error', className: 'hide'},
			createElement(
				"p",
				{className: 'power-board-validation-error'},
				'Please enter valid information in all fields to display payment methods.',
			),
		),
		createElement(
			"div",
			{id: 'powerBoardCheckout_wrapper'}
		),
		input
	);
};

// noinspection JSUnusedGlobalSymbols,JSUnresolvedReference,JSCheckFunctionSignatures
const Paydock = {
	name: "power_board",
	label: createElement(
		() =>
			createElement(
				"div",
				null,
				createElement(
					"div",
					{
						className: "power-board-payment-method-label"
					},
					label,
					createElement(
						"img", {
							src: `${window.powerBoardWidgetSettings.pluginUrlPrefix}assets/images/logo.png`,
							alt: label,
							className: "power-board-payment-method-label-logo",
						}
					)
				),
				description
					? createElement(
						"p", {
							className: "power-board-payment-method-desc"
						},
						description
					)
					: null
			)
		), content: <Content />, edit: <Content />, canMakePayment: () => true, ariaLabel: label, supports: { features: settings.supports }
};

registerPaymentMethod( Paydock );
