/* global wra_public */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.wra-load-more' );
		if ( ! btn ) {
			return;
		}

		var wrap      = btn.closest( '.wra-load-more-wrap' );
		var container = btn.closest( '.wra-load-more-container' );
		if ( ! wrap || ! container ) {
			return;
		}

		var feed = container.querySelector( '.wra-feed' );
		if ( ! feed ) {
			return;
		}

		var paramsRaw = feed.getAttribute( 'data-wra-params' );
		var nonce     = feed.getAttribute( 'data-wra-nonce' );
		var offset    = parseInt( wrap.getAttribute( 'data-wra-offset' ), 10 ) || 0;
		var params;

		try {
			params = JSON.parse( paramsRaw );
		} catch ( err ) {
			return;
		}

		var originalText = btn.textContent;
		btn.disabled     = true;
		btn.textContent  = originalText; // keep label, disable handles visual state

		var body = new URLSearchParams();
		body.append( 'action', 'wra_load_more' );
		body.append( 'nonce', nonce );
		body.append( 'params', JSON.stringify( params ) );
		body.append( 'offset', offset );

		fetch( wra_public.ajax_url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString(),
		} )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( resp ) {
				if ( ! resp.success ) {
					btn.disabled = false;
					return;
				}

				var tmp = document.createElement( 'div' );
				tmp.innerHTML = resp.data.html;
				while ( tmp.firstChild ) {
					feed.appendChild( tmp.firstChild );
				}

				wrap.setAttribute( 'data-wra-offset', offset + params.items );

				if ( resp.data.has_more ) {
					btn.disabled = false;
				} else {
					wrap.style.display = 'none';
				}
			} )
			.catch( function () {
				btn.disabled = false;
			} );
	} );
}() );
