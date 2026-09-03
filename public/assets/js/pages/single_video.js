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


            // Remove the active class from all tab contents
            $('.tabs-content > div').removeClass('active');

            // Add the active class to the clicked tab content
            $(tabName).addClass('active');

            // Remove the active class from all tab links
            $('.nav-tabs > li > a').removeClass('active');

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


        function custom_logo_player() {

            var componentButton = videojs.getComponent('Button');

            var controlBarLogo = videojs.extend(componentButton, {
                constructor: function (player, options) {

                    var defaults = {
                        id: '',
                        logo: '',
                        href: '#',
                    }

                    options = videojs.mergeOptions(defaults, options);

                    componentButton.call(this, player, options);

                    if (options.logo) {
                        this.update(options);
                    }
                },
                createEl: function () {
                    return videojs.dom.createEl('button', {
                        className: 'vjs-control vjs-logo-button'
                    });
                },
                update: function (options) {

                    var img = document.createElement('img');

                    if (options.logo) {
                        img.src = options.logo;
                    }

                    if (options.alt) {
                        img.alt = options.alt;
                    }

                    if (options.href) {
                        img.setAttribute('data-href', options.href);
                        if (options.href != '#') {
                            img.addEventListener("click", function () {
                                window.open(options.href, '_blank');
                            });
                        }
                    }

                    this.el().appendChild(img);
                },
            });
            videojs.registerComponent('controlBarLogo', controlBarLogo);

        }

        var playerjs = false;

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

            if (!$player.length) return;

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
                option.muted = true;
                option.techOrder = ['chromecast', 'html5', 'youtube'];
            }

            if (option.sources[0].type === 'video/vimeo') {
                option.techOrder = ['chromecast', 'html5', 'vimeo'];
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
                            type: option.sources[0].type
                        }];
                    }
                }



                if (!$('body').hasClass('logged-in')) {
                    let history = JSON.parse(localStorage.getItem('video_history') || '{}');
                    if (history[player_id_post] && typeof history[player_id_post].time !== "undefined") {
                        option.current_time = history[player_id_post].time;
                    }
                }

                if (typeof option.current_time !== "undefined" && option.current_time > 0) {
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
                var nuevoOptions = {
                    qualityMenu: true,
                    shareMenu: false,
                    relatedMenu: true,
                    zoomMenu: true,
                    rateMenu: true
                };
                if (option.logo && option.logo.url) {
                    nuevoOptions.logotitle = '';
                    nuevoOptions.logo = option.logo.url;
                    nuevoOptions.logoposition = 'RT';
                    nuevoOptions.logourl = '#';
                }
                playerjs.nuevo(nuevoOptions);

                if ($('body').hasClass('logged-in')) {
                    const video_current_time = localStorage.getItem("video_current_time");
                    if (video_current_time) {
                        saveVideoProgress(video_current_time);
                        localStorage.removeItem("video_current_time");
                    }
                }

                if (option.ads_tag_url && typeof playerjs.ima === "function") {
                    playerjs.ima({ adTagUrl: option.ads_tag_url });
                }

                if (typeof playerjs.seekButtons === "function") {
                    playerjs.seekButtons({ forward: 10, back: 10 });
                }

                if (typeof option.current_time !== "undefined" && option.current_time > 5) {
                    var watchedTime = option.current_time;
                    var timeText = new Date(watchedTime * 1000).toISOString().substr(11, 8);

                    /*
                     * Video.js drops currentTime() while the source still has
                     * no metadata, so seeking the moment the notice is clicked
                     * silently does nothing and playback starts from zero.
                     * Waiting for loadedmetadata when it has not arrived yet is
                     * what makes the resume actually land.
                     */
                    var startAt = function (time) {
                        if (playerjs.readyState() >= 1) {
                            playerjs.currentTime(time);
                            playerjs.play();
                            return;
                        }
                        playerjs.one('loadedmetadata', function () {
                            playerjs.currentTime(time);
                            playerjs.play();
                        });
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
                            startAt(0);
                            $('#jws-history-notice').remove();
                            $('#jws-history-overlay').remove();
                        });

                        $('#jws-continue-btn').on('click', function () {
                            startAt(watchedTime);
                            $('#jws-history-notice').remove();
                            $('#jws-history-overlay').remove();
                        });
                    } else {

                        startAt(watchedTime);
                    }
                }

                playerjs.ready(function () {

                    if (typeof option.current_time !== "undefined" && option.current_time > 0) {
                        playerjs.on('loadedmetadata', function () {
                        });
                    }

                    playerjs.hotkeys({
                        volumeStep: 0.1,
                        seekStep: 5,
                        enableVolumeScroll: false
                    });

                    playerjs.on('play', function () {
                        isVideoPlayed = true;
                        if (option.sources[0].type === 'video/youtube' || option.sources[0].type === 'video/vimeo') {
                            $('#videos_player iframe').show();
                            $('.vjs-poster').hide();
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

                    playerjs.on('seeked', function () {
                        lastSavedTime = playerjs.currentTime();
                    });

                    function jws_save_history_action() {

                        if (isVideoPlayed && !playerjs.isDisposed()) {

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

                    playerjs.on('ended', function () {
                        if (option.sources[0].type === 'video/youtube' || option.sources[0].type === 'video/vimeo') {
                            $('#videos_player iframe').hide();
                            $('.vjs-poster').show();
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
                });
            }
            /*
            $(window).on("beforeunload", function () {
                if (!playerjs) return;
                let currentTime = isVideoPlayed ? playerjs.currentTime() : "";
                let lengthOfVideo = isVideoPlayed ? playerjs.duration() : "";
                const currentTime_id = {
                    id: player_id_post,
                    time: currentTime,
                    endtime: lengthOfVideo
                };
                saveVideoProgress(JSON.stringify(currentTime_id));
                localStorage.setItem('video_current_time', JSON.stringify(currentTime_id));
            });
            */

        }



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
