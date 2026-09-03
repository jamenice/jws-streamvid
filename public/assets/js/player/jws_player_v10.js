/**
 * StreamVid — Video.js 10 engine bootstrap.
 *
 * Only loaded when Settings > StreamVid Player is set to "Video.js 10". The
 * legacy Video.js 7 stack (videojs.min.js + jws_player.js) is not on the page at
 * all in that mode, which is what keeps the two engines from both claiming
 * #videos_player.
 *
 * The markup itself is rendered by public/movies/player.php. This file:
 *   - pulls in the v10 ES modules (the preset, plus the one media adapter the
 *     source needs) via dynamic import,
 *   - restores the saved playback position and offers to resume,
 *   - keeps watch history in sync the same way the legacy player did,
 *   - auto-advances to the next episode,
 *   - and re-initialises after the episode / source AJAX swaps the markup.
 */
(function ($) {
    'use strict';

    if (typeof jwsPlayerV10 === 'undefined') {
        return;
    }

    var SAVE_INTERVAL = 3; // seconds of playback between history writes

    /*
     * Below this, resuming is not worth asking about — "continue from 0:02"
     * is noise on every video the viewer merely opened. The legacy engine drew
     * its notice on the same threshold.
     */
    var RESUME_PROMPT_MIN = 5;

    /**
     * Locales @videojs/html publishes. Anything else falls back to English
     * rather than firing a 404 at the CDN on every page view.
     */
    var LOCALES = [
        'ar', 'az', 'bg', 'bn', 'bs', 'ca', 'cs', 'cy', 'da', 'de', 'el', 'es',
        'et', 'eu', 'fa', 'fi', 'fr', 'gd', 'gl', 'he', 'hi', 'hr', 'hu', 'id',
        'it', 'ja', 'ko', 'lt', 'lv', 'mr', 'nb', 'ne', 'nl', 'nn', 'oc', 'pl',
        'pt', 'pt-BR', 'pt-PT', 'ro', 'ru', 'sk', 'sl', 'sr', 'sv', 'te', 'th',
        'tr', 'uk', 'vi', 'zh', 'zh-CN', 'zh-TW'
    ];

    /* ---------------------------------------------------------------------- */
    /* Module loading                                                          */
    /* ---------------------------------------------------------------------- */

    /*
     * `import()` is a syntax error to parsers that predate it, and a parse error
     * would take this whole file down rather than just the player. Building the
     * importer with `new Function` moves that failure to call time, where the
     * catch below can report it.
     */
    var dynamicImport = null;

    try {
        dynamicImport = new Function('url', 'return import(url);');
    } catch (e) {
        dynamicImport = null;
    }

    var moduleCache = {};

    function loadModule(path) {

        if (moduleCache[path]) {
            return moduleCache[path];
        }

        if (!dynamicImport) {
            moduleCache[path] = Promise.reject(new Error('This browser cannot load ES modules.'));
            return moduleCache[path];
        }

        moduleCache[path] = dynamicImport(jwsPlayerV10.base + path);

        return moduleCache[path];
    }

    function matchLocale(locale) {

        if (!locale) {
            return '';
        }

        // get_locale() arrives lowercased and dashed, e.g. "pt-br", "vi", "zh-cn".
        for (var i = 0; i < LOCALES.length; i++) {
            if (LOCALES[i].toLowerCase() === locale) {
                return LOCALES[i];
            }
        }

        var short = locale.split('-')[0];

        for (var j = 0; j < LOCALES.length; j++) {
            if (LOCALES[j].toLowerCase() === short) {
                return LOCALES[j];
            }
        }

        return '';
    }

    /* ---------------------------------------------------------------------- */
    /* Watch history — same payload the legacy player sent                     */
    /* ---------------------------------------------------------------------- */

    function saveVideoProgress(progress) {

        if ($('body').hasClass('logged-in')) {

            var data = {
                action: 'history_post',
                progress: progress
            };

            if (typeof streamvid_script !== 'undefined' && streamvid_script.is_episodes) {
                data.tv_shows = streamvid_script.episodes_tv_shows;
            }

            $.ajax({
                type: 'POST',
                dataType: 'json',
                url: (typeof jws_script !== 'undefined' && jws_script.ajax_url) ? jws_script.ajax_url : jwsPlayerV10.ajax_url,
                data: data
            });

            return;
        }

        var history = {};

        try {
            history = JSON.parse(localStorage.getItem('video_history') || '{}');
        } catch (e) {
            history = {};
        }

        history[progress.id] = progress;

        try {
            localStorage.setItem('video_history', JSON.stringify(history));
        } catch (e) { /* private mode / quota — history is best effort */ }
    }

    /** Where playback should resume from, server value first then guest storage. */
    function resumeTimeFor(config) {

        if (config.currentTime > 0) {
            return config.currentTime;
        }

        if ($('body').hasClass('logged-in')) {
            return 0;
        }

        try {
            var history = JSON.parse(localStorage.getItem('video_history') || '{}');
            var entry = history[config.postId];

            if (entry && typeof entry.time !== 'undefined') {
                return parseFloat(entry.time) || 0;
            }
        } catch (e) { /* unreadable storage means no resume point */ }

        return 0;
    }

    /**
     * Seeks, waiting for metadata only if it is not there yet.
     *
     * currentTime cannot be set before the duration is known, and with
     * preload="auto" on a cached file `loadedmetadata` has often already
     * fired by the time this runs — a listener alone would then never be
     * called and the resume point would be silently dropped.
     */
    function seekTo(media, time) {

        var apply = function () {
            try { media.currentTime = time; } catch (e) { }
        };

        if (media.readyState >= 1) {
            apply();
            return;
        }

        media.addEventListener('loadedmetadata', apply, { once: true });
    }

    function formatTime(seconds) {

        seconds = Math.max(0, Math.floor(seconds));

        var h = Math.floor(seconds / 3600);
        var m = Math.floor((seconds % 3600) / 60);
        var s = seconds % 60;

        var out = (m < 10 && h > 0 ? '0' + m : m) + ':' + (s < 10 ? '0' + s : s);

        return h > 0 ? h + ':' + out : out;
    }

    /* ---------------------------------------------------------------------- */
    /* Resume prompt                                                           */
    /* ---------------------------------------------------------------------- */

    function buildResumePrompt(playerEl, $wrap, media, resumeAt) {

        var strings = (typeof streamvid_script !== 'undefined') ? streamvid_script : {};

        var $prompt = $(
            '<div class="jws-v10-resume">' +
                '<span class="jws-v10-resume__label"></span>' +
                '<button type="button" class="jws-v10-resume__continue"></button>' +
                '<button type="button" class="jws-v10-resume__restart"></button>' +
            '</div>'
        );

        $prompt.find('.jws-v10-resume__label')
            .text((strings.history_text || 'You have watched up to') + ' ' + formatTime(resumeAt));
        $prompt.find('.jws-v10-resume__continue').text(strings.continue_watching || 'Continue watching');
        $prompt.find('.jws-v10-resume__restart').text(strings.watch_again || 'Watch again');

        $prompt.on('click', '.jws-v10-resume__continue', function () {
            seekTo(media, resumeAt);
            $prompt.remove();
            media.play();
        });

        $prompt.on('click', '.jws-v10-resume__restart', function () {
            seekTo(media, 0);
            $prompt.remove();
            media.play();
        });

        /*
         * Only a play the viewer actually started retires the offer.
         *
         * A bare play listener could not tell their click from autoplay or
         * from the content resuming after an ad break, and those tore the
         * prompt down before it had been read — which is the offer answering
         * itself. So a real gesture on the player has to have happened first,
         * and the listener is not `once`: a programmatic play simply passes
         * through and leaves the prompt standing.
         */
        var gestured = false;

        var noteGesture = function () {
            gestured = true;
        };

        playerEl.addEventListener('pointerdown', noteGesture, true);
        playerEl.addEventListener('keydown', noteGesture, true);

        media.addEventListener('play', function () {
            if (gestured) {
                $prompt.remove();
            }
        });

        $wrap.append($prompt);
    }

    /* ---------------------------------------------------------------------- */
    /* Control bar additions (logo, episode list)                              */
    /* ---------------------------------------------------------------------- */

    /**
     * Whether the episodes button belongs on this player, mirroring the same
     * three conditions the legacy skin checks.
     */
    function episodeListForButton() {

        if (typeof streamvid_script === 'undefined' || !streamvid_script.is_episodes || !streamvid_script.show_ep_list_btn) {
            return [];
        }

        if (streamvid_script.episodes_list && streamvid_script.episodes_list.length > 0) {
            return streamvid_script.episodes_list;
        }

        if (streamvid_script.seasons_data && streamvid_script.seasons_data.length > 0) {
            return streamvid_script.seasons_data.reduce(function (acc, season) {
                return acc.concat(season.episodes || []);
            }, []);
        }

        return [];
    }

    /**
     * Keeps #jws-ep-panel inside whatever is currently fullscreen.
     *
     * single_global.js appends the panel to `.videos_player`, which is right for
     * the legacy engine — there the fullscreen element is `.video-js`, and the
     * panel sits inside it. On v10 the fullscreen element is `<video-skin>`, and
     * `.videos_player` is its *parent*: everything outside the fullscreen element
     * stops rendering, so the panel silently disappears the moment you go
     * fullscreen. Moving it in (and back out again) is the whole fix — the
     * `:fullscreen .jws-ep-panel` rules in jws_ep_panel.css already handle the
     * rest, and a light-DOM child of the skin keeps its document styles.
     */
    function placeEpisodePanel(playerEl) {

        var panel = document.getElementById('jws-ep-panel');

        if (!panel) {
            return;
        }

        var skin = playerEl.querySelector('video-skin');
        var wrap = playerEl.closest('.videos_player');
        var fsEl = document.fullscreenElement || document.webkitFullscreenElement;
        var target = (fsEl && skin && (fsEl === skin || fsEl.contains(skin))) ? fsEl : wrap;

        if (target && panel.parentElement !== target) {
            target.appendChild(panel);
        }

        /*
         * A class, not the `:fullscreen .jws-ep-panel` rule the legacy engine
         * relies on. `document.fullscreenElement` reports <video-skin>, but only
         * because shadow retargeting hides the truth: the element actually put
         * into fullscreen is the <media-container> inside the skin's shadow root.
         * The panel is slotted into it, so it renders — but its DOM parent is
         * still <video-skin>, which does not match `:fullscreen`, and a
         * document-level descendant selector matches on the DOM tree rather than
         * the flat tree, so that rule can never fire here.
         *
         * Everything the class needs to do lives in jws_ep_panel.css. Vertical
         * offset is deliberately not one of those things: the panel sits at
         * `bottom: 0` in fullscreen the same as windowed.
         */
        panel.classList.toggle('jws-ep-panel--fs', target !== wrap);
    }

    /**
     * The one stylesheet this plugin puts inside the skin's shadow root.
     *
     * Everything here has to live in here rather than in jws_player_v10.css:
     * the skin's controls are shadow content, and a document stylesheet cannot
     * reach them.
     */
    function injectSkinStyles(root, cornerRadius) {

        if (root.getElementById('jws-v10-skin-style')) {
            return;
        }

        var style = document.createElement('style');

        style.id = 'jws-v10-skin-style';

        /*
         * Both selectors are compounds on purpose, and both had to be measured.
         *
         * The container's 1.75rem rounded corners are not reachable from outside
         * at all. `--media-container-border-radius` looks like the documented way
         * to change it, but setting it on <video-player> or on <video-skin> does
         * nothing: the skin re-declares the token on `.media-default-skin`, and a
         * local declaration beats an inherited one. Even inside the shadow root a
         * bare `media-container` selector loses (0,0,1 against the skin's 0,1,0),
         * so the element+class compound is the least heavy-handed thing that wins.
         *
         * Same story for the logo: the skin styles its control children with a
         * selector that outranks a lone class, so a bare `.jws-v10-logo` rule
         * loses even appended last — `display` in particular never applied.
         * Neither uses `!important`: the skin hides its own controls by rule, and
         * an important `display` would strand the logo on screen after the bar
         * has faded out.
         *
         * Nothing here for the episodes button on purpose — it wears the skin's
         * own .media-button classes, which already reset a native <button>'s
         * background, border, font and cursor and supply the size, pill radius,
         * hover fill and focus ring.
         */
        style.textContent =
            /* The skin's own 1.75rem is too round for this theme; the call site
               picks the value, square for a full-bleed layout. */
            'media-container.media-default-skin{--media-container-border-radius:' + cornerRadius + '}' +
            '.media-button-group img.jws-v10-logo{display:block;align-self:center;width:auto;' +
            'max-width:110px;max-height:30px;margin-inline-start:6px;object-fit:contain;pointer-events:none}' +
            /* The control bar gets tight on phones; the legacy skin dropped the
               logo there rather than crowding the buttons. */
            '@media (max-width:767px){.media-button-group img.jws-v10-logo{display:none}}';

        root.appendChild(style);
    }

    /**
     * Applies the shadow-root stylesheet to a player's skin, if it has upgraded.
     *
     * The v4 "play" layout drops the player into a full-bleed banner, so its
     * corners go square; everywhere else the skin's 1.75rem is pulled back to a
     * milder 10px. That test has to live out here rather than in the injected
     * stylesheet — a shadow-root selector cannot reach an ancestor outside its
     * own tree.
     */
    function styleSkin(playerEl, waiting) {

        var skin = playerEl.querySelector('video-skin');
        var root = skin && skin.shadowRoot;

        if (root) {
            playerEl.jwsV10StyleWaiting = false;

            /* --jws-player-radius comes from jws_player_v10.css, which also uses
               it for the pre-upgrade state. Reading it back rather than hard-coding
               keeps the two in step, and leaves the per-layout choice
               (.jws-player-global gets square corners) in the stylesheet. */
            var radius = getComputedStyle(playerEl).getPropertyValue('--jws-player-radius').trim();

            injectSkinStyles(root, radius || '10px');
            return;
        }

        /*
         * The skin has not upgraded yet, so there is nothing to style. Keep
         * looking once per animation frame rather than giving up: a rAF callback
         * runs *before* that frame is painted, so the stylesheet still lands in
         * the same frame the shadow tree first appears, instead of a paint later.
         *
         * Without this the only backstop was injectControlBarExtras(), which runs
         * after the media module and a requestAnimationFrame — far enough down the
         * chain that the skin's own 1.75rem corners get painted first and visibly
         * snap when the override finally arrives.
         */
        if (playerEl.jwsV10StyleWaiting && !waiting) {
            return;
        }

        playerEl.jwsV10StyleWaiting = true;

        if (playerEl.isConnected) {
            requestAnimationFrame(function () { styleSkin(playerEl, true); });
        }
    }

    /**
     * Puts the theme's player logo and the episode-list button into the v10
     * control bar, where the legacy jws skin used to put them.
     *
     * The packaged skin keeps its controls in a shadow root, so neither the
     * plugin stylesheet nor a plain appendChild from the page reaches them — the
     * elements and their styles both have to go inside. The docs are explicit
     * that a packaged skin's internals are not a supported API, so treat every
     * lookup as optional: a beta bump that renames `.media-button-group` should
     * cost these two extras, not the player.
     */
    function injectControlBarExtras(playerEl, config, attempt) {

        var wantsLogo = !!config.logo;
        var wantsEpisodes = episodeListForButton().length > 1;

        var skin = playerEl.querySelector('video-skin');
        var root = skin && skin.shadowRoot;

        if (!root) {
            attempt = attempt || 0;

            if (attempt < 20) {
                setTimeout(function () { injectControlBarExtras(playerEl, config, attempt + 1); }, 100);
            }

            return;
        }

        /* Normally already done from the load chain, well before first paint;
           this only covers a skin that upgraded late. Injection is idempotent. */
        styleSkin(playerEl);

        if (!wantsLogo && !wantsEpisodes) {
            return;
        }

        var groups = root.querySelectorAll('.media-button-group');

        if (!groups.length) {
            // The skin builds its shadow tree on upgrade, which can land a frame
            // or two after the element is defined.
            attempt = attempt || 0;

            if (attempt < 20) {
                setTimeout(function () { injectControlBarExtras(playerEl, config, attempt + 1); }, 100);
            } else {
                window.console && console.warn('[StreamVid] Video.js 10 skin has no .media-button-group — logo and episodes button skipped.');
            }

            return;
        }

        var group = groups[groups.length - 1];

        if (wantsEpisodes && !root.querySelector('.jws-v10-ep-btn')) {

            var label = (typeof streamvid_script !== 'undefined' && streamvid_script.episodes_label)
                ? streamvid_script.episodes_label
                : 'Episodes';

            var epBtn = document.createElement('button');

            epBtn.type = 'button';
            /*
             * Borrow the skin's own button classes so this control inherits its
             * sizing, hover, focus ring and disabled treatment instead of
             * carrying a second set that drifts every time the skin is bumped.
             * jws-v10-ep-btn stays on purely as the hook to find it again.
             */
            epBtn.className = 'jws-v10-ep-btn media-button media-button--subtle media-button--icon';
            epBtn.title = label;
            epBtn.setAttribute('aria-label', label);
            epBtn.setAttribute('commandfor', 'jws-v10-ep-tooltip');

            /* Inline SVG rather than the legacy Font Awesome glyph: the skin
               carries no icon font, and depending on one the theme may or may not
               load is how you get an empty button. Shaped like the skin's own
               icons — 18x18 box, filled paths taking `currentColor`, not stroked. */
            epBtn.innerHTML =
                '<svg class="media-icon media-icon--episodes" xmlns="http://www.w3.org/2000/svg"' +
                ' width="18" height="18" fill="currentColor" aria-hidden="true" viewBox="0 0 18 18">' +
                '<rect x="6" y="3" width="11" height="2" rx="1"></rect>' +
                '<rect x="6" y="8" width="11" height="2" rx="1"></rect>' +
                '<rect x="6" y="13" width="11" height="2" rx="1"></rect>' +
                '<rect x="1" y="3" width="2" height="2" rx="1"></rect>' +
                '<rect x="1" y="8" width="2" height="2" rx="1"></rect>' +
                '<rect x="1" y="13" width="2" height="2" rx="1"></rect>' +
                '</svg>';

            epBtn.addEventListener('click', function (event) {
                /* Keeps single_global.js's document-level "click anywhere closes
                   the panel" handler from firing on the very click that opened
                   it. That handler tests `closest('.jws-ep-list-btn')`, which can
                   never match from in here: the event is retargeted to the skin
                   host on its way out of the shadow tree. */
                event.stopPropagation();

                if (typeof jwsSingleGlobal !== 'undefined' && jwsSingleGlobal.toggle_episode_panel) {
                    jwsSingleGlobal.toggle_episode_panel();
                }

                /* Opening it for the first time while already fullscreen builds it
                   under .videos_player, which is outside the fullscreen element. */
                placeEpisodePanel(playerEl);
            });

            /* Every other control in the bar is a button followed by its own
               <media-tooltip>; match that so this one gets the same hover label. */
            var tip = document.createElement('media-tooltip');

            tip.id = 'jws-v10-ep-tooltip';
            tip.setAttribute('side', 'top');
            tip.className = 'media-surface media-tooltip';
            tip.innerHTML = '<media-tooltip-label></media-tooltip-label>';
            tip.querySelector('media-tooltip-label').textContent = label;

            /* Legacy sits it just before fullscreen; keep that order. */
            var fullscreen = group.querySelector('media-fullscreen-button');

            if (fullscreen) {
                group.insertBefore(epBtn, fullscreen);
                group.insertBefore(tip, fullscreen);
            } else {
                group.appendChild(epBtn);
                group.appendChild(tip);
            }
        }

        if (wantsLogo && !group.querySelector('.jws-v10-logo')) {
            var img = document.createElement('img');

            img.className = 'jws-v10-logo';
            img.src = config.logo;
            img.alt = '';

            group.appendChild(img);
        }
    }

    /* ---------------------------------------------------------------------- */
    /* Advertising (Google IMA)                                                */
    /* ---------------------------------------------------------------------- */

    /**
     * Runs the IMA SDK against a v10 player.
     *
     * videojs-ima is a Video.js 7 plugin and has no v10 port, but the SDK itself
     * never needed Video.js — it wants an ad container, a video element to render
     * ads into, and a playhead it can read. The plugin already drives IMA this way
     * for the iOS + YouTube case in single_global.js; this is the same shape,
     * generalised.
     *
     * Ads render into their own <video>, never into the content element. The
     * content may be a <youtube-video> or <vimeo-video>, which are not
     * HTMLVideoElements at all and cannot host an ad.
     *
     * Returns a small controller so the rest of the player can ask whether an ad
     * break is on screen.
     */
    function setupAds(playerEl, config, media, $wrap) {

        var state = {
            active: false,   // an ad break is on screen right now
            finished: false, // every break for this content has played (or failed)
            adCount: 0,      // ad breaks started so far
            /* On desktop IMA renders ads in its own element inside the container,
               not in the <video> handed to AdDisplayContainer (that one is only
               used for custom playback on iOS/Android). Ask the manager rather
               than the element when you want to know if an ad is really running. */
            remainingTime: function () { return -1; },
            contentComplete: function () { },
            destroy: function () { }
        };

        if (!config.adsTagUrl) {
            state.finished = true;
            return state;
        }

        if (typeof google === 'undefined' || !google.ima) {
            // Blocked SDK is the norm, not an exception — play the content.
            state.finished = true;
            window.console && console.warn('[StreamVid] IMA SDK unavailable — playing content without ads.');
            return state;
        }

        var wrapEl = $wrap[0] || playerEl;
        var adsLoader = null;
        var adsManager = null;
        var started = false;

        var adContainerEl = document.createElement('div');
        adContainerEl.className = 'jws-v10-ad-container';

        var adVideoEl = document.createElement('video');
        adVideoEl.className = 'jws-v10-ad-video';
        adVideoEl.setAttribute('playsinline', '');
        adVideoEl.setAttribute('webkit-playsinline', '');

        wrapEl.appendChild(adVideoEl);
        wrapEl.appendChild(adContainerEl);

        function adWidth() { return wrapEl.offsetWidth || 640; }
        function adHeight() { return wrapEl.offsetHeight || 360; }

        function showAdUi() {
            state.active = true;
            $wrap.addClass('jws-v10-ad-playing');
        }

        /*
         * Hide, do not tear down. With a VMAP carrying midrolls or a postroll the
         * same AdsManager is still needed after a break ends; destroying it here
         * would drop every later break. It also matters that the container stops
         * covering the video the moment the break ends — left on screen it eats
         * clicks and, on some Android builds, hides the video outright.
         */
        function hideAdUi() {
            state.active = false;
            $wrap.removeClass('jws-v10-ad-playing');
        }

        function destroyAds() {
            hideAdUi();
            state.finished = true;

            try { if (adsManager) adsManager.destroy(); } catch (e) { }
            adsManager = null;

            $(adContainerEl).remove();
            $(adVideoEl).remove();
        }

        state.destroy = destroyAds;

        function resizeAds() {
            if (!adsManager) {
                return;
            }
            var mode = (document.fullscreenElement || document.webkitFullscreenElement)
                ? google.ima.ViewMode.FULLSCREEN
                : google.ima.ViewMode.NORMAL;
            try { adsManager.resize(adWidth(), adHeight(), mode); } catch (e) { }
        }

        $(window).on('resize.jwsV10Ads', resizeAds);
        $(document).on('fullscreenchange.jwsV10Ads webkitfullscreenchange.jwsV10Ads', resizeAds);

        function onAdsManagerLoaded(event) {

            var settings = new google.ima.AdsRenderingSettings();
            settings.restoreCustomPlaybackStateOnAdBreakComplete = true;

            // IMA schedules midrolls off this, so it has to read the real content
            // clock — not the ad one.
            var playhead = {
                currentTime: 0,
                duration: 0
            };

            Object.defineProperty(playhead, 'currentTime', {
                get: function () { return media.currentTime || 0; }
            });
            Object.defineProperty(playhead, 'duration', {
                get: function () { return isFinite(media.duration) ? media.duration : 0; }
            });

            adsManager = event.getAdsManager(playhead, settings);

            state.remainingTime = function () {
                try { return adsManager ? adsManager.getRemainingTime() : -1; } catch (e) { return -1; }
            };

            adsManager.addEventListener(google.ima.AdErrorEvent.Type.AD_ERROR, onAdError);

            adsManager.addEventListener(google.ima.AdEvent.Type.STARTED, function (e) {
                state.adCount++;
                $(document.body).trigger('jws_player_v10_ad_started', [playerEl, e.getAd && e.getAd()]);
            });

            adsManager.addEventListener(google.ima.AdEvent.Type.CONTENT_PAUSE_REQUESTED, function () {
                showAdUi();
                try { media.pause(); } catch (e) { }
            });

            adsManager.addEventListener(google.ima.AdEvent.Type.CONTENT_RESUME_REQUESTED, function () {
                hideAdUi();
                if (!media.ended) {
                    media.play();
                }
            });

            /*
             * ALL_ADS_COMPLETED is the only event that means "no more breaks".
             * A per-ad "is this the last one" check races the SDK and leaves the
             * container up over the video.
             */
            adsManager.addEventListener(google.ima.AdEvent.Type.ALL_ADS_COMPLETED, function () {
                destroyAds();
                if (!media.ended) {
                    media.play();
                }
            });

            try {
                adsManager.init(adWidth(), adHeight(), google.ima.ViewMode.NORMAL);
                adsManager.start();
            } catch (e) {
                onAdError(e);
            }
        }

        function onAdError(error) {

            // AdErrorEvent wraps an AdError; both stringify to a minified class
            // name, so pull the code and message out explicitly or the log says
            // nothing at all.
            var detail = error;

            try {
                var adError = (error && typeof error.getError === 'function') ? error.getError() : error;

                if (adError && typeof adError.getErrorCode === 'function') {
                    detail = 'code ' + adError.getErrorCode() +
                        ' / vast ' + (typeof adError.getVastErrorCode === 'function' ? adError.getVastErrorCode() : '?') +
                        ' — ' + adError.getMessage();
                }
            } catch (e) { /* fall back to the raw value */ }

            window.console && console.warn('[StreamVid] IMA ad error, continuing with content:', detail);

            destroyAds();
            media.play();
        }

        var displayContainer = new google.ima.AdDisplayContainer(adContainerEl, adVideoEl);

        adsLoader = new google.ima.AdsLoader(displayContainer);
        adsLoader.addEventListener(google.ima.AdsManagerLoadedEvent.Type.ADS_MANAGER_LOADED, onAdsManagerLoaded, false);
        adsLoader.addEventListener(google.ima.AdErrorEvent.Type.AD_ERROR, onAdError, false);

        state.contentComplete = function () {
            try { adsLoader.contentComplete(); } catch (e) { }
        };

        /**
         * Kicks the ad request off. Must run inside the user's click when there is
         * one: iOS and Safari only let a media element start from a gesture, and
         * initialize() is what claims the ad element for later playback.
         */
        function startAds() {

            if (started) {
                return;
            }

            started = true;

            try {
                displayContainer.initialize();
            } catch (e) { /* already initialised */ }

            var request = new google.ima.AdsRequest();

            request.adTagUrl = config.adsTagUrl;
            request.linearAdSlotWidth = adWidth();
            request.linearAdSlotHeight = adHeight();
            request.nonLinearAdSlotWidth = adWidth();
            request.nonLinearAdSlotHeight = Math.floor(adHeight() / 3);

            try {
                adsLoader.requestAds(request);
            } catch (e) {
                onAdError(e);
            }
        }

        /*
         * The skin's own play button is the gesture. Letting the content start and
         * pausing it back is what videojs-contrib-ads did too — it keeps every
         * entry point (big play button, hotkey, autoplay) on one path.
         */
        media.addEventListener('play', function () {

            if (state.finished || state.active) {
                return;
            }

            try { media.pause(); } catch (e) { }
            startAds();
        });

        // Autoplay means no gesture is coming; the muted attribute is what makes
        // an unprompted ad legal.
        if (config.autoplay) {
            startAds();
        }

        return state;
    }

    /* ---------------------------------------------------------------------- */
    /* Per-player wiring                                                       */
    /* ---------------------------------------------------------------------- */

    function attachBehaviour(playerEl, config) {

        var media = playerEl.querySelector(config.mediaTag);

        if (!media) {
            return;
        }

        var $wrap = $(playerEl).closest('.videos_player');
        var hasPlayed = false;
        var lastSavedTime = 0;
        var resumeAt = resumeTimeFor(config);
        var continueWatching = (typeof streamvid_script !== 'undefined') && streamvid_script.video_continue_watching === 'yes';

        $wrap.removeClass('vjs-waiting loading');

        injectControlBarExtras(playerEl, config);

        $(document).on('fullscreenchange.jwsV10 webkitfullscreenchange.jwsV10', function () {
            placeEpisodePanel(playerEl);
        });

        var willPrompt = resumeAt > RESUME_PROMPT_MIN && continueWatching;

        /*
         * Autoplay has to be off BEFORE setupAds runs, not after.
         *
         * setupAds reads config.autoplay and starts the ad break itself, and
         * the content plays the moment that break ends — so disabling autoplay
         * further down left the prompt being answered for the viewer: the
         * video started, the prompt vanished, and playback ran from zero.
         *
         * The legacy engine dropped videojs's `autoplay` option for the same
         * reason. Here it is both a config value setupAds acts on and an
         * attribute the browser may already be acting on, so both have to go.
         */
        if (willPrompt) {
            config.autoplay = false;
            media.autoplay = false;
            media.removeAttribute('autoplay');

            try { media.pause(); } catch (e) { }
        }

        var ads = setupAds(playerEl, config, media, $wrap);

        if (resumeAt > 0) {

            if (willPrompt) {
                buildResumePrompt(playerEl, $wrap, media, resumeAt);
            } else {
                seekTo(media, resumeAt);
            }
        }

        media.addEventListener('playing', function () {
            hasPlayed = true;
        });

        media.addEventListener('seeked', function () {
            lastSavedTime = media.currentTime;
        });

        var saveTimer = setInterval(function () {

            // The markup is thrown away wholesale when an episode is switched.
            if (!playerEl.isConnected) {
                clearInterval(saveTimer);
                return;
            }

            if (!hasPlayed) {
                return;
            }

            var currentTime = media.currentTime;
            var duration = media.duration;

            if (!isFinite(currentTime) || !isFinite(duration)) {
                return;
            }

            if (currentTime - lastSavedTime >= SAVE_INTERVAL) {
                saveVideoProgress({ id: config.postId, time: currentTime, endtime: duration });
                lastSavedTime = currentTime;
            }

        }, 1000);

        media.addEventListener('ended', function () {

            var duration = media.duration;

            if (isFinite(duration)) {
                saveVideoProgress({ id: config.postId, time: duration, endtime: duration });
            }

            // Tells IMA the content is over, which is what releases a postroll.
            ads.contentComplete();

            var list = (typeof streamvid_script !== 'undefined') ? streamvid_script.episodes_list : null;

            if (!Array.isArray(list)) {
                return;
            }

            var index = list.findIndex(function (item) { return item.id == config.postId; });

            if (index === -1 || index >= list.length - 1) {
                return;
            }

            var next = list[index + 1];

            /* Navigating on a timer would cut a postroll off mid-ad, so wait for
               the break to finish first. */
            function advance() {

                if (ads.active) {
                    setTimeout(advance, 500);
                    return;
                }

                if (typeof jwsThemeModule !== 'undefined' && jwsThemeModule.show_notification) {
                    jwsThemeModule.show_notification(streamvid_script.next_episodes, 'success');
                }

                setTimeout(function () { window.location.href = next.link; }, 2000);
            }

            /* One tick of grace for IMA to raise CONTENT_PAUSE_REQUESTED before
               deciding no postroll is coming. */
            setTimeout(advance, 300);
        });

        playerEl.jwsV10 = { media: media, config: config, ads: ads };

        $(document.body).trigger('jws_player_v10_ready', [playerEl, media, config]);
    }

    /**
     * A module that never arrives — an ad blocker eating the CDN or the YouTube
     * embed, an offline CDN — would otherwise leave a spinner that never stops
     * and a play button that throws NO_TARGET. Say so instead.
     */
    function failPlayer(playerEl, error) {

        var $wrap = $(playerEl).closest('.videos_player');

        if ($wrap.find('.jws-v10-error').length) {
            return;
        }

        $wrap.removeClass('vjs-waiting loading').addClass('jws-v10-failed');

        $wrap.append(
            $('<div class="jws-v10-error"><span></span></div>')
                .find('span')
                .text('The video player could not load. Check that jsDelivr and the video host are not being blocked.')
                .end()
        );

        window.console && console.error('[StreamVid] Video.js 10 failed to load:', error);
    }

    function initPlayer(playerEl) {

        if (playerEl.jwsV10Init) {
            return;
        }

        playerEl.jwsV10Init = true;

        var config;

        try {
            config = JSON.parse(playerEl.getAttribute('data-jws-v10') || '{}');
        } catch (e) {
            return;
        }

        if (!config.mediaTag) {
            return;
        }

        var locale = matchLocale(jwsPlayerV10.locale);

        /*
         * Preset first, media adapter second — the order matters, and getting it
         * wrong fails in a way that looks like it works.
         *
         * A custom media element registers itself with the player context when it
         * upgrades. Upgrade it before <video-player> is defined and that
         * announcement goes nowhere: the element still plays (autoplay is the
         * browser's doing, not the player's), but the store never gets a target,
         * so every control throws `StoreError: NO_TARGET` on click. Loading the
         * two modules in parallel hits this whenever the adapter wins the race,
         * which it usually does — media/hlsjs-video.js is 362 bytes against
         * video.js's 67KB.
         */
        loadModule('video.js').then(function () {
            /*
             * Style the skin here and nowhere later. video.js defines <video-skin>
             * as it evaluates, which upgrades the element and builds its shadow
             * tree; this .then() is the microtask straight after that, so the
             * stylesheet lands before the browser has painted a single frame.
             * Injecting it further down the chain — past the media module, past
             * the requestAnimationFrame — guarantees at least one painted frame
             * with the skin's own 1.75rem corners, which reads as a visible jump
             * the moment the player appears.
             */
            styleSkin(playerEl);

            return config.mediaModule ? loadModule(config.mediaModule) : null;
        }).then(function () {
            // A missing translation should never stop the player from loading.
            return locale ? loadModule('locales/' + locale + '.js').catch(function () { }) : null;
        }).then(function () {
            /* A module can resolve without registering its element. Racing the
               wait means that shows up as an error rather than a stuck spinner. */
            return Promise.race([
                customElements.whenDefined(config.mediaTag === 'video' ? 'video-player' : config.mediaTag),
                new Promise(function (resolve, reject) {
                    setTimeout(function () { reject(new Error('Timed out waiting for <' + config.mediaTag + '> to register.')); }, 20000);
                })
            ]);
        }).then(function () {
            /* One frame for <video-player> to upgrade and pick the media element
               up, so the skin is never interactive before the store has it. */
            return new Promise(function (resolve) { requestAnimationFrame(resolve); });
        }).then(function () {
            attachBehaviour(playerEl, config);
        }).catch(function (error) {
            failPlayer(playerEl, error);
        });
    }

    function initAll() {
        $('video-player.jws_player_v10').each(function () {
            initPlayer(this);
        });
    }

    $(function () {

        initAll();

        /*
         * single_global.js replaces .videos_player wholesale when an episode or
         * a source is picked, and it only re-runs the legacy start_player when a
         * `videojs` global exists — which it does not here. Watching the DOM is
         * what picks the new markup up instead.
         */
        var pending = null;

        new MutationObserver(function () {
            clearTimeout(pending);
            pending = setTimeout(initAll, 50);
        }).observe(document.body, { childList: true, subtree: true });
    });

})(jQuery);
