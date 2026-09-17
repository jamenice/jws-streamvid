/* global jQuery, wp, JwsMetabox */
/**
 * Jws_Metabox admin UI: tabs, conditions, media, post/term pickers,
 * repeaters and the seasons / episodes editor.
 */
(function ($) {
	'use strict';

	var cfg = window.JwsMetabox || {};
	var t = cfg.i18n || {};

	function fmt(str, val) {
		return String(str || '').replace('%d', val).replace('%s', val);
	}

	function esc(str) {
		return String(str == null ? '' : str).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	function uid() {
		return 'r' + Math.random().toString(36).slice(2, 10);
	}

	function debounce(fn, wait) {
		var timer;
		return function () {
			var ctx = this, args = arguments;
			clearTimeout(timer);
			timer = setTimeout(function () { fn.apply(ctx, args); }, wait);
		};
	}

	function ajax(action, data) {
		return $.post(cfg.ajaxUrl, $.extend({ action: action, nonce: cfg.nonce }, data));
	}

	function store(key, value) {
		try {
			if (value === undefined) {
				return window.localStorage.getItem(key);
			}
			window.localStorage.setItem(key, value);
		} catch (e) {
			return null;
		}
	}

	/* ------------------------------------------------------------------ */
	/* Motion                                                             */
	/* ------------------------------------------------------------------ */

	var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	/** Play the enter animation on freshly inserted elements. */
	function animateIn($el) {
		if (reduceMotion) {
			return $el;
		}
		$el.addClass('is-entering');
		setTimeout(function () { $el.removeClass('is-entering'); }, 400);
		return $el;
	}

	/** Fade/collapse an element out, then remove it and run done(). */
	function animateOut($el, done) {
		if (reduceMotion || !$el.length) {
			$el.remove();
			if (done) { done(); }
			return;
		}
		$el.css('height', $el.outerHeight()).addClass('is-leaving');
		// Force layout so the height transition starts from the measured value.
		void $el[0].offsetHeight;
		$el.css('height', 0);
		setTimeout(function () {
			$el.remove();
			if (done) { done(); }
		}, 260);
	}

	/** Toggle a collapsible row/season, clipping its body while it animates. */
	function setCollapsed($items, collapsed) {
		$items.each(function () {
			var $el = $(this);
			var next = collapsed === undefined ? !$el.hasClass('is-collapsed') : collapsed;
			if (next === $el.hasClass('is-collapsed')) {
				return;
			}
			$el.addClass('is-animating').toggleClass('is-collapsed', next);
			clearTimeout($el.data('jwsAnim'));
			$el.data('jwsAnim', setTimeout(function () { $el.removeClass('is-animating'); }, reduceMotion ? 0 : 280));
		});
	}

	/* ------------------------------------------------------------------ */
	/* Tabs                                                               */
	/* ------------------------------------------------------------------ */

	function initTabs($box) {
		var storeKey = 'jwsMbTab:' + $box.data('box');

		function show(i) {
			$box.find('> .jws-mb__tabs .jws-mb__tab').removeClass('is-active').filter('[data-tab="' + i + '"]').addClass('is-active');
			$box.find('> .jws-mb__panels > .jws-mb__panel').removeClass('is-active').filter('[data-panel="' + i + '"]').addClass('is-active');
		}

		$box.on('click', '> .jws-mb__tabs .jws-mb__tab', function () {
			var i = $(this).data('tab');
			show(i);
			store(storeKey, String(i));
		});

		var saved = store(storeKey);
		if (saved !== null && $box.find('> .jws-mb__tabs [data-tab="' + saved + '"]').length) {
			show(saved);
		}
	}

	/* ------------------------------------------------------------------ */
	/* Conditions                                                         */
	/* ------------------------------------------------------------------ */

	function fieldValue($field) {
		var $cb = $field.find('input[type="checkbox"]').first();
		if ($cb.length) {
			return $cb.is(':checked') ? '1' : '0';
		}
		return String($field.find('select, input, textarea').first().val() || '');
	}

	function applyConditions($scope) {
		$scope.find('.jws-mb__field[data-conditions]').each(function () {
			var $f = $(this);
			var rules = $f.data('conditions');
			var $grid = $f.closest('.jws-mb__grid');
			rules = rules.field !== undefined ? [rules] : rules;
			var show = rules.every(function (cond) {
				var $src = $grid.children('.jws-mb__field[data-name="' + cond.field + '"]');
				if (!$src.length) {
					return true;
				}
				// A rule on a field that is itself hidden fails too.
				if ($src.hasClass('is-hidden')) {
					return false;
				}
				return [].concat(cond.value).map(String).indexOf(fieldValue($src)) !== -1;
			});
			$f.toggleClass('is-hidden', !show);
		});
	}

	/* ------------------------------------------------------------------ */
	/* Media                                                              */
	/* ------------------------------------------------------------------ */

	function openMedia($media) {
		var kind = $media.data('kind');
		var library = $media.data('library');
		var frame = wp.media({
			title: t.select,
			library: library ? { type: library } : {},
			multiple: false
		});
		frame.on('select', function () {
			var att = frame.state().get('selection').first().toJSON();
			$media.find('.jws-mb__media-id').val(att.id).trigger('change');
			var html;
			if (kind === 'image') {
				var size = (att.sizes && (att.sizes.medium || att.sizes.thumbnail)) || att;
				html = '<img src="' + esc(size.url) + '" alt="">';
			} else {
				html = '<span class="dashicons dashicons-media-default"></span><span class="jws-mb__media-name">' + esc(att.filename) + '</span>';
			}
			$media.find('.jws-mb__media-preview').html(html);
			$media.addClass('has-value');
		});
		frame.open();
	}

	function clearMedia($media) {
		$media.find('.jws-mb__media-id').val('').trigger('change');
		$media.removeClass('has-value');
		$media.find('.jws-mb__media-preview').html(
			$media.hasClass('jws-mb__season-thumb') ? '<span class="dashicons dashicons-format-image"></span>' : ''
		);
	}

	/* ------------------------------------------------------------------ */
	/* Post & term pickers (chips + dropdown)                             */
	/* ------------------------------------------------------------------ */

	function chipHtml(item, input, isTerm) {
		var thumb = item.thumb ? '<img src="' + esc(item.thumb) + '" alt="">' : '';
		var value = item.value !== undefined ? item.value : item.id;
		return '<li class="jws-mb__chip' + (isTerm ? ' jws-mb__chip--term' : '') + '" data-id="' + esc(value) + '">' +
			'<input type="hidden" name="' + esc(input) + '" value="' + esc(value) + '">' +
			thumb + '<span class="jws-mb__chip-title">' + esc(item.title || t.untitled) + '</span>' +
			(item.meta ? '<small>' + esc(item.meta) + '</small>' : '') +
			'<button type="button" class="jws-mb__chip-remove" aria-label="' + esc(t.remove) + '">&times;</button></li>';
	}

	function initPicker($picker) {
		if ($picker.data('ready')) {
			return;
		}
		$picker.data('ready', 1);

		var isTerm = $picker.hasClass('jws-mb__terms');
		var multiple = Number($picker.data('multiple')) === 1;
		var input = $picker.data('input');
		var $chips = $picker.find('> .jws-mb__chips');
		var $search = $picker.find('.jws-mb__search-input');
		var $drop = $picker.find('.jws-mb__dropdown');
		var active = -1;
		var request;

		if (multiple) {
			$chips.sortable({ items: '> li', tolerance: 'pointer', forcePlaceholderSize: true });
		}

		function selectedIds() {
			return $chips.children(':not(.is-leaving-chip)').map(function () { return String($(this).data('id')); }).get();
		}

		function close() {
			$drop.removeClass('is-open').prop('hidden', true).empty();
			active = -1;
		}

		function add(item) {
			if (!multiple) {
				$chips.empty();
			}
			var max = Number($picker.data('max')) || 0;
			if (multiple && max && $chips.children(':not(.is-leaving-chip)').length >= max) {
				close();
				$picker.addClass('is-full');
				setTimeout(function () { $picker.removeClass('is-full'); }, 800);
				return;
			}
			animateIn($(chipHtml(item, input, isTerm)).appendTo($chips));
			$picker.trigger('jws-mb:change', [selectedIds()]);
			$search.val('');
			close();
			if (!multiple) {
				$search.blur();
			}
		}

		function render(items, canAdd, term) {
			var ids = selectedIds();
			var html = '';
			var manyTypes = String($picker.data('post-type') || '').indexOf(',') !== -1;
			items.forEach(function (item) {
				if (ids.indexOf(String(item.id)) !== -1) {
					return;
				}
				html += '<button type="button" class="jws-mb__option" data-item="' + esc(JSON.stringify(item)) + '">' +
					(item.thumb ? '<img src="' + esc(item.thumb) + '" alt="">' : '') +
					'<span>' + esc(item.title || t.untitled) + (item.meta || (manyTypes && item.type_label) ? '<small>' + esc(item.meta || item.type_label) + '</small>' : '') + '</span>' +
					(item.status && item.status !== 'publish' ? '<em>' + esc(item.status) + '</em>' : '') +
					'</button>';
			});
			var exact = items.some(function (i) { return String(i.title).toLowerCase() === term.toLowerCase(); });
			if (isTerm && canAdd && term && !exact) {
				html += '<button type="button" class="jws-mb__option jws-mb__option--new" data-item="' +
					esc(JSON.stringify({ id: 'new:' + term, value: 'new:' + term, title: term })) + '">+ ' + esc(term) + '</button>';
			}
			$drop.html(html || '<div class="jws-mb__option-empty">' + esc(t.noResults) + '</div>').prop('hidden', false).addClass('is-open');
		}

		var search = debounce(function () {
			var term = $.trim($search.val());
			if (request) {
				request.abort();
			}
			if ($drop.prop('hidden')) {
				$drop.html('<div class="jws-mb__option-empty"><span class="jws-mb__spinner"></span>' + esc(t.loading) + '</div>').prop('hidden', false).addClass('is-open');
			} else {
				$drop.addClass('is-busy');
			}
			if (isTerm) {
				request = ajax('jws_mb_search_terms', { taxonomy: $picker.data('taxonomy'), s: term });
			} else if ($picker.data('source') === 'users') {
				request = ajax('jws_mb_search_users', { s: term });
			} else {
				request = ajax('jws_mb_search_posts', { post_type: $picker.data('post-type'), s: term, per_page: 20 });
			}
			request.done(function (res) {
				if (res && res.success) {
					render(res.data.items, res.data.canAdd, term);
				}
			}).always(function () {
				$drop.removeClass('is-busy');
			});
		}, 250);

		$search.on('focus input', search);
		$search.on('keydown', function (e) {
			var $opts = $drop.find('.jws-mb__option');
			if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
				e.preventDefault();
				active = Math.max(0, Math.min($opts.length - 1, active + (e.key === 'ArrowDown' ? 1 : -1)));
				$opts.removeClass('is-active').eq(active).addClass('is-active')[0].scrollIntoView({ block: 'nearest' });
			} else if (e.key === 'Enter') {
				e.preventDefault(); // never submit the post form from here
				if (active > -1 && $opts.eq(active).length) {
					$opts.eq(active).trigger('mousedown');
				}
			} else if (e.key === 'Escape') {
				close();
			}
		});
		$search.on('blur', function () {
			setTimeout(close, 150);
		});
		$drop.on('mousedown', '.jws-mb__option', function (e) {
			e.preventDefault();
			add($(this).data('item'));
		});
		$chips.on('click', '.jws-mb__chip-remove', function () {
			var $chip = $(this).closest('.jws-mb__chip');
			// Take the value out of the form immediately; the chip just fades.
			$chip.find('input').remove();
			$chip.removeAttr('data-id').addClass('is-leaving-chip');
			$picker.trigger('jws-mb:change', [selectedIds()]);
			setTimeout(function () { $chip.remove(); }, reduceMotion ? 0 : 180);
		});
	}

	/* ------------------------------------------------------------------ */
	/* Taxonomy pills                                                     */
	/* ------------------------------------------------------------------ */

	function initTax($tax) {
		if ($tax.data('ready')) {
			return;
		}
		$tax.data('ready', 1);

		var multiple = Number($tax.data('multiple')) === 1;
		var input = $tax.data('input');
		var $values = $tax.find('.jws-mb__tax-values');
		var $list = $tax.find('.jws-mb__tax-list');
		var $count = $tax.find('.jws-mb__tax-count');

		function refresh() {
			var n = $values.children().length;
			$count.text(n ? fmt($count.data('some'), n) : $count.data('none'));
			$tax.find('.jws-mb__tax-clear').prop('hidden', !n);
		}

		function setOn($pill, on) {
			var value = String($pill.data('value') || $pill.data('id'));
			$pill.toggleClass('is-on', on).attr('aria-pressed', on ? 'true' : 'false');
			var $existing = $values.children().filter(function () { return this.value === value; });
			if (on && !$existing.length) {
				// Appended, so the saved order is the order of clicks.
				$('<input type="hidden">').attr('name', input).val(value).appendTo($values);
			} else if (!on) {
				$existing.remove();
			}
		}

		$list.on('click', '.jws-mb__tax-pill', function () {
			var $pill = $(this);
			var on = !$pill.hasClass('is-on');
			if (on && !multiple) {
				$list.find('.jws-mb__tax-pill.is-on').not($pill).each(function () { setOn($(this), false); });
			}
			setOn($pill, on);
			if (!on && $pill.hasClass('is-new-term')) {
				$pill.addClass('is-leaving-chip');
				setTimeout(function () { $pill.remove(); }, reduceMotion ? 0 : 180);
			} else if (!reduceMotion) {
				$pill.removeClass('is-bump');
				void $pill[0].offsetWidth;
				$pill.addClass('is-bump');
			}
			refresh();
		});

		$tax.on('click', '.jws-mb__tax-clear', function () {
			$list.find('.jws-mb__tax-pill.is-on').each(function () { $(this).trigger('click'); });
		});

		$tax.on('input', '.jws-mb__tax-filter', function () {
			var q = $.trim(this.value).toLowerCase();
			$list.find('.jws-mb__tax-pill').each(function () {
				var name = String($(this).data('name') || '');
				$(this).toggleClass('is-filtered', q !== '' && name.indexOf(q) === -1);
			});
		});

		function addTerm() {
			var $field = $tax.find('.jws-mb__tax-add input');
			var name = $.trim($field.val());
			if (!name) {
				return;
			}
			var lower = name.toLowerCase();
			var $match = $list.find('.jws-mb__tax-pill').filter(function () { return String($(this).data('name')) === lower; }).first();
			if (!$match.length) {
				$match = $('<button type="button" class="jws-mb__tax-pill is-new-term" aria-pressed="false"><span class="jws-mb__tax-check" aria-hidden="true"></span><span></span><small>new</small></button>')
					.attr('data-name', lower)
					.data('value', 'new:' + name);
				$match.children('span').last().text(name);
				$match.insertBefore($tax.find('.jws-mb__tax-add'));
				animateIn($match);
			}
			if (!$match.hasClass('is-on')) {
				$match.trigger('click');
			}
			$field.val('').trigger('focus');
		}

		$tax.on('click', '.jws-mb__tax-add-btn', addTerm);
		$tax.on('keydown', '.jws-mb__tax-add input, .jws-mb__tax-filter', function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				if ($(this).closest('.jws-mb__tax-add').length) {
					addTerm();
				}
			} else if (e.key === 'Escape' && $(this).hasClass('jws-mb__tax-filter')) {
				$(this).val('').trigger('input');
			}
		});

		refresh();
	}

	/* ------------------------------------------------------------------ */
	/* Gallery                                                            */
	/* ------------------------------------------------------------------ */

	function refreshGallery($g) {
		var n = $g.find('.jws-mb__gallery-item').length;
		var max = Number($g.data('max')) || 0;
		$g.find('.jws-mb__gallery-count').text(max ? n + ' / ' + max : n);
		$g.find('.jws-mb__gallery-add').prop('disabled', max > 0 && n >= max);
	}

	function initGallery($g) {
		if ($g.data('ready')) {
			return;
		}
		$g.data('ready', 1);
		var $list = $g.find('.jws-mb__gallery-list');
		$list.sortable({ items: '> li', tolerance: 'pointer', placeholder: 'jws-mb__gallery-item jws-mb__placeholder' });

		$g.on('click', '.jws-mb__gallery-remove', function () {
			var $item = $(this).closest('.jws-mb__gallery-item');
			$item.find('input').remove();
			$item.addClass('is-leaving-chip');
			setTimeout(function () { $item.remove(); refreshGallery($g); }, reduceMotion ? 0 : 180);
		});

		$g.on('click', '.jws-mb__gallery-add', function () {
			var library = $g.data('library');
			var frame = wp.media({
				title: t.select,
				library: library && library !== 'all' ? { type: library } : { type: 'image' },
				multiple: 'add'
			});
			frame.on('select', function () {
				var max = Number($g.data('max')) || 0;
				var input = $g.data('input');
				frame.state().get('selection').each(function (att) {
					att = att.toJSON();
					if ($list.find('[data-id="' + att.id + '"]').length) {
						return;
					}
					if (max && $list.children().length >= max) {
						return;
					}
					var size = (att.sizes && (att.sizes.thumbnail || att.sizes.medium)) || att;
					animateIn($(
						'<li class="jws-mb__gallery-item" data-id="' + esc(att.id) + '">' +
						'<input type="hidden" name="' + esc(input) + '" value="' + esc(att.id) + '">' +
						'<img src="' + esc(size.url || att.icon) + '" alt="">' +
						'<button type="button" class="jws-mb__gallery-remove" aria-label="' + esc(t.remove) + '">&times;</button></li>'
					).appendTo($list));
				});
				refreshGallery($g);
			});
			frame.open();
		});
		refreshGallery($g);
	}

	/* ------------------------------------------------------------------ */
	/* Free-text label chips ("tags")                                     */
	/* ------------------------------------------------------------------ */

	function initTags($tags) {
		if ($tags.data('ready')) {
			return;
		}
		$tags.data('ready', 1);

		var input = $tags.data('input');
		var max = Number($tags.data('max')) || 0;
		var $chips = $tags.find('> .jws-mb__chips');
		var $field = $tags.find('.jws-mb__tags-input');

		$chips.sortable({ items: '> li', tolerance: 'pointer', forcePlaceholderSize: true });

		function values() {
			return $chips.children(':not(.is-leaving-chip)').map(function () { return String($(this).data('value')); }).get();
		}

		function has(v) {
			var lower = v.toLowerCase();
			return values().some(function (x) { return x.toLowerCase() === lower; });
		}

		function refreshSuggest() {
			var current = values().map(function (v) { return v.toLowerCase(); });
			$tags.find('.jws-mb__tag-suggest').each(function () {
				$(this).toggleClass('is-picked', current.indexOf($(this).text().toLowerCase()) !== -1);
			});
		}

		function addTag(v) {
			v = $.trim(v).slice(0, 40);
			if (!v || has(v)) {
				return false;
			}
			if (max && values().length >= max) {
				$field.trigger('jws-mb:full');
				$tags.addClass('is-full');
				setTimeout(function () { $tags.removeClass('is-full'); }, 500);
				return false;
			}
			var $chip = $(
				'<li class="jws-mb__chip jws-mb__chip--term" data-value="' + esc(v) + '">' +
				'<input type="hidden" name="' + esc(input) + '" value="' + esc(v) + '">' +
				'<span class="jws-mb__chip-title">' + esc(v) + '</span>' +
				'<button type="button" class="jws-mb__chip-remove" aria-label="' + esc(t.remove) + '">&times;</button></li>'
			);
			animateIn($chip.appendTo($chips));
			refreshSuggest();
			return true;
		}

		$chips.on('click', '.jws-mb__chip-remove', function () {
			var $chip = $(this).closest('.jws-mb__chip');
			$chip.find('input').remove();
			$chip.addClass('is-leaving-chip');
			animateOut($chip, refreshSuggest);
		});

		$field.on('keydown', function (e) {
			if (e.key === 'Enter' || e.key === ',') {
				e.preventDefault();
				if (addTag(this.value)) {
					this.value = '';
				}
			} else if (e.key === 'Backspace' && !this.value) {
				$chips.children(':not(.is-leaving-chip)').last().find('.jws-mb__chip-remove').trigger('click');
			}
		});
		$field.on('blur', function () {
			if (this.value && addTag(this.value)) {
				this.value = '';
			}
		});

		$tags.on('click', '.jws-mb__tag-suggest', function () {
			if (addTag($(this).text())) {
				$field.trigger('focus');
			}
		});

		refreshSuggest();
	}

	/* ------------------------------------------------------------------ */
	/* Repeater                                                           */
	/* ------------------------------------------------------------------ */

	function refreshRepeater($rep) {
		var $rows = $rep.find('> .jws-mb__rows > .jws-mb__row');
		var titleField = $rep.data('row-title');
		$rows.each(function (i) {
			var $row = $(this);
			$row.find('> .jws-mb__row-bar .jws-mb__row-index').text(i + 1);
			var title = '';
			if (titleField) {
				var $f = $row.find('> .jws-mb__collapse > .jws-mb__row-body > .jws-mb__field[data-name="' + titleField + '"]');
				title = $f.find('input').val() || '';
			}
			var person = $row.find('.jws-mb__chip-title').first().text();
			$row.find('> .jws-mb__row-bar .jws-mb__row-title').text([person, title].filter(Boolean).join(' — '));
		});
		$rep.find('> .jws-mb__repeater-head .jws-mb__count').text($rows.length);
		var max = Number($rep.data('max'));
		$rep.find('> .jws-mb__add-row').prop('disabled', max > 0 && $rows.length >= max);
	}

	function initRepeater($rep) {
		var $list = $rep.find('> .jws-mb__rows');
		$list.sortable({
			handle: '> .jws-mb__row-bar .jws-mb__handle',
			items: '> .jws-mb__row',
			tolerance: 'pointer',
			forcePlaceholderSize: true,
			placeholder: 'jws-mb__placeholder',
			update: function () { refreshRepeater($rep); }
		});
		refreshRepeater($rep);
	}

	/* ------------------------------------------------------------------ */
	/* Seasons editor                                                     */
	/* ------------------------------------------------------------------ */

	function initSeasons($wrap) {
		var $list = $wrap.find('.jws-mb__season-list');
		var tpl = $wrap.find('> .jws-mb__season-tpl').html();

		function refresh() {
			var $seasons = $list.children('.jws-mb__season');
			var total = 0;
			$seasons.each(function (i) {
				var $s = $(this);
				var $eps = $s.find('.jws-mb__episodes');
				var input = $eps.data('input');
				var $items = $eps.children('.jws-mb__episode');
				// Moved rows must post under the season they now live in.
				$items.find('input[type="hidden"]').attr('name', input);
				$items.each(function (n) {
					$(this).find('.jws-mb__ep-no').text(n + 1);
				});
				$s.find('.jws-mb__season-no').text('S' + (i + 1));
				$s.find('.jws-mb__season-count').text($items.length);
				$s.toggleClass('is-empty', !$items.length);
				$s.find('.jws-mb__season-name').attr('placeholder', fmt(t.season, i + 1));
				total += $items.length;
			});
			$wrap.find('.jws-mb__stat-seasons').text($seasons.length);
			$wrap.find('.jws-mb__stat-episodes').text(total);
			$wrap.find('.jws-mb__seasons-empty').prop('hidden', $seasons.length > 0);
			markDuplicates();
		}

		function markDuplicates() {
			var seen = {};
			$list.find('.jws-mb__episode').each(function () {
				var id = String($(this).data('id'));
				$(this).toggleClass('is-duplicate', !!seen[id]);
				seen[id] = true;
			});
		}

		function sortEpisodes($ol) {
			$ol.sortable({
				handle: '.jws-mb__handle',
				items: '> .jws-mb__episode',
				connectWith: $wrap.find('.jws-mb__episodes'),
				tolerance: 'pointer',
				placeholder: 'jws-mb__placeholder',
				forcePlaceholderSize: true,
				update: refresh
			});
		}

		$list.sortable({
			handle: '> .jws-mb__season-head > .jws-mb__handle',
			items: '> .jws-mb__season',
			tolerance: 'pointer',
			placeholder: 'jws-mb__placeholder jws-mb__placeholder--season',
			forcePlaceholderSize: true,
			start: function (e, ui) {
				ui.item.addClass('is-collapsed is-dragging');
				ui.placeholder.height(ui.helper.outerHeight());
			},
			stop: function (e, ui) { ui.item.removeClass('is-dragging'); },
			update: refresh
		});
		$list.find('.jws-mb__episodes').each(function () { sortEpisodes($(this)); });

		function addSeason() {
			var $s = animateIn($(tpl.replace(/__ROW__/g, uid())));
			$list.append($s);
			sortEpisodes($s.find('.jws-mb__episodes'));
			$wrap.find('.jws-mb__episodes').sortable('option', 'connectWith', $wrap.find('.jws-mb__episodes'));
			refresh();
			$s.find('.jws-mb__season-name').val(fmt(t.season, $list.children().length)).trigger('focus').select();
			return $s;
		}

		$wrap.on('click', '.jws-mb__season-add', addSeason);

		$wrap.on('click', '.jws-mb__season-remove', function () {
			var $s = $(this).closest('.jws-mb__season');
			if ($s.find('.jws-mb__episode').length && !window.confirm(t.confirmSeason)) {
				return;
			}
			animateOut($s, refresh);
		});

		$wrap.on('click', '.jws-mb__season-toggle', function () {
			setCollapsed($(this).closest('.jws-mb__season'));
		});
		$wrap.on('click', '.jws-mb__season-head', function (e) {
			if (e.target === this || $(e.target).is('.jws-mb__season-count')) {
				setCollapsed($(this).closest('.jws-mb__season'));
			}
		});

		$wrap.on('click', '.jws-mb__seasons-collapse', function () {
			var $all = $list.children('.jws-mb__season');
			var collapse = $all.filter(':not(.is-collapsed)').length > 0;
			setCollapsed($all, collapse);
		});

		$wrap.on('click', '.jws-mb__season-clear-thumb', function () {
			clearMedia($(this).closest('.jws-mb__season').find('.jws-mb__season-thumb'));
		});

		$wrap.on('click', '.jws-mb__episode-remove', function () {
			var $ep = $(this).closest('.jws-mb__episode');
			$ep.find('input').remove();
			animateOut($ep, refresh);
		});

		$wrap.on('keydown', '.jws-mb__season-name', function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();
			}
		});

		/* ---- episode rows ---------------------------------------------- */

		function episodeHtml(item, input) {
			var status = item.status && item.status !== 'publish'
				? '<span class="jws-mb__status jws-mb__status--' + esc(item.status) + '">' + esc(item.status) + '</span>' : '';
			var meta = [item.meta, '#' + item.id].filter(Boolean).join(' · ');
			return '<li class="jws-mb__episode" data-id="' + esc(item.id) + '">' +
				'<input type="hidden" name="' + esc(input) + '" value="' + esc(item.id) + '">' +
				'<span class="jws-mb__handle dashicons dashicons-menu"></span><span class="jws-mb__ep-no"></span>' +
				'<span class="jws-mb__ep-thumb">' + (item.thumb ? '<img src="' + esc(item.thumb) + '" alt="" loading="lazy">' : '<span class="dashicons dashicons-format-video"></span>') + '</span>' +
				'<span class="jws-mb__ep-info"><a href="' + esc(item.edit) + '" target="_blank" class="jws-mb__ep-title">' + esc(item.title || t.untitled) + '</a><small>' + esc(meta) + '</small></span>' +
				status +
				'<span class="jws-mb__ep-tools"><a href="' + esc(item.edit) + '" target="_blank" class="jws-mb__icon-btn"><span class="dashicons dashicons-edit"></span></a>' +
				'<button type="button" class="jws-mb__icon-btn jws-mb__episode-remove"><span class="dashicons dashicons-no-alt"></span></button></span></li>';
		}

		function addEpisodes($season, items) {
			var $ol = $season.find('.jws-mb__episodes');
			var input = $ol.data('input');
			var existing = currentIds();
			items.forEach(function (item) {
				if (existing[item.id]) {
					return;
				}
				existing[item.id] = true;
				$ol.append($(episodeHtml(item, input)).addClass('is-new'));
			});
			setCollapsed($season, false);
			refresh();
		}

		/** id => season number for every episode in the editor. */
		function currentIds() {
			var map = {};
			$list.children('.jws-mb__season').each(function (i) {
				$(this).find('.jws-mb__episode').each(function () {
					map[$(this).data('id')] = i + 1;
				});
			});
			return map;
		}

		/* ---- modal ----------------------------------------------------- */

		var $modal = $wrap.find('.jws-mb__modal');
		var $pickList = $modal.find('.jws-mb__picker-list');
		var $submit = $modal.find('.jws-mb__modal-submit');
		var $msg = $modal.find('.jws-mb__modal-msg');
		var state = { season: null, mode: 'pick', page: 1, pages: 1, selected: {}, order: [], req: null };

		// Keep the modal out of the postbox so its drag/transform styles never apply.
		$modal.appendTo(document.body);

		function openModal($season) {
			state.season = $season;
			state.selected = {};
			state.order = [];
			state.page = 1;
			var idx = $list.children().index($season) + 1;
			$modal.find('.jws-mb__modal-season').text($season.find('.jws-mb__season-name').val() || fmt(t.season, idx));
			$modal.prop('hidden', false).removeClass('is-closing');
			requestAnimationFrame(function () { $modal.addClass('is-open'); });
			$('body').addClass('jws-mb-modal-open');
			setMode(state.mode);
			updateCreatePreview();
			$msg.text('');
			if (state.mode === 'pick') {
				load();
				setTimeout(function () { $modal.find('.jws-mb__picker-search').trigger('focus'); }, 50);
			}
		}

		function closeModal() {
			$modal.removeClass('is-open').addClass('is-closing');
			setTimeout(function () {
				if ($modal.hasClass('is-closing')) {
					$modal.prop('hidden', true).removeClass('is-closing');
				}
			}, reduceMotion ? 0 : 200);
			$('body').removeClass('jws-mb-modal-open');
			if (state.req) {
				state.req.abort();
			}
		}

		function setMode(mode) {
			state.mode = mode;
			$modal.find('.jws-mb__modal-tabs button').removeClass('is-active').filter('[data-mode="' + mode + '"]').addClass('is-active');
			$modal.find('.jws-mb__modal-pane').each(function () {
				$(this).prop('hidden', $(this).data('pane') !== mode);
			});
			updateSubmit();
		}

		function updateSubmit() {
			if (state.mode === 'pick') {
				var n = state.order.length;
				$submit.text(fmt(t.addSelected, n)).prop('disabled', !n);
			} else {
				var count = createItems().length;
				$submit.text(fmt(t.createN, count)).prop('disabled', !count);
			}
		}

		function load() {
			if (state.req) {
				state.req.abort();
			}
			$pickList.addClass('is-loading');
			if (!$pickList.children('.jws-mb__pick').length) {
				$pickList.html(new Array(7).join('<li class="jws-mb__skeleton"><span></span><span></span></li>'));
			}
			state.req = ajax('jws_mb_search_posts', {
				post_type: $wrap.data('post-type'),
				s: $.trim($modal.find('.jws-mb__picker-search').val()),
				scope: $modal.find('.jws-mb__picker-scope').val(),
				order: $modal.find('.jws-mb__picker-order').val(),
				parent: cfg.postId,
				page: state.page,
				per_page: 30
			}).done(function (res) {
				if (!res || !res.success) {
					return;
				}
				state.pages = Math.max(1, res.data.pages);
				renderPick(res.data.items);
				$modal.find('.jws-mb__picker-total').text(res.data.total);
				$modal.find('.jws-mb__picker-page').text(state.page + ' / ' + state.pages);
				$modal.find('.jws-mb__picker-prev').prop('disabled', state.page <= 1);
				$modal.find('.jws-mb__picker-next').prop('disabled', state.page >= state.pages);
			}).always(function () {
				$pickList.removeClass('is-loading');
			});
		}

		function renderPick(items) {
			var inEditor = currentIds();
			var html = '';
			items.forEach(function (item) {
				var here = inEditor[item.id];
				var note = '';
				if (here) {
					note = '<span class="jws-mb__pill jws-mb__pill--here">' + esc(fmt(t.inThisShow, 'S' + here)) + '</span>';
				} else if (item.show_id && Number(item.show_id) !== Number(cfg.postId)) {
					note = '<span class="jws-mb__pill jws-mb__pill--other">' + esc(fmt(t.inOtherShow, item.show_title + (item.season ? ' · S' + item.season : ''))) + '</span>';
				}
				var checked = state.selected[item.id] ? ' checked' : '';
				html += '<li class="jws-mb__pick' + (here ? ' is-disabled' : '') + '">' +
					'<label><input type="checkbox" value="' + esc(item.id) + '"' + checked + (here ? ' disabled' : '') + '>' +
					'<span class="jws-mb__ep-thumb">' + (item.thumb ? '<img src="' + esc(item.thumb) + '" alt="" loading="lazy">' : '<span class="dashicons dashicons-format-video"></span>') + '</span>' +
					'<span class="jws-mb__ep-info"><strong>' + esc(item.title || t.untitled) + '</strong><small>' + esc([item.meta, '#' + item.id].filter(Boolean).join(' · ')) + '</small></span>' +
					(item.status !== 'publish' ? '<span class="jws-mb__status jws-mb__status--' + esc(item.status) + '">' + esc(item.status) + '</span>' : '') +
					note + '</label></li>';
				$pickList.data('item-' + item.id, item);
			});
			$pickList.html(html || '<li class="jws-mb__pick-empty">' + esc(t.noResults) + '</li>');
			syncSelectAll();
		}

		function syncSelectAll() {
			var $boxes = $pickList.find('input:not(:disabled)');
			$modal.find('.jws-mb__picker-all').prop('checked', $boxes.length > 0 && $boxes.filter(':checked').length === $boxes.length);
		}

		function toggleItem(id, on) {
			id = Number(id);
			if (on && !state.selected[id]) {
				state.selected[id] = $pickList.data('item-' + id);
				state.order.push(id);
			} else if (!on && state.selected[id]) {
				delete state.selected[id];
				state.order = state.order.filter(function (x) { return x !== id; });
			}
		}

		$pickList.on('change', 'input', function () {
			toggleItem(this.value, this.checked);
			syncSelectAll();
			updateSubmit();
		});

		// Shift-click selects a range.
		var lastIndex = null;
		$pickList.on('click', 'input', function (e) {
			var $boxes = $pickList.find('input');
			var idx = $boxes.index(this);
			if (e.shiftKey && lastIndex !== null) {
				var on = this.checked;
				$boxes.slice(Math.min(idx, lastIndex), Math.max(idx, lastIndex) + 1).not(':disabled').each(function () {
					this.checked = on;
					toggleItem(this.value, on);
				});
				syncSelectAll();
				updateSubmit();
			}
			lastIndex = idx;
		});

		$modal.on('change', '.jws-mb__picker-all', function () {
			var on = this.checked;
			$pickList.find('input:not(:disabled)').each(function () {
				this.checked = on;
				toggleItem(this.value, on);
			});
			updateSubmit();
		});

		$modal.on('input', '.jws-mb__picker-search', debounce(function () { state.page = 1; load(); }, 300));
		$modal.on('change', '.jws-mb__picker-scope, .jws-mb__picker-order', function () { state.page = 1; load(); });
		$modal.on('click', '.jws-mb__picker-prev', function () { state.page--; load(); });
		$modal.on('click', '.jws-mb__picker-next', function () { state.page++; load(); });
		$modal.on('click', '.jws-mb__modal-tabs button', function () {
			setMode($(this).data('mode'));
			if (state.mode === 'pick') {
				load();
			}
		});
		$modal.on('click', '.jws-mb__modal-close, .jws-mb__modal-backdrop', closeModal);
		$modal.on('keydown', function (e) {
			if (e.key === 'Escape') {
				closeModal();
			}
			if (e.key === 'Enter' && $(e.target).is('input')) {
				e.preventDefault();
			}
		});

		/* ---- quick create ---------------------------------------------- */

		function pad(n, width) {
			n = String(n);
			return n.length >= width ? n : new Array(width - n.length + 1).join('0') + n;
		}

		function createItems() {
			if (!state.season) {
				return [];
			}
			var seasonNo = $list.children().index(state.season) + 1;
			var startAt = state.season.find('.jws-mb__episode').length;
			var show = $.trim($('#title').val());
			var items = [];

			if ($modal.find('input[name="jws_mb_create_mode"]:checked').val() === 'list') {
				$modal.find('.jws-mb__create-titles').val().split(/\r?\n/).forEach(function (line) {
					line = $.trim(line);
					if (line) {
						items.push({ title: line, number: String(startAt + items.length + 1) });
					}
				});
			} else {
				var from = parseInt($modal.find('.jws-mb__create-from').val(), 10) || 1;
				var to = parseInt($modal.find('.jws-mb__create-to').val(), 10) || from;
				var pattern = $modal.find('.jws-mb__create-pattern').val() || '{n}';
				var width = Math.max(2, String(to).length);
				for (var n = from; n <= to && items.length < 200; n++) {
					items.push({
						title: pattern
							.replace(/\{show\}/g, show)
							.replace(/\{season\}/g, pad(seasonNo, 2))
							.replace(/\{nn\}/g, pad(n, width))
							.replace(/\{n\}/g, n)
							.replace(/^\s*-\s*/, ''),
						number: String(n)
					});
				}
			}
			if (!$modal.find('.jws-mb__create-number').is(':checked')) {
				items.forEach(function (i) { i.number = ''; });
			}
			return items;
		}

		function updateCreatePreview() {
			var items = createItems();
			var preview = items.slice(0, 3).map(function (i) { return i.title; }).join(', ');
			if (items.length > 3) {
				preview += ' … ' + items[items.length - 1].title;
			}
			$modal.find('.jws-mb__create-preview').text(items.length ? fmt(t.episodes, items.length) + ': ' + preview : '');
			updateSubmit();
		}

		$modal.on('input change', '[data-pane="create"] :input', function () {
			var list = $modal.find('input[name="jws_mb_create_mode"]:checked').val() === 'list';
			$modal.find('.jws-mb__create-range').prop('hidden', list);
			$modal.find('.jws-mb__create-list').prop('hidden', !list);
			updateCreatePreview();
		});

		$submit.on('click', function () {
			if (state.mode === 'pick') {
				addEpisodes(state.season, state.order.map(function (id) { return state.selected[id]; }).filter(Boolean));
				closeModal();
				return;
			}
			var items = createItems();
			if (!items.length) {
				return;
			}
			$submit.prop('disabled', true);
			$msg.text(t.loading);
			ajax('jws_mb_create_posts', {
				post_type: $wrap.data('post-type'),
				parent: cfg.postId,
				status: $modal.find('.jws-mb__create-status').val(),
				thumb: $modal.find('.jws-mb__create-thumb').is(':checked') ? 1 : 0,
				items: items
			}).done(function (res) {
				if (res && res.success) {
					addEpisodes(state.season, res.data.items);
					closeModal();
					showNotice(fmt(t.created, res.data.items.length));
				} else {
					$msg.text((res && res.data && res.data.message) || 'Error');
				}
			}).fail(function (xhr) {
				$msg.text((xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || 'Error');
			}).always(function () {
				updateSubmit();
			});
		});

		function showNotice(text) {
			var $n = $('<div class="jws-mb__toast"><span class="dashicons dashicons-yes-alt"></span></div>').append(document.createTextNode(text)).appendTo(document.body);
			requestAnimationFrame(function () { $n.addClass('is-in'); });
			setTimeout(function () { $n.addClass('is-out'); }, 2500);
			setTimeout(function () { $n.remove(); }, 3000);
		}

		$wrap.on('click', '.jws-mb__episodes-open', function () {
			openModal($(this).closest('.jws-mb__season'));
		});

		refresh();
	}

	/* ------------------------------------------------------------------ */
	/* Episode → season assignment (side box)                             */
	/* ------------------------------------------------------------------ */

	function initAssign($box) {
		var $wrap = $box.find('.jws-mb__assign');
		if (!$wrap.length) {
			return;
		}
		var $picker = $box.find('.jws-mb__posts');
		var $select = $wrap.find('.jws-mb__assign-season');
		var $name = $wrap.find('.jws-mb__assign-name');
		var newName = '';
		var req;

		function reset() {
			$select.find('option:not(:first)').remove();
			$select.val('');
			$name.prop('hidden', true).val('');
		}

		$picker.on('jws-mb:change', function (e, ids) {
			reset();
			if (req) {
				req.abort();
			}
			if (!ids.length) {
				$wrap.prop('hidden', true);
				return;
			}
			$wrap.prop('hidden', false);
			$select.prop('disabled', true);
			req = ajax('jws_mb_show_seasons', { show: ids[0], episode: cfg.postId }).done(function (res) {
				if (!res || !res.success) {
					return;
				}
				newName = res.data.newName;
				res.data.seasons.forEach(function (s) {
					var label = s.label + ' (' + fmt(t.episodes, s.count) + ')' + (s.contains ? ' ✓' : '');
					$('<option>').val(s.index).text(label).prop('disabled', s.contains).appendTo($select);
				});
				$('<option>').val('new').text('+ ' + (t.newSeason || 'New season')).appendTo($select);
				var $first = $select.find('option:not(:disabled)').eq(1);
				$select.val($first.length ? $first.val() : 'new').trigger('change');
			}).always(function () {
				$select.prop('disabled', false);
			});
		});

		$select.on('change', function () {
			var isNew = $select.val() === 'new';
			$name.prop('hidden', !isNew);
			if (isNew && !$name.val()) {
				$name.val(newName);
			}
		});
	}

	/* ------------------------------------------------------------------ */
	/* Drama episodes tab                                                 */
	/* ------------------------------------------------------------------ */

	function initDrama($wrap) {
		var $list = $wrap.find('.jws-mb__dep-list');
		var free = Number($wrap.data('free')) || 0;
		var price = Number($wrap.data('price')) || 0;
		var reordered = false;

		function refresh() {
			var n = 0;
			$list.children('.jws-mb__dep').each(function () {
				var $row = $(this);
				var removed = $row.find('.jws-mb__dep-remove input').is(':checked');
				var number = Number($row.data('number')) || 0;
				if (!removed) {
					n++;
					if (reordered) {
						number = n;
					}
				}
				$row.find('.jws-mb__ep-no').text(removed ? '–' : number);
				var isFree = $row.find('.jws-mb__dep-free input[type="checkbox"]').is(':checked') || (number > 0 && number <= free);
				$row.find('.jws-mb__dep-access')
					.text(isFree ? $wrap.data('i18n-free') : fmt($wrap.data('i18n-coins'), price))
					.toggleClass('jws-mb__pill--here', isFree);
			});
			$wrap.find('.jws-mb__dep-total').text(n);
			$wrap.find('.jws-mb__dep-empty').prop('hidden', $list.children().length > 0);
			$wrap.find('.jws-mb__dep-hint').prop('hidden', !reordered);
			$wrap.find('.jws-mb__dep-reordered').val(reordered ? 1 : 0);
		}

		$list.sortable({
			handle: '.jws-mb__handle',
			items: '> .jws-mb__dep',
			tolerance: 'pointer',
			placeholder: 'jws-mb__placeholder',
			forcePlaceholderSize: true,
			update: function () { reordered = true; refresh(); }
		});

		$wrap.on('change', '.jws-mb__dep input[type="checkbox"]', refresh);

		$wrap.on('click', '.jws-mb__dep-create-toggle', function () {
			var $form = $wrap.find('.jws-mb__dep-create');
			$form.prop('hidden', !$form.prop('hidden'));
		});

		$wrap.on('keydown', '.jws-mb__dep-create input', function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				$wrap.find('.jws-mb__dep-create-go').trigger('click');
			}
		});

		$wrap.on('click', '.jws-mb__dep-create-go', function () {
			var $btn = $(this);
			var $msg = $wrap.find('.jws-mb__dep-msg');
			$btn.prop('disabled', true);
			$msg.text(t.loading).removeClass('is-error');
			ajax('jws_mb_drama_create', {
				drama: cfg.postId,
				from: $wrap.find('.jws-mb__dep-from').val(),
				to: $wrap.find('.jws-mb__dep-to').val(),
				pattern: $wrap.find('.jws-mb__dep-pattern').val(),
				publish: $wrap.find('.jws-mb__dep-publish').is(':checked') ? 1 : 0
			}).done(function (res) {
				if (!res || !res.success) {
					$msg.text((res && res.data && res.data.message) || 'Error').addClass('is-error');
					return;
				}
				res.data.rows.forEach(function (row) {
					var $row = animateIn($(row.html).addClass('is-new'));
					// Keep running order: insert before the first row with a higher number.
					var $after = $list.children('.jws-mb__dep').filter(function () {
						return Number($(this).data('number')) > row.number;
					}).first();
					if ($after.length) {
						$row.insertBefore($after);
					} else {
						$list.append($row);
					}
				});
				var last = res.data.rows.length ? res.data.rows[res.data.rows.length - 1].number : Number($wrap.find('.jws-mb__dep-to').val());
				var span = Number($wrap.find('.jws-mb__dep-to').val()) - Number($wrap.find('.jws-mb__dep-from').val());
				$wrap.find('.jws-mb__dep-from').val(last + 1);
				$wrap.find('.jws-mb__dep-to').val(last + 1 + span);
				$msg.text(res.data.message);
				refresh();
			}).fail(function (xhr) {
				$msg.text((xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || 'Error').addClass('is-error');
			}).always(function () {
				$btn.prop('disabled', false);
			});
		});

		refresh();
	}

	/* ------------------------------------------------------------------ */
	/* Boot                                                               */
	/* ------------------------------------------------------------------ */

	function initScope($scope) {
		$scope.find('.jws-mb__posts, .jws-mb__terms').each(function () { initPicker($(this)); });
		$scope.find('.jws-mb__gallery').each(function () { initGallery($(this)); });
		$scope.find('.jws-mb__tax').each(function () { initTax($(this)); });
		$scope.find('.jws-mb__tags').each(function () { initTags($(this)); });
		if ($.fn.wpColorPicker) {
			$scope.find('input.jws-mb__color').each(function () {
				var $input = $(this);
				if ($input.closest('.wp-picker-container').length) {
					return;
				}
				$input.wpColorPicker({
					change: function () { setTimeout(function () { $input.trigger('input'); }, 0); },
					clear: function () { $input.trigger('input'); }
				});
			});
		}
		$scope.find('.jws-mb__repeater').each(function () {
			if (!$(this).data('ready')) {
				$(this).data('ready', 1);
				initRepeater($(this));
			}
		});
		applyConditions($scope);
	}

	/* Fields tied to post formats (blog posts): classic radio or the block editor store. */
	function currentFormat() {
		var data = window.wp && wp.data && wp.data.select && wp.data.select('core/editor');
		if (data && data.getEditedPostAttribute) {
			return data.getEditedPostAttribute('format') || 'standard';
		}
		var $checked = $('input[name="post_format"]:checked');
		return $checked.length && $checked.val() !== '0' ? $checked.val() : 'standard';
	}

	function applyFormats() {
		var format = currentFormat();
		$('.jws-mb').each(function () {
			var $box = $(this);
			var $fields = $box.find('.jws-mb__field[data-formats]');
			if (!$fields.length) {
				return;
			}
			$fields.each(function () {
				$(this).toggleClass('is-format-hidden', String($(this).data('formats')).split(',').indexOf(format) === -1);
			});
			$box.find('.jws-mb__format-empty').prop('hidden', $fields.not('.is-format-hidden').length > 0);
		});
	}

	$(function () {
		if (!$('.jws-mb__field[data-formats]').length) {
			return;
		}
		$(document).on('change', 'input[name="post_format"]', applyFormats);
		if (window.wp && wp.data && wp.data.subscribe) {
			var last = null;
			wp.data.subscribe(function () {
				var f = currentFormat();
				if (f !== last) {
					last = f;
					applyFormats();
				}
			});
		}
		applyFormats();
	});

	/* The taxonomy "Add New" form is submitted over ajax and only resets plain inputs. */
	$(document).ajaxSuccess(function (e, xhr, settings) {
		if (!settings || typeof settings.data !== 'string' || settings.data.indexOf('action=add-tag') === -1) {
			return;
		}
		if ($('#ajax-response .error, #ajax-response .notice-error').length) {
			return;
		}
		var $form = $('#addtag');
		$form.find('.jws-mb__chips').empty();
		$form.find('.jws-mb__gallery-list').empty();
		$form.find('.jws-mb__media').each(function () { clearMedia($(this)); });
		$form.find('input.jws-mb__color').each(function () {
			if ($.fn.wpColorPicker) {
				$(this).wpColorPicker('color', '');
			}
			$(this).val('');
		});
		$form.find('.jws-mb select').each(function () { this.selectedIndex = 0; });
		$form.find('.jws-mb input[type="checkbox"]').prop('checked', false);
		applyConditions($form);
	});

	$(function () {
		$('.jws-mb').each(function () {
			var $box = $(this);
			if ($box.hasClass('jws-mb--tabbed')) {
				initTabs($box);
			}
			$box.find('.jws-mb__seasons').each(function () { initSeasons($(this)); });
			initScope($box);
			initAssign($box);
			$box.find('.jws-mb__drama').each(function () { initDrama($(this)); });

			$box.on('change input', ':input', function () {
				applyConditions($box);
				var $rep = $(this).closest('.jws-mb__repeater');
				if ($rep.length) {
					refreshRepeater($rep);
				}
			});

			$box.on('click', '.jws-mb__copy-btn', function () {
				var $btn = $(this);
				var $input = $btn.siblings('input');
				$input.trigger('select');
				var done = function () {
					$btn.addClass('is-copied');
					setTimeout(function () { $btn.removeClass('is-copied'); }, 1200);
				};
				if (navigator.clipboard && window.isSecureContext) {
					navigator.clipboard.writeText($input.val()).then(done);
				} else {
					document.execCommand('copy');
					done();
				}
			});

			$box.on('click', '.jws-mb__media-pick', function () {
				openMedia($(this).closest('.jws-mb__media'));
			});
			$box.on('click', '.jws-mb__media-clear', function () {
				clearMedia($(this).closest('.jws-mb__media'));
			});

			$box.on('click', '.jws-mb__add-row', function () {
				var $rep = $(this).closest('.jws-mb__repeater');
				var $row = $($rep.find('> .jws-mb__tpl').html().replace(/__ROW__/g, uid()));
				$rep.find('> .jws-mb__rows').append(animateIn($row));
				initScope($row);
				refreshRepeater($rep);
				$row.find('input[type="search"], input[type="text"]').first().trigger('focus');
			});
			$box.on('click', '.jws-mb__row-remove', function () {
				var $rep = $(this).closest('.jws-mb__repeater');
				if (window.confirm(t.confirmRemove)) {
					var $row = $(this).closest('.jws-mb__row');
					$row.find(':input').prop('disabled', true);
					animateOut($row, function () { refreshRepeater($rep); });
				}
			});
			$box.on('click', '.jws-mb__row-toggle', function () {
				setCollapsed($(this).closest('.jws-mb__row'));
			});
			$box.on('click', '.jws-mb__chip-remove', function () {
				var $rep = $(this).closest('.jws-mb__repeater');
				setTimeout(function () { if ($rep.length) { refreshRepeater($rep); } }, 0);
			});
			$box.on('keydown', 'input[type="text"], input[type="number"]', function (e) {
				if (e.key === 'Enter' && $(this).closest('.jws-mb__row, .jws-mb__season').length) {
					e.preventDefault();
				}
			});
		});
	});
})(jQuery);
