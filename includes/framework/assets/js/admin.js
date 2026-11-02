jQuery( function ( $ ) {
	$( document ).on( 'click', '.bundlekit-install-btn', function () {
		var $btn = $( this );
		var $card = $btn.closest( '.bundlekit-plugin-card' );
		var $status = $card.find( '.bundlekit-install-status' );
		$btn.prop( 'disabled', true );
		$status.text( bundlekitAdmin.i18n.installing );
		$.post( bundlekitAdmin.ajaxUrl, {
			action: 'bundlekit_install_plugin',
			slug: $btn.data( 'slug' ),
			_ajax_nonce: bundlekitAdmin.nonce
		} ).done( function ( res ) {
			if ( res && res.success ) {
				$status.text( bundlekitAdmin.i18n.active );
				// Installed now, so per spec it no longer belongs in Recommended.
				$card.fadeOut( 400, function () { $( this ).remove(); } );
			} else {
				$status.text( ( res && res.data && res.data.message ) || bundlekitAdmin.i18n.error );
				$btn.prop( 'disabled', false );
			}
		} ).fail( function () {
			$status.text( bundlekitAdmin.i18n.error );
			$btn.prop( 'disabled', false );
		} );
	} );
} );
