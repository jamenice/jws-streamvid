/**
 * "Who's watching?" modal: every profile screen (choose, PIN, manage, add,
 * edit) in one full-screen dialog over the page. Each screen is loaded from
 * its URL with the header "X-Jws-Profiles: 1" (Jws_Profiles_Front::route).
 *
 * - A link to a screen anywhere on the page (header menu, profile sidebar,
 *   Kids bar, Profiles tab) opens it here. Forms in the modal, and the
 *   header menu's switch forms, post here.
 * - The server answers {html} (show that screen) or {go} (another screen,
 *   or a page: the page then loads, as after switching profiles).
 * - The gate (an account with 2+ profiles that has not picked one) opens
 *   the modal at once, and it cannot be closed until a profile is picked.
 * - #jws-profiles=<screen URL> in the address opens it on load: a direct
 *   visit to a screen URL comes back to the page with that.
 * - After a profile was saved, deleted or locked, closing reloads the page
 *   so the menu and lists show the change.
 */
( function () {
	'use strict';

	var config = window.jwsProfilesModal;

	if ( ! config || ! config.screen || ! window.fetch || ! window.URL || ! window.FormData ) {
		return;
	}

	var screenBase = new URL( config.screen, window.location.href );
	var i18n = config.i18n || {};
	var modal = null;
	var body = null;
	var closeButton = null;
	var forced = false;
	var dirty = false;
	var current = '';
	var back = '';
	var lastFocus = null;
	var controller = null;
	var lastSubmitter = null;
	var leaving = false;
	var picked = null;

	/*
	 * Switching profiles: the chosen tile (or the header menu button, or the
	 * PIN screen's Continue) shows a spinner until the next page is up.
	 */
	function pick( form, submitter ) {
		var target = form.closest( '.jws-pf-tile' ) || submitter || form.querySelector( '[type="submit"]' );

		unpick();
		if ( target ) {
			target.classList.add( 'is-loading' );
			picked = target;
		}
		if ( modal && modal.contains( form ) ) {
			modal.classList.add( 'jws-pfm--picking' );
		}
	}

	function unpick() {
		if ( picked ) {
			picked.classList.remove( 'is-loading' );
			picked = null;
		}
		if ( modal ) {
			modal.classList.remove( 'jws-pfm--picking' );
		}
	}

	function here() {
		var url = new URL( window.location.href );

		url.hash = '';
		return url.href;
	}

	function isScreen( url ) {
		if ( url.origin !== screenBase.origin ) {
			return false;
		}
		// Plain permalinks: ?jws_profiles=choose; pretty ones: /whos-watching/...
		if ( config.queryVar ) {
			return url.searchParams.has( config.queryVar );
		}
		return 0 === url.pathname.indexOf( screenBase.pathname );
	}

	// Every screen carries the page to return to after switching profiles.
	function withBack( url ) {
		if ( back && ! url.searchParams.has( 'back' ) ) {
			url.searchParams.set( 'back', back );
		}
		return url;
	}

	function build() {
		modal = document.createElement( 'div' );
		modal.className = 'jws-pfm';
		modal.hidden = true;
		modal.setAttribute( 'role', 'dialog' );
		modal.setAttribute( 'aria-modal', 'true' );
		modal.setAttribute( 'aria-label', i18n.dialog || '' );

		closeButton = document.createElement( 'button' );
		closeButton.type = 'button';
		closeButton.className = 'jws-pfm__close';
		closeButton.innerHTML = '<i class="jws-icon-x" aria-hidden="true"></i><span class="screen-reader-text"></span>';
		closeButton.lastChild.textContent = i18n.close || '';
		closeButton.addEventListener( 'click', close );

		body = document.createElement( 'div' );
		body.className = 'jws-pfm__body';

		modal.appendChild( closeButton );
		modal.appendChild( body );
		document.body.appendChild( modal );
	}

	function reveal( isForced ) {
		if ( ! modal ) {
			build();
		}
		if ( ! modal.hidden ) {
			return;
		}
		lastFocus = document.activeElement;
		forced = !! isForced;
		dirty = false;
		modal.classList.toggle( 'jws-pfm--gate', forced );
		modal.hidden = false;
		document.documentElement.classList.add( 'jws-pfm-open' );
		closeButton.focus( { preventScroll: true } );
	}

	function close() {
		if ( forced || ! modal || modal.hidden ) {
			return;
		}
		if ( controller ) {
			controller.abort();
		}
		if ( dirty ) {
			window.location.reload();
			return;
		}
		modal.hidden = true;
		body.innerHTML = '';
		current = '';
		document.documentElement.classList.remove( 'jws-pfm-open' );
		if ( lastFocus && lastFocus.focus ) {
			lastFocus.focus();
		}
	}

	/*
	 * PIN fields as four boxes, one digit each. The real field (name pin or
	 * new_pin) stays in the form as a hidden input holding the digits, so
	 * the server gets what it got before. Typing moves on, Backspace moves
	 * back, a pasted PIN fills the boxes; the PIN that opens a profile sends
	 * itself once complete.
	 *
	 * The boxes are text fields that show a dot, with the digits kept here:
	 * browsers treat any password field as an account password, ignore
	 * autocomplete="off" on it, offer saved passwords, fill one in, and ask
	 * to save the PIN as the site's password. The data-*ignore attributes
	 * keep password managers (LastPass, 1Password, Bitwarden, Dashlane) out.
	 */
	// What a PIN box shows for a digit.
	var MASK = '\u2022';

	function pinBoxes( scope ) {
		Array.prototype.forEach.call( scope.querySelectorAll( 'input[name="pin"], input[name="new_pin"]' ), function ( field ) {
			var group = document.createElement( 'div' );
			var boxes = [];
			var digits = [ '', '', '', '' ];
			var autoSend = 'pin' === field.name;
			var label = scope.querySelector( 'label[for="' + field.id + '"]' );

			group.className = 'jws-pf-otp' + ( field.classList.contains( 'has-error' ) || 'true' === field.getAttribute( 'aria-invalid' ) ? ' has-error' : '' );
			group.setAttribute( 'role', 'group' );
			if ( label ) {
				label.id = label.id || field.id + '-label';
				group.setAttribute( 'aria-labelledby', label.id );
				label.removeAttribute( 'for' );
			}
			if ( field.getAttribute( 'aria-describedby' ) ) {
				group.setAttribute( 'aria-describedby', field.getAttribute( 'aria-describedby' ) );
			}

			function sync() {
				field.value = digits.join( '' );
				group.classList.toggle( 'has-value', '' !== field.value );
				group.classList.remove( 'has-error' );
			}

			// Puts digits into the boxes from box `from` on; returns the box after the last one filled.
			function fill( from, text ) {
				var at = from;

				text.split( '' ).forEach( function ( digit ) {
					if ( at < boxes.length ) {
						digits[ at ] = digit;
						boxes[ at ].value = MASK;
						at++;
					}
				} );
				sync();
				return Math.min( at, boxes.length - 1 );
			}

			function done() {
				if ( autoSend && 4 === field.value.length && field.form ) {
					if ( field.form.requestSubmit ) {
						field.form.requestSubmit();
					} else {
						field.form.submit();
					}
				}
			}

			for ( var i = 0; i < 4; i++ ) {
				var box = document.createElement( 'input' );

				box.type = 'text';
				box.className = 'jws-pf-otp__box h4';
				box.inputMode = 'numeric';
				box.autocomplete = 'off';
				box.spellcheck = false;
				box.setAttribute( 'autocapitalize', 'off' );
				box.setAttribute( 'autocorrect', 'off' );
				box.setAttribute( 'data-lpignore', 'true' );
				box.setAttribute( 'data-1p-ignore', '' );
				box.setAttribute( 'data-bwignore', '' );
				box.setAttribute( 'data-form-type', 'other' );
				box.setAttribute( 'aria-label', ( i + 1 ) + ' / 4' );
				box.dataset.index = i;
				boxes.push( box );
				group.appendChild( box );
			}

			group.addEventListener( 'input', function ( event ) {
				var box = event.target;
				var index = +box.dataset.index;
				var typed = box.value.replace( /\D/g, '' ); // The dot is not a digit.

				if ( typed ) {
					boxes[ fill( index, typed ) ].focus();
				} else {
					// Emptied: the digit was deleted. Anything else typed (a letter) is dropped.
					if ( '' === box.value ) {
						digits[ index ] = '';
					}
					box.value = digits[ index ] ? MASK : '';
					sync();
				}
				done();
			} );

			group.addEventListener( 'keydown', function ( event ) {
				var index = +event.target.dataset.index;

				if ( 'Backspace' === event.key && '' === event.target.value && index > 0 ) {
					event.preventDefault();
					digits[ index - 1 ] = '';
					boxes[ index - 1 ].value = '';
					boxes[ index - 1 ].focus();
					sync();
				} else if ( 'ArrowLeft' === event.key && index > 0 ) {
					event.preventDefault();
					boxes[ index - 1 ].focus();
				} else if ( 'ArrowRight' === event.key && index < 3 ) {
					event.preventDefault();
					boxes[ index + 1 ].focus();
				}
			} );

			group.addEventListener( 'paste', function ( event ) {
				var text = ( event.clipboardData || window.clipboardData ).getData( 'text' ).replace( /\D/g, '' );

				if ( text ) {
					event.preventDefault();
					boxes[ fill( +event.target.dataset.index, text ) ].focus();
					done();
				}
			} );

			group.addEventListener( 'focusin', function ( event ) {
				event.target.select();
			} );

			if ( field.hasAttribute( 'autofocus' ) ) {
				boxes[ 0 ].setAttribute( 'autofocus', '' );
			}
			field.removeAttribute( 'autofocus' );
			field.type = 'hidden';
			field.parentNode.insertBefore( group, field );
		} );
	}

	// A PIN box group left half-filled (or empty where the PIN is required) stops its form.
	document.addEventListener( 'submit', function ( event ) {
		var form = event.target;
		var field = modal && modal.contains( form ) && form.querySelector( 'input[type="hidden"][name="pin"], input[type="hidden"][name="new_pin"]' );
		var group = field && field.previousElementSibling;

		if ( ! field || ! group || ! group.classList.contains( 'jws-pf-otp' ) ) {
			return;
		}

		var length = field.value.length;

		if ( ( length > 0 && length < 4 ) || ( 0 === length && field.required ) ) {
			event.preventDefault();
			group.classList.add( 'has-error' );
			group.querySelectorAll( '.jws-pf-otp__box' )[ Math.min( length, 3 ) ].focus();
		}
	}, true );

	/*
	 * Notices a screen arrives with ("Profile saved.", data-jws-toast) show as
	 * the theme's toast, like the profile page's. Without Toastify they stay
	 * where they are. Field errors (role="alert") always stay by their field.
	 */
	// Screen readers hear the notice that left the screen for the toast.
	function announce( text ) {
		var live = document.getElementById( 'jws-pfm-live' );

		if ( ! live ) {
			live = document.createElement( 'div' );
			live.id = 'jws-pfm-live';
			live.className = 'screen-reader-text';
			live.setAttribute( 'role', 'status' );
			live.setAttribute( 'aria-live', 'polite' );
			document.body.appendChild( live );
		}
		live.textContent = '';
		window.setTimeout( function () {
			live.textContent = text;
		}, 50 );
	}

	function toastNotices( scope ) {
		var theme = window.jwsThemeModule;

		if ( ! theme || 'function' !== typeof theme.show_notification || 'function' !== typeof window.Toastify ) {
			return;
		}

		Array.prototype.forEach.call( scope.querySelectorAll( '[data-jws-toast]' ), function ( notice ) {
			var text = notice.textContent.trim();

			if ( text ) {
				var safe = document.createElement( 'div' );
				safe.textContent = text;
				theme.show_notification( safe.innerHTML, 'error' === notice.getAttribute( 'data-jws-toast' ) ? 'error' : 'success' );
				announce( text );
			}
			notice.parentNode.removeChild( notice );
		} );
	}

	/*
	 * A profile the plan does not include (data-jws-pf-locked): a click says
	 * why, as the theme's toast. Screen readers hear it either way.
	 */
	document.addEventListener( 'click', function ( event ) {
		var tile = event.target.closest && event.target.closest( '[data-jws-pf-locked]' );
		var theme = window.jwsThemeModule;

		if ( ! tile ) {
			return;
		}
		event.preventDefault();

		var text = tile.getAttribute( 'data-jws-pf-locked' );

		if ( theme && 'function' === typeof theme.show_notification && 'function' === typeof window.Toastify ) {
			var safe = document.createElement( 'div' );
			safe.textContent = text;
			theme.show_notification( safe.innerHTML, 'error' );
		}
		announce( text );
	} );

	function show( html ) {
		unpick();
		reveal( forced );
		body.innerHTML = html;
		pinBoxes( body );
		toastNotices( body );
		Array.prototype.forEach.call( body.querySelectorAll( 'form[data-max]' ), limitKeep );
		modal.scrollTop = 0;

		var title = body.querySelector( '#jws-pf-title' );

		if ( title ) {
			modal.setAttribute( 'aria-labelledby', 'jws-pf-title' );
			modal.removeAttribute( 'aria-label' );
			title.setAttribute( 'tabindex', '-1' );
		}

		var target = body.querySelector( '[autofocus]' ) || title || closeButton;

		target.focus( { preventScroll: true } );
	}

	function fail() {
		unpick();

		// Opened right after sign-in (jwsOpenProfiles): carry on to the page, which asks again if it should.
		if ( forced && 'function' === typeof config.fallback ) {
			config.fallback();
			return;
		}

		reveal( forced );

		var old = body.querySelector( '.jws-pfm__error' );
		var message = document.createElement( 'p' );
		var wrap = body.querySelector( '.jws-pf__wrap' ) || body;

		if ( old ) {
			old.parentNode.removeChild( old );
		}
		message.className = 'jws-pf-msg jws-pf-msg--error jws-pfm__error';
		message.setAttribute( 'role', 'alert' );
		message.textContent = i18n.error || '';
		wrap.insertBefore( message, wrap.firstChild );
	}

	// Where the server sends us: another screen loads here; a page loads in the window.
	function follow( url, action ) {
		var next = new URL( url, window.location.href );

		if ( isScreen( next ) ) {
			if ( action && 'switch' !== action ) {
				dirty = true;
			}
			return load( next.href );
		}

		var page = new URL( next.href );

		leaving = true;
		page.hash = '';
		if ( page.href === here() ) {
			window.location.reload();
		} else {
			window.location.assign( next.href );
		}
		return null;
	}

	/*
	 * Fetches a screen (GET) or posts a form to it. init: method, body and
	 * action (the form's jws_profiles_action).
	 */
	function load( url, init ) {
		var target = withBack( new URL( url, window.location.href ) );
		var mine = window.AbortController ? new window.AbortController() : null;

		init = init || {};

		if ( controller ) {
			controller.abort();
		}
		controller = mine;

		if ( modal ) {
			modal.classList.add( 'is-loading' );
		}
		document.documentElement.classList.add( 'jws-pfm-busy' );

		return window.fetch( target.href, {
			method: init.method || 'GET',
			body: init.body,
			credentials: 'same-origin',
			headers: { 'X-Jws-Profiles': '1' },
			signal: mine ? mine.signal : undefined,
		} ).then( function ( response ) {
			if ( -1 === ( response.headers.get( 'content-type' ) || '' ).indexOf( 'json' ) ) {
				throw new Error( 'jws-profiles: not JSON' );
			}
			return response.json();
		} ).then( function ( data ) {
			if ( data && data.go ) {
				return follow( data.go, init.action );
			}
			if ( data && 'string' === typeof data.html ) {
				current = target.href;
				show( data.html );
				return null;
			}
			throw new Error( 'jws-profiles: unexpected answer' );
		} ).catch( function ( error ) {
			if ( error && 'AbortError' === error.name ) {
				return;
			}
			fail();
		} ).then( function () {
			if ( controller === mine && ! leaving ) {
				controller = null;
				if ( modal ) {
					modal.classList.remove( 'is-loading' );
				}
				document.documentElement.classList.remove( 'jws-pfm-busy' );
			}
		} );
	}

	function open( url, isForced ) {
		var target = new URL( url, window.location.href );

		back = target.searchParams.get( 'back' ) || here();
		reveal( isForced );
		return load( target.href );
	}

	document.addEventListener( 'click', function ( event ) {
		var submit = event.target.closest && event.target.closest( 'button, input[type="submit"]' );

		if ( submit && submit.form ) {
			lastSubmitter = submit;
		}

		if ( event.defaultPrevented || 0 !== event.button || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey ) {
			return;
		}

		var link = event.target.closest && event.target.closest( 'a[href]' );

		if ( ! link || ( link.target && '_self' !== link.target ) ) {
			return;
		}

		var url = new URL( link.href, window.location.href );

		if ( ! isScreen( url ) ) {
			return;
		}

		event.preventDefault();

		if ( modal && ! modal.hidden ) {
			load( url.href );
		} else {
			open( url.href, false );
		}
	} );

	document.addEventListener( 'submit', function ( event ) {
		var form = event.target;
		var inModal = !! ( modal && modal.contains( form ) );

		if ( event.defaultPrevented || 'post' !== ( form.getAttribute( 'method' ) || 'get' ).toLowerCase() ) {
			return;
		}

		// Forms in a screen post to that screen; the header menu's forms name theirs.
		var action = form.getAttribute( 'action' ) ? new URL( form.action, window.location.href ) : ( inModal && current ? new URL( current ) : null );

		if ( ! action || ! isScreen( action ) ) {
			return;
		}

		var data = new window.FormData( form );
		var submitter = event.submitter || ( lastSubmitter && lastSubmitter.form === form ? lastSubmitter : null );

		if ( submitter && submitter.name ) {
			data.append( submitter.name, submitter.value );
		}

		event.preventDefault();
		lastSubmitter = null;

		if ( ! inModal ) {
			back = data.get( 'back' ) || here();
		}
		if ( 'switch' === data.get( 'jws_profiles_action' ) ) {
			pick( form, submitter );
		}

		load( action.href, { method: 'POST', body: data, action: data.get( 'jws_profiles_action' ) } );
	} );

	// Add / edit: the avatar button shows or hides the choices; picking one updates the button.
	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest && event.target.closest( '.jws-pf-avatar-btn' );
		var list = button && document.getElementById( button.getAttribute( 'aria-controls' ) );

		if ( ! list ) {
			return;
		}
		list.hidden = ! list.hidden;
		button.setAttribute( 'aria-expanded', list.hidden ? 'false' : 'true' );
		if ( ! list.hidden ) {
			var picked = list.querySelector( 'input:checked' ) || list.querySelector( 'input' );

			if ( picked ) {
				picked.focus( { preventScroll: true } );
			}
		}
	} );

	/*
	 * "Your plan allows N profiles": once as many are ticked as the plan has
	 * places (data-max), the rest cannot be ticked. The server checks too.
	 */
	function limitKeep( form ) {
		var max = parseInt( form.getAttribute( 'data-max' ), 10 ) || 0;
		var boxes = form.querySelectorAll( 'input[name="keep[]"]' );
		var ticked = form.querySelectorAll( 'input[name="keep[]"]:checked' ).length;

		Array.prototype.forEach.call( boxes, function ( box ) {
			box.disabled = ! box.checked && ticked >= max;
		} );
	}

	document.addEventListener( 'change', function ( event ) {
		var input = event.target;

		if ( 'keep[]' === input.name && input.form && input.form.hasAttribute( 'data-max' ) ) {
			limitKeep( input.form );
			return;
		}

		var choice = 'avatar' === input.name && input.closest( '.jws-pf-avatar-opt' );
		var preview = choice && input.form && input.form.querySelector( '.jws-pf-avatar-btn__img' );
		var image = choice && choice.querySelector( 'img' );

		if ( preview && image ) {
			preview.src = image.src;
		}
	} );

	document.addEventListener( 'keydown', function ( event ) {
		if ( ! modal || modal.hidden ) {
			return;
		}

		if ( 'Escape' === event.key ) {
			close();
			return;
		}

		// Keep keyboard focus inside the dialog.
		if ( 'Tab' === event.key ) {
			var items = Array.prototype.filter.call(
				modal.querySelectorAll( 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), summary, [tabindex]:not([tabindex="-1"])' ),
				function ( item ) {
					return item.offsetWidth || item.offsetHeight || item === document.activeElement;
				}
			);

			if ( ! items.length ) {
				return;
			}

			var first = items[ 0 ];
			var last = items[ items.length - 1 ];

			if ( event.shiftKey && ( document.activeElement === first || ! modal.contains( document.activeElement ) ) ) {
				event.preventDefault();
				last.focus();
			} else if ( ! event.shiftKey && ( document.activeElement === last || ! modal.contains( document.activeElement ) ) ) {
				event.preventDefault();
				first.focus();
			}
		}
	} );

	window.addEventListener( 'pageshow', function ( event ) {
		if ( event.persisted ) {
			leaving = false;
			unpick();
			if ( modal ) {
				modal.classList.remove( 'is-loading' );
			}
			document.documentElement.classList.remove( 'jws-pfm-busy' );
		}
	} );

	// The gate first; else a screen named in the address (a direct visit to a screen URL comes back here).
	var named = window.location.hash.match( /^#jws-profiles=(.+)$/ );

	if ( named ) {
		window.history.replaceState( window.history.state, '', here() );
	}

	if ( config.gate ) {
		open( config.gate, true );
	} else if ( named ) {
		open( decodeURIComponent( named[ 1 ] ), false );
	}
}() );
