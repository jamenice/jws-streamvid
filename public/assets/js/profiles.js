/**
 * Profile page V3: the "Sections" sheet on phones and the current tab
 * scrolled into view on tablets. Without this file the menu simply shows
 * above the tab on phones.
 */
( function () {
	'use strict';

	var button = document.querySelector( '.jws-pv3__sections' );
	var side = document.getElementById( 'jws-pv3-side' );

	if ( ! button || ! side ) {
		return;
	}

	var backdrop = document.querySelector( '.jws-pv3__backdrop' );
	var close = side.querySelector( '.jws-pv3__close' );
	var phone = window.matchMedia( '(max-width: 767px)' );
	var tablet = window.matchMedia( '(min-width: 768px) and (max-width: 1024px)' );
	var lastFocus = null;

	document.documentElement.classList.add( 'jws-pv3-js' );
	button.hidden = false;

	function open() {
		lastFocus = document.activeElement;
		side.classList.add( 'is-open' );
		side.setAttribute( 'role', 'dialog' );
		side.setAttribute( 'aria-modal', 'true' );
		button.setAttribute( 'aria-expanded', 'true' );
		if ( backdrop ) {
			backdrop.hidden = false;
		}
		document.body.classList.add( 'jws-pv3-locked' );
		( close || side ).focus();
	}

	function shut() {
		if ( ! side.classList.contains( 'is-open' ) ) {
			return;
		}
		side.classList.remove( 'is-open' );
		side.removeAttribute( 'role' );
		side.removeAttribute( 'aria-modal' );
		button.setAttribute( 'aria-expanded', 'false' );
		if ( backdrop ) {
			backdrop.hidden = true;
		}
		document.body.classList.remove( 'jws-pv3-locked' );
		if ( lastFocus ) {
			lastFocus.focus();
		}
	}

	button.addEventListener( 'click', open );

	if ( close ) {
		close.addEventListener( 'click', shut );
	}
	if ( backdrop ) {
		backdrop.addEventListener( 'click', shut );
	}

	document.addEventListener( 'keydown', function ( event ) {
		if ( 'Escape' === event.key ) {
			shut();
		}
	} );

	// Leaving phone width with the sheet open would leave the page locked.
	phone.addEventListener( 'change', function ( event ) {
		if ( ! event.matches ) {
			shut();
		}
	} );

	if ( tablet.matches ) {
		var current = side.querySelector( '.jws-pv3__link.is-active' );
		var strip = side.querySelector( '.jws-pv3__nav' );

		if ( current && strip ) {
			strip.scrollLeft = current.offsetLeft - ( strip.clientWidth - current.offsetWidth ) / 2;
		}
	}
}() );
