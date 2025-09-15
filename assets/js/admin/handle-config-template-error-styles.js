const checkoutTemplatesSelectBoxes = [
	'woocommerce_power_board_power_board_CREDENTIALS_ACCESS_KEY',
	'woocommerce_power_board_power_board_CHECKOUT_CONFIGURATION_ID',
	'woocommerce_power_board_power_board_CHECKOUT_CUSTOMISATION_ID'
];
// noinspection JSUnresolvedReference
jQuery( document ).ready(
	function() {
		checkoutTemplatesSelectBoxes.map(
			templateSelectBoxId => {
				const element            = document.getElementById( templateSelectBoxId );
				const descriptionElement = element.parentElement.querySelector( 'p.description' );
				if ( descriptionElement ) {
					if ( !element.classList.contains( 'grey-description' ) ) {
						descriptionElement.style.color = 'red';
					}
					element.addEventListener(
						'change',
						() => {
							if ( element.value ) {
								descriptionElement.style.display = 'none';
							}
						}
					);
				}
			}
		);
	}
);
