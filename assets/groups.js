/**
 * Client-side filtering for the group finder.
 *
 * Progressive enhancement: the markup is complete and readable without this file.
 * All filtering is in-memory over already-rendered cards — no API calls, no reloads.
 */
( function () {
	'use strict';

	function initFinder( root ) {
		var search = root.querySelector( '.ccgrp-search' );
		var type   = root.querySelector( '.ccgrp-type' );
		var cards  = Array.prototype.slice.call( root.querySelectorAll( '.ccgrp-card' ) );
		var empty  = root.querySelector( '.ccgrp-noresults' );

		if ( ! cards.length || ( ! search && ! type ) ) return;

		function apply() {
			var q = search ? search.value.trim().toLowerCase() : '';
			var t = type ? type.value : '';
			var shown = 0;

			cards.forEach( function ( card ) {
				var haystack = card.getAttribute( 'data-name' ) || '';
				var cardType = card.getAttribute( 'data-type' ) || '';
				var match = ( ! q || haystack.indexOf( q ) !== -1 ) && ( ! t || cardType === t );

				card.hidden = ! match;
				if ( match ) shown++;
			} );

			if ( empty ) empty.hidden = shown !== 0;
		}

		// Debounced so typing in a large list doesn't thrash layout.
		var timer = null;
		function debounced() {
			window.clearTimeout( timer );
			timer = window.setTimeout( apply, 120 );
		}

		if ( search ) search.addEventListener( 'input', debounced );
		if ( type ) type.addEventListener( 'change', apply );
	}

	function boot() {
		document.querySelectorAll( '.ccgrp' ).forEach( initFinder );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
