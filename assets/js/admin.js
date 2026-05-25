/* global wp, wra_admin */
(function () {
	'use strict';

	/* ── Confirm-before-submit dialogs ──────────────────────────── */
	document.addEventListener( 'submit', function ( event ) {
		var form    = event.target;
		var message = form.getAttribute( 'data-wra-confirm' );
		if ( message && ! window.confirm( message ) ) {
			event.preventDefault();
		}
	} );

	/* ── Fallback image media picker ────────────────────────────── */
	var addBtn  = document.getElementById( 'wra-add-fallback-image' );
	var preview = document.getElementById( 'wra-fallback-images-preview' );
	var idsInput = document.getElementById( 'wra-fallback-image-ids' );

	if ( addBtn && preview && idsInput && window.wp && window.wp.media ) {
		var frame;

		addBtn.addEventListener( 'click', function ( e ) {
			e.preventDefault();

			if ( frame ) {
				frame.open();
				return;
			}

			frame = wp.media( {
				title:   wra_admin.media_title,
				button:  { text: wra_admin.media_button },
				multiple: true,
				library: { type: 'image' },
			} );

			frame.on( 'select', function () {
				var selection = frame.state().get( 'selection' );
				var ids       = idsInput.value ? idsInput.value.split( ',' ).filter( Boolean ) : [];

				selection.each( function ( attachment ) {
					var id    = String( attachment.id );
					if ( ids.indexOf( id ) !== -1 ) {
						return;
					}
					ids.push( id );

					var sizes = attachment.attributes.sizes;
					var thumb = ( sizes && sizes.thumbnail ) ? sizes.thumbnail.url : attachment.attributes.url;

					var span = document.createElement( 'span' );
					span.className  = 'wra-fallback-thumb';
					span.dataset.id = id;
					span.innerHTML  =
						'<img src="' + thumb + '" alt="">' +
						'<button type="button" class="wra-remove-thumb" aria-label="Remove">&times;</button>';
					preview.appendChild( span );
				} );

				idsInput.value = ids.join( ',' );
			} );

			frame.open();
		} );

		/* Remove an individual thumbnail */
		preview.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '.wra-remove-thumb' );
			if ( ! btn ) {
				return;
			}
			var thumb = btn.closest( '.wra-fallback-thumb' );
			var id    = thumb.dataset.id;
			thumb.remove();
			idsInput.value = idsInput.value.split( ',' ).filter( function ( i ) {
				return i !== id;
			} ).join( ',' );
		} );
	}
}() );
