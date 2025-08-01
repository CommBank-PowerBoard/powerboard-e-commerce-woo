// noinspection PhpCSValidationInspection
// noinspection JSUnresolvedReference
jQuery(
	function ($) {
		$( document ).ready(
		function () {
			const CONFIG                      = {
				phoneInputIds: {
					shipping: '#shipping-phone',
					billing: '#billing-phone',
				},
				errorMessageClassName: 'wc-block-components-validation-error',
				phonePattern: /^(\+)?([(\(\d\)\s]{1,3})?([\d\s\(\d\)\-]{1,14})$/,
				errorMessageHtml: `<div class ="wc-block-components-validation-error" role="alert"><p>Please enter a valid phone number</p></div>`,
			};

			const getPhoneInputs     = () =>
			Object.entries( CONFIG.phoneInputIds )
				.reduce(
					(acc, [key, selector]) => {
						const $input = $( selector );
						if ($input.length) {
							acc[key] = $input;
						}
						return acc;
				},
					{}
					);

			// noinspection DuplicatedCode
			const validatePhone = ($input) => {
				const phone     = $input.val();
				$input.next( `.${CONFIG.errorMessageClassName}` ).remove();
				if (phone && (!CONFIG.phonePattern.test( phone ) || (phone.match(/\d/g) || []).length < 4 ) )  {
					$input.after( CONFIG.errorMessageHtml );
					// noinspection JSUnresolvedReference
					$input.addClass( 'power-board-invalid-phone' );

					return false;
				}
				// noinspection JSUnresolvedReference
				$input.removeClass( 'power-board-invalid-phone' );

				return true;
			};

			const initPhoneNumbersValidation = () => {
				const phoneInputs            = getPhoneInputs();
				if ( !Object.keys( phoneInputs )?.length ) {
					return;
				}

				Object.values( phoneInputs ).forEach(
				$input => $input.on(
					'blur input',
					() => window.getValidationResults(
						phoneInputs,
					validatePhone
						)
					)
			);

				window.getValidationResults( phoneInputs, validatePhone );
			};

			const waitForShippingPhoneRender = () => {
				let attempts                 = 0;
				const maxAttempts            = 10;

				const interval                      = setInterval(
					() => {
						const $shippingPhoneElement = $( CONFIG.phoneInputIds.shipping );
						if ($shippingPhoneElement.length) {
							clearInterval( interval );
							initPhoneNumbersValidation();

							$( '.wc-block-checkout__use-address-for-billing input[type="checkbox"]' ).on( "change", initPhoneNumbersValidation );
						}
						attempts++;
						if (attempts >= maxAttempts) {
							clearInterval( interval );
						}
				},
					500
					);
			};

			waitForShippingPhoneRender();
		}
		);

		function setPaymentMethodWatcher() {
			const radioButtons = $( '.wc-block-components-radio-control__input' ).filter(
				function () {
					// noinspection JSUnresolvedReference
					return $( this ).attr( 'id' ).includes( 'payment-method' );
				}
				)

			radioButtons.on( 'change', (event) => setPaymentMethod( event.target.value ) );
		}
		function setPaymentMethod(method) {
			if (method !== 'power_board') {
					window.widgetPowerBoard = null;
					toggleOrderButton( false );
			} else {
				toggleOrderButton( true );
				window.handleWidgetDisplay();
			}
		}

		function toggleOrderButton( hide ) {
			const orderButton = document.querySelector( '.wc-block-components-checkout-place-order-button' );
			if ( !orderButton ) {
				return;
			}
			orderButton.style.visibility = hide ? 'hidden' : 'visible';
		}

		function triggerFirstPaymentMethodChanges() {
			toggleOrderButton( true );
			const firstPaymentInterval        = setInterval(
				() => {
					const $checkedInput       = $( '.wc-block-components-radio-control__input:checked' );
					const $checkedInputs      = Object.values( $checkedInput );
					const $paymentMethodInput = $checkedInputs.filter(
						inputEl => inputEl.id?.includes( 'payment-method' )
						);
				if ($paymentMethodInput.length > 0) {
					clearInterval( firstPaymentInterval );
					setPaymentMethod( $paymentMethodInput[0].value );
					// noinspection JSUnresolvedReference
					jQuery( '.wc-block-components-form' )[0].dispatchEvent( new Event( "change" ) );
				}
				},
				200
			)
		}

		const firstInitInterval             = setInterval(
		() => {
			const $radioSelectPaymentMethod = $( '.wc-block-components-radio-control__input' );
			if ($radioSelectPaymentMethod) {
				clearInterval( firstInitInterval );
				triggerFirstPaymentMethodChanges();
				setPaymentMethodWatcher();
			}
		},
		200
		);
	}
)
