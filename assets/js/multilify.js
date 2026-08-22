/**
 * Multilify front-end behaviour.
 *
 * Remembers the language a visitor picks from the switcher so browser
 * detection never overrides a deliberate choice on a later visit.
 */
( function () {
	'use strict';

	var COOKIE = 'multilify_language';
	var MAX_AGE = 31536000; // One year.

	function remember( code ) {
		if ( ! code ) {
			return;
		}

		var secure = 'https:' === window.location.protocol ? '; secure' : '';

		document.cookie = COOKIE + '=' + encodeURIComponent( code ) +
			'; path=/; max-age=' + MAX_AGE + '; samesite=lax' + secure;
	}

	document.addEventListener( 'click', function ( event ) {
		var link = event.target.closest( '.wp-multilang-switcher .lang-link' );

		if ( link ) {
			remember( link.getAttribute( 'data-lang' ) );
		}
	} );
}() );
