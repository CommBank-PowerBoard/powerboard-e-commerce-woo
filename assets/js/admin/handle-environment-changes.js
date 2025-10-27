const checkoutVersionSelectBoxId        = 'woocommerce_power_board_power_board_CHECKOUT_VERSION';
const selectElementsToUpdateOnEnvChange = [
	checkoutVersionSelectBoxId,
	'woocommerce_power_board_power_board_CHECKOUT_CONFIGURATION_ID',
	'woocommerce_power_board_power_board_CHECKOUT_CUSTOMISATION_ID'
];
const elementsToUpdateOnEnvChange = [
	'woocommerce_power_board_power_board_CREDENTIALS_ACCESS_KEY',
	...selectElementsToUpdateOnEnvChange
];
const environmentSelectBoxId = 'woocommerce_power_board_power_board_ENVIRONMENT_ENVIRONMENT';

// noinspection JSUnresolvedReference
jQuery( document ).ready(
	function() {
		const environmentSelectBoxElement = document.getElementById( environmentSelectBoxId );
		if ( !environmentSelectBoxElement ) {
			return;
		}
		const selectedEnvironmentSavedToDB = environmentSelectBoxElement.value;
		const form                         = document.getElementById( 'mainform' );

		let savedElements = saveSelectOptionsByEnvironment();
		const formData    = getFormData();

		environmentSelectBoxElement.addEventListener(
			'change',
			() => {
				elementsToUpdateOnEnvChange.forEach(
					( element ) => {
						if ( selectedEnvironmentSavedToDB !== environmentSelectBoxElement.value ) {
							removeElementsValues( element );
						} else {
							addElementsValueAndOptions( element );
						}
					}
				);
			}
		);

		function removeElementsValues( element ) {
			form.elements[ element ].value = '';
			if ( selectElementsToUpdateOnEnvChange.includes( form.elements[ element ].id ) ) {
				if ( form.elements[ element ].id !== checkoutVersionSelectBoxId ) {
					form.elements[ element ].innerHTML = '';
				}
				form.elements[ element ].selectedIndex = -1;
			}
		}

		function addElementsValueAndOptions( element ) {
			form.elements[ element ].value = formData[ element ];
			if ( savedElements[ environmentSelectBoxElement.value ] ) {
				const savedEnvElements = savedElements[ environmentSelectBoxElement.value ];
				form.elements[ element ].innerHTML = savedEnvElements[ element ];

				if ( !formData[ element ] ) {
					form.elements[ element ].selectedIndex = -1;
				}
			}
		}

		function getFormData() {
			const formData = {};
			for ( let element of form.elements ) {
				if ( element.name?.includes( 'woocommerce_power_board_power_board' ) ) {
					formData[ element.name ] = element.value;
				}
			}
			return formData;
		}

		function saveSelectOptionsByEnvironment() {
			let savedElements                                     = {};
			selectElementsToUpdateOnEnvChange.forEach(
				( id ) => {
					savedElements[ selectedEnvironmentSavedToDB ] = {
						...savedElements[ selectedEnvironmentSavedToDB ],
						[ id ]: form.elements[ id ].innerHTML
					};
				}
			);
			return savedElements;
		}
	}
);
