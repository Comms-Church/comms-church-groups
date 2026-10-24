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
		var status = root.querySelector( '.ccgrp-status' );
		var reset  = root.querySelector( '.ccgrp-reset' );

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

			// Say how many are left; stay quiet when nothing is filtered.
			if ( status ) {
				status.textContent = ( q || t )
					? ( status.getAttribute( 'data-template' ) || '%1$d / %2$d' )
						.replace( '%1$d', shown ).replace( '%2$d', cards.length )
					: '';
			}
		}

		// Filtering is in-memory over rendered cards, so respond on every keystroke.
		if ( search ) search.addEventListener( 'input', apply );
		if ( type ) type.addEventListener( 'change', apply );
		if ( reset ) reset.addEventListener( 'click', function () {
			if ( search ) search.value = '';
			if ( type ) type.value = '';
			apply();
			( search || type ).focus();
		} );
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
