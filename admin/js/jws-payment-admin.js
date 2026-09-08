/**
 * The Payment System settings screen.
 *
 * Three small jobs: switch tabs without losing the form, copy a webhook URL,
 * and let someone check a pasted secret before saving it.
 *
 * The tab state is carried in a hidden field rather than only in the URL hash,
 * so saving from the Stripe tab comes back to the Stripe tab — a settings page
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

	function show(name) {
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
	}

	tabs.forEach(function (tab) {
		tab.addEventListener('click', function (event) {
			event.preventDefault();
			show(tab.dataset.tab);
			history.replaceState(null, '', '#' + tab.dataset.tab);
		});
	});

	/*
	 * Only now: until the panels can be switched, hiding them would leave a
	 * page with no way to reach four of its five sections.
	 */
	root.classList.add('is-ready');

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
