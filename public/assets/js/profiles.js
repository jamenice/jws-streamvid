/**
 * Profile page V3.
 *
 * 1. The "Sections" sheet on phones and the current tab scrolled into view
 *    on tablets. Without this file the menu shows above the tab on phones.
 * 2. Tabs load in place. Links and forms inside the page that stay on this
 *    profile fetch the next tab (the server sends only the profile block)
 *    and swap the tab, its title and the menu; the address bar and the Back
 *    button follow. Anything unexpected (a
 *    network error, a signed-out session, a page that is not this profile)
 *    falls back to a normal page load, and without this file every link and
 *    form is a normal page load as before.
 *
 * Opt out on a link or form with data-jws-reload.
 */
( function () {
	'use strict';

	var root = document.querySelector( '.jws-pv3[data-jws-base]' );

	if ( ! root ) {
		return;
	}

	var tablet = window.matchMedia( '(min-width: 768px) and (max-width: 1024px)' );
	var phone = window.matchMedia( '(max-width: 767px)' );
	var reduceMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' );

	/* ------------------------------------------------------------------ *
	 * Sections sheet (phones) and the menu strip (tablets)
	 * ------------------------------------------------------------------ */

	var button = root.querySelector( '.jws-pv3__sections' );
	var side = document.getElementById( 'jws-pv3-side' );
	var backdrop = root.querySelector( '.jws-pv3__backdrop' );
	var lastFocus = null;

	function openSheet() {
		lastFocus = document.activeElement;
		side.classList.add( 'is-open' );
		side.setAttribute( 'role', 'dialog' );
		side.setAttribute( 'aria-modal', 'true' );
		button.setAttribute( 'aria-expanded', 'true' );
		if ( backdrop ) {
			backdrop.hidden = false;
		}
		document.body.classList.add( 'jws-pv3-locked' );
		( side.querySelector( '.jws-pv3__close' ) || side ).focus();
	}

	function shutSheet( restoreFocus ) {
		if ( ! side || ! side.classList.contains( 'is-open' ) ) {
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
		if ( restoreFocus && lastFocus ) {
			lastFocus.focus();
		}
	}

	function centerActiveLink() {
		if ( ! side || ! tablet.matches ) {
			return;
		}
		var current = side.querySelector( '.jws-pv3__link.is-active' );
		var strip = side.querySelector( '.jws-pv3__nav' );

		if ( current && strip ) {
			strip.scrollLeft = current.offsetLeft - ( strip.clientWidth - current.offsetWidth ) / 2;
		}
	}

	if ( button && side ) {
		document.documentElement.classList.add( 'jws-pv3-js' );
		button.hidden = false;
		button.addEventListener( 'click', openSheet );

		var close = side.querySelector( '.jws-pv3__close' );

		if ( close ) {
			close.addEventListener( 'click', function () {
				shutSheet( true );
			} );
		}
		if ( backdrop ) {
			backdrop.addEventListener( 'click', function () {
				shutSheet( true );
			} );
		}

		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key ) {
				shutSheet( true );
			}
		} );

		// Leaving phone width with the sheet open would leave the page locked.
		phone.addEventListener( 'change', function ( event ) {
			if ( ! event.matches ) {
				shutSheet( false );
			}
		} );

		centerActiveLink();
	}

	/* ------------------------------------------------------------------ *
	 * One-time notices as the theme's toast
	 * ------------------------------------------------------------------ */

	// Query arguments of a one-time notice (removed, cleared, signed out): shown once, kept out of the address bar.
	var NOTICE_ARGS = [ 'jws_lib', 'n' ];
	var live = null;

	function escapeHtml( text ) {
		var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', '\'': '&#39;' };

		return text.replace( /[&<>"']/g, function ( c ) {
			return map[ c ];
		} );
	}

	// Screen readers do not read the toast, so its text also goes to a polite live region.
	function announce( text ) {
		if ( ! live ) {
			live = document.createElement( 'div' );
			live.className = 'screen-reader-text';
			live.setAttribute( 'role', 'status' );
			live.setAttribute( 'aria-live', 'polite' );
			root.appendChild( live );
		}
		live.textContent = '';
		window.setTimeout( function () {
			live.textContent = text;
		}, 100 );
	}

	/*
	 * Notices marked data-jws-toast (titles removed, history cleared, device
	 * signed out, form expired) show as the theme's toast
	 * (jwsThemeModule.show_notification, Toastify) and leave the page.
	 * Without the theme's toast they stay where they are.
	 */
	/*
	 * Cloudflare Email Obfuscation (Scrape Shield) turns every address in the
	 * HTML it serves into a.__cf_email__ with the address encoded in
	 * data-cfemail (and mailto links into /cdn-cgi/l/email-protection#...),
	 * then decodes them once, on page load. A tab loaded in place comes
	 * through Cloudflare too: decode its addresses the same way, or they stay
	 * "[email protected]".
	 */
	function cfDecode( hex ) {
		var key = parseInt( hex.substr( 0, 2 ), 16 );
		var bytes = [];

		for ( var i = 2; i < hex.length; i += 2 ) {
			bytes.push( parseInt( hex.substr( i, 2 ), 16 ) ^ key );
		}
		if ( window.TextDecoder ) {
			return new window.TextDecoder().decode( new Uint8Array( bytes ) );
		}
		return String.fromCharCode.apply( null, bytes );
	}

	function decodeEmails( scope ) {
		Array.prototype.forEach.call( scope.querySelectorAll( '.__cf_email__[data-cfemail]' ), function ( el ) {
			el.parentNode.replaceChild( document.createTextNode( cfDecode( el.getAttribute( 'data-cfemail' ) ) ), el );
		} );
		Array.prototype.forEach.call( scope.querySelectorAll( 'a[href*="/cdn-cgi/l/email-protection#"]' ), function ( link ) {
			var hex = link.getAttribute( 'href' ).split( '#' )[ 1 ];
			if ( hex ) {
				link.setAttribute( 'href', 'mailto:' + cfDecode( hex ) );
			}
		} );
	}

	function toastNotices( scope ) {
		var theme = window.jwsThemeModule;

		if ( ! theme || 'function' !== typeof theme.show_notification || 'function' !== typeof window.Toastify ) {
			return;
		}

		Array.prototype.forEach.call( scope.querySelectorAll( '[data-jws-toast]' ), function ( notice ) {
			var text = notice.textContent.trim();

			if ( text ) {
				theme.show_notification( escapeHtml( text ), 'error' === notice.getAttribute( 'data-jws-toast' ) ? 'error' : 'success' );
				announce( text );
			}
			notice.parentNode.removeChild( notice );
		} );
	}

	toastNotices( root );

	// A normal page load with a notice (a form sent without in-place loading): keep the notice out of the address bar too.
	if ( window.URL && window.history.replaceState ) {
		var here = new URL( window.location.href );
		var hadNotice = NOTICE_ARGS.some( function ( key ) {
			return here.searchParams.has( key );
		} );

		if ( hadNotice ) {
			NOTICE_ARGS.forEach( function ( key ) {
				here.searchParams.delete( key );
			} );
			window.history.replaceState( window.history.state, '', here.href );
		}
	}

	/* ------------------------------------------------------------------ *
	 * Tabs in place
	 * ------------------------------------------------------------------ */

	if ( ! window.fetch || ! window.DOMParser || ! window.URL || ! window.Promise || ! window.history.pushState ) {
		return;
	}

	var base = new URL( root.getAttribute( 'data-jws-base' ), window.location.href );
	var controller = null;
	var lastSubmitter = null;

	function inProfile( url ) {
		if ( url.origin !== base.origin || 0 !== url.pathname.indexOf( base.pathname ) ) {
			return false;
		}

		// Plain permalinks: the profile is ?author=ID on every tab.
		var same = true;
		base.searchParams.forEach( function ( value, key ) {
			if ( url.searchParams.get( key ) !== value ) {
				same = false;
			}
		} );
		return same;
	}

	// Pages that must load in full. None now: the playlist screen (?playlist=) brings its own popups and
	// pages/profile.js binds them on document, so it loads in place like the other tabs.
	function needsFullLoad() {
		return false;
	}

	function linkTarget( link ) {
		var href = link.getAttribute( 'href' );

		if ( ! href || '#' === href.charAt( 0 ) || 0 === href.indexOf( 'javascript:' ) ) {
			return null;
		}
		if ( ( link.target && '_self' !== link.target ) || link.hasAttribute( 'download' ) || link.hasAttribute( 'data-jws-reload' ) ) {
			return null;
		}
		if ( link.hasAttribute( 'data-modal-jws' ) || link.hasAttribute( 'data-modal-get-jws' ) || link.hasAttribute( 'data-jws-coin-modal' ) ) {
			return null;
		}

		var url = new URL( link.href, window.location.href );

		if ( ! inProfile( url ) || needsFullLoad( url ) ) {
			return null;
		}
		// Another anchor on this same page: the browser scrolls to it.
		if ( url.hash && url.pathname === window.location.pathname && url.search === window.location.search ) {
			return null;
		}
		return url;
	}

	function withoutNotice( href ) {
		var url = new URL( href, window.location.href );

		NOTICE_ARGS.forEach( function ( key ) {
			url.searchParams.delete( key );
		} );
		return url.href;
	}

	function setBusy( busy ) {
		var area = root.querySelector( '.jws-pv3__content' );

		root.classList.toggle( 'is-loading', busy );
		if ( area ) {
			if ( busy ) {
				area.setAttribute( 'aria-busy', 'true' );
			} else {
				area.removeAttribute( 'aria-busy' );
			}
		}
	}

	// Shows the picked tab at once; the loaded page confirms it.
	function markActive( link ) {
		var cls = link.classList.contains( 'jws-pv3__link' ) ? 'jws-pv3__link' : 'jws-pv3__tab';
		var group = link.closest( '.jws-pv3__nav, .jws-pv3__tabs' );

		if ( ! group || ! link.classList.contains( cls ) ) {
			return;
		}
		Array.prototype.forEach.call( group.querySelectorAll( '.' + cls + '.is-active' ), function ( item ) {
			item.classList.remove( 'is-active' );
			item.removeAttribute( 'aria-current' );
		} );
		link.classList.add( 'is-active' );
		link.setAttribute( 'aria-current', 'page' );
	}

	// Files this page has run or loaded, so a tab never loads one twice.
	var loadedScripts = new window.Set();
	var loadedStyles = new window.Set();

	Array.prototype.forEach.call( document.scripts, function ( script ) {
		if ( script.src ) {
			loadedScripts.add( script.src );
		}
	} );
	Array.prototype.forEach.call( document.querySelectorAll( 'link[rel="stylesheet"][href]' ), function ( link ) {
		loadedStyles.add( link.href );
	} );

	function hasScript( src ) {
		return loadedScripts.has( src );
	}

	function hasStyle( href ) {
		return loadedStyles.has( href );
	}

	/*
	 * Cloudflare Rocket Loader renames every script type to
	 * "<hash>-text/javascript" so the browser skips it until Rocket Loader
	 * runs it. That only happens on a full page load: in a tab loaded in
	 * place the scripts would never run (the Invoice button stayed dead), so
	 * the prefix is dropped and they run like any other script.
	 */
	function scriptType( script ) {
		return ( script.getAttribute( 'type' ) || '' ).toLowerCase().replace( /^[0-9a-f]+-(?=text\/javascript$|application\/javascript$|module$)/, '' );
	}

	function isJs( script ) {
		var type = scriptType( script );
		return '' === type || 'module' === type || 'text/javascript' === type || 'application/javascript' === type;
	}

	/*
	 * Runs a script from the loaded page: in place when it sits in the new
	 * tab, at the end of the body otherwise. A file already on the page is
	 * not loaded twice. Resolves once it has run.
	 */
	function runScript( old ) {
		return new Promise( function ( resolve ) {
			var inPage = document.documentElement.contains( old );

			if ( ! isJs( old ) || ( old.src && hasScript( old.src ) ) ) {
				resolve();
				return;
			}

			var script = document.createElement( 'script' );

			Array.prototype.forEach.call( old.attributes, function ( attr ) {
				script.setAttribute( attr.name, attr.value );
			} );

			if ( old.hasAttribute( 'type' ) ) {
				script.setAttribute( 'type', scriptType( old ) );
			}

			if ( old.src ) {
				loadedScripts.add( old.src );
				script.async = false;
				script.onload = resolve;
				script.onerror = resolve;
			} else {
				script.text = old.text;
			}

			if ( inPage ) {
				old.parentNode.replaceChild( script, old );
			} else {
				document.body.appendChild( script );
			}

			if ( ! old.src ) {
				resolve();
			}
		} );
	}

	function runInOrder( scripts ) {
		return scripts.reduce( function ( chain, script ) {
			return chain.then( function () {
				return runScript( script );
			} );
		}, Promise.resolve() );
	}

	// Styles and script files the next tab needs that this page has not loaded yet.
	function importAssets( doc, nextContent ) {
		var waits = [];

		Array.prototype.forEach.call( doc.querySelectorAll( 'link[rel="stylesheet"][href]' ), function ( link ) {
			if ( ( link.id && document.getElementById( link.id ) ) || hasStyle( link.href ) ) {
				return;
			}
			var style = document.importNode( link, true );

			loadedStyles.add( link.href );
			waits.push( new Promise( function ( resolve ) {
				style.onload = resolve;
				style.onerror = resolve;
				window.setTimeout( resolve, 3000 );
			} ) );
			document.head.appendChild( style );
		} );

		Array.prototype.forEach.call( doc.querySelectorAll( 'style[id]' ), function ( style ) {
			if ( ! document.getElementById( style.id ) ) {
				document.head.appendChild( document.importNode( style, true ) );
			}
		} );

		// Scripts outside the tab run only when they have a file or an id (WordPress localised data); others are page-wide and already ran.
		var scripts = Array.prototype.filter.call( doc.scripts, function ( script ) {
			if ( nextContent.contains( script ) ) {
				return false;
			}
			if ( script.id ) {
				return ! document.getElementById( script.id );
			}
			return !! script.src && ! hasScript( script.src );
		} );

		return Promise.all( waits ).then( function () {
			return runInOrder( scripts );
		} );
	}

	// The theme turns selects into select2 on page load (jwsThemeModule.select2_global): the same selects and options, for one tab only.
	var SELECT2 = 'select:not([aria-labelledby]):not(#pmpro-admin-membership-access):not(.jws-no-select2)';

	// Selects in Magnific Popup modals stay native, as in the theme: select2's list opens under the popup.
	var IN_MODAL = '.mfp-hide select, .mfp-content select';

	function enhance( scope ) {
		var $ = window.jQuery;

		if ( $ && $.fn.select2 ) {
			$( scope ).find( SELECT2 ).not( IN_MODAL ).select2( {
				dropdownAutoWidth: true,
				minimumResultsForSearch: 10,
			} );
		}
	}

	function teardown( scope ) {
		var $ = window.jQuery;

		if ( $ && $.fn.select2 ) {
			$( scope ).find( 'select.select2-hidden-accessible' ).select2( 'destroy' );
		}
	}

	function swap( doc ) {
		var nextRoot = doc.querySelector( '.jws-pv3[data-jws-base]' );
		var nextContent = nextRoot && nextRoot.querySelector( '.jws-pv3__content' );
		var area = root.querySelector( '.jws-pv3__content' );

		if ( ! nextContent || ! area || nextRoot.getAttribute( 'data-jws-base' ) !== root.getAttribute( 'data-jws-base' ) || nextRoot.classList.contains( 'jws-pv3--public' ) !== root.classList.contains( 'jws-pv3--public' ) ) {
			throw new Error( 'jws-pv3: not the same profile page' );
		}

		var fresh = document.importNode( nextContent, true );

		teardown( area );
		area.parentNode.replaceChild( fresh, area );
		enhance( fresh );

		[ '.jws-pv3__title', '.jws-pv3__card', '.jws-pv3__nav' ].forEach( function ( selector ) {
			var mine = root.querySelector( selector );
			var theirs = nextRoot.querySelector( selector );

			if ( mine && theirs ) {
				mine.innerHTML = theirs.innerHTML;
			}
		} );

		if ( doc.title ) {
			document.title = doc.title;
		}

		return fresh;
	}

	function transition( update ) {
		if ( document.startViewTransition && ! reduceMotion.matches ) {
			return document.startViewTransition( update ).updateCallbackDone;
		}
		return new Promise( function ( resolve ) {
			update();
			resolve();
		} );
	}

	function settle( fresh, opts ) {
		if ( window.jQuery ) {
			window.jQuery( document.body ).trigger( 'sticky_kit:recalc' );
		}
		centerActiveLink();

		if ( opts.scroll ) {
			var top = root.getBoundingClientRect().top;

			if ( top < 0 ) {
				window.scrollTo( {
					top: window.pageYOffset + top,
					behavior: reduceMotion.matches ? 'auto' : 'smooth',
				} );
			}
		}

		// Same place on the page as before the load ("Show more").
		if ( 'number' === typeof opts.keepY && Math.abs( window.pageYOffset - opts.keepY ) > 1 ) {
			window.scrollTo( 0, opts.keepY );
		}

		// The first title that was not there before ("Show more"), without scrolling to it.
		if ( 'number' === typeof opts.focusItem ) {
			var item = fresh.querySelectorAll( '.jws-lib__item' )[ opts.focusItem ];
			var focusable = item && item.querySelector( 'a[href], button' );
			if ( focusable ) {
				focusable.focus( { preventScroll: true } );
			}
		}

		// The whole block: the account card (the member's email) is swapped too, not only the tab.
		decodeEmails( root );

		toastNotices( fresh );

		// Focus goes to the tab title, or to a notice left in the page when the toast is not there.
		var target = ( opts.form && fresh.querySelector( '[data-jws-toast]' ) ) || root.querySelector( '.jws-pv3__title' );

		if ( target && opts.focus !== false ) {
			target.setAttribute( 'tabindex', '-1' );
			target.focus( { preventScroll: true } );
		}

		document.dispatchEvent( new window.CustomEvent( 'jws-pv3:load', { detail: { url: window.location.href } } ) );
	}

	function submitNatively( form, submitter ) {
		if ( submitter && submitter.name ) {
			var input = document.createElement( 'input' );

			input.type = 'hidden';
			input.name = submitter.name;
			input.value = submitter.value;
			form.appendChild( input );
		}
		window.HTMLFormElement.prototype.submit.call( form );
	}

	/*
	 * Loads url and swaps the tab in. opts: method, body, form, submitter,
	 * history ('push', 'replace' or 'none'), scroll, focus.
	 */
	function visit( url, opts ) {
		opts = opts || {};

		if ( controller ) {
			controller.abort();
		}

		var mine = window.AbortController ? new window.AbortController() : null;
		var response = null;

		controller = mine;
		setBusy( true );

		return window.fetch( url, {
			method: opts.method || 'GET',
			body: opts.body,
			credentials: 'same-origin',
			// The server answers with the profile block only (Jws_Profiles_Front::fragment); a full page still works.
			headers: { 'X-Jws-Pv3': '1' },
			signal: mine ? mine.signal : undefined,
		} ).then( function ( res ) {
			response = res;
			return res.text();
		} ).then( function ( text ) {
			var doc = new window.DOMParser().parseFromString( text, 'text/html' );
			var nextRoot = doc.querySelector( '.jws-pv3[data-jws-base]' );
			var nextContent = nextRoot && nextRoot.querySelector( '.jws-pv3__content' );

			if ( ! nextContent ) {
				throw new Error( 'jws-pv3: no tab in the response' );
			}

			return importAssets( doc, nextContent ).then( function () {
				var fresh = null;

				return transition( function () {
					fresh = swap( doc );
				} ).then( function () {
					var address = withoutNotice( response.url || url );

					if ( 'push' === opts.history && address !== window.location.href ) {
						window.history.pushState( { jwsPv3: true }, '', address );
					} else if ( 'none' !== opts.history ) {
						window.history.replaceState( { jwsPv3: true }, '', address );
					}

					return runInOrder( Array.prototype.slice.call( fresh.querySelectorAll( 'script' ) ) ).then( function () {
						settle( fresh, opts );
					} );
				} );
			} );
		} ).catch( function ( error ) {
			if ( error && 'AbortError' === error.name ) {
				return;
			}
			if ( response ) {
				// The server answered (a POST is done): show that page as a normal load.
				window.location.assign( response.url || url );
			} else if ( opts.form ) {
				submitNatively( opts.form, opts.submitter );
			} else {
				window.location.assign( url );
			}
		} ).then( function () {
			if ( controller === mine ) {
				controller = null;
				setBusy( false );
			}
		} );
	}

	/*
	 * Other scripts (pages/profile.js after saving a playlist) load a profile
	 * page in place too. Resolves when the tab is in.
	 */
	window.jwsPv3Visit = function ( url, opts ) {
		var target = new URL( url, window.location.href );
		if ( ! inProfile( target ) ) {
			window.location.assign( target.href );
			return Promise.resolve();
		}
		return visit( target.href, Object.assign( { history: 'push' }, opts || {} ) );
	};

	document.addEventListener( 'click', function ( event ) {
		var submit = event.target.closest && event.target.closest( 'button, input[type="submit"]' );

		if ( submit && submit.form && root.contains( submit.form ) ) {
			lastSubmitter = submit;
		}

		if ( event.defaultPrevented || 0 !== event.button || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey ) {
			return;
		}

		var link = event.target.closest && event.target.closest( 'a[href]' );

		if ( ! link || ! root.contains( link ) ) {
			return;
		}

		var url = linkTarget( link );

		if ( ! url ) {
			return;
		}

		event.preventDefault();

		/*
		 * "Show more" (library tabs): the same tab with more titles. The reader
		 * stays where they are and focus moves to the first new title; the
		 * address is replaced, so Back does not step through each "Show more".
		 */
		if ( link.hasAttribute( 'data-jws-more' ) ) {
			visit( url.href, {
				history: 'replace',
				scroll: false,
				focus: false,
				keepY: window.pageYOffset,
				focusItem: parseInt( link.getAttribute( 'data-jws-more' ), 10 ) || 0,
			} );
			return;
		}

		markActive( link );
		shutSheet( false );
		visit( url.href, { history: 'push', scroll: true } );
	} );

	document.addEventListener( 'submit', function ( event ) {
		var form = event.target;

		if ( event.defaultPrevented || ! root.contains( form ) || form.hasAttribute( 'data-jws-reload' ) ) {
			return;
		}

		var method = ( form.getAttribute( 'method' ) || 'get' ).toLowerCase();
		var action = new URL( form.getAttribute( 'action' ) || window.location.href, window.location.href );
		var submitter = event.submitter || ( lastSubmitter && lastSubmitter.form === form ? lastSubmitter : null );

		if ( ( 'get' !== method && 'post' !== method ) || ! inProfile( action ) || needsFullLoad( action ) ) {
			return;
		}

		var data = new window.FormData( form );

		if ( submitter && submitter.name ) {
			data.append( submitter.name, submitter.value );
		}

		event.preventDefault();
		lastSubmitter = null;

		if ( 'get' === method ) {
			action.search = new window.URLSearchParams( data ).toString();
			action.hash = '';
			visit( action.href, { history: 'push', scroll: false } );
		} else {
			visit( action.href, { method: 'POST', body: data, form: form, submitter: submitter, history: 'replace', scroll: false } );
		}
	} );

	window.history.replaceState( Object.assign( {}, window.history.state, { jwsPv3: true } ), '', window.location.href );

	window.addEventListener( 'popstate', function ( event ) {
		if ( event.state && event.state.jwsPv3 && inProfile( new URL( window.location.href ) ) ) {
			visit( window.location.href, { history: 'none', scroll: false, focus: false } );
		}
	} );
}() );
