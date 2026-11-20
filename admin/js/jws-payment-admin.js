/**
 * The Payment System settings screen.
 *
 * Three small jobs: switch tabs without losing the form, copy a webhook URL,
 * and let someone check a pasted secret before saving it.
 *
 * The tab state is carried in a hidden field rather than only in the URL hash,
 * so saving from the Gateways tab comes back to the Gateways tab — a settings page
 * that always reopens on the first tab makes fixing one field a three-click
 * job every time.
 */
(function () {
	'use strict';

	var strings = window.jws_payment_admin || {};

	var root = document.querySelector('.jws-pay');

	if (!root) {
		return;
	}

	/* ---------------------------------------------------------------------- */
	/* Tabs                                                                    */
	/* ---------------------------------------------------------------------- */

	var tabs = Array.prototype.slice.call(root.querySelectorAll('.nav-tab'));
	var panels = Array.prototype.slice.call(root.querySelectorAll('.jws-pay-panel'));
	var field = root.querySelector('input[name="active_tab"]');

	/* The Gateways tab has one tab of its own per gateway. */
	var subtabs = Array.prototype.slice.call(root.querySelectorAll('.jws-pay-subtab'));
	var sections = Array.prototype.slice.call(root.querySelectorAll('.jws-pay-gateway'));
	var gatewayField = root.querySelector('input[name="active_gateway"]');

	function showGateway(id, focus) {
		var known = subtabs.some(function (tab) {
			return tab.dataset.gateway === id;
		});

		if (!known) {
			id = subtabs.length ? subtabs[0].dataset.gateway : '';
		}

		subtabs.forEach(function (tab) {
			var current = tab.dataset.gateway === id;

			tab.classList.toggle('is-active', current);
			tab.setAttribute('aria-selected', current ? 'true' : 'false');
			tab.tabIndex = current ? 0 : -1;

			if (current && focus) {
				tab.focus();
			}
		});

		sections.forEach(function (section) {
			section.classList.toggle('is-active', section.id === 'jws-pay-gateway-' + id);
		});

		/* Saved with the form, so Save comes back to this gateway. */
		if (gatewayField) {
			gatewayField.value = id;
		}
	}

	function show(name) {
		/* A gateway's name (#stripe, #mollie…) opens the Gateways tab on that
		   gateway. Stripe and PayPal had a tab each before, so old bookmarks to
		   them land there too. */
		var gateway = name && document.getElementById('jws-pay-gateway-' + name) ? name : '';

		if (gateway) {
			name = 'gateways';
		}

		var known = tabs.some(function (tab) {
			return tab.dataset.tab === name;
		});

		if (!known) {
			name = 'general';
		}

		tabs.forEach(function (tab) {
			tab.classList.toggle('nav-tab-active', tab.dataset.tab === name);
		});

		panels.forEach(function (panel) {
			panel.classList.toggle('is-active', panel.dataset.panel === name);
		});

		/* The Orders tab has its own forms, so the settings Save button below
		   it would be a button that saves a different tab's fields. */
		root.classList.toggle('is-orders', name === 'orders');

		/* Every form on the page carries the tab, so whichever one is
		   submitted comes back where the user was. */
		root.querySelectorAll('input[name="active_tab"]').forEach(function (input) {
			input.value = name;
		});

		if (gateway) {
			showGateway(gateway);
		}
	}

	tabs.forEach(function (tab) {
		tab.addEventListener('click', function (event) {
			event.preventDefault();
			show(tab.dataset.tab);
			history.replaceState(null, '', '#' + tab.dataset.tab);
		});
	});

	subtabs.forEach(function (tab) {
		tab.addEventListener('click', function () {
			showGateway(tab.dataset.gateway);
			/* The gateway's own name, so the link opens straight on it. */
			history.replaceState(null, '', '#' + tab.dataset.gateway);
		});

		/* Arrow keys, Home and End move between gateways, as in any tab list. */
		tab.addEventListener('keydown', function (event) {
			var index = subtabs.indexOf(tab);
			var next = null;

			if ('ArrowRight' === event.key) {
				next = subtabs[(index + 1) % subtabs.length];
			} else if ('ArrowLeft' === event.key) {
				next = subtabs[(index - 1 + subtabs.length) % subtabs.length];
			} else if ('Home' === event.key) {
				next = subtabs[0];
			} else if ('End' === event.key) {
				next = subtabs[subtabs.length - 1];
			}

			if (next) {
				event.preventDefault();
				showGateway(next.dataset.gateway, true);
				history.replaceState(null, '', '#' + next.dataset.gateway);
			}
		});
	});

	/*
	 * Only now: until the panels can be switched, hiding them would leave a
	 * page with no way to reach four of its five sections.
	 */
	root.classList.add('is-ready');

	/* The gateway the server says was last open, then whatever the address
	   asks for, which may name a gateway of its own. */
	showGateway(gatewayField ? gatewayField.value : '');
	show((window.location.hash || '').replace('#', '') || (field && field.value) || 'general');

	/* ---------------------------------------------------------------------- */
	/* Copy a webhook URL                                                      */
	/* ---------------------------------------------------------------------- */

	root.addEventListener('click', function (event) {
		var button = event.target.closest('.jws-pay-copy-button');

		if (!button) {
			return;
		}

		event.preventDefault();

		var done = function () {
			button.textContent = strings.copied;
			setTimeout(function () {
				button.textContent = strings.copy;
			}, 1600);
		};

		/* navigator.clipboard is unavailable over plain http on most browsers,
		   which is exactly how a local install is reached. */
		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(button.dataset.copy).then(done, fallback);
		} else {
			fallback();
		}

		function fallback() {
			var scratch = document.createElement('textarea');
			scratch.value = button.dataset.copy;
			scratch.setAttribute('readonly', '');
			scratch.style.position = 'fixed';
			scratch.style.opacity = '0';
			document.body.appendChild(scratch);
			scratch.select();

			try {
				document.execCommand('copy');
				done();
			} catch (e) {
				window.prompt(strings.copy, button.dataset.copy);
			}

			document.body.removeChild(scratch);
		}
	});

	/* ---------------------------------------------------------------------- */
	/* Reveal a secret                                                         */
	/* ---------------------------------------------------------------------- */

	root.addEventListener('click', function (event) {
		var button = event.target.closest('.jws-pay-reveal');

		if (!button) {
			return;
		}

		event.preventDefault();

		var input = button.parentNode.querySelector('input');

		if (!input) {
			return;
		}

		var hidden = input.type === 'password';

		input.type = hidden ? 'text' : 'password';
		button.textContent = hidden ? strings.hide : strings.show;
	});
})();
