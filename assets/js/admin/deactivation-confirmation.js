// noinspection PhpCSValidationInspection
// noinspection JSUnresolvedReference

jQuery(
	function ( $ ) {
		if ( !window.powerBoardWidgetSettings || !window.powerBoardWidgetSettings.pluginName ) {
			return;
		}

		const pluginName = window.powerBoardWidgetSettings.pluginName;

		$( document ).on(
			'click',
			'a[href*="plugins.php?action=deactivate"][aria-label="Deactivate ' + pluginName + '"]',
			function ( e ) {
				e.preventDefault();
				// noinspection JSUnresolvedReference
				const urlRedirect = $( this ).attr( 'href' );
				// noinspection JSUnresolvedReference
				if ( confirm( 'Are you sure you want to deactivate ' + pluginName + '?' ) ) {
					window.location.href = urlRedirect;
				}
			}
		);
	}
);
