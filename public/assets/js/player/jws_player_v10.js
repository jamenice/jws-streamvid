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

            if (typeof streamvid_script !== 'undefined' && streamvid_script.is_drama_episode) {
                data.drama = streamvid_script.episodes_drama;
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
     *
     * An iframe embed takes the seek without honouring it. YouTube accepts a
     * seekTo() issued before it has the video itself, starts from zero anyway,
     * and the adapter's own 50ms poll then writes that zero back over the
     * position — `seeking` at 45s followed by `seeked` at 0 is exactly what a
     * resumed watch looked like. The position only sticks once playback is
     * actually running, so for those the seek is made again on the first
     * `playing`, and only if it did not take the first time.
     */
    function seekTo(media, time) {

        var apply = function () {
            try { media.currentTime = time; } catch (e) { }
        };

        if (media.readyState >= 1) {
            apply();
        } else {
            media.addEventListener('loadedmetadata', apply, { once: true });
        }

        if (time > 0 && (media.localName === 'youtube-video' || media.localName === 'vimeo-video')) {

            media.addEventListener('playing', function reapply() {

                media.removeEventListener('playing', reapply);

                /* A second's grace: the embed may have landed close enough on
                   its own, and seeking again would only stutter. */
                if (Math.abs(media.currentTime - time) > 1.5) {
                    apply();
                }

            });
        }
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
    /* Fullscreen orientation                                                  */
    /* ---------------------------------------------------------------------- */

    /*
     * Whether the screen should be turned for this viewer at all.
     *
     * Desktop Chrome exposes `screen.orientation.lock` and rejects every call
     * to it, so feature detection alone is not enough — a phone or tablet is
     * the only place a lock means anything. iPadOS reports itself as a Mac,
     * hence the touch-point check.
     */
    function isHandheld() {

        var ua = navigator.userAgent || '';

        if (/Android|iPhone|iPod|IEMobile|Opera Mini|Mobile/i.test(ua)) {
            return true;
        }

        return /iPad|Macintosh/.test(ua) && navigator.maxTouchPoints > 1;
    }


    /* Set only while *this* script holds a lock, so leaving fullscreen never
       releases an orientation lock some other part of the page took out. */
    var orientationLocked = false;

    /**
     * Turns the phone to landscape on the way into fullscreen and hands the
     * orientation back on the way out.
     *
     * Video.js 10 puts the skin's <media-container> into fullscreen through the
     * Fullscreen API and stops there — unlike a native <video> fullscreen on
     * iOS, that does nothing to the device orientation, so a phone held upright
     * gets a letterboxed strip. The Screen Orientation API is what closes that
     * gap, and it is deliberately called *after* the fullscreen change rather
     * than before: `lock()` rejects with a SecurityError unless the document is
     * already fullscreen.
     */
    function syncFullscreenOrientation(media) {

        var orientation = window.screen && window.screen.orientation;

        if (!orientation || typeof orientation.lock !== 'function' || !isHandheld()) {
            return;
        }

        if (!(document.fullscreenElement || document.webkitFullscreenElement)) {

            if (orientationLocked) {
                orientationLocked = false;
                try { orientation.unlock(); } catch (e) { }
            }

            return;
        }

        /*
         * A portrait video — a vertical clip, a phone-shot upload — is worse off
         * turned sideways, so leave those alone. Dimensions are still 0 before
         * metadata arrives; landscape is the right guess there.
         */
        var w = media.videoWidth || 0;
        var h = media.videoHeight || 0;

        if (w && h && h > w) {
            return;
        }

        var pending;

        try {
            pending = orientation.lock('landscape');
        } catch (e) {
            return;
        }

        orientationLocked = true;

        /* Rejects on a device that cannot turn (most tablets in a desktop
           browser, a screen the OS has already locked). Nothing to report — but
           the flag has to come back off, or the next exit unlocks a lock that
           was never taken. */
        if (pending && typeof pending.catch === 'function') {
            pending.catch(function () { orientationLocked = false; });
        }
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
    function injectSkinStyles(root, cornerRadius, hideFullscreen) {

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
            '@media (max-width:767px){.media-button-group img.jws-v10-logo{display:none}}' +
            /* <media-poster>'s <slot name="poster"> falls back to its own bare
               <img>, with no src, whenever we skip rendering img[slot="poster"]
               for a video with no poster. Un-styled, that empty <img> still
               takes up the poster's 100%x100% box and shows as a border/outline
               over the video. It's only ever this fallback: a real poster comes
               from our own <img slot="poster" src="..."> and always has a src. */
            'media-poster img:not([src]){display:none}' +

            /*
             * Centre play button.
             *
             * The default v10 skin has no equivalent of the legacy
             * .vjs-big-play-button — play/pause live in the control bar, and the
             * only thing in the middle is a transient status flash you cannot
             * click. On a poster that reads as a player with nothing to press,
             * and after an ad break WebKit refused to resume it is the one thing
             * that gets the video going again.
             *
             * <media-play-button> reflects the store's own `data-paused`, so
             * visibility needs no scripting: it is on screen exactly while the
             * content is not playing.
             */
            'media-container.media-default-skin .jws-v10-big-play{position:absolute;inset:0;' +
            'z-index:11;place-items:center;width:66px;height:66px;margin:auto;padding:0;' +
            'display:none;border:0;border-radius:50%;color:#fff;background:rgba(0,0,0,.55);' +
            'backdrop-filter:blur(6px);cursor:pointer;' +
            'transition:background .15s ease-out,scale .15s ease-out}' +
            'media-container.media-default-skin .jws-v10-big-play[data-paused]{display:grid}' +
            'media-container.media-default-skin .jws-v10-big-play:hover{background:rgba(0,0,0,.78);scale:1.06}' +
            /* Optical centring: a triangle's visual mass sits left of its box. */
            'media-container.media-default-skin .jws-v10-big-play svg{width:30px;height:30px;' +
            'margin-inline-start:4px}' +

            /*
             * Startup spinner.
             *
             * The skin's own buffering indicator is driven by `waiting && !paused`
             * (plus a 500ms delay), which is right for a mid-playback stall and
             * useless for the one window that actually takes time here: the
             * content is still paused while the ES modules are fetched, the
             * custom element is waited on and hls.js pulls the manifest. Nothing
             * is drawn for any of it. The element is already in the skin, so this
             * only has to show it — jws_player_v10.js owns the attribute and drops
             * it once the media element has registered.
             *
             * The compound is load-bearing. `--media-spinner-animation:none` is
             * declared at `.media-default-skin .media-buffering-indicator:not(
             * [data-visible])` — (0,3,0) — so a lone `:host([...]) .class` at
             * (0,2,0) is overruled and the spinner sits frozen. Restating the
             * container beats it without reaching for !important.
             *
             * `initial` rather than the animation itself: the keyframes live in a
             * <style> inside the spinner SVG, which reads the token as
             * `var(--media-spinner-animation, <the real animation>)`. Setting a
             * custom property to `initial` makes it guaranteed-invalid, so that
             * fallback applies — and the skin keeps owning its own timing instead
             * of this file carrying a copy that goes stale on the next bump.
             */
            ':host([data-jws-loading]) media-container.media-default-skin .media-buffering-indicator' +
            '{display:grid;--media-spinner-animation:initial}' +
            /* One or the other, never a spinner with a play button behind it. */
            ':host([data-jws-loading]) media-container.media-default-skin .jws-v10-big-play' +
            '{display:none}' +

            /* Drama short is a vertical, single-episode-at-a-time feed — a
               fullscreen toggle is redundant there and the icon just crowds
               the compact control bar, so the skin's own button is hidden
               rather than removing it from the packaged markup. */
            (hideFullscreen ? 'media-fullscreen-button{display:none!important}' : '');

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
    /* Paired with the attribute styleSkin() sets. Kept out here because both the
       ready path and the failure path have to be able to stop the spinner. */
    function clearLoadingFlag(playerEl) {

        var skin = playerEl.querySelector('video-skin');

        if (skin) {
            skin.removeAttribute('data-jws-loading');
        }
    }

    function styleSkin(playerEl, waiting) {

        var skin = playerEl.querySelector('video-skin');
        var root = skin && skin.shadowRoot;

        if (root) {
            playerEl.jwsV10StyleWaiting = false;

            /* Set here rather than in player.php: the rule it drives lives in the
               stylesheet injected just below, and until that lands the attribute
               would mean nothing. attachBehaviour() clears it. */
            if (!playerEl.jwsV10Ready) {
                skin.setAttribute('data-jws-loading', '');
            }

            /* --jws-player-radius comes from jws_player_v10.css, which also uses
               it for the pre-upgrade state. Reading it back rather than hard-coding
               keeps the two in step, and leaves the per-layout choice
               (.jws-player-global gets square corners) in the stylesheet. */
            var radius = getComputedStyle(playerEl).getPropertyValue('--jws-player-radius').trim();

            injectSkinStyles(root, radius || '10px', !!playerEl.closest('.sv-short-player'));
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
     * Adds the centre play button the default v10 skin does not ship.
     *
     * <media-play-button> is the skin's own element, so this inherits the whole
     * behaviour — the click toggles through the store, the aria-label tracks the
     * state and translations, and `data-paused` is reflected for the stylesheet
     * to key off. Only the markup and the placement are ours.
     *
     * It goes in <media-container> rather than <media-controls>: the controls
     * fade out on idle, and a play button that disappears while the video is
     * paused is worse than none at all.
     */
    function injectCenterPlayButton(root) {

        var container = root.querySelector('media-container');

        if (!container || root.querySelector('.jws-v10-big-play')) {
            return;
        }

        var btn = document.createElement('media-play-button');

        btn.className = 'jws-v10-big-play';

        /* Sized to the box in the injected stylesheet rather than to the skin's
           18x18 control icons — this one is read from across the room. */
        btn.innerHTML =
            '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">' +
            '<path d="M8 5v14l11-7z"></path>' +
            '</svg>';

        /* Before <media-controls>, so the bar and its menus still stack over it. */
        var controls = container.querySelector('media-controls');

        if (controls) {
            container.insertBefore(btn, controls);
        } else {
            container.appendChild(btn);
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

        /* Above the early return: the centre play button is not conditional on
           the theme having a logo or the post having episodes. */
        injectCenterPlayButton(root);

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

        /*
         * Ad-path tracing, off unless asked for: `?jwsdebug=1` on the URL, or
         * `window.jwsPlayerV10Debug = true` from the console before pressing play.
         *
         * Everything this path does happens inside the IMA SDK or inside WebKit's
         * gesture rules, and both fail by doing nothing at all. Without a trace
         * the only signal is "the ad did not play", which does not distinguish a
         * tag that never resolved from a creative that never rendered from a
         * resume that was refused.
         */
        var adDebug = /[?&]jwsdebug=1/.test(window.location.search) || !!window.jwsPlayerV10Debug;

        function adLog() {

            if (!adDebug || !window.console) {
                return;
            }

            var args = Array.prototype.slice.call(arguments);

            args.unshift('[StreamVid ads]');
            console.log.apply(console, args);
        }

        adLog('setup', {
            mediaTag: config.mediaTag,
            tag: String(config.adsTagUrl).slice(0, 120)
        });

        /*
         * True from the moment ads are requested until the first break has
         * either taken the screen or been ruled out. It is the only window in
         * which this code holds the content back; after it, IMA owns the
         * pausing and resuming.
         */
        var holdingForFirstBreak = false;

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

        /* ------------------------------------------------------------------ */
        /* Ad controls                                                         */
        /* ------------------------------------------------------------------ */

        /*
         * IMA draws only the skip button and whatever click-through the creative
         * carries; play/pause, sound and fullscreen have always been the
         * publisher's to provide. The legacy engine got them from videojs-ima,
         * which is not ported to v10 — and because the skin is hidden for the
         * duration of a break (it would otherwise scrub content that is not on
         * screen), the viewer was left with nothing but Skip.
         */
        var $adBar = $(
            '<div class="jws-v10-ad-bar">' +
                '<button type="button" class="jws-v10-ad-btn jws-v10-ad-toggle"></button>' +
                '<button type="button" class="jws-v10-ad-btn jws-v10-ad-mute"></button>' +
                '<span class="jws-v10-ad-count"></span>' +
                '<button type="button" class="jws-v10-ad-btn jws-v10-ad-fs"></button>' +
            '</div>'
        );

        var adPaused = false;
        var adMuted = media.muted;
        var countTimer = null;

        function adIcon(name) {
            return '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentcolor" aria-hidden="true">' + name + '</svg>';
        }

        var AD_ICONS = {
            play: '<path d="M8 5v14l11-7z"></path>',
            pause: '<path d="M6 5h4v14H6zm8 0h4v14h-4z"></path>',
            sound: '<path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3a4.5 4.5 0 0 0-2.5-4v8a4.5 4.5 0 0 0 2.5-4z"></path>',
            muted: '<path d="M3 9v6h4l5 5V4L7 9H3zm18.6-.6-1.4-1.4-2.2 2.2-2.2-2.2-1.4 1.4 2.2 2.2-2.2 2.2 1.4 1.4 2.2-2.2 2.2 2.2 1.4-1.4-2.2-2.2z"></path>',
            enter: '<path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"></path>',
            exit: '<path d="M5 16h3v3h2v-5H5v2zm3-8H5v2h5V5H8v3zm6 11h2v-3h3v-2h-5v5zm2-11V5h-2v5h5V8h-3z"></path>'
        };

        function fullscreenEl() {
            return document.fullscreenElement || document.webkitFullscreenElement || null;
        }

        function syncAdBar() {
            $adBar.find('.jws-v10-ad-toggle').html(adIcon(adPaused ? AD_ICONS.play : AD_ICONS.pause));
            $adBar.find('.jws-v10-ad-mute').html(adIcon(adMuted ? AD_ICONS.muted : AD_ICONS.sound));
            $adBar.find('.jws-v10-ad-fs').html(adIcon(fullscreenEl() ? AD_ICONS.exit : AD_ICONS.enter));
        }

        function drawCountdown() {

            var left = state.remainingTime();
            var $out = $adBar.find('.jws-v10-ad-count');
            var label = (typeof streamvid_script !== 'undefined' && streamvid_script.ad_label) || 'Ad';

            // -1 is the SDK saying it does not know — a live or unmeasured pod.
            $out.text(left >= 0 ? label + ' · ' + formatTime(left) : label);
        }

        /* ------------------------------------------------------------------ */
        /* Break-over watchdog                                                 */
        /* ------------------------------------------------------------------ */

        /*
         * Set once the ad element itself has been seen playing.
         *
         * Which element the creative actually plays in is not ours to decide.
         * Desktop IMA builds its own inside the ad container and never touches
         * the <video> handed to AdDisplayContainer; iOS and Android play into
         * that element directly ("custom playback"). Only the second case gives
         * us a playhead to watch, so the watchdog below arms itself on evidence
         * rather than on a user-agent guess.
         */
        var adElementPlayed = false;

        adVideoEl.addEventListener('playing', function () {
            adElementPlayed = true;
        });

        var quietTicks = 0;

        /*
         * Whether the creative now on screen is the last of its pod.
         *
         * This is what decides how long the watchdog waits, and the wait is what
         * the viewer feels as dead air after the ad. STARTED fills it in from the
         * SDK's own pod info; true is the right default because a plain VAST tag
         * is a pod of one and never reports otherwise.
         */
        var lastInPod = true;

        /**
         * Ends the break when the SDK stops talking.
         *
         * On iOS the creative plays through and then IMA simply goes quiet: no
         * COMPLETE, no CONTENT_RESUME_REQUESTED, no ALL_ADS_COMPLETED. Every
         * teardown this file has hangs off those events, so nothing ran — the ad
         * bar stayed up, `.jws-v10-ad-playing` stayed on the wrapper, and the
         * `visibility: hidden` it puts on the skin left a black rectangle where
         * the video should have been. That black screen is the bug; the content
         * never being asked to resume is the same silence seen from the other end.
         *
         * A watchdog is the only answer available: there is no second event to
         * listen for, and the SDK cannot be made to speak.
         */
        function checkBreakOver() {

            if (!state.active || adPaused) {
                quietTicks = 0;
                return;
            }

            var over;

            if (adElementPlayed) {
                /* Custom playback: the element IS the ad, so its own playhead is
                   authoritative. A pod plays back to back through one element,
                   and the gap between two ads is well under the grace below. */
                over = adVideoEl.ended || adVideoEl.paused;
            } else {
                /* IMA's own element, which we cannot see. Its clock is all there
                   is — and `-1` means the SDK does not know, never "finished", so
                   an unmeasured pod is deliberately left alone rather than being
                   torn down early. */
                over = state.remainingTime() === 0;
            }

            if (!over) {
                quietTicks = 0;
                return;
            }

            /*
             * Two graces, because the two cases are not equally certain.
             *
             * A creative that reached `ended` and was the last of its pod is a
             * finished break, full stop — nothing more is coming, and the only
             * thing left to wait for is a healthy SDK getting its own event in
             * first, which takes a tick or two at most. 750ms.
             *
             * Everything else is a guess: an element merely paused, an unfinished
             * pod, a manager clock reading zero. There the wait also has to cover
             * the gap between two ads while the next creative loads, so it stays
             * at ~3s. STARTED zeroes the count as well.
             *
             * The long wait was the dead air after the ad — it applied to the
             * single-preroll case too, which is the one case that never needed it.
             */
            var settled = adElementPlayed && adVideoEl.ended && lastInPod;

            if (++quietTicks < (settled ? 3 : 12)) {
                return;
            }

            quietTicks = 0;

            adLog('break went quiet with no CONTENT_RESUME_REQUESTED — recovering');
            window.console && console.warn('[StreamVid] The ad break ended without telling us; resuming the content.');

            /*
             * Torn down rather than merely hidden, unlike a normal break end. The
             * SDK has already proved it is not driving this manager any more, so
             * keeping it around for a midroll it will never announce would only
             * risk a second silent black screen later in the video.
             */
            destroyAds();
            resumeContent();
        }

        $adBar.on('click', '.jws-v10-ad-toggle', function () {

            if (!adsManager) {
                return;
            }

            try {
                if (adPaused) {
                    adsManager.resume();
                } else {
                    adsManager.pause();
                }
            } catch (e) { /* the break ended under us */ }
        });

        $adBar.on('click', '.jws-v10-ad-mute', function () {

            adMuted = !adMuted;

            try {
                if (adsManager) {
                    adsManager.setVolume(adMuted ? 0 : 1);
                }
            } catch (e) { }

            // Keep the content in step, so unmuting the ad does not hand back a
            // silent film when the break ends.
            media.muted = adMuted;

            syncAdBar();
        });

        $adBar.on('click', '.jws-v10-ad-fs', function () {

            var current = fullscreenEl();

            try {
                if (current) {
                    (document.exitFullscreen || document.webkitExitFullscreen).call(document);
                } else {
                    (wrapEl.requestFullscreen || wrapEl.webkitRequestFullscreen).call(wrapEl);
                }
            } catch (e) { }
        });

        function showAdUi() {

            state.active = true;
            $wrap.addClass('jws-v10-ad-playing');

            /*
             * Parented to whatever is actually fullscreen, the way the episode
             * panel is: in fullscreen the element on screen is inside the skin's
             * shadow root, and a bar left in .videos_player would simply not be
             * rendered.
             */
            var host = fullscreenEl() || wrapEl;

            if ($adBar[0].parentElement !== host) {
                host.appendChild($adBar[0]);
            }

            syncAdBar();
            drawCountdown();

            /* One timer for both: the watchdog needs exactly the cadence the
               countdown already runs at, and a second interval would only be
               another thing to clear. */
            clearInterval(countTimer);
            countTimer = setInterval(function () {
                drawCountdown();
                checkBreakOver();
            }, 250);
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

            clearInterval(countTimer);
            countTimer = null;

            $adBar.detach();
        }

        function destroyAds() {
            hideAdUi();
            state.finished = true;

            try { if (adsManager) adsManager.destroy(); } catch (e) { }
            adsManager = null;

            $(adContainerEl).remove();
            $(adVideoEl).remove();
            $adBar.remove();
        }

        state.destroy = destroyAds;

        /**
         * Every "the break is over, carry on" path goes through here.
         *
         * A bare media.play() hides the one failure that matters. WebKit refuses a
         * resume it does not consider gesture-backed by rejecting the promise, and
         * an unhandled rejection says nothing at all — no log, no UI, just a video
         * that sits there. That is what made this look like a dead player rather
         * than a blocked one.
         *
         * The refusal itself is now survivable: the content is paused, so the
         * centre play button is on screen and one tap starts it. This only has to
         * make the reason findable in the console.
         */
        function resumeContent() {

            if (media.ended) {
                return;
            }

            var pending;

            try {
                pending = media.play();
            } catch (e) {
                pending = null;
            }

            if (pending && typeof pending.catch === 'function') {
                pending.then(function () {
                    adLog('content resumed');
                }, function (error) {
                    window.console && console.warn(
                        '[StreamVid] The content did not resume after the ad break:',
                        (error && error.name) || error
                    );
                });
            }
        }

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

        $(document).on('fullscreenchange.jwsV10Ads webkitfullscreenchange.jwsV10Ads', function () {

            resizeAds();

            /* Entering or leaving fullscreen changes which element is on
               screen, so the bar has to move with it — and its own icon has
               just gone stale. */
            if (state.active) {
                showAdUi();
            }
        });

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

            /* Every event the SDK defines, so a break that dies halfway through
               says where it stopped instead of just never appearing. */
            if (adDebug) {
                Object.keys(google.ima.AdEvent.Type).forEach(function (key) {
                    adsManager.addEventListener(google.ima.AdEvent.Type[key], function () {
                        adLog('event', key);
                    });
                });
            }

            state.remainingTime = function () {
                try { return adsManager ? adsManager.getRemainingTime() : -1; } catch (e) { return -1; }
            };

            adsManager.addEventListener(google.ima.AdErrorEvent.Type.AD_ERROR, onAdError);

            adsManager.addEventListener(google.ima.AdEvent.Type.STARTED, function (e) {

                state.adCount++;
                adPaused = false;

                /* A fresh ad in the pod: whatever quiet the watchdog had counted
                   during the gap was the SDK working, not the SDK giving up. */
                quietTicks = 0;

                var ad = e.getAd && e.getAd();

                /*
                 * How many ads are left decides how patient the watchdog has to
                 * be. A total of -1 is the SDK saying it does not know — a live
                 * pod — and an unknown remainder is not a last ad, so those keep
                 * the long wait rather than risking a teardown mid-break.
                 */
                try {
                    var pod = ad && ad.getAdPodInfo && ad.getAdPodInfo();
                    var total = pod ? pod.getTotalAds() : 0;

                    lastInPod = !pod || (total > 0 && pod.getAdPosition() >= total);
                } catch (err) {
                    lastInPod = true;
                }

                adLog('ad started', { adCount: state.adCount, lastInPod: lastInPod });

                syncAdBar();
                $(document.body).trigger('jws_player_v10_ad_started', [playerEl, ad]);
            });

            /* The SDK pauses an ad on its own too — a click-through opening a
               new tab is the usual one — so the button follows the manager
               rather than assuming its own clicks are the only source. */
            adsManager.addEventListener(google.ima.AdEvent.Type.PAUSED, function () {
                adPaused = true;
                syncAdBar();
            });

            adsManager.addEventListener(google.ima.AdEvent.Type.RESUMED, function () {
                adPaused = false;
                syncAdBar();
            });

            // The break starts at whatever the content was set to.
            try { adsManager.setVolume(adMuted ? 0 : 1); } catch (e) { }

            adsManager.addEventListener(google.ima.AdEvent.Type.CONTENT_PAUSE_REQUESTED, function () {
                holdingForFirstBreak = false;
                showAdUi();
                try { media.pause(); } catch (e) { }
            });

            adsManager.addEventListener(google.ima.AdEvent.Type.CONTENT_RESUME_REQUESTED, function () {
                hideAdUi();
                resumeContent();
            });

            /*
             * ALL_ADS_COMPLETED is the only event that means "no more breaks".
             * A per-ad "is this the last one" check races the SDK and leaves the
             * container up over the video.
             */
            adsManager.addEventListener(google.ima.AdEvent.Type.ALL_ADS_COMPLETED, function () {
                holdingForFirstBreak = false;
                destroyAds();
                resumeContent();
            });

            try {
                adsManager.init(adWidth(), adHeight(), google.ima.ViewMode.NORMAL);
                adsManager.start();
                adLog('manager started');
            } catch (e) {
                onAdError(e);
                return;
            }

            /*
             * A VMAP whose breaks are all midrolls (or a lone postroll) opens
             * with no ad at all, and the SDK says nothing about it — no
             * CONTENT_PAUSE_REQUESTED, so nothing arrives to undo the pause
             * this flow took in order to make the ad request, and the video
             * simply never starts.
             *
             * The cue points say so outright: they are the break offsets, and
             * a preroll is the offset 0 among them. No zero means the content
             * is meant to be playing right now.
             *
             * The empty case is deliberately left alone: a plain VAST tag has
             * no cue points and IS the preroll, so it must be waited for.
             */
            var cuePoints = [];

            try {
                cuePoints = adsManager.getCuePoints() || [];
            } catch (e) { /* not a VMAP; treat as a preroll */ }

            adLog('cue points', cuePoints);

            if (cuePoints.length && cuePoints.indexOf(0) === -1) {
                holdingForFirstBreak = false;

                if (!state.active) {
                    resumeContent();
                }
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

            holdingForFirstBreak = false;
            destroyAds();
            resumeContent();
        }

        var displayContainer = new google.ima.AdDisplayContainer(adContainerEl, adVideoEl);

        adsLoader = new google.ima.AdsLoader(displayContainer);
        adsLoader.addEventListener(google.ima.AdsManagerLoadedEvent.Type.ADS_MANAGER_LOADED, onAdsManagerLoaded, false);
        adsLoader.addEventListener(google.ima.AdErrorEvent.Type.AD_ERROR, onAdError, false);

        var primed = false;

        /*
         * initialize() is the call that claims the element for later ad playback,
         * and WebKit only honours it from inside a gesture's own call stack.
         *
         * startAds() runs from the media element's `play` handler, and that is a
         * queued task — the gesture has already unwound by then. Safari's
         * transient activation window is generous enough that it usually still
         * lands, which is exactly why this went unnoticed: the break played, and
         * only the resume afterwards failed. Priming from the pointer event
         * itself removes the guess. The legacy engine wires the same thing to
         * touchstart in single_global.js.
         */
        function primeAdContainer() {

            if (primed) {
                return;
            }

            primed = true;

            try {
                displayContainer.initialize();
                adLog('display container claimed');
            } catch (e) {
                adLog('display container refused', e);
            }
        }

        /* Capture phase, so this runs before the skin's own button handler gets
           the chance to call play() and start the queued-task race above. */
        playerEl.addEventListener('pointerdown', primeAdContainer, true);
        playerEl.addEventListener('touchstart', primeAdContainer, true);

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
            holdingForFirstBreak = true;

            /* Normally already done from the pointer event above; this covers a
               start with no pointer behind it — a hotkey, or an allowed autoplay. */
            primeAdContainer();

            var request = new google.ima.AdsRequest();

            request.adTagUrl = config.adsTagUrl;
            request.linearAdSlotWidth = adWidth();
            request.linearAdSlotHeight = adHeight();
            request.nonLinearAdSlotWidth = adWidth();
            request.nonLinearAdSlotHeight = Math.floor(adHeight() / 3);

            adLog('requesting ads', adWidth() + 'x' + adHeight());

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
         *
         * Holding the content back is only right while it is still unknown
         * whether an ad is about to take the screen. Once that first break has
         * resolved one way or the other, IMA drives the content itself through
         * CONTENT_PAUSE/RESUME_REQUESTED, and this handler must keep its hands
         * off — resuming a break ends in media.play(), which lands right back
         * here, and pausing there paused the very content it had just been
         * asked to resume. startAds() being a no-op the second time round then
         * left nothing at all to un-pause it: the video never played.
         *
         * That is why midrolls broke outright while a lone preroll sometimes
         * recovered — ALL_ADS_COMPLETED fires only once no break is left, and
         * its own media.play() happened to rescue that one case.
         */
        media.addEventListener('play', function () {

            if (state.finished || state.active) {
                return;
            }

            if (!started) {
                try { media.pause(); } catch (e) { }
                startAds();
                return;
            }

            // Request already away; hold only until the first break resolves.
            if (holdingForFirstBreak) {
                try { media.pause(); } catch (e) { }
            }
        });

        /*
         * Autoplay no longer requests ads up front.
         *
         * An unmuted autoplay is refused by every current browser, and the
         * refusal is the point — the viewer presses play instead. Requesting
         * ads on the strength of an autoplay that may never happen put a break
         * on screen in front of a video that was still sitting on its poster.
         *
         * The `play` handler above is the one path that matters, and it is
         * reached the same way whether playback started by itself or by a
         * click, so an autoplay that IS allowed still gets its pre-roll.
         */

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

        /* Handed to single_global.js so the v3 "Watch Now" popup can start and
           stop this player the way it does the legacy `playerjs` handle. */
        playerEl.jwsMedia = media;

        var $wrap = $(playerEl).closest('.videos_player');
        var hasPlayed = false;
        var lastSavedTime = 0;
        var resumeAt = resumeTimeFor(config);
        var continueWatching = (typeof streamvid_script !== 'undefined') && streamvid_script.video_continue_watching === 'yes';

        /*
         * Startup is over: the media element has registered, so the click shield
         * comes off and the spinner stops. jwsV10Ready keeps styleSkin() from
         * putting the attribute straight back if the skin upgrades late, or on a
         * re-run after the episode AJAX has swapped the markup.
         */
        playerEl.jwsV10Ready = true;
        $wrap.removeClass('vjs-waiting loading');
        clearLoadingFlag(playerEl);

        injectControlBarExtras(playerEl, config);

        $(document).on('fullscreenchange.jwsV10 webkitfullscreenchange.jwsV10', function () {
            placeEpisodePanel(playerEl);
            syncFullscreenOrientation(media);
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
        clearLoadingFlag(playerEl);

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

        /*
         * Movies layout v3 parks the player inside a hidden #video-popup and
         * magnificPopup *moves* that subtree into its own wrap when the viewer
         * clicks "Watch Now" — and moves it back on close.
         *
         * A player cannot survive that. <youtube-video> and <vimeo-video> put
         * an <iframe> in their shadow root, and re-inserting an iframe anywhere
         * reloads its document: the YT.Player handle the adapter is holding
         * then talks to a window that no longer exists, so play/pause do
         * nothing, no state event ever arrives again (the spinner never
         * clears), and the reloaded embed starts itself from the autoplay=1 in
         * its own src — which is the video still playing after the popup has
         * been closed. custom-media-element also destroys the media host a
         * microtask after a disconnect it does not see reversed.
         *
         * So nothing is built here while the markup is still parked. The popup
         * rebuilds it in place once it has stopped moving, and empties it again
         * on close — see the .jws-play handler in pages/single_global.js.
         */
        if (playerEl.closest('.mfp-hide')) {
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

            /* The v3 popup waits on this to press play — by the time it opens,
               the player may still be a few module fetches away from ready. */
            $(playerEl).trigger('jws:v10ready');
        }).catch(function (error) {
            failPlayer(playerEl, error);
        });
    }

    function initAll() {
        $('video-player.jws_player_v10').each(function () {
            initPlayer(this);
        });
    }

    /* Lets the v3 popup build its player the moment the markup has landed in
       its final place, rather than waiting on the MutationObserver below. */
    jwsPlayerV10.initAll = initAll;

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
