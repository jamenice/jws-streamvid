/**
 * Short drama watch screen.
 *
 * Episode switching swaps the stage over AJAX rather than navigating: these are
 * two-minute episodes and a full reload would throw away the header, the panel
 * and every module on the page between each one.
 *
 * The player itself is handled by jws_player_v10.js, which watches the DOM and
 * initialises whatever markup turns up — so replacing the stage is all this file
 * has to do.
 *
 */
(function ($) {
    'use strict';

    $(function () {

        var $page = $('.sv-short-page');

        /* No early return: the buy panel at the foot of this file also opens
           from the Coins tab in the account area, where there is no watch
           screen. Every handler below is bound to $page, which is an empty set
           there, so they cost nothing and never fire. */

        /* ------------------------------------------------------------------ */
        /* Header height                                                       */
        /* ------------------------------------------------------------------ */

        /* .sv-short-page fills what's left under the header via
           calc(100vh - var(--sv-header-h)), because the header's real height
           varies with the chosen header template, the admin bar and
           responsive breakpoints. Measuring it here beats hard-coding a
           number that only matches one theme setup. */
        if ($page.length) {
            (function () {
                var $header = $('.site-header').first();

                function updateHeaderHeight() {
                    var h = $header.length
                        ? Math.ceil($header.offset().top + $header.outerHeight())
                        : 90;
                    document.documentElement.style.setProperty('--sv-header-h', h + 'px');
                }

                updateHeaderHeight();
                $(window).on('resize load', updateHeaderHeight);
            })();
        }

        /* ------------------------------------------------------------------ */
        /* Episode range tabs                                                  */
        /* ------------------------------------------------------------------ */

        $page.on('click', '.sv-short-range', function () {

            var range = $(this).data('range');

            $page.find('.sv-short-range').removeClass('active');
            $(this).addClass('active');

            $page.find('.sv-short-episode-grid').each(function () {
                // .hidden is what the server rendered with; keep using it so the
                // two agree and nothing flashes on load.
                this.hidden = String($(this).data('range')) !== String(range);
            });
        });

        /* ------------------------------------------------------------------ */
        /* Episode switching                                                   */
        /* ------------------------------------------------------------------ */

        var loading = false;

        /* The stage for the episode either side of the one playing, fetched
           while it plays so that prev/next lands on markup that is already
           here. These are two-minute episodes: the wait to start the next one
           is most of what the viewer actually feels.

           An entry is kept only while that episode is still a neighbour. The
           markup bakes in the resume position the server had when it was
           fetched, so one that outlives its neighbourhood would drop the
           viewer back at a point they have since moved past. */
        var prefetched  = {};
        var prefetching = {};
        var prefetchWait = null;

        /* Long enough that the episode on screen has the connection to itself
           while it fills its own buffer. */
        var PREFETCH_DELAY = 800;

        /* How long a card takes to travel a full stage height. Must match the
           transition on .sv-is-leaving / .sv-is-entering in drama.css: it is
           what the outgoing card is removed on. */
        var SLIDE_MS = 320;

        var slideTimer = null;

        function markActive(episodeId) {

            $page.find('.sv-short-episode').each(function () {

                var $chip = $(this);
                var isNow = String($chip.data('episode')) === String(episodeId);

                if (isNow === $chip.hasClass('active')) {
                    return;
                }

                $chip.toggleClass('active', isNow);

                if (isNow) {
                    // The number is replaced by the equaliser bars, and vice versa.
                    $chip.html('<span class="sv-short-playing"><span></span><span></span><span></span></span>');
                } else {
                    // data-ep-number is rendered by PHP, so this doesn't depend on
                    // having captured the chip's text before it became active —
                    // the chip that was already active on page load never went
                    // through that step and would otherwise come back empty.
                    var lock = $chip.hasClass('locked')
                        ? '<i class="jws-icon-lock-key-fill sv-short-lock"></i>' : '';
                    $chip.html($chip.data('epNumber') + lock);
                }
            });
        }

        function openRangeOf(episodeId) {

            var $chip = $page.find('.sv-short-episode[data-episode="' + episodeId + '"]');
            var range = $chip.closest('.sv-short-episode-grid').data('range');

            if (typeof range === 'undefined') {
                return;
            }

            $page.find('.sv-short-range').each(function () {
                $(this).toggleClass('active', String($(this).data('range')) === String(range));
            });

            $page.find('.sv-short-episode-grid').each(function () {
                this.hidden = String($(this).data('range')) !== String(range);
            });
        }

        /**
         * @param {number|string} episodeId
         * @param {boolean} isPrefetch Whether this is a neighbour being warmed
         *   rather than an episode the viewer opened — the server needs to know,
         *   because rendering a stage is what moves the drama's resume point.
         */
        function fetchEpisode(episodeId, isPrefetch) {

            return $.ajax({
                url: jwsDrama.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'jws_drama_episode',
                    episode_id: episodeId,
                    prefetch: isPrefetch ? 1 : 0
                }
            });
        }

        /**
         * Which way the two cards should travel, worked out from the episode
         * numbers rather than from what was clicked — so jumping to episode 30
         * from the list slides the same way as pressing next thirty times, and
         * so back/forward moves the way the viewer originally came.
         *
         * @param {Object} data The episode response.
         * @returns {number} 1 to move towards a later episode, -1 towards an
         *   earlier one, 0 when there is nothing to compare against.
         */
        function slideDirection(data) {

            var from = parseInt($page.find('.sv-short-episode.active').data('epNumber'), 10);
            var to   = parseInt(data.number, 10);

            if (!from || !to || from === to) {
                return 0;
            }

            return to > from ? 1 : -1;
        }

        /**
         * Drops whatever a slide still had in the air: the card that was on its
         * way out, and the transition class on the one that stayed.
         *
         * Called before starting another so that a fast run of swipes never
         * stacks cards up, and so the drag gesture below is never handed a card
         * that still eases towards the finger instead of tracking it.
         */
        function endSlide() {

            clearTimeout(slideTimer);

            var $stage = $page.find('.sv-short-stage');

            $stage.children('.sv-short-player.sv-is-leaving').remove();

            /* .sv-is-entering is what carries the transition; left on, it would
               have the drag gesture below ease towards the finger instead of
               tracking it. Any inline transform is deliberately left alone — a
               card the finger has just let go of is exactly what the slide
               about to start needs to carry on from. */
            $stage.children('.sv-short-player').removeClass('sv-is-entering');
        }

        /**
         * Puts the next episode's card in place, sliding it past the one it
         * replaces.
         *
         * @param {Object} data The episode response.
         */
        function swapStage(data) {

            var $stage = $page.find('.sv-short-stage');

            endSlide();

            var $outgoing = $stage.children('.sv-short-player');
            var $incoming = $(data.stage);
            var $card     = $incoming.filter('.sv-short-player');
            var direction = slideDirection(data);

            /* Everything but the card being replaced goes now — the controls
               carry the new prev/next, and the card is the only part worth
               animating. Removing the old <video-player> is what lets the
               MutationObserver in jws_player_v10.js pick the new one up; the
               old one's save timer stops itself once it is detached. */
            $stage.children().not($outgoing).remove();
            $stage.removeClass('is-loading');

            if (!direction || !$outgoing.length || !$card.length) {
                $outgoing.remove();
                $stage.append($incoming);
                return;
            }

            /* The outgoing card stays on screen for the length of the slide,
               and a video still playing behind the new one is heard as well as
               seen. */
            $outgoing.find('video, hlsjs-video, youtube-video, vimeo-video').each(function () {
                try {
                    this.muted = true;
                    this.pause();
                } catch (e) { /* a source that never got as far as playing */ }
            });

            /* Set while the card is still detached, so it is already off stage
               the first time it is styled and has nothing to transition from.

               110% of the card, not 100%: the percentage is of the card's own
               height, which is a shade under the stage's once the desktop
               max-height caps it, and the last thing wanted is the top of the
               next episode already peeking in before the slide starts. */
            $card.css('transform', 'translateY(' + (direction > 0 ? 110 : -110) + '%)');

            $outgoing.addClass('sv-is-leaving');
            $stage.append($incoming);

            /* Forces both start positions to be computed before they change —
               including a card the finger dragged part of the way, which then
               carries on from where it was let go rather than jumping back. */
            $card[0].offsetHeight; // eslint-disable-line no-unused-expressions

            $card.addClass('sv-is-entering').css('transform', '');
            $outgoing.css('transform', 'translateY(' + (direction > 0 ? -110 : 110) + '%)');

            slideTimer = setTimeout(endSlide, SLIDE_MS);
        }

        function applyEpisode(data, push) {

            swapStage(data);

            $page.find('.sv-short-title').text(data.title);
            $page.find('.sv-short-breadcrumbs .jws-breadcrumbs__item--current .jws-breadcrumbs__text').text(data.crumb);

            markActive(data.episodeId);
            openRangeOf(data.episodeId);

            // On mobile the panel is a sheet over the stage; picking an
            // episode from it should hand the screen back to the video
            // instead of leaving the sheet open over the new episode.
            setPanelOpen(false);

            if (push && window.history && window.history.pushState) {
                window.history.pushState({ jwsDramaEpisode: data.episodeId }, data.title, data.permalink);
            }

            document.title = data.title;

            $(document.body).trigger('jws_drama_episode_changed', [data]);

            // Neighbours have moved along with the episode.
            prefetchNeighbours();
        }

        function loadEpisode(episodeId, push) {

            if (loading || !episodeId) {
                return;
            }

            var ready = prefetched[episodeId];

            if (ready) {
                /* Already here: swap it straight in, with none of the dim —
                   there is nothing to wait for. */
                delete prefetched[episodeId];
                applyEpisode(ready, push);
                return;
            }

            loading = true;

            var $stage = $page.find('.sv-short-stage');

            $stage.addClass('is-loading');

            fetchEpisode(episodeId, false).done(function (response) {

                if (!response || !response.success) {
                    // Nothing sensible to show in place — let the browser do it.
                    window.location.href = $page.find('.sv-short-episode[data-episode="' + episodeId + '"]').attr('href');
                    return;
                }

                applyEpisode(response.data, push);

            }).fail(function () {
                var href = $page.find('.sv-short-episode[data-episode="' + episodeId + '"]').attr('href');
                if (href) { window.location.href = href; }
            }).always(function () {
                loading = false;
                $stage.removeClass('is-loading');
            });
        }

        /**
         * The prev/next episode ids, read off the stage's own controls — the
         * same source the arrows, the swipe and the keyboard already go by, so
         * this follows whatever the server last rendered, the ends of the
         * series included.
         *
         * @returns {string[]} One id, two, or none at all.
         */
        function neighbourIds() {

            var ids = [];

            $.each([-1, 1], function (index, direction) {

                var id = navLink(direction).data('episode');

                if (id) {
                    ids.push(String(id));
                }
            });

            return ids;
        }

        /** Warms the episode either side of the one now playing. */
        function prefetchNeighbours() {

            clearTimeout(prefetchWait);

            var wanted = neighbourIds();

            $.each(prefetched, function (id) {
                if ($.inArray(String(id), wanted) === -1) {
                    delete prefetched[id];
                }
            });

            /* Nobody on a metered connection asked to download two episodes
               they may never open. */
            var link = navigator.connection;

            if (!wanted.length || (link && (link.saveData || /2g$/.test(link.effectiveType || '')))) {
                return;
            }

            prefetchWait = setTimeout(function () {

                $.each(wanted, function (index, id) {

                    if (prefetched[id] || prefetching[id]) {
                        return;
                    }

                    prefetching[id] = true;

                    fetchEpisode(id, true).done(function (response) {

                        /* The viewer can have jumped somewhere else entirely
                           while this was in flight, in which case the prune
                           above has already been and gone — storing it now
                           would put back the stale entry it just removed. */
                        if (response && response.success && $.inArray(id, neighbourIds()) !== -1) {
                            prefetched[id] = response.data;
                            warmMedia(response.data.stage);
                        }

                    }).always(function () {
                        delete prefetching[id];
                    });
                });

            }, PREFETCH_DELAY);
        }

        /* ------------------------------------------------------------------ */
        /* Media warm-up                                                       */
        /* ------------------------------------------------------------------ */

        var warmed       = {};
        var preconnected = {};
        var $mediaProbes = null;

        /**
         * Opens the connection the next episode's video will need, and pulls in
         * the first thing that will be asked for over it.
         *
         * Having the stage markup ready only removes the admin-ajax wait. The
         * video still starts from a cold CDN connection, which on a phone is
         * the larger half of the gap between pressing next and seeing a frame.
         *
         * @param {string} stageHtml The stage exactly as the server rendered it.
         */
        function warmMedia(stageHtml) {

            /* Parsed detached: a <video-player> only upgrades once it is in the
               document, so reading it here starts no second player. */
            var raw = $('<div>').html(stageHtml).find('[data-jws-v10]').attr('data-jws-v10');
            var config;

            try {
                config = raw ? JSON.parse(raw) : null;
            } catch (e) {
                return;
            }

            // A locked episode has no player and so nothing to warm.
            if (!config || !config.src || warmed[config.src]) {
                return;
            }

            warmed[config.src] = true;

            var url;

            try {
                // Resolves the protocol-relative "//host/..." that the Bunny and
                // Cloudflare branches of the player template emit.
                url = new URL(config.src, window.location.href);
            } catch (e) {
                return;
            }

            if (url.origin !== window.location.origin && !preconnected[url.origin]) {
                preconnected[url.origin] = true;
                $('<link rel="preconnect" crossorigin>').attr('href', url.origin).appendTo('head');
            }

            if (config.type === 'application/x-mpegURL') {
                /* The playlist is the first thing hls.js asks for and is a
                   couple of kilobytes, so it is worth having in the HTTP cache.
                   Which rendition follows is its decision, not ours — the
                   segments are left to it. */
                if (window.fetch) {
                    window.fetch(url.href, { credentials: 'omit', mode: 'cors' }).catch(function () { });
                }
                return;
            }

            /* A progressive file: metadata only. "auto" would let the browser
               buffer as far ahead as it likes into an episode that may never be
               opened. */
            if (!$mediaProbes) {
                $mediaProbes = $('<div aria-hidden="true" style="display:none"></div>').appendTo(document.body);
            }

            $mediaProbes
                .append($('<video muted playsinline preload="metadata"></video>').attr('src', url.href))
                .children().slice(0, -2).remove();
        }

        // Episode chips and the up/down arrows both carry data-episode.
        $page.on('click', '.sv-short-episode, .sv-short-nav', function (event) {

            // Leave modified clicks alone so "open in new tab" still works.
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.which > 1) {
                return;
            }

            var episodeId = $(this).data('episode');

            if (!episodeId) {
                return;
            }

            event.preventDefault();

            if ($(this).hasClass('active')) {
                return;
            }

            // A locked chip: just switch to that episode's lock panel (title,
            // breadcrumb and URL all follow) — spending coins is only ever
            // done from an explicit Unlock button (.sv-short-unlock), never
            // as a side effect of picking an episode from the list.
            loadEpisode(episodeId, true);
        });

        // Back/forward should move between episodes, not reload the page.
        $(window).on('popstate', function (event) {

            var state = event.originalEvent && event.originalEvent.state;

            if (state && state.jwsDramaEpisode) {
                loadEpisode(state.jwsDramaEpisode, false);
            }
        });

        // The episode the page opened on has neighbours too.
        if ($page.length) {
            prefetchNeighbours();
        }

        /* ------------------------------------------------------------------ */
        /* Fullscreen                                                          */
        /* ------------------------------------------------------------------ */

        $page.on('click', '.sv-short-fullscreen', function () {

            /*
             * Fullscreen .sv-short-page, not just the stage: the Fullscreen API
             * only paints the requested element's own subtree, and .sv-short-panel
             * is a sibling of .sv-short-stage, not a descendant. Fullscreening the
             * stage alone would strand the panel outside the fullscreen element,
             * so the panel-toggle button could never show it while fullscreen is
             * active. .sv-short-page still keeps the player centred on black via
             * the same flex layout it already uses out of fullscreen.
             */
            var target = $page[0];

            if (!target) {
                return;
            }

            if (document.fullscreenElement || document.webkitFullscreenElement) {
                (document.exitFullscreen || document.webkitExitFullscreen).call(document);
                return;
            }

            (target.requestFullscreen || target.webkitRequestFullscreen).call(target);
        });

        /*
         * .jws-form-login-popup is printed once at wp_footer, as a sibling of
         * .sv-short-page rather than a descendant — so the moment fullscreen
         * is active, the same Fullscreen API containment described above
         * stops it from rendering at all. Its .open class still gets added
         * correctly (see openLoginPopup() below); it just never paints.
         * Reparenting it into the fullscreen element while one is active,
         * and back to <body> once it isn't, is what keeps "Sign In" working
         * from inside fullscreen without touching every place that opens it.
         */
        var $loginPopup = $('.jws-form-login-popup');

        $(document).on('fullscreenchange webkitfullscreenchange', function () {

            if (!$loginPopup.length) {
                return;
            }

            var fullscreenEl = document.fullscreenElement || document.webkitFullscreenElement;

            if (fullscreenEl === $page[0]) {
                $loginPopup.appendTo(fullscreenEl);
            } else if (!$loginPopup.parent().is('body')) {
                $loginPopup.appendTo(document.body);
            }
        });

        /* ------------------------------------------------------------------ */
        /* Stage controls: hidden until tapped, auto-hide after inactivity     */
        /* ------------------------------------------------------------------ */

        var stageControlsTimer = null;

        function showStageControls() {

            $page.find('.sv-short-stage-controls').addClass('is-visible');

            clearTimeout(stageControlsTimer);
            stageControlsTimer = setTimeout(function () {
                $page.find('.sv-short-stage-controls').removeClass('is-visible');
            }, 4000);
        }

        /* Delegated on .sv-short-stage rather than its buttons individually:
           a click on the fullscreen/nav/panel-toggle buttons bubbles up here
           too (none of their handlers call stopPropagation), so pressing one
           of them counts as "interacting" and restarts the same 4s timer
           instead of needing a second listener. */
        $page.on('click', '.sv-short-stage', function () {
            showStageControls();
        });

        /* ------------------------------------------------------------------ */
        /* Episode panel toggle (mobile: the panel is a fixed sheet over the   */
        /* stage, closed until asked for)                                     */
        /* ------------------------------------------------------------------ */

        function setPanelOpen(open) {

            $page.toggleClass('sv-panel-open', open);

            $page.find('.sv-short-panel-toggle')
                .toggleClass('is-active', open)
                .attr('aria-expanded', open ? 'true' : 'false');
        }

        $page.on('click', '.sv-short-panel-toggle', function () {
            setPanelOpen(!$page.hasClass('sv-panel-open'));
        });

        // A fixed sheet over the stage needs the usual ways out: tap
        // anywhere outside it, or press Escape.
        $(document).on('click', function (event) {

            if (!$page.hasClass('sv-panel-open')) {
                return;
            }

            if ($(event.target).closest('.sv-short-panel, .sv-short-panel-toggle').length) {
                return;
            }

            setPanelOpen(false);
        });

        $(document).on('keydown', function (event) {
            if (event.key === 'Escape' && $page.hasClass('sv-panel-open')) {
                setPanelOpen(false);
            }
        });

        /* The stage — controls included — is replaced wholesale on every
           episode switch, so the incoming markup always starts as the
           server rendered it: controls hidden, toggle button unsynced from
           .sv-panel-open. Bring both back in line with page state. */
        $(document.body).on('jws_drama_episode_changed', function () {
            showStageControls();
            setPanelOpen($page.hasClass('sv-panel-open'));
        });

        /* ------------------------------------------------------------------ */
        /* Keyboard: up / down move between episodes                           */
        /* ------------------------------------------------------------------ */

        /**
         * The prev/next slot in .sv-short-stage-controls is always rendered
         * (stage.php), as either an <a class="sv-short-nav"> when that
         * neighbour exists or a disabled <span> placeholder when it doesn't
         * — so the two slots always occupy positions 0 (prev) and 1 (next)
         * in that order. Selecting only "a.sv-short-nav" and indexing into
         * *that* filtered set instead would shift position whenever the prev
         * slot is a placeholder (e.g. on the first episode of a range): the
         * lone "next" link would land at index 0 and get read as "prev".
         *
         * @param {number} direction -1 for the previous episode, 1 for next.
         * @returns {jQuery} The link, or an empty set if that neighbour
         *   doesn't exist.
         */
        function navLink(direction) {

            return $page
                .find('.sv-short-stage-controls > .sv-short-stage-btn.sv-short-nav, .sv-short-stage-controls > .sv-short-stage-btn.is-disabled')
                .eq(direction < 0 ? 0 : 1)
                .filter('a.sv-short-nav');
        }

        $(document).on('keydown', function (event) {

            if (event.metaKey || event.ctrlKey || event.altKey) {
                return;
            }

            // Never steal keys from a field the viewer is typing in.
            var tag = (event.target.tagName || '').toLowerCase();

            if (tag === 'input' || tag === 'textarea' || tag === 'select' || event.target.isContentEditable) {
                return;
            }

            if (event.key !== 'ArrowUp' && event.key !== 'ArrowDown') {
                return;
            }

            var $link = navLink(event.key === 'ArrowUp' ? -1 : 1);

            if ($link.length) {
                event.preventDefault();
                loadEpisode($link.data('episode'), true);
            }
        });

        /* ------------------------------------------------------------------ */
        /* Touch: swipe up / down move between episodes (mobile)               */
        /* ------------------------------------------------------------------ */

        /* Vertical travel needed before a touch counts as a swipe rather than
           a tap, and how much horizontal drift is still allowed while doing
           so (a diagonal or mostly-horizontal drag — e.g. across the seek
           bar — should not be read as "next/previous episode"). */
        var SWIPE_MIN_Y  = 60;
        var SWIPE_MAX_X  = 80;
        var SWIPE_RESIST = 0.5;  // the card trails the finger, doesn't match it 1:1
        var SWIPE_CAP    = 120;  // px it can be dragged before it stops following

        var touchStartX   = 0;
        var touchStartY   = 0;
        var touchTracking = false;
        var $dragCard     = $();

        /* The card that is actually here, never one still sliding out. */
        function stagePlayer() {
            return $page.find('.sv-short-stage > .sv-short-player').not('.sv-is-leaving');
        }

        /* Plain transform write, no `transition` — this has to be
           instantaneous so the card tracks the finger 1:1 while dragging. */
        function dragOffset($el, y) {
            $el.css('transform', y ? 'translateY(' + y + 'px)' : '');
        }

        /* Eases a gesture that never became a swipe back into place, then drops
           the inline styles again so they don't linger and block the stage's
           own opacity transition (the dim while the next episode loads) on
           whatever ends up in this slot next.

           Only ever a snap back: a swipe that did land is carried off by
           swapStage(), in the same movement that brings the next card in. */
        function snapBack($el) {

            if (!$el.length) {
                return;
            }

            $el.css({ transition: 'transform 0.22s ease', transform: '' });

            setTimeout(function () {
                $el.css('transition', '');
            }, 220);
        }

        $page.on('touchstart', '.sv-short-stage', function (event) {

            var touch = event.originalEvent.touches[0];

            if (!touch || loading) {
                return;
            }

            touchStartX   = touch.clientX;
            touchStartY   = touch.clientY;
            touchTracking = true;
            $dragCard     = stagePlayer();
        });

        $page.on('touchmove', '.sv-short-stage', function (event) {

            if (!touchTracking || !$dragCard.length) {
                return;
            }

            var touch = event.originalEvent.touches[0];

            if (!touch) {
                return;
            }

            var deltaX = touch.clientX - touchStartX;
            var deltaY = touch.clientY - touchStartY;

            // A mostly-horizontal drag isn't this gesture — leave the card alone.
            if (Math.abs(deltaX) > Math.abs(deltaY)) {
                return;
            }

            // Rubber-band harder toward whichever edge has nowhere to go, the
            // same cue a native list gives once it's out of items to show.
            var hasNeighbour = navLink(deltaY < 0 ? 1 : -1).length > 0;
            var travel       = deltaY * SWIPE_RESIST * (hasNeighbour ? 1 : 0.3);

            dragOffset($dragCard, Math.max(-SWIPE_CAP, Math.min(SWIPE_CAP, travel)));
        });

        $page.on('touchend', '.sv-short-stage', function (event) {

            if (!touchTracking) {
                return;
            }

            touchTracking = false;

            var $card = $dragCard;

            $dragCard = $();

            var touch = event.originalEvent.changedTouches[0];

            if (!touch) {
                snapBack($card);
                return;
            }

            var deltaX = touch.clientX - touchStartX;
            var deltaY = touch.clientY - touchStartY;

            if (Math.abs(deltaY) < SWIPE_MIN_Y || Math.abs(deltaX) > SWIPE_MAX_X) {
                snapBack($card);
                return;
            }

            // Swipe up (finger moves toward the top, deltaY < 0) advances to
            // the next episode, mirroring every other short-video feed.
            var $link = navLink(deltaY < 0 ? 1 : -1);

            if (!$link.length) {
                snapBack($card);
                return;
            }

            /* The card is left exactly where the finger let go of it: the slide
               swapStage() is about to start picks it up from there and carries
               it the rest of the way out, so the gesture and the transition read
               as one movement rather than two that fight over the same
               transform. */
            loadEpisode($link.data('episode'), true);
        });

        $page.on('touchcancel', '.sv-short-stage', function () {
            touchTracking = false;
            snapBack($dragCard);
            $dragCard = $();
        });

        /* ------------------------------------------------------------------ */
        /* Wheel: scrolling over the stage moves between episodes (desktop)    */
        /* ------------------------------------------------------------------ */

        /* One flick of a trackpad is one episode, not the dozen its momentum
           tail would otherwise ask for. Travel accumulates until it is worth a
           switch, and the wheel then has to fall quiet before another can
           start — a wait that also covers the slide, so the next episode has
           settled before a second gesture can move off it. */
        var WHEEL_TRAVEL = 80;
        var WHEEL_QUIET  = 350;

        var wheelTravel = 0;
        var wheelSpent  = false;
        var wheelIdle   = null;

        /* deltaY is not always pixels: Firefox reports lines, and a page at a
           time is rarer still. Left unconverted, three lines of travel would
           never reach a threshold counted in pixels and the wheel would appear
           dead on those browsers. */
        function wheelPixels(wheel) {

            if (1 === wheel.deltaMode) {
                return wheel.deltaY * 16;
            }

            if (2 === wheel.deltaMode) {
                return wheel.deltaY * 400;
            }

            return wheel.deltaY;
        }

        $page.on('wheel', '.sv-short-stage', function (event) {

            var wheel = event.originalEvent;

            // A sideways or mostly-sideways gesture is not this one.
            if (!wheel.deltaY || Math.abs(wheel.deltaX) > Math.abs(wheel.deltaY)) {
                return;
            }

            /* Nothing that way — at either end of the series, hand the gesture
               back to the page rather than swallowing it into a stage that
               cannot answer it. */
            if (!navLink(wheel.deltaY > 0 ? 1 : -1).length) {
                return;
            }

            event.preventDefault();

            clearTimeout(wheelIdle);
            wheelIdle = setTimeout(function () {
                wheelTravel = 0;
                wheelSpent  = false;
            }, WHEEL_QUIET);

            if (wheelSpent) {
                return;
            }

            wheelTravel += wheelPixels(wheel);

            if (Math.abs(wheelTravel) < WHEEL_TRAVEL) {
                return;
            }

            // Scrolling down (deltaY > 0) goes on to the next episode.
            var $link = navLink(wheelTravel > 0 ? 1 : -1);

            wheelSpent  = true;
            wheelTravel = 0;

            if ($link.length) {
                loadEpisode($link.data('episode'), true);
            }
        });

        /* ------------------------------------------------------------------ */
        /* Coin wallet tabs (account area)                                     */
        /* ------------------------------------------------------------------ */

        /* Bound on document, not $page: the Coins tab is the account area,
           not the watch screen. */
        $(document).on('click', '.sv-coin-tab', function () {

            var $tab  = $(this);
            var name  = $tab.data('tab');
            var $tabs = $tab.closest('.sv-coin-tabs');

            if ($tab.hasClass('active')) {
                return;
            }

            $tabs.find('.sv-coin-tab').removeClass('active').attr('aria-selected', 'false');
            $tab.addClass('active').attr('aria-selected', 'true');

            $tabs.siblings('.sv-coin-tab-panel').each(function () {
                this.hidden = String($(this).data('tab-panel')) !== String(name);
            });
        });

        /* ------------------------------------------------------------------ */
        /* Unlock                                                              */
        /* ------------------------------------------------------------------ */

        function notify(message, type) {
            if (typeof jwsThemeModule !== 'undefined' && jwsThemeModule.show_notification) {
                jwsThemeModule.show_notification(message, type || 'success');
            }
        }

        /**
         * Opens the theme's login popup, and tells it where to come back to.
         *
         * Both forms in that popup post a `redirect` field; without one the
         * server falls back to the permalink of the queried object, which on
         * an admin-ajax request is nothing at all — so a sign-in started from
         * the watch screen lands on the home page instead of the episode the
         * viewer was trying to open. Filling the field is what the theme does
         * for its own PMPro "Login" link, and this is the same trick.
         *
         * @param {string} [returnUrl] Defaults to the page as it stands now.
         */
        function openLoginPopup(returnUrl) {

            var url = returnUrl || window.location.href;

            /* Both the login and the register form, so either route back. */
            $('.jws-form-login-popup form').each(function () {

                var $field = $(this).find('input[name="redirect"]');

                if ($field.length) {
                    $field.val(url);
                } else {
                    $(this).append($('<input type="hidden" name="redirect">').val(url));
                }
            });

            $('.jws-form-login-popup').addClass('open');
        }

        /*
         * Delegated, unlike the theme's own binding: the stage is replaced
         * wholesale on every episode switch, so a Sign In link that arrived
         * with it was never bound and clicking it did nothing at all.
         */
        $(document).on('click', '.sv-short-page .jws-open-login', function (event) {

            event.preventDefault();

            var href = $(this).attr('href');

            openLoginPopup(href && '#' !== href ? href : window.location.href);
        });

        function requireLogin() {

            if (jwsDrama.loggedIn) {
                return false;
            }

            notify(jwsDrama.i18n.signIn, 'error');
            openLoginPopup();

            return true;
        }

        $page.on('click', '.sv-short-unlock', function () {
            unlockEpisode($(this).data('episode'), $(this));
        });

        /**
         * Shared by the stage's own Unlock button and by clicking a locked
         * chip in the episode list.
         *
         * @param {number|string} episodeId
         * @param {jQuery} [$btn] Element to disable/spin while the request is
         *   in flight — the stage button or the chip that was clicked.
         */
        function unlockEpisode(episodeId, $btn) {

            if (requireLogin()) {
                return;
            }

            if (!episodeId || ($btn && $btn.prop('disabled'))) {
                return;
            }

            if ($btn) {
                $btn.prop('disabled', true).addClass('is-loading');
            }

            $.ajax({
                url: jwsDrama.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'jws_drama_unlock',
                    episode_id: episodeId,
                    nonce: jwsDrama.unlockNonce
                }
            }).done(function (response) {

                if (!response || !response.success) {

                    var reason = response && response.data && response.data.reason;

                    /* A locked episode with an empty wallet is the whole reason
                       the buy panel exists — a toast saying "not enough coins"
                       is a dead end. */
                    if (reason === 'insufficient_coins') {
                        buyPanel.open(episodeId);
                        return;
                    }

                    notify((response && response.data && response.data.message) || jwsDrama.i18n.failed, 'error');
                    return;
                }

                /* Any warmed copy of this episode is the lock panel, which is
                   no longer what it is. */
                delete prefetched[episodeId];

                // It is paid for now, so it stops being a locked chip.
                $page.find('.sv-short-episode[data-episode="' + episodeId + '"]').removeClass('locked')
                     .find('.sv-short-lock').remove();

                buyPanel.setBalance(response.data.balance);

                notify(response.data.message, 'success');
                $(document.body).trigger('jws_drama_episode_unlocked', [response.data]);

                var activeId = $page.find('.sv-short-episode.active').data('episode');

                if (String(activeId) === String(episodeId)) {
                    /* Swapping in the returned stage is what turns the lock
                       panel into a player; jws_player_v10.js takes it from
                       there. */
                    $page.find('.sv-short-stage').html(response.data.stage);
                } else {
                    /* Unlocked from the list rather than the stage itself —
                       the unlock response has no title/breadcrumb/permalink,
                       so go through the normal episode load to bring those
                       (and the "active" chip) in sync. */
                    loadEpisode(episodeId, true);
                }

            }).fail(function () {
                notify(jwsDrama.i18n.failed, 'error');
            }).always(function () {
                if ($btn) {
                    $btn.prop('disabled', false).removeClass('is-loading');
                }
            });
        }

        /* ------------------------------------------------------------------ */
        /* Buy panel                                                           */
        /* ------------------------------------------------------------------ */

        /**
         * One shelf, two doors: the lock panel on the watch screen and the Top
         * up button on the Coins tab both open this. The markup comes from the
         * server on every open rather than being cached, because the balance in
         * its header goes stale the moment anything is spent.
         */
        var buyPanel = (function () {

            var $host   = null;
            var loading = false;
            var busy    = false;

            function host() {

                if ($host) {
                    return $host;
                }

                $host = $('<div class="sv-buy-modal" hidden>'
                    + '<div class="sv-buy-backdrop"></div>'
                    + '<div class="sv-buy-panel"></div>'
                    + '</div>').appendTo(document.body);

                return $host;
            }

            function close() {

                if (!$host) {
                    return;
                }

                $host.attr('hidden', true).removeClass('is-open');
                $(document.body).removeClass('sv-buy-lock');
            }

            /*
             * .sv-buy-modal is built directly under <body> (see host()
             * above), so it has the same Fullscreen API containment problem
             * as the login and share popups: the browser only paints the
             * fullscreen element's own subtree, so opening this modal while
             * .sv-short-page is fullscreen adds .is-open correctly but
             * nothing appears, since the modal sits outside that subtree.
             * Keep it inside whichever element is fullscreen, moving it back
             * to <body> once nothing is.
             */
            function syncHostParent() {

                if (!$host) {
                    return;
                }

                var fullscreenEl = document.fullscreenElement || document.webkitFullscreenElement;

                if (fullscreenEl) {
                    $host.appendTo(fullscreenEl);
                } else if (!$host.parent().is('body')) {
                    $host.appendTo(document.body);
                }
            }

            $(document).on('fullscreenchange webkitfullscreenchange', syncHostParent);

            /*
             * Everything from here to payByWallet() is the module's own
             * Stripe/PayPal checkout. The panel stopped rendering gateway
             * buttons when coins moved to WooCommerce and VIP to PMPro, so
             * none of it runs on a normal buy any more — it is kept for the
             * subscriptions it already sold, whose webhooks still land in
             * class-drama-stripe-events.php and class-drama-paypal-events.php.
             */

            /* The method is chosen first and stays chosen; a card is the buy
               button. */
            function chosen() {
                return host().find('.sv-buy-gateway.is-selected:not([hidden])').first();
            }

            function select($btn) {

                if (!$btn || !$btn.length) {
                    return;
                }

                host().find('.sv-buy-gateway').removeClass('is-selected').attr('aria-checked', 'false');
                $btn.addClass('is-selected').attr('aria-checked', 'true');
            }

            /* Whatever is left standing after the wallet probe. */
            function selectDefault() {
                select(host().find('.sv-buy-gateway:not([hidden])').first());
            }

            /**
             * Stripe.js, fetched the first time a panel that needs it opens.
             *
             * Not enqueued with the page: most visits never reach the buy
             * panel, and this is a third-party request on every drama screen if
             * it goes in the footer.
             */
            var stripeReady = null;

            function stripeJs(key) {

                if (stripeReady) {
                    return stripeReady;
                }

                stripeReady = $.Deferred();

                if (window.Stripe) {
                    stripeReady.resolve(window.Stripe(key));
                    return stripeReady;
                }

                $.getScript('https://js.stripe.com/v3/')
                    .done(function () {
                        stripeReady.resolve(window.Stripe ? window.Stripe(key) : null);
                    })
                    .fail(function () {
                        stripeReady.resolve(null);
                    });

                return stripeReady;
            }

            /**
             * Asks the browser which wallets it can really open, and unhides
             * only those.
             *
             * Apple Pay and Google Pay are rendered hidden by the server, so a
             * shopper on a machine that cannot do either never sees a button
             * that would dead-end. Plain HTTP fails this on every browser,
             * which is why neither shows up in local development.
             */
            function probeWallets() {

                var $row     = host().find('.sv-buy-gateways');
                var $wallets = $row.find('.sv-buy-gateway[data-flow="wallet"]');
                var key      = $row.data('stripe-key');

                if (!$wallets.length || !key) {
                    selectDefault();
                    return;
                }

                stripeJs(key).done(function (stripe) {

                    if (!stripe) {
                        selectDefault();
                        return;
                    }

                    /* canMakePayment() needs a total; the real one is only
                       known once a card is clicked, so this is a placeholder
                       and the request is updated before it is ever shown. */
                    var request = stripe.paymentRequest({
                        country: String($row.data('stripe-country') || 'US'),
                        currency: String($row.data('currency') || 'USD').toLowerCase(),
                        total: { label: document.title, amount: 100 },
                        requestPayerEmail: true
                    });

                    request.canMakePayment().then(function (result) {

                        if (result) {
                            if (result.applePay) {
                                $row.find('[data-method="apple_pay"]').removeAttr('hidden');
                            }

                            if (result.googlePay) {
                                $row.find('[data-method="google_pay"]').removeAttr('hidden');
                            }
                        }

                        selectDefault();

                    }).catch(selectDefault);
                });
            }

            function error(message) {
                host().find('.sv-buy-error').text(message || '').attr('hidden', !message);
            }

            function open(episodeId) {

                if (requireLogin() || loading) {
                    return;
                }

                loading = true;

                $.ajax({
                    url: jwsDrama.ajax_url,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'jws_drama_purchase_panel',
                        episode_id: episodeId || 0,
                        nonce: jwsDrama.unlockNonce
                    }
                }).done(function (response) {

                    if (!response || !response.success) {
                        notify((response && response.data && response.data.message) || jwsDrama.i18n.failed, 'error');
                        return;
                    }

                    host().find('.sv-buy-panel').html(response.data.html);
                    syncHostParent();
                    host().removeAttr('hidden').addClass('is-open');
                    $(document.body).addClass('sv-buy-lock');

                    /* Only the legacy Stripe/PayPal panel prints gateway
                       buttons; the shop-backed one has none to probe. */
                    if (host().find('.sv-buy-gateways').length) {
                        probeWallets();
                    }

                }).fail(function () {
                    notify(jwsDrama.i18n.failed, 'error');
                }).always(function () {
                    loading = false;
                });
            }

            function checkout(order) {

                return $.ajax({
                    url: jwsDrama.ajax_url,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'jws_drama_checkout',
                        method: order.method,
                        kind: order.kind,
                        item: order.item,
                        episode_id: host().find('.sv-buy').data('episode') || 0,
                        nonce: jwsDrama.unlockNonce
                    }
                });
            }

            /**
             * Apple Pay / Google Pay, without leaving the page.
             *
             * The sheet is opened first and the server is called from inside it.
             * That order is not a preference: show() has to run in the task the
             * click started, and an await before it means Safari has already
             * decided the gesture is spent. So the amount comes off the card,
             * and the PaymentIntent is created once the shopper has actually
             * picked a card in the sheet.
             */
            function payByWallet(order, done) {

                var $row = host().find('.sv-buy-gateways');
                var key  = $row.data('stripe-key');

                if (!key || !window.Stripe) {
                    error(jwsDrama.i18n.failed);
                    done();
                    return;
                }

                var stripe  = window.Stripe(key);
                var request = stripe.paymentRequest({
                    country: String($row.data('stripe-country') || 'US'),
                    currency: String($row.data('currency') || 'USD').toLowerCase(),
                    total: { label: order.label, amount: order.amount },
                    requestPayerEmail: true
                });

                request.on('paymentmethod', function (event) {

                    checkout(order).done(function (response) {

                        if (!response || !response.success || !response.data || !response.data.clientSecret) {
                            event.complete('fail');
                            error((response && response.data && response.data.message) || jwsDrama.i18n.failed);
                            done();
                            return;
                        }

                        var secret = response.data.clientSecret;

                        /* handleActions:false so the sheet can be dismissed
                           before any 3-D Secure step, which cannot be shown
                           underneath it. */
                        stripe.confirmCardPayment(secret, { payment_method: event.paymentMethod.id }, { handleActions: false })
                            .then(function (result) {

                                if (result.error) {
                                    event.complete('fail');
                                    error(result.error.message || jwsDrama.i18n.failed);
                                    done();
                                    return;
                                }

                                event.complete('success');

                                if (result.paymentIntent.status === 'requires_action') {
                                    return stripe.confirmCardPayment(secret).then(function (again) {
                                        if (again.error) {
                                            error(again.error.message || jwsDrama.i18n.failed);
                                            done();
                                            return;
                                        }
                                        settled(response.data.orderId, done);
                                    });
                                }

                                settled(response.data.orderId, done);
                            });

                    }).fail(function () {
                        event.complete('fail');
                        error(jwsDrama.i18n.failed);
                        done();
                    });
                });

                request.on('cancel', done);

                request.show();
            }

            /**
             * The money is through, but the coins arrive on a webhook that may
             * be a second or two behind. Reloading is the honest way to show
             * whatever has actually landed.
             */
            function settled(orderId, done) {
                done();
                close();
                window.location.reload();
            }

            function setBalance(value) {
                $('.sv-coin-balance-number').text(value);
                $('.sv-buy-balance').text(value);
            }

            $(document)
                .on('click', '[data-jws-coin-modal]', function (event) {
                    event.preventDefault();
                    open($(this).data('episode'));
                })
                .on('click', '.sv-buy-close, .sv-buy-backdrop', close)
                .on('keydown', function (event) {
                    if (event.key === 'Escape' && $host && $host.hasClass('is-open')) {
                        close();
                    }
                })
                .on('click', '.sv-buy-gateway', function () {
                    select($(this));
                    error('');
                })
                /*
                 * A coin package goes into the WooCommerce cart and on to its
                 * checkout — the same trip a rental takes. A VIP plan is an
                 * <a> straight to the PMPro checkout, so it needs no handler
                 * at all; both leave the site's own money handling to the
                 * plugin that already does it.
                 */
                .on('click', '.sv-buy-pack', function () {

                    var $card = $(this);

                    /* One purchase at a time, whichever card started it. */
                    if (busy) {
                        return;
                    }

                    busy = true;
                    $card.addClass('is-busy');
                    error('');

                    $.ajax({
                        url: jwsDrama.ajax_url,
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'jws_drama_add_package',
                            package: $card.data('package'),
                            nonce: jwsDrama.unlockNonce
                        }
                    }).done(function (response) {

                        if (response && response.success && response.data && response.data.redirect) {
                            window.location.href = response.data.redirect;
                            return;
                        }

                        if (response && response.data && response.data.reason === 'not_logged_in') {
                            close();
                            requireLogin();
                        } else {
                            error((response && response.data && response.data.message) || jwsDrama.i18n.failed);
                        }

                        busy = false;
                        $card.removeClass('is-busy');

                    }).fail(function () {
                        error(jwsDrama.i18n.failed);
                        busy = false;
                        $card.removeClass('is-busy');
                    });
                });

            return { open: open, close: close, setBalance: setBalance };
        }());
    });

})(jQuery);
