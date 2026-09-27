/**
 * Live TV › Import Demo: runs the import one short step at a time —
 * categories, each channel, then the menu link and sample page — so no single
 * request runs into the host's time limit. A failed step can simply be run
 * again: the import updates what it already made.
 */
(function () {
	'use strict';

	var cfg = window.jwsTvDemo;
	if (!cfg) {
		return;
	}

	var t = cfg.i18n || {};
	var importBtn = document.getElementById('jws-tv-demo-import');
	var removeBtn = document.getElementById('jws-tv-demo-remove');
	var box = document.getElementById('jws-tv-demo-progress');
	var status = document.getElementById('jws-tv-demo-status');
	var log = document.getElementById('jws-tv-demo-log');
	var removeStatus = document.getElementById('jws-tv-demo-remove-status');

	if (!importBtn || !box) {
		return;
	}

	var progress = box.querySelector('progress');

	function post(data) {
		var body = new FormData();
		body.append('_ajax_nonce', cfg.nonce);
		Object.keys(data).forEach(function (key) {
			[].concat(data[key]).forEach(function (value) {
				body.append(key, value);
			});
		});

		return fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
			.then(function (response) {
				return response.json().catch(function () {
					throw new Error(t.failed + ' (HTTP ' + response.status + ')');
				});
			})
			.then(function (json) {
				if (!json || !json.success) {
					throw new Error(json && json.data && json.data.message ? json.data.message : t.failed);
				}
				return json.data;
			});
	}

	function line(text, warning) {
		var li = document.createElement('li');
		li.textContent = text;
		if (warning) {
			li.className = 'is-warning';
		}
		log.appendChild(li);
		log.scrollTop = log.scrollHeight;
	}

	function say(el, text, error) {
		el.textContent = text;
		el.classList.toggle('is-error', !!error);
	}

	function link(href, text) {
		var a = document.createElement('a');
		a.href = href;
		a.textContent = text;
		return a;
	}

	function options() {
		var menu = document.querySelector('.jws-tv-demo select[name="menu"]');
		var page = document.querySelector('.jws-tv-demo input[name="page"]');
		return {
			levels: Array.prototype.map.call(document.querySelectorAll('.jws-tv-demo input[name="levels[]"]:checked'), function (i) {
				return i.value;
			}),
			menu: menu ? menu.value : '0',
			page: page && page.checked ? '1' : ''
		};
	}

	function busy(on) {
		importBtn.disabled = on;
		if (removeBtn) {
			removeBtn.disabled = on;
		}
	}

	async function runImport() {
		var opts = options();

		busy(true);
		box.hidden = false;
		log.textContent = '';
		progress.value = 0;
		say(status, '');

		try {
			var start = await post({ action: 'jws_tv_demo_import', step: 'start' });
			var total = start.channels.length + 2;
			var done = 1;

			line(t.categories);
			progress.value = done / total * 100;

			for (var i = 0; i < start.channels.length; i++) {
				say(status, (t.importing || '%s').replace('%s', start.channels[i]));
				var result = await post({ action: 'jws_tv_demo_import', step: 'channel', index: i, 'levels[]': opts.levels });
				line(result.message);
				(result.warnings || []).forEach(function (w) { line(w, true); });
				progress.value = ++done / total * 100;
			}

			say(status, t.finishing);
			var finish = await post({ action: 'jws_tv_demo_import', step: 'finish', menu: opts.menu, page: opts.page });
			(finish.messages || []).forEach(function (m) { line(m); });
			(finish.warnings || []).forEach(function (w) { line(w, true); });
			progress.value = 100;

			say(status, t.done);
			status.appendChild(link(finish.links.archive, t.view));
			status.appendChild(link(finish.links.channels, t.channels));
			if (finish.links.page) {
				status.appendChild(link(finish.links.page, t.editPage));
			}
		} catch (e) {
			say(status, e.message, true);
		}

		busy(false);
	}

	importBtn.addEventListener('click', runImport);

	if (removeBtn) {
		removeBtn.addEventListener('click', function () {
			if (!window.confirm(t.confirmRemove)) {
				return;
			}
			busy(true);
			say(removeStatus, t.removing);
			post({ action: 'jws_tv_demo_remove' })
				.then(function (data) {
					say(removeStatus, data.message);
				})
				.catch(function (e) {
					say(removeStatus, e.message, true);
				})
				.then(function () {
					busy(false);
				});
		});
	}
})();
