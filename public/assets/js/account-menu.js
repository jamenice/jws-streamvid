/**
 * Header account menu on phones (.jws_account .jws-am-dropdown): a panel
 * under the avatar, almost as wide as the screen, as on desktop. Opened and
 * closed by tapping the avatar.
 *
 * The panel is position: fixed, so it stays put while the page scrolls under
 * it. A sticky header (.is-sticky, moved with transform) becomes the
 * containing block of a fixed child, so its offset is measured and taken
 * off. The height stops at the theme's bottom
 * toolbar (.jws-toolbar-wap) when the page has one.
 */
( function () {
	'use strict';

	var PHONE = 767;
	var GAP = 6;

	function menus() {
		return document.querySelectorAll( '.jws_account .jws-am-dropdown' );
	}

	/**
	 * Where a fixed box at top: 0 actually lands next to the menu: 0 when the
	 * viewport is the containing block, the sticky header's top when that is.
	 * Measured with an empty probe, so the menu's own transitions do not count.
	 */
	function origin( parent ) {
		var probe = document.createElement( 'span' );
		probe.style.cssText = 'position:fixed;top:0;left:0;width:0;height:0;visibility:hidden;pointer-events:none;';
		parent.appendChild( probe );
		var top = probe.getBoundingClientRect().top;
		parent.removeChild( probe );
		return top;
	}

	function place() {
		var phone = window.innerWidth <= PHONE;

		Array.prototype.forEach.call( menus(), function ( menu ) {
			if ( ! phone ) {
				menu.style.removeProperty( '--jws-am-top' );
				menu.style.removeProperty( '--jws-am-max' );
				return;
			}

			var account = menu.closest( '.jws_account' );
			var anchor = ( account && account.querySelector( '.dr-button' ) ) || account || menu.parentNode;
			var want = Math.round( anchor.getBoundingClientRect().bottom + GAP );

			menu.style.setProperty( '--jws-am-top', Math.round( want - origin( menu.parentNode ) ) + 'px' );

			var limit = window.innerHeight;
			var bar = document.querySelector( '.jws-toolbar-wap' );
			if ( bar && document.body.classList.contains( 'has-tool-bar' ) ) {
				var barTop = bar.getBoundingClientRect().top;
				if ( barTop > want && barTop < limit ) {
					limit = barTop;
				}
			}

			menu.style.setProperty( '--jws-am-max', Math.max( 160, Math.round( limit - want - GAP * 2 ) ) + 'px' );
		} );
	}

	function placeIfOpen() {
		if ( document.querySelector( '.jws_account.open .jws-am-dropdown' ) ) {
			place();
		}
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

		window.setTimeout( function () {
			if ( wasOpen && touch && touch.matches ) {
				account.classList.remove( 'open' );
				button.blur();
				return;
			}
			place();
		}, 0 );
	}, true );

	// A sticky header slides in and out while scrolling; the address bar changes the height.
	window.addEventListener( 'scroll', placeIfOpen, { passive: true } );
	window.addEventListener( 'resize', place );

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', place );
	} else {
		place();
	}
}() );
