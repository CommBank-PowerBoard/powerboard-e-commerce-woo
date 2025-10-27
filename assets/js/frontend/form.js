// noinspection PhpCSValidationInspection
// noinspection JSUnresolvedReference
jQuery(
	function( $ ) {

		function setPaymentMethodWatcher() {
			const radioButtons = $( '.wc-block-components-radio-control__input' ).filter(
				function() {
					// noinspection JSUnresolvedReference
					return $( this ).attr( 'id' ).includes( 'payment-method' );
				}
			);

			radioButtons.on( 'change', ( event ) => setPaymentMethod( event.target.value ) );
		}

		function setPaymentMethod( method ) {
			if ( method !== 'power_board' ) {
				// Clean up PowerBoard widget when other payment method is selected
				window.widgetPowerBoard = null;
			}
		}

		function ensureOrderButtonVisible() {
			const orderButton = document.querySelector(
				'.wc-block-components-checkout-place-order-button'
			);
			if ( orderButton ) {
				orderButton.style.visibility = 'visible';
				orderButton.style.display    = '';
			}
		}

		function triggerFirstPaymentMethodChanges() {
			// Ensure order button is always visible (mirror classic checkout behavior)
			ensureOrderButtonVisible();

			const firstPaymentInterval        = setInterval(
				() => {
					const radioSelector = '.wc-block-components-radio-control__input:checked';
					const $checkedInput = $( radioSelector );
					const $checkedInputs      = Object.values( $checkedInput );
					const $paymentMethodInput = $checkedInputs.filter(
						inputEl => inputEl.id?.includes( 'payment-method' )
					);
					if ( $paymentMethodInput.length > 0 ) {
						clearInterval( firstPaymentInterval );
						setPaymentMethod( $paymentMethodInput[ 0 ].value );
						// noinspection JSUnresolvedReference
						jQuery( '.wc-block-components-form' )[ 0 ].dispatchEvent(
							new Event( 'change' )
						);
					}
				},
				200
			);
		}

		const firstInitInterval             = setInterval(
			() => {
				const $radioSelectPaymentMethod = $( '.wc-block-components-radio-control__input' );
				if ( $radioSelectPaymentMethod ) {
					clearInterval( firstInitInterval );
					triggerFirstPaymentMethodChanges();
					setPaymentMethodWatcher();

					// Continuously ensure the place order button stays visible
					// This mirrors classic checkout behavior where button is always available
					setInterval(
						() => {
							ensureOrderButtonVisible();
						},
						1000
					);
				}
			},
			200
		);
	}
);
