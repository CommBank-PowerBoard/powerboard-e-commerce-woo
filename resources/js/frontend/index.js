import {__} from '@wordpress/i18n';
// noinspection NpmUsedModulesInstalled
import {registerPaymentMethod} from '@woocommerce/blocks-registry';
import {decodeEntities} from '@wordpress/html-entities';
import {getSetting} from '@woocommerce/settings';
import {createElement, useEffect} from 'react';
// noinspection NpmUsedModulesInstalled
import {select} from '@wordpress/data';
// noinspection NpmUsedModulesInstalled
import {CART_STORE_KEY,CHECKOUT_STORE_KEY} from '@woocommerce/block-data';
import canMakePayment from "../includes/canMakePayment";

const store    = select( CHECKOUT_STORE_KEY );
const cart     = select( CART_STORE_KEY );
const settings = getSetting( 'power_board_data', {} );

const textDomain   = 'power-board';
const defaultLabel = __( 'PowerBoard Payments', textDomain );

const label                   = decodeEntities( settings.title ) || defaultLabel;
let totalChangesTimeout       = null;
let totalChangesSecondTimeout = null;
let billingAddress            = null;
let shippingAddress           = null;
let lastMasterWidgetInit      = null;

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

const toggleOrderButton = ( hide ) => {
	const orderButton   = document.querySelector( '.wc-block-components-checkout-place-order-button' );
	if ( !orderButton ) {
		return;
	}

	orderButton.style.visibility = hide ? 'hidden' : 'visible';
};

const getSelectedShippingValue = () => {
	// noinspection JSUnresolvedReference
	const selectedShipping = jQuery( '.wc-block-components-radio-control__input:checked' ).filter(
		function () {
			// noinspection JSUnresolvedReference
			const id = jQuery( this ).attr( 'id' )
			return id.includes( 'rate' ) || id.includes( 'shipping' );
		}
	)

	return selectedShipping[0]?.value;
}

