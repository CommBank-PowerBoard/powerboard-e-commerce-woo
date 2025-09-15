const getSelectedIndex = ( element ) => {
	if ( element.value === '' ) {
		return -1;
	}
	return element.selectedIndex;
};

// noinspection JSUnresolvedReference
jQuery( document ).ready(
	function( $ ) {
		const element = $( '.power_board-settings' ).get();
		element.map(
			( el ) => {
				if ( !el.classList.contains( 'is-optional' ) ) {
					for ( const elChild of el.children ) {
						if ( elChild.value === '' ) {
							elChild.setAttribute( 'disabled', true );
						}
					}
				}
				// The dropdown will show a blank value when default option is selected
				el.selectedIndex = getSelectedIndex( el );
				el.addEventListener(
					'change',
					function() {
						el.selectedIndex = getSelectedIndex( this );
					}
				);
			}
		);
	}
);
