var jwsSingleGlobal;
(function ($) {
    'use strict';
    jwsSingleGlobal = (function () {

        return {
            episodes_carousel: function () {

                var episodes = $('.global-episodes .jws-pisodes_advanced-slider');
                episodes.owlCarousel('destroy');
                jwsThemeModule.owl_caousel_init(episodes);

            },
            cast_carousel: function () {

                var cast = $('.global-cast .jws-person-advanced-slider');
                cast.owlCarousel('destroy');
                jwsThemeModule.owl_caousel_init(cast);

                var videos = $('.global-video .jws-videos-advanced-slider');
                videos.owlCarousel('destroy');
                jwsThemeModule.owl_caousel_init(videos);

                var movies = $('.global-movies .jws_movies_advanced_slider');
                movies.owlCarousel('destroy');
                jwsThemeModule.owl_caousel_init(movies);

            },
            jws_scroll_to_episodes: function () {


                if ($('.single-episodes .version-v2 .jws-episodes_advanced-element .jws-scrollbar').length) {
                    var ep_list = $('.single-episodes .videos_player').data('playerid');

                    var playlist = jQuery('.single-episodes .jws-episodes_advanced-element .jws-scrollbar');

                    if (jQuery('#episodes-item-' + ep_list).length) {

                        var position = jQuery('#episodes-item-' + ep_list).addClass('active').position().top;

                        playlist.animate({ scrollTop: position }, { duration: 0 });

                    }

                }

            }

        }

    }());
    jQuery(document).ready(function ($) {

        jwsSingleGlobal.cast_carousel();
        jwsSingleGlobal.episodes_carousel();



        jwsSingleGlobal.jws_scroll_to_episodes();

        function adjustEpisodesHeight() {
            var tvShowsInfoHeight = $('.tv-shows-info').outerHeight();
            $('.sidebar-list .jws-episodes_advanced-element').css('--top-hright', tvShowsInfoHeight + 'px');
        }

        adjustEpisodesHeight();
        $(window).resize(adjustEpisodesHeight);

        if ($('.single-videos .playlist-list').length) {
            var playlistid = $('.single-videos .videos_player').data('playerid');
            document.getElementById('playlist-item-' + playlistid).scrollIntoView({
                behavior: 'smooth',
                block: 'center',
                inline: 'start'
            });
        }



        $(document).on('click', '.jws-list-top .change-layout', function (e) {
            var sidebar = $('.sidebar-list');
            sidebar.toggleClass('list grid'); // Toggle classes efficiently
            jwsSingleGlobal.jws_scroll_to_episodes(); // Re-run scroll logic after layout change

        });


        $('.nav-tabs > li > a').on('click', function (e) {
            e.preventDefault();
            // Get the tab name from the data attribute
            var tabName = $(this).attr('href');
            var tabs = $(this).closest('.jws-tabs');


            // Remove the active class from all tab contents
            tabs.find('.tabs-content > div').removeClass('active');

            // Add the active class to the clicked tab content
            $(tabName).addClass('active');

            // Remove the active class from all tab links
            tabs.find('.nav-tabs > li > a').removeClass('active');

            // Add the active class to the clicked tab link
            $(this).addClass('active');

            jwsSingleGlobal.cast_carousel();
            jws_js_content_check();
        });

        $('#review_form form').on('submit', function (e) {
            var rating = $('#comment_rating').val();
            if (!rating) {
                alert('Please select your rating!');
                e.preventDefault();
                return false;
            }
        });

        $('#comment_rating_stars i').on('mouseover', function () {
            var rating = $(this).data('rating');
            $('#comment_rating_stars i').removeClass('active');
            $('#comment_rating_stars i').slice(0, rating).addClass('active');
        });
        $('#comment_rating_stars i').on('mouseout', function () {
            var rating = $('#comment_rating').val();
            $('#comment_rating_stars i').removeClass('active');
            $('#comment_rating_stars i').slice(0, rating).addClass('active');
        });
        $('#comment_rating_stars i').on('click', function () {
            var rating = $(this).data('rating');
            $('#comment_rating').val(rating);
        });


        function jws_js_content_check() {
            if ($(window).width() < 767) {
                if ($('.js-content').height() > 60) {
                    $('.view-more-content').show();
                    $('.js-content').addClass('js-more');
                }
            } else {
                if ($('.js-content').height() > 72) {
                    $('.view-more-content').show();
                    $('.js-content').addClass('js-more');
                }
            }

        };

        function jws_js_content() {
            jws_js_content_check();
            $(document).on('click', '.view-more-content', function () {
                $('.js-content-container').toggleClass('open');
                $('.js-content').toggleClass('js-more');
            });

        }
        jws_js_content();

        function saveVideoProgress(video_current_time) {

            var data = {};
            data.action = 'history_post';
            data.progress = JSON.parse(video_current_time);

            if (streamvid_script.is_episodes) {

                data.tv_shows = streamvid_script.episodes_tv_shows;

            }

            if ($('body').hasClass('logged-in')) {
                jQuery.ajax({
                    type: 'POST',
                    dataType: 'json',
                    url: jws_script.ajax_url,
                    data: data,
                    success: function (response) { }
                });
            } else {
                let history = JSON.parse(localStorage.getItem('video_history') || '{}');
                history[data.progress.id] = data.progress;
                localStorage.setItem('video_history', JSON.stringify(history));
            }
        }


        var playerjs = false;
        var playerCleanup = null;

        function start_player($player_wap, $reload) {

            // The Video.js 10 engine leaves no `videojs` global; jws_player_v10.js
            // picks the markup up instead. Two of the callers below reach here
            // without checking, so guard once at the entry point.
            if (typeof videojs !== 'function') {
                return false;
            }

            var $player = $player_wap.find('.jws_player'),
                option = $player.data('player'),
                player_start = $player.attr('id'),
                player_id_post = $player_wap.find('[data-playerid]').attr('data-playerid'),
                isVideoPlayed = false,
                lastSavedTime = 0,
                saveInterval = 3;


            if (streamvid_script.block_devtool === 'yes') {

            }

            if (!$player.length) {
                return;
            }

            if (typeof videojs.getPlugin === 'function' && typeof videojs.getPlugin('chromecast') !== 'undefined') {
                option.techOrder = ['chromecast', 'html5'];
                option.chromecast = {
                    modifyLoadRequestFn: function (loadRequest) {
                        loadRequest.media.hlsSegmentFormat = 'ts';
                        loadRequest.media.hlsVideoSegmentFormat = 'ts';
                        return loadRequest;
                    }
                };
                option.plugins = { chromecast: {}, airplayButton: {} };
            }

            option.playsinline = true;

            if (option.sources[0].type === 'video/youtube') {
                option.techOrder = ['html5', 'youtube'];
                if (typeof videojs.getPlugin === 'function' && typeof videojs.getPlugin('chromecast') !== 'undefined') {
                    option.techOrder = ['chromecast', 'html5', 'youtube'];
                }
                // Force no autoplay for YouTube — let user click to play
                option.autoplay = false;
                // Pass playsinline to YouTube iframe via tech options
                option.youtube = {
                    playsinline: 1,
                    iv_load_policy: 3,
                    rel: 0,
                    modestbranding: 1,
                    autoplay: 0,
                    ytControls: 0
                };
                // For YouTube: force muted so any auto-triggered play produces no sound
                // Will be unmuted on user click
                option.muted = true;
            }

            if (option.sources[0].type === 'video/vimeo') {
                option.techOrder = ['html5', 'vimeo'];
                if (typeof videojs.getPlugin === 'function' && typeof videojs.getPlugin('chromecast') !== 'undefined') {
                    option.techOrder = ['chromecast', 'html5', 'vimeo'];
                }
            }

            if ($reload) videojs(player_start).dispose();

            if (!playerjs || $reload) {
                $('.videos_player').removeClass('vjs-waiting');
                var quality_lists = option.sources[0].item_quality || [];

                if (quality_lists.length > 0) {
                    const sources = quality_lists.map((quality, index) => ({
                        src: atob(quality.url),
                        type: atob(quality.type),
                        label: atob(quality.label),
                        res: 240,
                        default: index === 0
                    }));
                    option.sources = sources;
                } else {
                    if (option.sources && option.sources.length > 0) {
                        option.sources = [{
                            src: atob(option.sources[0].src),
                            type: option.sources[0].type  // ✅ preserve type
                        }];
                    }
                }

                var isYouTube = option.sources[0].type === 'video/youtube';
                var isVimeo = option.sources[0].type === 'video/vimeo';
                var isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
                var _isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);

                // Safari + ads: disable autoplay so that when user clicks play,
                // adsManager.start() runs within the gesture context — required by Safari.
                if (_isSafari && option.ads_tag_url && !isYouTube) {
                    option.autoplay = false;
                }

                // videojs-ima/contrib-ads only work against the native html5 tech —
                // Vimeo's iframe-based tech has no <video> element for them to attach to.
                if (isVimeo) {
                    option.ads_tag_url = '';
                }

                if (!$('body').hasClass('logged-in')) {
                    let history = JSON.parse(localStorage.getItem('video_history') || '{}');
                    if (history[player_id_post] && typeof history[player_id_post].time !== "undefined") {
                        option.current_time = history[player_id_post].time;
                    }
                }

                if (typeof option.current_time !== "undefined" && option.current_time > 0 && streamvid_script.video_continue_watching === 'yes') {
                    option.autoplay = false;
                }
                option.textTrackSettings = false;
                option.html5 = { nativeTextTracks: false };

                option.controlBar = {
                    currentTimeDisplay: true,
                    timeDivider: true,
                    durationDisplay: true,
                    progressControl: true,
                    volumePanel: {
                        inline: false
                    }
                };

                playerjs = videojs(player_start, option);

                // === YouTube autoplay prevention ===
                // Strategy: hide iframe (opacity:0) + mute so even if jws plugin
                // triggers play internally, user sees/hears nothing.
                // On user click: show iframe, unmute, play.
                // NO pause() calls — avoids the play/pause bounce loop.
                var ytUserClicked = false;
                var origPlay = null;
                var ytAutoGuard = null; // placeholder so references don't error
                var adIsPlaying = false; // iOS YouTube ad flag — hoisted so jwsReady handlers can read it
                var adYTInited = false;  // iOS YouTube ad init flag — hoisted so jwsReady handlers can read it
                var imaInitialized = false; // track whether IMA plugin was initialized (playerjs.ima becomes object after init, not function)
                var isIOSYouTubeAd = !!(option.ads_tag_url && isYouTube && isIOS); // true = iOS+YT+ads mode, never show big play button via jwsReady
                if (isYouTube) {
                    playerjs.autoplay(false);

                    // Hide the YouTube iframe — user sees poster/big-play-button only
                    var $ytPlayerEl = $('#' + player_start);
                    $ytPlayerEl.find('iframe').css({ 'opacity': '0', 'pointer-events': 'none' });

                    // Mute so any auto-triggered play produces no sound
                    playerjs.muted(true);

                    // Store original play for later restore
                    origPlay = playerjs.play.bind(playerjs);
                }

                var jwsOptions = {
                    qualityMenu: true,
                    shareMenu: false,
                    relatedMenu: true,
                    zoomMenu: true,
                    rateMenu: true
                };
                if (option.logo && option.logo.url) {
                    jwsOptions.logotitle = '';
                    jwsOptions.logocontrolbar = option.logo.url;
                    jwsOptions.logourl = '#';
                }

                if ($('body').hasClass('logged-in')) {
                    const video_current_time = localStorage.getItem("video_current_time");
                    if (video_current_time) {
                        saveVideoProgress(video_current_time);
                        localStorage.removeItem("video_current_time");
                    }
                }




                if (option.ads_tag_url && isYouTube && isIOS) {
                    // iOS + YouTube: videojs-ima CANNOT work (YouTube uses iframe, not <video>)
                    // Solution: show ad in a separate <video> element using IMA SDK directly,
                    // then load YouTube after ad completes — must be triggered by user gesture
                    playerjs.jws(jwsOptions);

                    var $playerWrap = $('#' + player_start).closest('.videos_player');
                    // adIsPlaying and adYTInited are declared at outer scope above — reuse them here

                    function initIOSYouTubeAd() {
                        if (adYTInited) return;
                        adYTInited = true;
                        adIsPlaying = true;

                        // Hide big play button while ad is playing
                        $playerWrap.find('.vjs-big-play-button').hide();

                        if (typeof google === 'undefined' || !google.ima) {
                            // IMA SDK not loaded — just play YouTube directly
                            adIsPlaying = false;
                            ytUserClicked = true;
                            playerjs.muted(false);
                            $playerWrap.find('iframe').css({ 'opacity': '1', 'pointer-events': '' });
                            $playerWrap.find('.vjs-big-play-button').hide();
                            playerjs.play();
                            return;
                        }

                        // adContainerEl: IMA injects skip/countdown UI here — sits on top
                        // adVideoEl: actual ad video playback — sits beneath adContainerEl
                        var adContainerEl = document.createElement('div');
                        adContainerEl.id = 'jws-ios-ad-container-' + player_start;
                        adContainerEl.style.cssText = 'position:absolute;top:0;left:0;width:100%;height:100%;z-index:9999;pointer-events:auto;';

                        var adVideoEl = document.createElement('video');
                        adVideoEl.setAttribute('playsinline', '');
                        adVideoEl.setAttribute('webkit-playsinline', '');
                        adVideoEl.style.cssText = 'position:absolute;top:0;left:0;width:100%;height:100%;z-index:9998;background:#000;';

                        var $wrap = $playerWrap[0];
                        $wrap.style.position = 'relative';
                        $wrap.appendChild(adVideoEl);
                        $wrap.appendChild(adContainerEl);

                        // Keep YouTube iframe hidden and non-interactive during ad
                        $playerWrap.find('iframe').css({ 'visibility': 'hidden', 'pointer-events': 'none' });

                        var adDisplayContainer = new google.ima.AdDisplayContainer(adContainerEl, adVideoEl);
                        adDisplayContainer.initialize();

                        var adsLoader = new google.ima.AdsLoader(adDisplayContainer);
                        var adsManager;

                        adsLoader.addEventListener(
                            google.ima.AdsManagerLoadedEvent.Type.ADS_MANAGER_LOADED,
                            function (e) {
                                adsManager = e.getAdsManager(adVideoEl);
                                adsManager.addEventListener(google.ima.AdErrorEvent.Type.AD_ERROR, onAdDone);
                                adsManager.addEventListener(google.ima.AdEvent.Type.ALL_ADS_COMPLETED, onAdDone);
                                try {
                                    adsManager.init($wrap.offsetWidth, $wrap.offsetHeight, google.ima.ViewMode.NORMAL);
                                    adsManager.start();
                                } catch (adErr) {
                                    onAdDone();
                                }
                            }
                        );

                        adsLoader.addEventListener(google.ima.AdErrorEvent.Type.AD_ERROR, onAdDone);

                        function onAdDone() {
                            adIsPlaying = false;
                            try { if (adsManager) adsManager.destroy(); } catch (e) { }
                            $('#jws-ios-ad-container-' + player_start).remove();
                            if (adVideoEl && adVideoEl.parentNode) adVideoEl.parentNode.removeChild(adVideoEl);
                            // Now show YouTube iframe and play
                            $playerWrap.find('iframe').css({ 'visibility': 'visible', 'opacity': '1', 'pointer-events': '' });
                            $playerWrap.find('.vjs-big-play-button').hide();
                            ytUserClicked = true;
                            playerjs.muted(false);
                            playerjs.play();
                        }

                        var adsRequest = new google.ima.AdsRequest();
                        adsRequest.adTagUrl = option.ads_tag_url;
                        adsRequest.linearAdSlotWidth = $wrap.offsetWidth;
                        adsRequest.linearAdSlotHeight = $wrap.offsetHeight;
                        adsRequest.nonLinearAdSlotWidth = $wrap.offsetWidth;
                        adsRequest.nonLinearAdSlotHeight = Math.floor($wrap.offsetHeight / 3);
                        adsLoader.requestAds(adsRequest);
                    }

                    // Use a custom overlay button instead of hijacking vjs-big-play-button
                    // This avoids any vjs internal click handlers triggering YouTube play
                    var $iosAdTrigger = $('<button class="jws-ios-ad-trigger" style="position:absolute;top:0;left:0;width:100%;height:100%;z-index:10001;background:transparent;border:none;cursor:pointer;"></button>');
                    $playerWrap.css('position', 'relative').append($iosAdTrigger);

                    // Hide big play button immediately — show our overlay trigger instead
                    // Do NOT set z-index higher on vjs-big-play-button, just hide it outright
                    $playerWrap.find('.vjs-big-play-button').hide();

                    $iosAdTrigger.one('touchend click', function (e) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        $iosAdTrigger.remove(); // remove overlay so IMA ad container can receive touches
                        initIOSYouTubeAd();
                    });

                } else if (option.ads_tag_url && typeof playerjs.ima === "function") {
                    // Non-iOS or non-YouTube: use videojs-ima plugin
                    // NOTE: after calling playerjs.ima({...}), playerjs.ima becomes an object instance
                    // (not a function), so we use a separate flag to track IMA was initialized.
                    // Safari autoplay is already disabled above when ads are present.

                    imaInitialized = true;
                    playerjs.ima({
                        adTagUrl: option.ads_tag_url,
                        disableCustomPlaybackForIOS10Plus: false,
                        requestMode: 'onLoad',
                        adsRenderingSettings: {
                            restoreCustomPlaybackStateOnAdBreakComplete: true,
                            useStyledLinearAds: true,
                            useStyledNonLinearAds: true
                        }
                    });
                    playerjs.jws(jwsOptions);

                    // Safari fix: AdDisplayContainer.initialize() MUST run inside a user gesture.
                    // Register on both touchstart (mobile Safari) and the first play event (desktop
                    // Safari / Chrome) so it fires synchronously within the gesture call stack.
                    var _imaContainerInit = function () {
                        if (playerjs.ima && typeof playerjs.ima.initializeAdDisplayContainer === 'function') {
                            playerjs.ima.initializeAdDisplayContainer();
                        }
                    };
                    playerjs.el().addEventListener('touchstart', function _imaTouch() {
                        playerjs.el().removeEventListener('touchstart', _imaTouch, true);
                        _imaContainerInit();
                    }, true);
                    playerjs.one('play', function () {
                        _imaContainerInit();
                    });

                    playerjs.one('adend', function () {
                        playerjs.muted(false);
                        if (playerjs.paused()) playerjs.play();
                    });
                    playerjs.one('adserror', function () {
                        playerjs.muted(false);
                    });
                } else {
                    playerjs.jws(jwsOptions);
                }



                if (typeof option.current_time !== "undefined" && option.current_time > 5) {
                    var watchedTime = option.current_time;
                    var timeText = new Date(watchedTime * 1000).toISOString().substr(11, 8);

                    /*
                     * Resuming has to survive three quite different starts:
                     * no ads at all, a pre-roll that plays and hands back, and
                     * an ad tag that never resolves. Seeking on one event
                     * covers only one of them — adend never arrives when the
                     * tag fails, and currentTime() is dropped outright while
                     * the source has no metadata yet, both of them silently.
                     * That is why "Continue" kept landing back at zero.
                     *
                     * So the seek is armed instead of fired: whichever of
                     * these moments comes first and finds real content
                     * running is the one that applies it, and the rest are
                     * unhooked.
                     */
                    var armResume = function (time) {

                        var applied = false;

                        var apply = function () {

                            if (applied || playerjs.readyState() < 1) {
                                return;
                            }

                            /* Never seek inside an ad break — that cuts the
                               ad off, which is the very thing the adend
                               handling was written to avoid. */
                            var ads = playerjs.ads;

                            if (ads && typeof ads.isInAdMode === 'function' && ads.isInAdMode()) {
                                return;
                            }

                            applied = true;
                            playerjs.currentTime(time);

                            playerjs.off('adend', apply);
                            playerjs.off('adserror', apply);
                            playerjs.off('loadedmetadata', apply);
                            playerjs.off('playing', apply);
                            playerjs.off('timeupdate', apply);
                        };

                        playerjs.on('adend', apply);
                        playerjs.on('adserror', apply);
                        playerjs.on('loadedmetadata', apply);
                        playerjs.on('playing', apply);
                        /* The backstop: content is demonstrably running by the
                           first timeupdate, whatever did or did not happen
                           with the ad. */
                        playerjs.on('timeupdate', apply);

                        apply();
                    };

                    if (streamvid_script.video_continue_watching === 'yes') {
                        var noticeHtml = `
                            <div id="jws-history-notice">
                                <span>${streamvid_script.history_text} <b>${timeText}</b></span>
                                <div class="d-flex">
                                    <button id="jws-replay-btn">${streamvid_script.watch_again}</button>
                                    <button id="jws-continue-btn">${streamvid_script.continue_watching}</button>
                                </div>
                            </div>
                            <div id="jws-history-overlay"></div>
                        `;
                        $player.css('position', 'relative');
                        $player.before(noticeHtml);
                        $('.vjs-big-play-button').hide();

                        $('#jws-history-overlay').on('click', function (e) {
                            e.stopPropagation();
                            e.preventDefault();
                            return false;
                        });

                        $('#jws-replay-btn').on('click', function () {
                            ytUserClicked = true;
                            playerjs.muted(false);
                            $('#' + player_start).find('iframe').css({ 'opacity': '1', 'pointer-events': '' });
                            armResume(0);
                            playerjs.play();
                            $('#jws-history-notice').remove();
                            $('#jws-history-overlay').remove();
                        });

                        $('#jws-continue-btn').on('click', function () {
                            ytUserClicked = true;
                            playerjs.muted(false);
                            $('#' + player_start).find('iframe').css({ 'opacity': '1', 'pointer-events': '' });
                            armResume(watchedTime);
                            playerjs.play();
                            $('#jws-history-notice').remove();
                            $('#jws-history-overlay').remove();
                        });
                    } else {
                        /*
                         * Same arming as above, and for the same reasons: it
                         * holds off while an ad is on screen (seeking there
                         * kills the pre-roll) without staking the resume on an
                         * ad event that may never come.
                         *
                         * play() is deliberately not called here — the viewer
                         * presses play themselves, or autoplay does it; doing
                         * it from this branch would start the ad early.
                         */
                        armResume(watchedTime);
                    }
                }
                playerjs.on('jwsReady', function () {

                    playerjs.hotkeys({
                        volumeStep: 0.1,
                        seekStep: 5,
                        enableVolumeScroll: false
                    });

                    // FIX: YouTube big play button
                    if (isYouTube) {
                        var $playerEl = $('#' + player_start);
                        var $bigPlay = $playerEl.find('.vjs-big-play-button');

                        // Disable iframe-blocker so clicks pass through to YouTube iframe
                        $playerEl.find('.vjs-iframe-blocker').css('pointer-events', 'none');

                        // Ensure big play button stays on top
                        // For iOS+YouTube+ads: NEVER show big play button — our custom overlay handles it
                        if (!isIOSYouTubeAd) {
                            $bigPlay.css({
                                'z-index': '10000',
                                'pointer-events': 'auto',
                                'display': ''
                            });
                        } else {
                            // Always keep hidden — custom $iosAdTrigger overlay is the play trigger
                            $bigPlay.hide();
                        }

                        // Override big play button click for YouTube
                        $bigPlay.off('click.ytfix touchend.ytfix')
                            .on('click.ytfix touchend.ytfix', function (e) {
                                e.preventDefault();
                                e.stopImmediatePropagation();
                                // Block if iOS ad is currently playing
                                if (adIsPlaying) return;
                                // Remove autoplay guard and show iframe
                                ytUserClicked = true;
                                playerjs.muted(false);
                                $playerEl.find('iframe').css({ 'opacity': '1', 'pointer-events': '' });
                                $bigPlay.css('display', 'none');
                                try {
                                    var playPromise = playerjs.play();
                                    if (playPromise && typeof playPromise.then === 'function') {
                                        playPromise.then(null, function () {
                                            $bigPlay.css('display', '');
                                        });
                                    }
                                } catch (ytErr) {
                                    $bigPlay.css('display', '');
                                }
                            });

                        // Fix: Override play-control (play/pause toggle button) for YouTube
                        var $playControl = $playerEl.find('.vjs-play-control');
                        $playControl.off('click.ytplayfix').on('click.ytplayfix', function (e) {
                            e.preventDefault();
                            e.stopImmediatePropagation();
                            // Block if iOS ad is currently playing
                            if (adIsPlaying) return;
                            // Remove autoplay guard on user click
                            ytUserClicked = true;
                            playerjs.muted(false);
                            $playerEl.find('iframe').css({ 'opacity': '1', 'pointer-events': '' });
                            if (playerjs.paused()) {
                                playerjs.play();
                            } else {
                                playerjs.pause();
                            }
                        });
                    }

                    playerjs.on('play', function () {
                        if (!ytUserClicked && isYouTube) return; // ignore auto-triggered play events
                        isVideoPlayed = true;
                        if (isYouTube) {
                            $('#videos_player iframe').show();
                            $('.vjs-poster').hide();
                            var $pEl = $('#' + player_start);
                            $pEl.closest('.videos_player').find('.vjs-big-play-button').css('display', 'none');
                            // Update play/pause button icon to "pause"
                            $pEl.find('.vjs-play-control').removeClass('vjs-paused').addClass('vjs-playing');
                        }
                        if (typeof streamvid_script !== 'undefined' && streamvid_script.is_rented_video && streamvid_script.rent_expire === 'never') {
                            $.ajax({
                                url: jws_script.ajax_url,
                                type: 'POST',
                                data: {
                                    action: 'jws_video_check_start',
                                    id: player_id_post
                                }
                            });

                            streamvid_script.rent_expire = 'started';
                        }
                    });

                    playerjs.on('pause', function () {
                        if (isYouTube) {
                            // Show big play button when paused so user can resume
                            // But NOT during iOS+YouTube+ads — ad is managing the UI
                            if (!playerjs.ended() && !isIOSYouTubeAd) {
                                var $pEl = $('#' + player_start);
                                $pEl.closest('.videos_player').find('.vjs-big-play-button').css('display', '');
                                // Update play/pause button icon to "play"
                                $pEl.find('.vjs-play-control').removeClass('vjs-playing').addClass('vjs-paused');
                            }
                        }
                    });

                    playerjs.on('seeked', function () {
                        lastSavedTime = playerjs.currentTime();
                    });

                    function jws_save_history_action() {

                        if (isVideoPlayed && playerjs && typeof playerjs.isDisposed === 'function' && !playerjs.isDisposed()) {

                            let currentTime = playerjs.currentTime();
                            let lengthOfVideo = playerjs.duration();
                            if (currentTime - lastSavedTime >= saveInterval) {
                                const currentTime_id = {
                                    id: player_id_post,
                                    time: currentTime,
                                    endtime: lengthOfVideo
                                };
                                saveVideoProgress(JSON.stringify(currentTime_id));
                                lastSavedTime = currentTime;

                            }
                        }
                    }
                    let hideTimer = setInterval(jws_save_history_action, 1000);

                    playerCleanup = function () {
                        clearInterval(hideTimer);
                        if (playerjs && typeof playerjs.off === 'function') {
                            playerjs.off('ended');
                        }
                    };

                    playerjs.on('ended', function () {
                        if (isYouTube) {
                            $('#videos_player iframe').hide();
                            $('.vjs-poster').show();
                            $('#' + player_start).closest('.videos_player').find('.vjs-big-play-button').css('display', '');
                        }
                        if (option.sources[0].type === 'video/youtube') {
                            $('#videos_player iframe').hide();
                            $('.vjs-poster').show();
                            // iOS: show big play button when ended
                            $('#' + player_start).closest('.videos_player').find('.vjs-big-play-button').show();
                        }
                        var episode_list = streamvid_script.episodes_list;
                        if (Array.isArray(episode_list)) {
                            let index = episode_list.findIndex(item => item.id == player_id_post);
                            if (index !== -1 && index < episode_list.length - 1) {
                                var nextItem = episode_list[index + 1];
                                jwsThemeModule.show_notification(streamvid_script.next_episodes, 'success');
                                setTimeout(function () { window.location.href = nextItem['link']; }, 2000);
                            }
                        }

                        let currentTime = playerjs.duration();
                        let lengthOfVideo = playerjs.duration();
                        const currentTime_id = {
                            id: player_id_post,
                            time: currentTime,
                            endtime: lengthOfVideo
                        };
                        saveVideoProgress(JSON.stringify(currentTime_id));
                    });

                    setTimeout(function () { $player.removeAttr('data-player'); $('#videos_player').removeAttr('data-player'); }, 500);

                    // === Episode List Button in Player ===
                    var _epListForBtn = (streamvid_script.episodes_list && streamvid_script.episodes_list.length > 0)
                        ? streamvid_script.episodes_list
                        : (streamvid_script.seasons_data && streamvid_script.seasons_data.length > 0
                            ? streamvid_script.seasons_data.reduce(function (acc, s) { return acc.concat(s.episodes || []); }, [])
                            : []);
                    if (
                        typeof streamvid_script !== 'undefined' &&
                        streamvid_script.is_episodes &&
                        streamvid_script.show_ep_list_btn &&
                        _epListForBtn.length > 1
                    ) {
                        var $controlBar = $('#' + player_start).find('.vjs-control-bar');
                        if ($controlBar.length && !$controlBar.find('.jws-ep-list-btn').length) {
                            var $epBtn = $(
                                '<button class="vjs-control vjs-button jws-ep-list-btn" title="Episodes">' +
                                '<span class="vjs-icon-placeholder" aria-hidden="true"></span>' +
                                '<span class="vjs-control-text">Episodes</span>' +
                                '</button>'
                            );
                            var $fullscreen = $controlBar.find('.vjs-fullscreen-control');
                            if ($fullscreen.length) {
                                $epBtn.insertBefore($fullscreen);
                            } else {
                                $controlBar.append($epBtn);
                            }
                        }
                    }

                });
            }

        }

        // === Build episode list HTML for a given episode array ===
        function jws_render_ep_list(list, currentId) {
            var html = '';
            for (var i = 0; i < list.length; i++) {
                var ep = list[i];
                var active = (ep.id == currentId) ? ' active' : '';
                var title = ep.title || (streamvid_script.episode_text || 'Episode') + ' ' + (i + 1);
                var thumb = ep.thumb ? '<img src="' + ep.thumb + '" alt="">' : '<span class="jws-ep-no">' + (i + 1) + '</span>';
                html += '<div class="jws-ep-item' + active + '" data-id="' + ep.id + '" data-link="' + ep.link + '">';
                html += '<div class="jws-ep-thumb">' + thumb + '</div>';
                html += '<div class="jws-ep-info"><span class="jws-ep-num">' + (i + 1) + '</span><span class="jws-ep-title">' + title + '</span></div>';
                html += '</div>';
            }
            return html;
        }

        // === Build & toggle episode panel ===
        function jws_build_episode_panel() {
            if (!$('#jws-ep-panel').length) {
                var currentId = parseInt($('.videos_player').data('playerid') || 0);
                var seasons = (streamvid_script.seasons_data && streamvid_script.seasons_data.length > 1)
                    ? streamvid_script.seasons_data : null;
                var activeSeason = streamvid_script.episodes_current_season || 0;
                var list = (streamvid_script.episodes_list && streamvid_script.episodes_list.length > 0)
                    ? streamvid_script.episodes_list
                    : (streamvid_script.seasons_data && streamvid_script.seasons_data[activeSeason]
                        ? streamvid_script.seasons_data[activeSeason].episodes || []
                        : []);

                var html = '<div id="jws-ep-panel" class="jws-ep-panel">';
                html += '<div class="jws-ep-panel-header">';
                html += '<span>' + (streamvid_script.episodes_label || 'Episodes') + '</span>';
                html += '<div class="ep-right">';
                if (seasons) {
                    html += '<select class="jws-ep-season-select">';
                    for (var s = 0; s < seasons.length; s++) {
                        var sel = (s === activeSeason) ? ' selected' : '';
                        html += '<option value="' + s + '"' + sel + '>' + seasons[s].label + '</option>';
                    }
                    html += '</select>';
                }
                html += '<button class="jws-ep-panel-close"><i class="jws-icon-x"></i></button>';
                html += '</div>';
                html += '</div>';
                html += '<div class="jws-ep-panel-body">' + jws_render_ep_list(list, currentId) + '</div>';
                html += '</div>';

                // Append directly into .video-js so panel is always inside the player container
                var $vjsEl = $('.videos_player .video-js').first();
                ($vjsEl.length ? $vjsEl : $('.videos_player')).append(html);

                // Force set correct select value after DOM append (selected attr in string is unreliable)
                if (seasons) {
                    $('#jws-ep-panel .jws-ep-season-select').val(activeSeason);
                }

                // Season select change
                $(document).on('change', '#jws-ep-panel .jws-ep-season-select', function () {
                    var idx = parseInt($(this).val());
                    var seasons = streamvid_script.seasons_data || [];
                    if (!seasons[idx]) return;
                    // Keep current season index in sync
                    streamvid_script.episodes_current_season = idx;
                    var newList = seasons[idx].episodes || [];
                    var curId = parseInt($('.videos_player').data('playerid') || 0);
                    $('#jws-ep-panel .jws-ep-panel-body').html(jws_render_ep_list(newList, curId));
                    // Scroll to top
                    $('#jws-ep-panel .jws-ep-panel-body')[0].scrollTop = 0;
                });

                $(document).on('click', '#jws-ep-panel .jws-ep-panel-close', function () {
                    $('#jws-ep-panel').removeClass('open');
                });
                $(document).on('click', function (e) {
                    if ($('#jws-ep-panel').hasClass('open') && !$(e.target).closest('#jws-ep-panel, .jws-ep-list-btn').length) {
                        $('#jws-ep-panel').removeClass('open');
                    }
                });
            }
        }

        // Exposed because the Video.js 10 engine puts its episodes button inside
        // the skin's shadow root. Events from there are retargeted to the host
        // element by the time they reach document, so the delegated handler below
        // can never match it — jws_player_v10.js binds the button directly and
        // calls this instead of reimplementing the toggle.
        jwsSingleGlobal.toggle_episode_panel = function () {
            if (!$('#jws-ep-panel').length) {
                jws_build_episode_panel();
            }
            $('#jws-ep-panel').toggleClass('open');
            var $active = $('#jws-ep-panel .jws-ep-item.active');
            if ($active.length) {
                var panel = $('#jws-ep-panel .jws-ep-panel-body')[0];
                panel.scrollTop = $active[0].offsetTop - 60;
            }
        };

        // Toggle panel on button click
        $(document).on('click', '.jws-ep-list-btn', function (e) {
            e.stopPropagation();
            jwsSingleGlobal.toggle_episode_panel();
        });

        // Click episode in panel → load via AJAX
        $(document).on('click', '.jws-ep-item', function (e) {
            e.preventDefault();
            var $item = $(this);
            if ($item.hasClass('active')) {
                $('#jws-ep-panel').removeClass('open');
                return;
            }
            var epId = $item.data('id');
            var epLink = $item.data('link');

            //var wasFullscreen = !!(
            //playerjs && !playerjs.isDisposed() && playerjs.isFullscreen && playerjs.isFullscreen()
            //);
            var wasFullscreen = false; // Disable fullscreen check for now — too many edge cases where it causes more problems than it solves (see #146)

            $('#jws-ep-panel .jws-ep-item').removeClass('active');
            $item.addClass('active');
            $('#jws-ep-panel').removeClass('open');

            $('.videos_player').addClass('loading').append(
                '<div class="jws-ep-loader"><svg class="circular" viewBox="25 25 50 50"><circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/></svg></div>'
            );

            function doReplacePlayer(response) {
                // Step 1: Update current season index so jws-ep-season-select stays in sync
                if (streamvid_script.seasons_data && streamvid_script.seasons_data.length > 1) {
                    for (var si = 0; si < streamvid_script.seasons_data.length; si++) {
                        var eps = streamvid_script.seasons_data[si].episodes || [];
                        for (var ei = 0; ei < eps.length; ei++) {
                            if (eps[ei].id == epId) {
                                streamvid_script.episodes_current_season = si;
                                break;
                            }
                        }
                    }
                }

                // Step 2: cleanup interval + ended handler before dispose
                // to prevent spurious 'ended' event saving wrong progress
                if (playerCleanup) { playerCleanup(); playerCleanup = null; }
                if (playerjs && !playerjs.isDisposed()) {
                    try {
                        playerjs.pause();
                        playerjs.dispose();
                    } catch (e) { }
                }
                playerjs = false;
                $('#jws-ep-panel').remove();

                // Step 2: delay DOM replace by 2 rAFs to allow videojs resize-manager
                // iframe callbacks (queued during dispose) to fully flush before we
                // mutate the DOM — prevents "Cannot read properties of null (reading 'style')"
                requestAnimationFrame(function () {
                    requestAnimationFrame(function () {
                        $('.videos_player').replaceWith(response.data.player);
                        if (response.data.sources && $('.sources-videos').length) {
                            $('.sources-videos').replaceWith(response.data.sources);
                        }
                        if (response.data.title) {
                            document.title = response.data.title + ' - ' + document.title.split(' - ').slice(-1)[0];
                        }
                        if (epLink && window.history && window.history.pushState) {
                            // Preserve existing query params (e.g. ?version=v1) when switching episodes
                            var currentParams = new URLSearchParams(window.location.search);
                            var epUrl = new URL(epLink, window.location.href);
                            currentParams.forEach(function (value, key) {
                                if (!epUrl.searchParams.has(key)) {
                                    epUrl.searchParams.set(key, value);
                                }
                            });
                            window.history.pushState({ epId: epId }, response.data.title || '', epUrl.toString());
                        }
                        if (typeof videojs === 'function') {
                            start_player($('.videos_player'), false);
                            if (wasFullscreen) {
                                var fsWait = setInterval(function () {
                                    if (playerjs && typeof playerjs.requestFullscreen === 'function' && !playerjs.isDisposed()) {
                                        clearInterval(fsWait);
                                        playerjs.one('jwsReady', function () {
                                            try { playerjs.requestFullscreen(); } catch (fse) { }
                                        });
                                    }
                                }, 50);
                                setTimeout(function () { clearInterval(fsWait); }, 5000);
                            }
                        }

                        // Refresh episodes section based on layout version
                        if (response.data.episodes_section && response.data.episodes_selector) {
                            var $target = $(response.data.episodes_selector).first();
                            if ($target.length) {
                                $target.replaceWith(response.data.episodes_section);
                                // Re-init owl carousel (v1/v3 slider)
                                var $slider = $(response.data.episodes_selector + ' .jws-pisodes_advanced-slider');
                                if ($slider.length && typeof jwsThemeModule !== 'undefined') {
                                    jwsThemeModule.owl_caousel_init($slider);
                                }
                                // Scroll to active episode
                                jwsSingleGlobal.jws_scroll_to_episodes();
                            }
                        }
                        jwsThemeModule.events_click_hover();

                    });
                });
            }

            function doLoadEpisode() {
                // Detect layout version from #main class: version-v1, version-v2, version-v3
                var versionMatch = ($('#main').attr('class') || '').match(/version-(v\d+)/);
                var layoutVersion = versionMatch ? versionMatch[1] : 'v1';

                // For v2: capture current list/grid state from .sidebar-list class
                var episodesView = '';
                if (layoutVersion === 'v2') {
                    episodesView = $('.sidebar-list').hasClass('list') ? 'list' : 'grid';
                }

                $.ajax({
                    url: jws_script.ajax_url,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'jws_ajax_episode_player',
                        id: epId,
                        version: layoutVersion,
                        episodes_view: episodesView
                    },
                    success: function (response) {
                        if (response.success) {
                            doReplacePlayer(response);
                        }
                    },
                    complete: function () {
                        $('.videos_player').removeClass('loading');
                        $('.jws-ep-loader').remove();
                    }
                });
            }

            // Load episode directly — do NOT exit fullscreen first.
            // After new player is ready, requestFullscreen() will be called (see doReplacePlayer).
            doLoadEpisode();
        });

        function player_action() {

            var player;

            if (typeof videojs == 'function') {


                $('.videos_player').each(function () {

                    var $this = $(this);

                    if (!$('.single-movies .site-main').hasClass('version-v3')) {
                        player = start_player($this);
                    }


                });

                $('.single-movies .version-v3 .video-play .jws-play').magnificPopup({
                    type: 'inline',
                    midClick: true,
                    mainClass: 'mfp-fade',
                    callbacks: {
                        beforeOpen: function () {

                            if (!playerjs) {
                                start_player($('.videos_player'));
                            } else {
                                playerjs.play();
                            }

                            this.st.mainClass = 'videojs-popup animation-popup';
                        },
                        beforeClose: function () {
                            if (playerjs) {
                                playerjs.pause();
                                playerjs.disablePictureInPicture();
                            }

                        }
                    },
                });

            }


            $(document).on('click', '.sources-videos button', function (e) {
                var data = {};
                var button = $(this);
                var container = button.parents('.sources-videos');
                var post_id = container.data('id');
                e.preventDefault();


                $('.videos_player').addClass('loading');

                if (container.hasClass('sources-table')) {
                    $('.jws-play').trigger('click');

                } else {

                    container.find('li').removeClass('active');
                    button.parent().addClass('active');
                }

                $('.videos_player').empty();
                $('.videos_player').append('<div class="loader"><svg class="circular" viewBox="25 25 50 50"><circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/></svg></div>').addClass('loading');


                data.action = 'jws_ajax_sources';
                data.id = post_id;
                data.index = button.data('index');

                if (button.hasClass('main')) {
                    data.index = 'main';
                }

                $.ajax({
                    url: jws_script.ajax_url,
                    data: data,
                    type: 'POST',
                    dataType: 'json',
                }).success(function (response) {

                    $('.videos_player').replaceWith(response.data.content);
                    player = start_player($('.videos_player'), true);



                }).complete(function () {
                    $('.videos_player').removeClass('loading');
                }).error(function (ex) {
                    console.log(ex);
                });
            });


        }

        player_action();


        // load more button click event
        $(document).on('click', '#comments .page-numbers a', function (e) {

            e.preventDefault();
            var url = $(this).attr('href');

            $(document.body).trigger('jws_comments_filter_ajax', [url, $(this)]);

        });

        $(document.body).on('jws_comments_filter_ajax', function (e, url, element) {

            $('#comments').append('<div class="loader"><svg class="circular" viewBox="25 25 50 50"><circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/></svg></div>').addClass('loading');

            if ('?' == url.slice(-1)) {
                url = url.slice(0, -1);
            }

            url = url.replace(/%2C/g, ',');

            window.history.pushState(null, "", url);
            $(window).bind("popstate", function () {
                window.location = location.href
            });

            $.get(url, function (res) {
                $('#comments').replaceWith($(res).find('#comments'));
            }, 'html');
        });

        $(document).on('click', ".jws-buy-video , .jws-rent-video", function (e) {
            e.preventDefault();
            var button = $(this);
            var id = button.data('id');
            var type = button.data('type');
            var data = {
                id: id,
                type: type,
                action: "jws_videos_live_quickview",
            };

            button.addClass('loading');
            if (!button.find('.loader').length) {
                button.append('<div class="loader"><svg class="circular" viewBox="25 25 50 50"><circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/></svg></div>');
            }
            if ($('#jws-quickview-single').length <= 0) {
                $('body').append('<div id="jws-quickview-single" class="buy-rent-videos"></div>');
            }

            $.ajax({
                url: jws_script.ajax_url,
                data: data,
                type: 'POST',
                dataType: 'json',
            }).success(function (response) {

                $('#jws-quickview-single').html(response.data.content);
                $('#jws-quickview-single').addClass('open');



            }).complete(function () {
                button.removeClass('loading');

            }).error(function (ex) {
                console.log(ex);
            });
        });
        $(document).on('click', ".btn-buy-ticket", function (e) {

            e.preventDefault();

            var button = $(this);
            var id = button.data('id');
            var nonce = button.data('nonce');
            var type = button.data('type');
            var data = {
                id: id,
                action: "jws_add_ticket_to_cart",
                nonce: nonce,
                type: type
            };
            button.addClass('loading');
            if (!button.find('.loader').length) {
                button.append('<div class="loader"><svg class="circular" viewBox="25 25 50 50"><circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/></svg></div>');
            }

            $.ajax({
                url: jws_script.ajax_url,
                data: data,
                method: 'POST',
                success: function (response) {
                    console.log(response);
                    if (response.success) {

                        jwsThemeModule.show_notification(response.data.message, 'success');
                        window.location.href = jws_script.checkout_url;

                    } else {


                        if (response.data[0].code == 'not_logged_in') {
                            $('.jws-form-login-popup').addClass('open');
                        }

                        if (response.data[0].code == 'duplicate_item') {
                            window.location.href = jws_script.checkout_url
                        } else {

                            jwsThemeModule.show_notification(response.data[0].message, 'error');

                        }


                    }

                },
                error: function () {
                    console.log('error');
                },
                complete: function () {
                    button.removeClass('loading');
                },
            });



        });

    });

})(jQuery);