const initMasterWidgetCheckout = () => {
	// noinspection JSUnresolvedReference
	if ( canMakePayment( settings.total_limitation, cart.getCartTotals()?.total_price ) ) {
		const initTimestamp  = ( new Date() ).getTime();
		lastMasterWidgetInit = initTimestamp;
		setTimeout( () => toggleOrderButton( true ), 100 );

		// noinspection JSUnresolvedReference
		jQuery.ajax(
		{
			url: '/?wc-ajax=power-board-create-charge-intent',
			type: 'POST',
			data: {
				_wpnonce: PowerBoardAjaxCheckout.wpnonce_intent,
				order_id: store.getOrderId(),
				total: cart.getCartTotals(),
				address: cart.getCustomerData().billingAddress,
				selected_shipping_id: getSelectedShippingValue(),
			},
			success: ( response ) => {
				if ( ! checkIsFormValid() ) {
					// noinspection JSUnresolvedReference
					let error = jQuery( '#fields-validation-error' )[0];
					// noinspection JSUnresolvedReference
					let loading = jQuery( '#loading' )[0];
					showInvalidFormError( loading, error );
				} else {
					if (initTimestamp === lastMasterWidgetInit) {
						if (response.success) {
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
									const orderId = store.getOrderId();
									// noinspection JSUnresolvedReference
									jQuery.ajax(
										{
											url: '/?wc-ajax=power-board-process-payment-result',
											method: 'POST',
											data: {
												_wpnonce: PowerBoardAjaxCheckout.wpnonce_process_payment,
												order_id: orderId,
												payment_response: data,
												create_account: document.querySelector( '.wc-block-components-checkbox.wc-block-checkout__create-account' )?.querySelector( 'input' ).checked,
											},
											success: function (response) {
												if (response.success) {
													// noinspection JSUnresolvedReference
													paymentSourceElement.val( JSON.stringify( { ...data, orderId: orderId } ) );
													orderButton.click();

													window.widgetPowerBoard = null;
												} else {
													// noinspection JSUnresolvedReference
													window.showWarning( response.data.message );
													initMasterWidgetCheckout();
												}
											}
										}
									);
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
						} else {
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

const checkIsFormValid = () => {
	// noinspection JSUnresolvedReference
	let isFormValid = jQuery( '.wc-block-components-form' )[0].checkValidity() && isShippingFormValid() && isShippingPhoneValid();

	// noinspection JSUnresolvedReference
	let useSameBillingAndShipping = jQuery( '.wc-block-checkout__use-address-for-billing input[type="checkbox"]' ).checked;
	if ( !useSameBillingAndShipping ) {
		isFormValid = isFormValid && isBillingFormValid() && isBillingPhoneValid();
	}
	// noinspection JSUnresolvedReference
	let additionalTerms = document.getElementById( '_woo_additional_terms' );
	if ( additionalTerms && !additionalTerms.checked ) {
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

const handleWidgetDisplay = ( waitForExternalWidgetDisplay = false ) => {
	let isFormValid       = checkIsFormValid();
	// noinspection JSUnresolvedReference
	let error = jQuery( '#fields-validation-error' )[0];
	// noinspection JSUnresolvedReference
	let intentCreationError = jQuery( '#intent-creation-error' )[0];
	// noinspection JSUnresolvedReference
	let loading = jQuery( '#loading' )[0];
	toggleWidgetVisibility( true );
	intentCreationError.classList.add( 'hide' );
	if ( isFormValid ) {
		if ( loading.classList.length > 0 ) {
			loading.classList.remove( 'hide' );
		}
		error.classList.add( 'hide' );
	} else {
		showInvalidFormError( loading, error );
	}

	if ( isFormValid && !waitForExternalWidgetDisplay ) {
		clearTimeout( window.initWidgetTimer );
		window.initWidgetTimer = setTimeout(
			() => {
				initMasterWidgetCheckout();
			},
			500
		);
	}
};
window.handleWidgetDisplay = handleWidgetDisplay;

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
	// noinspection DuplicatedCode
	if (totalChangesTimeout) {
		clearTimeout( totalChangesTimeout );
	}
	toggleWidgetVisibility( true );
	totalChangesTimeout     = setTimeout(
		() => {
			const spanTotal = getUIOrderTotal();
			const cartTotal = +event.detail.cartTotal;
			if (spanTotal) {
				if (spanTotal !== cartTotal) {
					if (totalChangesSecondTimeout) {
						clearTimeout( totalChangesSecondTimeout );
					}
					totalChangesSecondTimeout = setTimeout(
						() => {
							const spanTotal   = getUIOrderTotal();
							if (spanTotal) {
								if (spanTotal !== cartTotal) {
									window.reloadAfterExternalCartChanges();
								} else {
									handleWidgetDisplay();
								}
							}
						},
						300
					)
				} else {
					handleWidgetDisplay();
				}
			}
	},
		300
		)
};

// noinspection DuplicatedCode
const getUIOrderTotal = () => {
	// noinspection JSUnresolvedReference
	const orderTotalElement = jQuery( '.wc-block-components-totals-footer-item-tax-value' )[0];
	return orderTotalElement ? +orderTotalElement?.innerText.replace( /[^0-9.,]*/, '' ) : null;
};

const handleFormChanged = ( event ) => {
	setTimeout(
		() => {
			// noinspection JSUnresolvedReference
			const billingAddressFormData = cart.getCustomerData().billingAddress;
			// noinspection JSUnresolvedReference
			const shippingAddressFormData = cart.getCustomerData().shippingAddress;
			// noinspection JSUnresolvedReference
			const isShippingRateBeingSelected = cart.isShippingRateBeingSelected();
			if ( billingAddress !== billingAddressFormData || shippingAddress !== shippingAddressFormData || ( event.target.id.includes( '_woo_additional_terms' ) ) ) {
				billingAddress  = billingAddressFormData;
				shippingAddress = shippingAddressFormData;
				handleWidgetDisplay();
			} else if ( isShippingRateBeingSelected ) {
				handleWidgetDisplay( true );
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
			{id: 'fields-validation-error', className: 'hide'},
			createElement(
				"p",
				{className: 'power-board-validation-error'},
				'Please fill in the required fields of the form to display payment methods',
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
				{
					className: 'power-board-payment-method-label'
				},
				label,
				createElement(
					"img",
					{
						src: `${window.powerBoardWidgetSettings.pluginUrlPrefix}assets/images/logo.png`,
						alt: label,
						className: 'power-board-payment-method-label-logo'
					}
				)
			)
	), content: <Content />, edit: <Content />, canMakePayment: () => true, ariaLabel: label, supports: { features: settings.supports }
};

registerPaymentMethod( Paydock );
