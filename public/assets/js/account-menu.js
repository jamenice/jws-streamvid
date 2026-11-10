/**
 * Header account menu on phones (.jws_account .jws-am-dropdown): a panel
 * under the avatar, almost as wide as the screen, as on desktop. Opened and
 * closed by tapping the avatar.
 *
 * The panel is absolute inside .jws_account (profiles.css), so it scrolls
 * with the header, and with a sticky header it slides with it: nothing runs
 * while the page scrolls. Its place and size are set when it opens and when
 * the screen size changes. The height stops at the theme's bottom toolbar
 * (.jws-toolbar-wap) when the page has one.
 */
( function () {
	'use strict';

	var PHONE = 767;
	var EDGE = 12;
	var GAP = 6;
	var VARS = [ '--jws-am-top', '--jws-am-left', '--jws-am-right', '--jws-am-width', '--jws-am-max' ];

	function place() {
		var phone = window.innerWidth <= PHONE;

		Array.prototype.forEach.call( document.querySelectorAll( '.jws_account .jws-am-dropdown' ), function ( menu ) {
			if ( ! phone ) {
				VARS.forEach( function ( name ) {
					menu.style.removeProperty( name );
				} );
				return;
			}

			var account = menu.closest( '.jws_account' );
			var anchor = account.querySelector( '.dr-button' ) || account;
			var box = account.getBoundingClientRect();
			var below = anchor.getBoundingClientRect().bottom;
			var width = document.documentElement.clientWidth - EDGE * 2;

			menu.style.setProperty( '--jws-am-top', Math.round( below - box.top + GAP ) + 'px' );
			menu.style.setProperty( '--jws-am-left', Math.round( EDGE - box.left ) + 'px' );
			menu.style.setProperty( '--jws-am-right', 'auto' );
			menu.style.setProperty( '--jws-am-width', Math.round( width ) + 'px' );

			var limit = window.innerHeight;
			var bar = document.querySelector( '.jws-toolbar-wap' );
			if ( bar && document.body.classList.contains( 'has-tool-bar' ) ) {
				var barTop = bar.getBoundingClientRect().top;
				if ( barTop > below && barTop < limit ) {
					limit = barTop;
				}
			}

			menu.style.setProperty( '--jws-am-max', Math.max( 160, Math.round( limit - below - GAP * 3 ) ) + 'px' );
		} );
	}

	/*
	 * Phones and touch screens open the menu by tap only (profiles.css turns
	 * hover off there), and a second tap on the avatar closes it. The theme's
	 * handler (main.js dropdown_ui) always leaves it open, so the state before
	 * the tap is read in the capture phase and applied after that handler.
	 */
	var touch = window.matchMedia ? window.matchMedia( '(hover: none), (max-width: ' + PHONE + 'px)' ) : null;

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest && event.target.closest( '.jws_account .dr-button' );
		var account = button && button.closest( '.jws_account' );

		if ( ! account || ! account.querySelector( '.jws-am-dropdown' ) ) {
			return;
		}

		var wasOpen = account.classList.contains( 'open' );

		if ( ! wasOpen ) {
			place(); // Before it shows, so it does not appear at the old place first.
		}

		window.setTimeout( function () {
			if ( wasOpen && touch && touch.matches ) {
				account.classList.remove( 'open' );
				button.blur();
			}
		}, 0 );
	}, true );

	window.addEventListener( 'resize', place );

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', place );
	} else {
		place();
	}
}() );
