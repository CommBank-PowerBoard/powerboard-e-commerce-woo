// noinspection JSUnresolvedReference
jQuery(
	function ($) {
		$( document ).ready(
			() => {
				const CONFIG                      = {
					phoneInputIds: {
						shipping: '#shipping_phone',
						billing: '#billing_phone',
					},
					baseCheckboxIdName: 'payment_method',
					errorMessageClassName: 'wc-block-components-validation-error',
					phonePattern: /^(\+)?([(\(\d\)\s]{1,3})?([\d\s\(\d\)\-]{1,14})$/,
					errorMessageHtml: `<div class ="classic-checkout-validation-error wc-block-components-validation-error" role="alert"><p>Please enter a valid phone number</p></div>`,
				};
				const getPhoneInputs              = () =>
					Object.entries( CONFIG.phoneInputIds )
					.reduce(
						( acc, [key, selector] ) => {
							const $input          = $( selector );
							if ( $input.length ) {
								acc[key] = $input;
							}
							return acc;
						},
						{}
					);
				// noinspection DuplicatedCode
				const validatePhone = ( $input ) => {
					// noinspection JSUnresolvedReference
					const phone = $input.val();
					$input.next( `.${CONFIG.errorMessageClassName}` ).remove();
					if ( phone && ( !CONFIG.phonePattern.test( phone ) || (phone.match(/\d/g) || []).length < 4 ) ) {
						$input.after( CONFIG.errorMessageHtml );
						// noinspection JSUnresolvedReference
						$input.addClass( 'power-board-invalid-phone' );
						return false;
					}
					// noinspection JSUnresolvedReference
					$input.removeClass( 'power-board-invalid-phone' );
					return true;
				};
				const initPhoneNumberValidation = () => {
					const phoneInputs           = getPhoneInputs();
					if ( !Object.keys( phoneInputs ).length ) {
						return;
					}
					Object.values( phoneInputs ).forEach( input => input.on( 'blur input', () => window.getValidationResults( phoneInputs, validatePhone ) ) );
					window.getValidationResults( phoneInputs, validatePhone );
				};
				const powerBoardHelper = {
					invalidPostcode: false,
					invalidEmail: false,
					paymentMethodLoaded: null,
					selectedPaymentMethod: null,
					form: null,
					formChangedTimer: null,
					lastAddressVerified: null,
					totalChangesTimeout: null,
					totalChangesSecondTimeout: null,
					shippingChangedTimeout: null,
					lastMasterWidgetInit: null,
					currentSavedShipping: null,
					widgetVisibilityInterval: null,
					showErrorMessage( errorMessage ) {
						window.scrollTo( { top: 0, behavior: 'smooth' } );
						let $wrapper   = $( '.woocommerce-notices-wrapper' ).first();
						if ( ! $wrapper.length ) {
							$wrapper = $( 'form[name="checkout"]' ).prepend( '<div class="woocommerce-notices-wrapper"></div>' ).find( '.woocommerce-notices-wrapper' );
						}
						$wrapper.empty().append( '<ul class="woocommerce-error" role="alert"><li>' + errorMessage + '</li></ul>' );
					},
					clearCustomNotices() {
						const container = document.querySelector( '.woocommerce-notices-wrapper' );
						if ( container ) {
							container.innerHTML = '';
						}
					},
					reInitMasterWidget() {
						let loading = $( '#loading' );
						this.toggleWidgetVisibility( true );
						loading.show();
						this.initMasterWidget();
					},
					handleWidgetError() {
						this.reInitMasterWidget();

						const noticesWrapper      = $( 'div.woocommerce-notices-wrapper' )[0];
						const removeErrorInterval = setInterval(
							() => {
								if ( noticesWrapper?.children.length > 0 ) {
									clearInterval( removeErrorInterval );
									const removeErrorTimeout = setTimeout(
										() => {
											clearTimeout( removeErrorTimeout );
											for ( let notice of noticesWrapper.children ) {
												if ( notice.classList.contains( 'is-error' ) ) {
													notice.remove();
												}
											}
										},
										10000
									);
								}
							},
							200
						);
					},
					setFieldLikeInvalid( fieldName ) {
						let element = document.getElementById( `${fieldName}_field` );
						if (element) {
							element.classList.add( "woocommerce-invalid" );
							element.classList.add( "woocommerce-invalid-required-field" )
						}
					},
					setFieldLikeValid( fieldName ) {
						let element = document.getElementById( `${fieldName}_field` );
						if (element) {
							element.classList.remove( "woocommerce-invalid" );
							element.classList.remove( "woocommerce-invalid-required-field" )
						}
					},
					getFieldsList( ignoreCheckbox = false, completeList = false ) {
						const fieldsNames = [
							'first_name',
							'last_name',
							'country',
							'address_1',
							'address_2',
							'city',
							'state',
							'postcode',
							'email',
							'phone',
						];

						if (completeList) {
							fieldsNames.push( 'company' );
						}
						let result           = [];
						let shippingCheckbox = $( '[name="ship_to_different_address"]' )
						let prefixes         = ['billing_']
						if ( shippingCheckbox?.[0]?.checked || ignoreCheckbox ) {
							prefixes.push( 'shipping_' )
						}
						prefixes.map(
							( prefix ) => {
								fieldsNames.map(
									( field ) => {
										if ( !completeList ) {
											if ('shipping_' === prefix && ['email', 'phone'].includes( field )) {
												return;
											}
											if ('billing_' === prefix && ['phone'].includes( field )) {
												return;
											}
										}
										result.push( `${prefix}${field}` )
									}
								)
							}
						)
						return result;
					},
					isValidForm( paymentMethod ) {
						this.hideFormValidationError( paymentMethod );
						let fieldList                 = this.getFieldsList();
						let result                    = true;
						const additionalTermsCheckbox = document.getElementById( '_woo_additional_terms' );
						const invalidPhone            = document.getElementById( 'shipping_phone' )?.className.includes( 'power-board-invalid-phone' ) || document.getElementById( 'billing_phone' )?.className.includes( 'power-board-invalid-phone' );
						if ( this.invalidPostcode || this.invalidEmail || invalidPhone || ( additionalTermsCheckbox && !additionalTermsCheckbox.checked ) ) {
							result = false;
						}
						const defaultTermsCheckbox = document.getElementById( 'terms' );
						if ( defaultTermsCheckbox && !defaultTermsCheckbox.checked ) {
							result = false;
						}
						fieldList.filter( field => !field.includes( 'address_2' ) ).forEach(
							( fieldName ) => {
								const el = document.getElementById( fieldName );
								if ( el && el.value ) {
									this.setFieldLikeValid( fieldName );
								} else {
									this.setFieldLikeInvalid( fieldName );
									this.showFormValidationError( paymentMethod );
									result = false;
								}
							}
						);
					if ( result ) {
						// noinspection JSUnresolvedReference
						let isPhoneNumberValid = this.isBillingPhoneValid();
						// noinspection JSUnresolvedReference
						const useSameBillingAndShipping = document.getElementById( 'ship-to-different-address-checkbox' )?.checked;
						if ( !useSameBillingAndShipping ) {
							isPhoneNumberValid = isPhoneNumberValid && this.isShippingPhoneValid();
						}
						return isPhoneNumberValid;
					}
						return result;
					},
					isShippingPhoneValid() {
						return !document.getElementById( 'shipping_phone' )?.classList?.contains( 'power-board-invalid-phone' );
					},
					isBillingPhoneValid() {
						return !document.getElementById( 'billing_phone' )?.classList?.contains( 'power-board-invalid-phone' );
					},
					toggleWidgetVisibility( hide ) {
						let widget        = $( '#classic-powerBoardCheckout_wrapper #standaloneWidget' );
						let widgetList    = $( '#classic-powerBoardCheckout_wrapper #list' );
						let widgetSpinner = $( '#classic-powerBoardCheckout_wrapper #spinner' );
						if (hide) {
							widget?.hide();
							widgetList?.hide();
							widgetSpinner?.hide();
						} else {
							widget?.show();
							widgetList?.show();
							widgetSpinner?.show();
						}
					},
					setPaymentMethod( methodName, forceInit = false ) {
						if ( !forceInit && this.paymentMethodLoaded === methodName ) {
							return;
						}
						this.selectedPaymentMethod = methodName;
						let error                  = $( '#required-fields-validation-error' );
						let invalidFieldsError     = $( '#invalid-fields-error' );
						let createIntentError      = $( '#intent-creation-error' );
						let loading                = $( '#loading' );
						loading.show();
						error.hide();
						invalidFieldsError.hide();
						createIntentError.hide();

						if ( !this.isValidForm( methodName ) && methodName === 'power_board' ) {
							this.toggleWidgetVisibility( true );
							this.toggleOrderButton( true );
							loading.hide();

							const invalidPhone = document.getElementById( 'shipping_phone' )?.className.includes( 'power-board-invalid-phone' ) || document.getElementById( 'billing_phone' )?.className.includes( 'power-board-invalid-phone' );
							if ( this.invalidPostcode || this.invalidEmail || invalidPhone ) {
								invalidFieldsError.show();
							} else {
								error.show();
							}

							return;
						}
						if (methodName !== 'power_board' ) {
							this.toggleWidgetVisibility( true );
							loading.show();
							error.hide();
						}
						this.paymentMethodLoaded = methodName;
						switch (this.paymentMethodLoaded) {
							case 'power_board':
								this.toggleOrderButton( true );
								this.toggleWidgetVisibility( true );
								this.initMasterWidget();
								break;
							default:
								window.widgetPowerBoard = null;
								this.toggleOrderButton( false );
						}
					},
					showFormValidationError( paymentMethod ) {
						if (paymentMethod) {
							$( `#classic-${paymentMethod}` ).hide();
							$( `#classic-${paymentMethod}-error` ).show();
						}
					},
					hideFormValidationError( paymentMethod ) {
						if (paymentMethod) {
							$( `#classic-${paymentMethod}` ).show();
							$( `#classic-${paymentMethod}-error` ).hide();
						}
					},
					initMasterWidget() {
						const initTimestamp       = Date.now();
						this.lastMasterWidgetInit = initTimestamp;
						setTimeout( () => this.toggleOrderButton( true ), 100 );
						let addressData     = this.getAddressData( false );
						let billingAddress  = addressData.address;
						let shippingAddress = billingAddress;
						this.clearCustomNotices();
						const shipToCheckbox = document.getElementById( 'ship-to-different-address-checkbox' );
						if ( shipToCheckbox && shipToCheckbox.checked ) {
							shippingAddress = addressData.shipping_address;
						}

						const createCheckbox = document.getElementById( 'createaccount' );
						const createAccount  = createCheckbox && createCheckbox.checked ? 'true' : 'false';
						// noinspection JSUnresolvedReference
						const data = {
							_wpnonce: PowerBoardAjaxCheckout.wpnonce_intent,
							address: billingAddress,
							shipping_address: shippingAddress,
							create_account: createAccount
						};

						if ( this.widgetVisibilityInterval ) {
							clearInterval( this.widgetVisibilityInterval )
						}
						// noinspection JSUnresolvedReference
						jQuery.ajax(
							{
								url: '/?wc-ajax=power-board-create-charge-intent',
								type: 'POST',
								data: data,
								success: ( response ) => {
									if ( !this.isValidForm( 'power_board' ) ) {
										let error = $( '#fields-validation-error' );

										if ( this.invalidPostcode || this.invalidEmail ) {
											error = $( '#invalid-fields-error' );
										}
										let loading = $( '#loading' );
										this.toggleWidgetVisibility( true );
										this.toggleOrderButton( true );
										loading.hide();
										error.show();
									} else {
										if (initTimestamp === this.lastMasterWidgetInit) {
											const showError = message => this.showErrorMessage( message );
											if (response.success) {
												const checkoutWrapper = document.getElementById( 'classic-powerBoardCheckout_wrapper' );
												if (!checkoutWrapper?.checkVisibility()) {
													this.widgetVisibilityInterval = setInterval(
														() => {
															if ( checkoutWrapper?.checkVisibility() ) {
																this.loadMasterWidget( response );
																clearInterval( this.widgetVisibilityInterval );
															}
														},
														2000
													);
												} else {
													this.loadMasterWidget( response );
												}
											} else {
												if ( response.data?.code === 'invalid_account_creation' ) {
													showError( response.data.message );
												}

												// noinspection JSUnresolvedReference
												let error = jQuery( '#intent-creation-error' );
												// noinspection JSUnresolvedReference
												let loading = jQuery( '#loading' );
												this.toggleWidgetVisibility( true );
												this.toggleOrderButton( true );
												loading.hide();
												error.show();
											}
										}
									}
								}
							}
						);
					},
					loadMasterWidget(response) {
						// noinspection JSUnresolvedReference
						this.toggleWidgetVisibility( false );
						// noinspection JSUnresolvedReference
						window.widgetPowerBoard = new cba.Checkout( '#classic-powerBoardCheckout_wrapper', response.data.token );
						// noinspection JSUnresolvedReference
						window.widgetPowerBoard.setEnv( this.getConfigs().environment )
						const showError          = message => this.showErrorMessage( message );
						const handleWidgetError  = () => this.handleWidgetError();
						const submitForm         = () => this.form.submit();
						const intentId           = response.data.intentId;
						// noinspection JSUnresolvedReference
						window.widgetPowerBoard.onPaymentSuccessful(
							( data ) => {
								// noinspection JSUnresolvedReference
								jQuery( '#chargeid' ).val( data.charge_id );
								// noinspection JSUnresolvedReference
								jQuery( '#intentid' ).val( intentId );
								submitForm();
								window.widgetPowerBoard = null;
							}
						);
						// noinspection JSUnresolvedReference
						window.widgetPowerBoard.onPaymentFailure(
							( data ) => {
								// noinspection JSUnresolvedReference
								jQuery.ajax(
									{
										url: '/?wc-ajax=power-board-process-payment-result',
										method: 'POST',
										data: {
											_wpnonce: PowerBoardAjaxCheckout.wpnonce_process_payment,
											payment_response: {
												...data,
												errorMessage: data.message || 'Transaction failed',
											}
										},
										success: () => {
											showError( 'Transaction failed. Please check your payment details or contact your bank' );
											handleWidgetError();
											window.widgetPowerBoard = null;
										}
									}
								);
							}
						);
						// noinspection JSUnresolvedReference
						window.widgetPowerBoard.onPaymentExpired(
							() => {
								showError( 'Your payment session has expired. Please retry your payment' );
								handleWidgetError();
								window.widgetPowerBoard = null;
							}
						);
					},
					getAddressData( returnJson = true ) {
						let fieldList          = this.getFieldsList( true, true );
						let result             = {
							shipping_address: {},
							address: {}
						};
						fieldList.forEach(
							( fieldName ) => {
								let element    = document.getElementById( fieldName );
								let value      = element ? element.value : '';
								let isShipping = fieldName.startsWith( 'shipping_' );
								result[isShipping ? 'shipping_address' : 'address'][fieldName.replace( 'shipping_', '' ).replace( 'billing_', '' )] = value;
							}
						);
					if ( returnJson ) {
						return JSON.stringify( result );
					}
						return result;
					},
					getConfigs() {
						// noinspection JSUnresolvedReference
						let settings              = $( `#classic-power_board-settings` ).val()
						settings                  = JSON.parse( settings )
						return settings;
					},
					toggleOrderButton( hide ) {
						setTimeout(
							() => {
								const orderButton = document.querySelector( 'button#place_order' );
								if ( orderButton ) {
									orderButton.style.visibility = hide ? 'hidden' : 'visible';
								}
							},
							100
						);
					},
					handleCartTotalChanged( event ) {
						if (this.totalChangesTimeout) {
							clearTimeout( this.totalChangesTimeout );
						}
						if (this.totalChangesSecondTimeout) {
							clearTimeout( this.totalChangesSecondTimeout );
						}
						this.toggleWidgetVisibility( true );

						this.totalChangesTimeout = setTimeout(
							() => {
								const orderTotal = this.getUIOrderTotal();
								const cartTotal  = +event.detail.cartTotal;
								if (orderTotal) {
									const address = this.getAddressData( true );
									if (orderTotal !== cartTotal && this.lastAddressVerified === address) {
										if (this.totalChangesSecondTimeout) {
											clearTimeout( this.totalChangesSecondTimeout );
										}
										this.totalChangesSecondTimeout = setTimeout(
											() => {
												const orderTotal       = this.getUIOrderTotal();
												if (orderTotal) {
													if (orderTotal !== cartTotal) {
														// noinspection JSUnresolvedReference
														$( document.body ).trigger( 'update_checkout' );
													}

													this.initMasterWidget();
												}
											},
											300
										)
									} else {
										this.initMasterWidget();
									}
								}
								this.currentSavedShipping = event.detail.shippingId;
						},
							300
						)
					},
					getUIOrderTotal() {
						const orderTotal = $( '.order-total' )[0];
						return orderTotal ? +orderTotal.innerText.replace( /[^0-9.,]*/, '' ) : null;
					},
					init() {
						const paymentMethodInterval = setInterval(
							() => {
								// noinspection JSUnresolvedReference
								const paymentMethod = $( 'input[name="payment_method"]:checked' ).val();
								if ( paymentMethod && ! this.paymentMethodLoaded ) {
									this.lastAddressVerified = this.getAddressData( true );
									clearInterval( paymentMethodInterval );
									this.checkIsValidPostCodeAndLoadPayment( false );
									this.checkIsValidEmailAndLoadPayment();
								}
							},
							100
						);

						this.form = $( 'form[name="checkout"]' );
						$( '#ship-to-different-address-checkbox' ).on(
							'change',
							function () {
								const selectedPayment = $( 'input[name="payment_method"]:checked' ).val();
								powerBoardHelper.setPaymentMethod( selectedPayment, true );
							}
							);
						this.form.on(
							'change',
							( event ) => {
								try {
									if ( event.target.id.includes( 'chargeid' ) || event.target.id.includes( 'intentid' ) ) {
										return
									}
									this.handleShippingChanged( event.target.id );
									this.handleFormChanged( event.target );
								} catch ( e ) {
									console.error( e );
								}
							}
						);

						document.addEventListener( "power_board_cart_total_changed", this.handleCartTotalChanged.bind( this ) );

						$( document.body ).on(
							'updated_checkout',
							() => {
								const selectedPayment = $( 'input[name="payment_method"]:checked' ).val();
								if ( selectedPayment === 'power_board' ) {
									this.setPaymentMethod( selectedPayment, true );
								}
						}
							);
					},
					handleShippingChanged( eventTargetId ) {
						if (this.shippingChangedTimeout) {
							clearTimeout( this.shippingChangedTimeout );
						}
						if ( eventTargetId.includes( 'shipping_method' ) ) {
							if (this.currentSavedShipping !== eventTargetId.value) {
								this.shippingChangedTimeout = setTimeout(
									() => {
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
														// Dispatch the custom event with updated cart total in detail
														const event = new CustomEvent(
															'power_board_cart_total_changed',
															{
																detail: {
																	cartTotal: response.data.cart_total
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
							}
						}
					},
					handleFormChanged( eventTarget ) {
						if (this.formChangedTimer) {
							clearTimeout( this.formChangedTimer );
						}
						this.formChangedTimer        = setTimeout(
							() => {
								const eventTargetId  = eventTarget.id;
								this.handleOrderCommentsChanges( eventTargetId, eventTarget.value );
								const currentAddress = this.getAddressData( true );
								if ( eventTargetId.includes( 'billing_postcode' ) || eventTargetId.includes( 'billing_country' ) || eventTargetId.includes( 'billing_state' ) ) {
									this.checkIsValidPostCodeAndLoadPayment();
								} else if ( eventTargetId.includes( 'billing_email' ) ) {
									this.checkIsValidEmailAndLoadPayment();
								} else if (
									this.lastAddressVerified !== currentAddress
									|| eventTargetId.includes( 'payment_method' )
									|| eventTargetId.includes( 'createaccount' )
									|| eventTargetId.includes( '_woo_additional_terms' )
									|| eventTargetId.includes( 'terms' )
								) {
									this.lastAddressVerified = currentAddress;
									// noinspection JSUnresolvedReference
									this.setPaymentMethod(
										$( 'input[name="payment_method"]:checked' ).val(),
										true
									);
								}
							},
							500
						);
					},
					checkIsValidPostCodeAndLoadPayment( loadPayment = true ) {
						const postcode = document.getElementById( 'billing_postcode' ).value;
						const country  = document.getElementById( 'billing_country' ).value;
						// noinspection JSUnresolvedReference
						const selectedPaymentMethod = $( 'input[name="payment_method"]:checked' ).val();

						if ( postcode && country ) {
							// noinspection JSUnresolvedReference
							jQuery.ajax(
								{
									url: '/?wc-ajax=power-board-check-postcode',
									type: 'POST',
									data: {
										_wpnonce: PowerBoardAjaxCheckout.wpnonce_check_postcode,
										postcode: postcode,
										country: country,
									},
									success: ( response ) => {
										if ( response.success ) {
											const postcodeInput  = document.getElementById( 'billing_postcode' );
											this.invalidPostcode = false;
											document.querySelector( '.power-board-postcode-error-message' )?.remove();
											postcodeInput.classList.remove( 'power-board-invalid-postcode' );
										} else {
											this.invalidPostcode = true;
											const postcodeInput  = document.getElementById( 'billing_postcode' );
											this.addErrorMessageToField( postcodeInput, response.data.message, 'postcode' );
										}

										if ( loadPayment ) {
											this.setPaymentMethod( selectedPaymentMethod, true );
										}
									}
								}
							);
						} else {
							if ( loadPayment ) {
								this.setPaymentMethod( selectedPaymentMethod, true );
							}
						}
					},
					checkIsValidEmailAndLoadPayment( loadPayment = true ) {
						const email = document.getElementById( 'billing_email' ).value;
						// noinspection JSUnresolvedReference
						const selectedPaymentMethod = $( 'input[name="payment_method"]:checked' ).val();

						if ( email ) {
							// noinspection JSUnresolvedReference
							jQuery.ajax(
								{
									url: '/?wc-ajax=power-board-check-email',
									type: 'POST',
									data: {
										_wpnonce: PowerBoardAjaxCheckout.wpnonce_check_email,
										email: email,
									},
									success: ( response ) => {
										const emailInput = document.getElementById( 'billing_email' );
										if ( response.success ) {
											this.invalidEmail = false;
											document.querySelector( '.power-board-email-error-message' )?.remove();
											emailInput.classList.remove( 'power-board-invalid-email' );
										} else {
											this.invalidEmail = true;
											this.addErrorMessageToField( emailInput, response.data.message, 'email' );
										}

										if ( loadPayment ) {
											this.setPaymentMethod( selectedPaymentMethod, true );
										}
									}
								}
							);
						} else {
							if ( loadPayment ) {
								this.setPaymentMethod( selectedPaymentMethod, true );
							}
						}
					},
					addErrorMessageToField( fieldInput, errorMessage, fieldIdentifier ) {
						document.querySelector( '.power-board-' + fieldIdentifier + '-error-message' )?.remove();
						const messageContainer = document.createElement( "div" );
						messageContainer.setAttribute( 'class', 'classic-checkout-validation-error wc-block-components-validation-error power-board-' + fieldIdentifier + '-error-message' );
						messageContainer.setAttribute( 'role', 'alert' );
						const	message   = document.createElement( "p" );
						message.innerText = errorMessage;
						messageContainer.appendChild( message );
						fieldInput.after( messageContainer );
						fieldInput.classList.add( 'power-board-invalid-' + fieldIdentifier );
					},
					handleOrderCommentsChanges( eventTargetId, value ) {
						if ( eventTargetId.includes( 'order_comments' ) ) {
							// noinspection JSUnresolvedReference
							jQuery.ajax(
								{
									url: '/?wc-ajax=power-board-update-order-notes',
									type: 'POST',
									data: {
										_wpnonce: PowerBoardAjaxCheckout.wpnonce_update_order_notes,
										value: value,
									}
								}
							);
						}
					},
					addBeforeLeavePageListener() {
						window.onbeforeunload = function () {
							document.removeEventListener( "power_board_cart_total_changed", this.handleCartTotalChanged )
						};
					}
				}

				powerBoardHelper.init();
				powerBoardHelper.addBeforeLeavePageListener();
				initPhoneNumberValidation();
				// Initialize cart changes helper for cross-tab synchronization
				if (window.CartChangesHelper) {
					window.CartChangesHelper.getInstance();
				} else {
					// Load cart changes helper dynamically if not already loaded
					const script  = document.createElement( 'script' );
					script.src    = window.powerBoardSettings?.assetsUrl + '/js/helpers/cart-changes.helper.js' ||
								'/wp-content/plugins/power-board/assets/js/helpers/cart-changes.helper.js';
					script.onload = () => {
						if (window.CartChangesHelper) {
							window.CartChangesHelper.getInstance();
						}
					};
					document.head.appendChild( script );
				}
			}
		);
	}
)
