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

        /* The episode the viewer has moved to whose stage has not arrived
           yet. The switch itself happens on the gesture, against a placeholder
           card, so what is held here is only the request still owed to it —
           and holding it is what lets a second gesture supersede the first
           instead of being swallowed while the first one loads. */
        var pending = null;

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

        /* Episodes an ad has opened, as the payload that opened them.
           The grant is never written down: ask the server for one of these
           again and the answer is the paywall. This map is the whole of "for
           this visit" — it dies with the page, which is exactly what the lock
           panel promised, and until then it is what a swipe back finds instead
           of the wall the viewer already got past. */
        var adOpened = {};

        /* Long enough that the episode on screen has the connection to itself
           while it fills its own buffer. */
        var PREFETCH_DELAY = 800;

        /* How long a card takes to travel a full stage height. Must match the
           transition on .sv-is-leaving / .sv-is-entering in drama.css: it is
           what the outgoing card is removed on. */
        var SLIDE_MS = 320;

        var slideTimer = null;

        /* When the slide in flight lands, so a stage that beats it can wait for
           it rather than cut across it. */
        var slideEndsAt = 0;

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
         * Which way the two cards should travel, worked out from where the two
         * episodes sit in the series rather than from what was clicked — so
         * jumping to episode 30 from the list slides the same way as pressing
         * next thirty times, and so back/forward moves the way the viewer
         * originally came.
         *
         * Read off the panel's chips rather than off the response, because the
         * card that slides in is often a placeholder with no response behind it
         * yet.
         *
         * @param {number|string} episodeId The episode being moved to.
         * @returns {number} 1 to move towards a later episode, -1 towards an
         *   earlier one, 0 when there is nothing to compare against.
         */
        function directionTo(episodeId) {

            var ids  = episodeIds();
            var from = $.inArray(currentEpisodeId(), ids);
            var to   = $.inArray(String(episodeId), ids);

            if (-1 === from || -1 === to || from === to) {
                return 0;
            }

            return to > from ? 1 : -1;
        }

        /* The slide is a CSS transition, which reduced motion switches off; the
           timers below have to agree with it, or a stage that is already here
           would be held back for a slide that never ran. */
        function reducedMotion() {
            return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
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

            slideEndsAt = 0;

            var $stage = $page.find('.sv-short-stage');

            $stage.children('.sv-short-player.sv-is-leaving').remove();

            /* .sv-is-entering is what carries the transition; left on, it would
               have the drag gesture below ease towards the finger instead of
               tracking it. Any inline transform is deliberately left alone — a
               card the finger has just let go of is exactly what the slide
               about to start needs to carry on from. */
            $stage.children('.sv-short-player').removeClass('sv-is-entering');
        }

        /* The controls are absolutely positioned and carry no z-index of their
           own, so they stay above the card only while they are the last thing
           on the stage — which a card appended beside kept controls, rather
           than alongside its own, would otherwise break. */
        function raiseControls($stage) {

            var $controls = $stage.children('.sv-short-stage-controls');

            if ($controls.length && !$controls.is(':last-child')) {
                $controls.appendTo($stage);
            }
        }

        /**
         * Puts a card on the stage, sliding it past the one it replaces.
         *
         * @param {jQuery} $incoming The card, plus anything that belongs on the
         *   stage beside it — a server-rendered stage brings its own controls,
         *   a placeholder brings nothing.
         * @param {number} direction 1 towards a later episode, -1 towards an
         *   earlier one, 0 to put it there without a slide.
         */
        function swapStage($incoming, direction) {

            var $stage = $page.find('.sv-short-stage');

            endSlide();

            /* An ad plays over the stage, so a stage that changes ends it. */
            destroyAdBreak();

            var $outgoing = $stage.children('.sv-short-player');
            var $card     = $incoming.filter('.sv-short-player');

            /* Everything the incoming set replaces goes now — a server-rendered
               stage brings its own controls, a placeholder leaves the ones
               already here standing for renderNavSlots() to repoint. The card
               is the only part worth animating. Removing the old <video-player>
               is what lets the MutationObserver in jws_player_v10.js pick the
               new one up; the old one's save timer stops itself once it is
               detached. */
            var $keep = $incoming.filter('.sv-short-stage-controls').length
                ? $outgoing
                : $outgoing.add($stage.children('.sv-short-stage-controls'));

            $stage.children().not($keep).remove();

            if (!direction || !$outgoing.length || !$card.length) {
                $outgoing.remove();
                $stage.append($incoming);
                raiseControls($stage);
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
            raiseControls($stage);

            /* Forces both start positions to be computed before they change —
               including a card the finger dragged part of the way, which then
               carries on from where it was let go rather than jumping back. */
            $card[0].offsetHeight; // eslint-disable-line no-unused-expressions

            $card.addClass('sv-is-entering').css('transform', '');
            $outgoing.css('transform', 'translateY(' + (direction > 0 ? -110 : 110) + '%)');

            slideEndsAt = Date.now() + (reducedMotion() ? 0 : SLIDE_MS);
            slideTimer  = setTimeout(endSlide, SLIDE_MS);
        }

        /**
         * Drops the real stage into a placeholder's place, where it stands.
         *
         * No slide: the one the viewer asked for ran when they asked for it,
         * and the card sitting here is the one that made it.
         *
         * @param {string} stageHtml The stage exactly as the server rendered it.
         */
        function replaceStage(stageHtml) {

            var $stage = $page.find('.sv-short-stage');

            endSlide();

            /* The placeholder, and the controls that came in with the episode
               before it: both are replaced by what has just arrived. */
            $stage.empty().append($(stageHtml));
        }

        /**
         * Runs something once the slide in flight has finished, or straight away
         * when there is none.
         *
         * A stage that arrives faster than the slide has to wait for it: put in
         * mid-flight it would appear wherever the placeholder had got to,
         * halfway up the stage, and jump from there.
         */
        function afterSlide(done) {

            var left = slideEndsAt - Date.now();

            if (left > 0) {
                setTimeout(done, left);
                return;
            }

            done();
        }

        /**
         * @param {Object} data The episode response.
         * @param {boolean} push Whether this switch should add a history entry.
         * @param {boolean} [inPlace] Whether the stage is already showing this
         *   episode as a placeholder, which the real one then replaces where it
         *   stands — everything else about the switch happened when the viewer
         *   asked for it.
         */
        function applyEpisode(data, push, inPlace) {

            if (inPlace) {
                replaceStage(data.stage);
            } else {
                swapStage($(data.stage), directionTo(data.episodeId));
            }

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

        /** The episode's own page — where a switch falls back to when AJAX can't. */
        function episodeHref(episodeId) {
            return $page.find('.sv-short-episode[data-episode="' + episodeId + '"]').attr('href') || '';
        }

        /**
         * The card that stands in for an episode whose stage is not here yet.
         *
         * The gesture is answered by this, immediately, rather than by a stage
         * that sits on the episode being left until the network says it may
         * move — which on a slow connection read as the swipe having missed.
         */
        function pendingCard() {

            return $(
                '<div class="sv-short-player sv-short-player--pending">' +
                    '<span class="sv-short-spinner" aria-hidden="true"></span>' +
                '</div>'
            );
        }

        /** Drops the request for an episode the viewer has already moved past. */
        function endPending() {

            if (!pending) {
                return;
            }

            var stale = pending;

            /* Cleared before the abort, not after: abort() runs the fail
               handler synchronously, and that handler decides what to do by
               whether its episode is still the one being waited on. */
            pending = null;
            stale.xhr.abort();
        }

        /**
         * Moves to an episode whose stage has to be fetched: the switch is made
         * now, against a placeholder, and the stage drops into it on arrival.
         *
         * @param {number|string} episodeId
         * @param {boolean} push Whether to add a history entry.
         */
        function startPending(episodeId, push) {

            endPending();

            var href      = episodeHref(episodeId);
            var direction = directionTo(episodeId);
            var id        = String(episodeId);

            swapStage(pendingCard(), direction);

            /* Everything that says which episode this is moves with the card;
               only the frames are still owed. The arrows move too, or a second
               swipe would be read against the episode just left. */
            markActive(episodeId);
            openRangeOf(episodeId);
            renderNavSlots();
            setPanelOpen(false);

            if (push && href && window.history && window.history.pushState) {
                window.history.pushState({ jwsDramaEpisode: id }, '', href);
            }

            var xhr = fetchEpisode(episodeId, false);

            pending = { id: id, xhr: xhr };

            xhr.done(function (response) {

                // Superseded: the viewer has swiped past this one already.
                if (!pending || pending.id !== id) {
                    return;
                }

                if (!response || !response.success) {
                    // Nothing sensible to show in place — let the browser do it.
                    if (href) {
                        window.location.href = href;
                    }
                    return;
                }

                afterSlide(function () {

                    if (!pending || pending.id !== id) {
                        return;
                    }

                    pending = null;

                    /* Never pushed again here: the URL moved with the card, at
                       the moment the viewer asked for it. */
                    applyEpisode(response.data, false, true);
                });

            }).fail(function () {

                if (pending && pending.id === id && href) {
                    window.location.href = href;
                }
            });
        }

        function loadEpisode(episodeId, push) {

            if (!episodeId || String(episodeId) === currentEpisodeId()) {
                return;
            }

            /* An ad-opened episode is checked first and never spent: it is the
               only copy of a stage the server will not render a second time. */
            var ready = adOpened[episodeId] || prefetched[episodeId];

            if (ready) {
                /* Already here: it goes straight in, so there is nothing for a
                   placeholder to stand in for. */
                delete prefetched[episodeId];
                endPending();
                applyEpisode(ready, push);
                return;
            }

            startPending(episodeId, push);
        }

        /**
         * Every episode of the series, in order, read off the panel's chips.
         *
         * The panel renders the lot — the ranges only hide them — so this is
         * the one list of the series the page always has, including while the
         * stage is showing a placeholder instead of the prev/next the server
         * rendered.
         *
         * @returns {string[]}
         */
        function episodeIds() {

            return $page.find('.sv-short-episode').map(function () {
                return String($(this).data('episode'));
            }).get();
        }

        /** The episode being watched, or the one being moved to. */
        function currentEpisodeId() {

            return pending
                ? pending.id
                : String($page.find('.sv-short-episode.active').data('episode') || '');
        }

        /**
         * @param {number} direction -1 for the previous episode, 1 for next.
         * @returns {string} The id, or '' at that end of the series.
         */
        function neighbourId(direction) {

            var ids = episodeIds();
            var at  = $.inArray(currentEpisodeId(), ids);

            if (-1 === at) {
                return '';
            }

            return ids[at + (direction < 0 ? -1 : 1)] || '';
        }

        /**
         * @returns {string[]} One id, two, or none at all.
         */
        function neighbourIds() {

            var ids = [];

            $.each([-1, 1], function (index, direction) {

                var id = neighbourId(direction);

                if (id) {
                    ids.push(id);
                }
            });

            return ids;
        }

        /*
         * Both prev/next slots of .sv-short-stage-controls, in that order.
         *
         * stage.php always renders the pair, as an <a class="sv-short-nav">
         * where that neighbour exists and a disabled <span> placeholder where
         * it doesn't — so prev is always slot 0 and next always slot 1.
         * Selecting only "a.sv-short-nav" would shift position whenever the
         * prev slot is a placeholder (e.g. on the first episode): the lone
         * "next" link would land at index 0 and get read as "prev".
         */
        var NAV_SLOTS = '.sv-short-stage-controls > .sv-short-stage-btn.sv-short-nav, '
                      + '.sv-short-stage-controls > .sv-short-stage-btn.is-disabled';

        /**
         * Points the stage's arrows at the neighbours of the episode now
         * showing.
         *
         * The server sends these with every stage, so they normally need no
         * help — except while a placeholder stands in for one, which is exactly
         * when a viewer is most likely to press them again.
         */
        function renderNavSlots() {

            var $slots = $page.find(NAV_SLOTS);

            $.each([-1, 1], function (index, direction) {

                var $slot = $slots.eq(index);

                if (!$slot.length) {
                    return;
                }

                var id = neighbourId(direction);

                /* Not the same element either way round — a neighbour is a
                   link, the end of the series a disabled span — so the slot is
                   rebuilt rather than relabelled. The caret inside it is
                   carried over, which is what keeps prev pointing up and next
                   down without this having to know which is which. */
                var $new = $(id ? '<a></a>' : '<span></span>')
                    .addClass('sv-short-stage-btn')
                    .addClass(id ? 'sv-short-nav' : 'is-disabled')
                    .html($slot.html());

                if (id) {
                    $new.attr({
                        'data-episode': id,
                        'href': episodeHref(id),
                        'aria-label': direction < 0 ? jwsDrama.i18n.prevEpisode : jwsDrama.i18n.nextEpisode
                    });
                } else {
                    $new.attr('aria-hidden', 'true');
                }

                $slot.replaceWith($new);
            });
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

                    // An ad-opened neighbour would come back as the paywall.
                    if (prefetched[id] || prefetching[id] || adOpened[id]) {
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

            var id = neighbourId(event.key === 'ArrowUp' ? -1 : 1);

            if (id) {
                event.preventDefault();
                loadEpisode(id, true);
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
           the inline styles again so they don't linger on whatever ends up in
           this slot next.

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

            if (!touch) {
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
            var hasNeighbour = !!neighbourId(deltaY < 0 ? 1 : -1);
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
            var id = neighbourId(deltaY < 0 ? 1 : -1);

            if (!id) {
                snapBack($card);
                return;
            }

            /* The card is left exactly where the finger let go of it: the slide
               swapStage() is about to start picks it up from there and carries
               it the rest of the way out, so the gesture and the transition read
               as one movement rather than two that fight over the same
               transform. */
            loadEpisode(id, true);
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
            if (!neighbourId(wheel.deltaY > 0 ? 1 : -1)) {
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
            var id = neighbourId(wheelTravel > 0 ? 1 : -1);

            wheelSpent  = true;
            wheelTravel = 0;

            if (id) {
                loadEpisode(id, true);
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
        /* Ad unlock                                                           */
        /* ------------------------------------------------------------------ */

        /* In link mode this is a plain <a> the browser opens itself — a
           window.open() from script is what pop-up blockers exist for — and all
           the handler does is start the clock the server will hold the claim to
           anyway. In video mode it is a <button> and the break plays here. */
        $page.on('click', '.sv-short-ad-unlock', function () {

            var $btn = $(this);
            var episodeId = String($btn.data('episode'));

            /* Already running: in link mode the viewer is welcome to open the
               ad again — the link still navigates — but a second clock would
               race the first one to the same single-use ticket. */
            if (!episodeId || $btn.hasClass('is-waiting')) {
                return;
            }

            if ($btn.data('adMode') === 'video') {
                startAdVideo(episodeId, $btn);
                return;
            }

            startAdUnlock(episodeId, $btn);
        });

        /* ------------------------------------------------------------------ */
        /* Ad unlock: the video break                                          */
        /* ------------------------------------------------------------------ */

        /* The break on screen, if there is one. At most one ever runs: it takes
           the whole stage, and there is only one stage. */
        var adBreak = null;

        /**
         * Takes down whatever break is running, earned or not.
         *
         * Called from swapStage() as well as from the break's own end, so an ad
         * can never outlive the card it was playing over — a viewer who swipes
         * away mid-ad leaves nothing behind but an unclaimed ticket, which
         * expires on its own.
         */
        function destroyAdBreak() {

            if (!adBreak) {
                return;
            }

            var ending = adBreak;

            adBreak = null;

            try { if (ending.manager) { ending.manager.destroy(); } } catch (e) { }
            try { if (ending.loader) { ending.loader.destroy(); } } catch (e) { }

            document.removeEventListener('visibilitychange', ending.onVisible);

            ending.$overlay.remove();
            ending.reset();
        }

        /**
         * Plays a rewarded VAST break over the stage and opens the episode if it
         * is watched to the end.
         *
         * IMA is driven directly, the way jws_player_v10.js drives it for the
         * content player: videojs-ima was never ported to v10, and this break
         * has no content player to hang off in any case — the stage under it is
         * the paywall.
         *
         * @param {string} episodeId
         * @param {jQuery} $btn The button that was clicked.
         */
        function startAdVideo(episodeId, $btn) {

            var $label = $btn.find('.sv-short-ad-label');
            var label  = $label.text();

            function reset() {
                $btn.removeClass('is-waiting');
                $label.text(label);
            }

            if (typeof google === 'undefined' || !google.ima || !jwsDrama.adTag) {
                // A blocked SDK is the norm, not an exception.
                notify(jwsDrama.i18n.adNoAd, 'error');
                return;
            }

            destroyAdBreak();

            $btn.addClass('is-waiting');
            $label.text(jwsDrama.i18n.adLoading);

            var $overlay = $(
                '<div class="sv-short-ad-break">' +
                    '<video class="sv-short-ad-media" playsinline webkit-playsinline></video>' +
                    '<div class="sv-short-ad-slot"></div>' +
                    '<button type="button" class="sv-short-ad-resume" hidden>' +
                        '<i class="jws-icon-play-fill" aria-hidden="true"></i>' +
                    '</button>' +
                    '<p class="sv-short-ad-status"></p>' +
                '</div>'
            ).appendTo($page.find('.sv-short-stage'));

            /*
             * Clicking a linear ad opens the advertiser in another tab and
             * leaves this one paused — that is IMA's behaviour, not a fault,
             * but the SDK draws no way back from it. It renders the skip button
             * and the creative's own click-through and nothing else; play and
             * pause have always been the publisher's to provide, which is why
             * the content player carries its own ad bar too.
             *
             * Without this the viewer came back from the advertiser to a still
             * frame with no control on it, and an ad that cannot finish is an
             * episode that never opens.
             */
            var $resume = $overlay.find('.sv-short-ad-resume');

            function showResume(paused) {
                $resume[0].hidden = !paused;
            }

            $resume.on('click', function (event) {

                /* Kept off the ad underneath: this button sits over the
                   creative, and a click that reached it would open the
                   advertiser all over again. */
                event.stopPropagation();

                if (adBreak && adBreak.manager) {
                    try { adBreak.manager.resume(); } catch (e) { }
                }
            });

            /* Coming back to the tab is the ordinary way out of a click-through,
               so it resumes on its own — the button is for the times it isn't
               (a click that never left the page, a pause from the creative). */
            function onVisible() {

                if ('visible' !== document.visibilityState) {
                    return;
                }

                /* Not gated on the button being up: a break can be paused
                   without IMA having said so — a background tab pauses media
                   on its own — and resuming an ad that was never paused is a
                   no-op, while missing one that was leaves the viewer stuck on
                   a still frame with an episode they cannot reach. */
                if (adBreak && adBreak.manager) {
                    try { adBreak.manager.resume(); } catch (e) { }
                }
            }

            document.addEventListener('visibilitychange', onVisible);

            $overlay.find('.sv-short-ad-status').text(jwsDrama.i18n.adLoading);

            var slotEl  = $overlay.find('.sv-short-ad-slot')[0];
            var videoEl = $overlay.find('.sv-short-ad-media')[0];

            /* Inside the click, before anything asynchronous: iOS and Safari
               only let a media element start from a gesture, and initialize()
               is what claims this one for the ad to play in later. */
            var display = new google.ima.AdDisplayContainer(slotEl, videoEl);

            try { display.initialize(); } catch (e) { /* already initialised */ }

            var loader = new google.ima.AdsLoader(display);

            adBreak = {
                id: episodeId,
                loader: loader,
                manager: null,
                earned: false,
                $overlay: $overlay,
                onVisible: onVisible,
                reset: reset
            };

            function width()  { return $overlay.width() || 360; }
            function height() { return $overlay.height() || 640; }

            /** The break is over, one way or another. */
            function finish(running) {

                var earned = running.earned;

                destroyAdBreak();

                if (!earned) {
                    notify(jwsDrama.i18n.adNotFinished, 'error');
                    return;
                }

                claimAdUnlock(episodeId, running.token, reset);
            }

            function onError(error) {

                var detail = error;

                try {
                    var adError = (error && typeof error.getError === 'function') ? error.getError() : error;

                    if (adError && typeof adError.getErrorCode === 'function') {
                        detail = 'code ' + adError.getErrorCode() + ' — ' + adError.getMessage();
                    }
                } catch (e) { /* fall back to the raw value */ }

                window.console && console.warn('[StreamVid] rewarded ad error:', detail);

                var running = adBreak;

                destroyAdBreak();

                /* No ad to watch is not the viewer failing to watch one, so it
                   is worth saying differently — and it must never be worth an
                   episode, or an ad blocker would be the cheapest way in. */
                if (running) {
                    notify(jwsDrama.i18n.adNoAd, 'error');
                }
            }

            loader.addEventListener(google.ima.AdErrorEvent.Type.AD_ERROR, onError, false);

            loader.addEventListener(google.ima.AdsManagerLoadedEvent.Type.ADS_MANAGER_LOADED, function (event) {

                if (!adBreak) {
                    return; // swiped away while the tag was being fetched
                }

                var manager = event.getAdsManager(videoEl);

                adBreak.manager = manager;

                manager.addEventListener(google.ima.AdErrorEvent.Type.AD_ERROR, onError);

                manager.addEventListener(google.ima.AdEvent.Type.STARTED, function () {
                    $overlay.addClass('is-playing').find('.sv-short-ad-status').text('');
                    showResume(false);
                });

                manager.addEventListener(google.ima.AdEvent.Type.PAUSED, function () {
                    showResume(true);
                });

                manager.addEventListener(google.ima.AdEvent.Type.RESUMED, function () {
                    showResume(false);
                });

                /*
                 * The mark the episode is earned at, and never SKIPPED: a
                 * viewer who skipped past the whole thing is worth nothing to
                 * the advertiser and so is worth nothing here.
                 *
                 * Which mark it is has to be a setting, because a creative that
                 * declares a skipoffset draws its own Skip button and the SDK
                 * gives nobody a way to take it away. A site serving skippable
                 * ads either rewards something short of the end, or rewards
                 * almost nobody.
                 */
                var rewardAt = google.ima.AdEvent.Type.COMPLETE;

                if ('start' === jwsDrama.adReward) {
                    rewardAt = google.ima.AdEvent.Type.STARTED;
                } else if ('midpoint' === jwsDrama.adReward) {
                    rewardAt = google.ima.AdEvent.Type.MIDPOINT;
                }

                manager.addEventListener(rewardAt, function () {
                    if (adBreak) {
                        adBreak.earned = true;
                    }
                });

                /* Skipping an ad that has already earned the episode ends the
                   break there and then: the reward is settled, and making the
                   viewer watch the SDK tear itself down before the episode
                   appears reads as the skip not having worked.

                   Only when it is earned, though — skipping the first ad of a
                   pod that has not paid out yet leaves the rest of the pod its
                   chance, and ALL_ADS_COMPLETED below is what closes it. */
                manager.addEventListener(google.ima.AdEvent.Type.SKIPPED, function () {
                    if (adBreak && adBreak.earned) {
                        finish(adBreak);
                    }
                });

                /* The only event that means "no break is left" — a per-ad check
                   races the SDK and leaves the slot up over the stage. */
                manager.addEventListener(google.ima.AdEvent.Type.ALL_ADS_COMPLETED, function () {
                    if (adBreak) {
                        finish(adBreak);
                    }
                });

                try {
                    manager.init(width(), height(), google.ima.ViewMode.NORMAL);
                    manager.start();
                } catch (e) {
                    onError(e);
                }
            }, false);

            /* The ticket first: a viewer who has run out of episodes for today
               should be told so before an advertiser is billed for showing them
               anything. */
            $.ajax({
                url: jwsDrama.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'jws_drama_ad_start',
                    episode_id: episodeId,
                    nonce: jwsDrama.adNonce
                }
            }).done(function (response) {

                if (!adBreak) {
                    return;
                }

                if (!response || !response.success) {
                    notify((response && response.data && response.data.message) || jwsDrama.i18n.failed, 'error');
                    destroyAdBreak();
                    return;
                }

                adBreak.token = response.data.token;

                var request = new google.ima.AdsRequest();

                request.adTagUrl = jwsDrama.adTag;
                request.linearAdSlotWidth  = width();
                request.linearAdSlotHeight = height();
                request.nonLinearAdSlotWidth  = width();
                request.nonLinearAdSlotHeight = Math.floor(height() / 3);

                try {
                    loader.requestAds(request);
                } catch (e) {
                    onError(e);
                }

            }).fail(function () {
                notify(jwsDrama.i18n.failed, 'error');
                destroyAdBreak();
            });
        }

        /**
         * Asks for a ticket, then counts down the wait the server answered with.
         *
         * @param {string} episodeId
         * @param {jQuery} $btn The link that was clicked. It may be gone from
         *   the page before this finishes — the viewer can swipe on while the
         *   ad tab is open — which is why nothing here depends on it.
         */
        function startAdUnlock(episodeId, $btn) {

            var $label = $btn.find('.sv-short-ad-label');
            var label  = $label.text();

            function done() {
                $btn.removeClass('is-waiting');
                $label.text(label);
            }

            $btn.addClass('is-waiting');
            $label.text(jwsDrama.i18n.adClaiming);

            $.ajax({
                url: jwsDrama.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'jws_drama_ad_start',
                    episode_id: episodeId,
                    nonce: jwsDrama.adNonce
                }
            }).done(function (response) {

                if (!response || !response.success) {
                    notify((response && response.data && response.data.message) || jwsDrama.i18n.failed, 'error');
                    done();
                    return;
                }

                countAdDown(episodeId, response.data.token, (response.data.seconds | 0) + 1, $label, done);

            }).fail(function () {
                notify(jwsDrama.i18n.failed, 'error');
                done();
            });
        }

        /**
         * One second past what the server asks for, so a claim is never refused
         * for arriving on the same second it became valid.
         */
        function countAdDown(episodeId, token, left, $label, done) {

            if (left <= 0) {
                $label.text(jwsDrama.i18n.adClaiming);
                claimAdUnlock(episodeId, token, done);
                return;
            }

            $label.text(jwsDrama.i18n.adWait.replace('%d', left));

            setTimeout(function () {
                countAdDown(episodeId, token, left - 1, $label, done);
            }, 1000);
        }

        function claimAdUnlock(episodeId, token, done) {

            $.ajax({
                url: jwsDrama.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'jws_drama_ad_claim',
                    episode_id: episodeId,
                    token: token,
                    nonce: jwsDrama.adNonce
                }
            }).done(function (response) {

                if (!response || !response.success) {
                    notify((response && response.data && response.data.message) || jwsDrama.i18n.failed, 'error');
                    done();
                    return;
                }

                var data = response.data;

                adOpened[episodeId] = data;

                /* Whatever was warmed for this episode is the lock panel, which
                   is no longer what the page should show for it. */
                delete prefetched[episodeId];

                // Watchable for this visit, so it stops being a locked chip.
                $page.find('.sv-short-episode[data-episode="' + episodeId + '"]')
                     .removeClass('locked')
                     .find('.sv-short-lock').remove();

                /* Nothing is announced: the episode replacing the paywall says
                   it, and a toast over the opening seconds of a two-minute
                   episode sits on the very thing the viewer just earned. Only
                   failures speak up — those are the ones needing words.

                   Applied only if the viewer is still here. Swiping on while
                   the ad tab was open is not a request to be dragged back; the
                   episode is in adOpened either way and opens without a wall
                   next time they come to it. */
                if (currentEpisodeId() === episodeId) {
                    applyEpisode(data, false);
                } else {
                    done();
                }

            }).fail(function () {
                notify(jwsDrama.i18n.failed, 'error');
                done();
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
