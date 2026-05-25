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

		btn.disabled = true;

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

				// Record the index of the first new child before inserting.
				var firstNewIndex = feed.children.length;

				var tmp = document.createElement( 'div' );
				tmp.innerHTML = resp.data.html;
				while ( tmp.firstChild ) {
					feed.appendChild( tmp.firstChild );
				}

				wrap.setAttribute( 'data-wra-offset', offset + params.items );

				// Move focus to the first newly appended article so keyboard and
				// screen-reader users land in the new content immediately.
				var firstNew = feed.children[ firstNewIndex ];
				if ( firstNew ) {
					firstNew.setAttribute( 'tabindex', '-1' );
					firstNew.focus();
				}

				// Announce the count via the aria-live region.
				var loaded    = feed.children.length - firstNewIndex;
				var announcer = container.querySelector( '.wra-announcer' );
				if ( announcer && loaded > 0 ) {
					// Clear first so identical text still triggers the announcement.
					announcer.textContent = '';
					announcer.textContent = ( wra_public.items_loaded || '%d more items loaded.' ).replace( '%d', loaded );
				}

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
