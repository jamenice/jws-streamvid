/**
 * Live streaming with LiveKit: the host's webcam studio, the OBS stream key,
 * and the viewer.
 *
 * Host   (templates/studio.php): go live from the webcam, toggle camera and
 *        microphone, end the stream, or create the RTMP ingress for OBS.
 * Viewer (templates/viewer.php): join, watch, follow the host through
 *        reconnects. Chat is chat.js (window.jwsLiveChat).
 *
 * Media is attached with LiveKit's own track.attach(); nothing here depends
 * on Video.js.
 */
(function ($) {
    'use strict';

    if (typeof jwsLivestream === 'undefined' || typeof window.LivekitClient === 'undefined') {
        return;
    }

    var cfg = jwsLivestream;
    var i18n = cfg.i18n || {};
    var HOST_IDENTITY = 'user_' + cfg.host_id;

    function t(key) {
        return i18n[key] || '';
    }

    function notify(message, type) {
        message = message || t('join_error');
        if (message && typeof jwsThemeModule !== 'undefined' && jwsThemeModule.show_notification) {
            jwsThemeModule.show_notification($('<div>').text(message).html(), type || 'error');
        }
    }

    function errorMessage(response) {
        var data = response && (response.responseJSON || response);
        return data && data.data && data.data.message;
    }

    function post(action, data) {
        return $.post(cfg.ajax_url, $.extend({ action: action, nonce: cfg.nonce }, data), null, 'json');
    }

    function sendData(room, topic, payload) {
        return room.localParticipant.publishData(new TextEncoder().encode(JSON.stringify(payload)), { reliable: true, topic: topic });
    }

    function updateViewerCount(room) {
        $('.view-count span').text(room.remoteParticipants.size);
    }

    function parseMetadata(participant) {
        try {
            return participant && participant.metadata ? JSON.parse(participant.metadata) : null;
        } catch (e) {
            return null;
        }
    }

    function withLoader($button) {
        $button.addClass('loading');
        if (!$button.find('.loader').length) {
            $button.append('<div class="loader"><svg class="circular" viewBox="25 25 50 50"><circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/></svg></div>');
        }
    }

    function stopLoader($button) {
        $button.removeClass('loading').find('.loader').remove();
    }

    /* ---------------------------------------------------------------------- */
    /* Countdown to a scheduled stream: reload at zero to open it              */
    /* ---------------------------------------------------------------------- */

    function countdown() {

        $('.jws-live-countdown').each(function () {

            var $box = $(this);
            var target = Number($box.data('date'));
            var timer;

            if (!target) {
                return;
            }

            function pad(n) {
                return String(n).padStart(2, '0');
            }

            function tick() {
                var diff = target - Date.now();

                if (diff <= 0) {
                    clearInterval(timer);
                    window.location.reload();
                    return;
                }

                $box.find('.days').text(pad(Math.floor(diff / 86400000)));
                $box.find('.hours').text(pad(Math.floor(diff / 3600000) % 24));
                $box.find('.minutes').text(pad(Math.floor(diff / 60000) % 60));
                $box.find('.seconds').text(pad(Math.floor(diff / 1000) % 60));
            }

            tick();
            timer = setInterval(tick, 1000);
        });
    }

    /* ---------------------------------------------------------------------- */
    /* Host: webcam studio                                                     */
    /* ---------------------------------------------------------------------- */

    function endStream($button) {

        if (!window.confirm(t('stop_confirm'))) {
            return $.Deferred().reject().promise();
        }

        withLoader($button);

        return post('jws_livekit_end', { id: $button.data('id') })
            .done(function (response) {
                if (!response || !response.success) {
                    notify(errorMessage(response));
                    return;
                }
                notify(response.data.message, 'success');
            })
            .fail(function (xhr) {
                notify(errorMessage(xhr));
            })
            .always(function () {
                stopLoader($button);
            });
    }

    function hostStudio() {

        var $start = $('#start-webcam-live');

        if (!$start.length) {
            return;
        }

        var $stop = $('#stop-webcam-live').hide();
        var $preview = $('#livekit-video');
        var $player = $('.webcam-player');
        var $camera = $('#webcam-action');
        var $mic = $('#mic-action');
        var room = null;
        var endedByHost = false;

        function setToggle($button, on, labelOn, labelOff) {
            $button.toggleClass('enabled', on).toggleClass('disabled', !on)
                .attr('aria-pressed', on ? 'true' : 'false')
                .attr('title', on ? labelOn : labelOff)
                .attr('aria-label', on ? labelOn : labelOff);
        }

        function resetStudio() {
            $preview.empty().hide();
            $stop.hide();
            $start.show();
            $player.removeClass('open');
            setToggle($camera, true, t('camera_off_label'), t('camera_on_label'));
            setToggle($mic, true, t('mic_off_label'), t('mic_on_label'));
            room = null;
        }

        $start.on('click', function () {

            if (room || $start.hasClass('loading')) {
                return;
            }

            withLoader($start);

            post('jws_livekit_host_token', { id: $start.data('id') })
                .done(async function (response) {

                    if (!response || !response.success) {
                        stopLoader($start);
                        notify(errorMessage(response));
                        return;
                    }

                    var LK = window.LivekitClient;

                    room = new LK.Room({ adaptiveStream: true, dynacast: true });
                    endedByHost = false;

                    room.on(LK.RoomEvent.ParticipantConnected, function () { updateViewerCount(room); })
                        .on(LK.RoomEvent.ParticipantDisconnected, function () { updateViewerCount(room); })
                        .on(LK.RoomEvent.Reconnecting, function () { notify(t('host_reconnecting'), 'error'); })
                        .on(LK.RoomEvent.Reconnected, function () { notify(t('host_reconnected'), 'success'); })
                        .on(LK.RoomEvent.Disconnected, function (reason) {
                            if (!endedByHost && reason !== LK.DisconnectReason.CLIENT_INITIATED) {
                                notify(t('host_disconnected'));
                            }
                            resetStudio();
                        });

                    try {
                        await room.connect(cfg.livekit_url, response.data);
                        await room.localParticipant.enableCameraAndMicrophone();
                    } catch (error) {
                        stopLoader($start);
                        notify(error && error.name === 'NotAllowedError'
                            ? t('camera_denied')
                            : t('start_error') + (error && error.message ? ' (' + error.message + ')' : ''));
                        if (room) {
                            endedByHost = true;
                            room.disconnect();
                        }
                        resetStudio();
                        return;
                    }

                    stopLoader($start);

                    var publication = room.localParticipant.getTrackPublication(LK.Track.Source.Camera);

                    if (publication && publication.track) {
                        var video = publication.track.attach();
                        video.muted = true;
                        $preview.show().empty().append(video);
                    }

                    $start.hide();
                    $stop.show();
                    $player.addClass('open');
                    updateViewerCount(room);
                })
                .fail(function (xhr) {
                    stopLoader($start);
                    notify(errorMessage(xhr));
                });
        });

        $camera.on('click', async function () {
            if (!room) return;
            var on = !room.localParticipant.isCameraEnabled;
            await room.localParticipant.setCameraEnabled(on);
            setToggle($camera, on, t('camera_off_label'), t('camera_on_label'));
            sendData(room, 'camera_status', { type: on ? 'on' : 'off' });
        });

        $mic.on('click', async function () {
            if (!room) return;
            var on = !room.localParticipant.isMicrophoneEnabled;
            await room.localParticipant.setMicrophoneEnabled(on);
            setToggle($mic, on, t('mic_off_label'), t('mic_on_label'));
        });

        $stop.on('click', function () {

            if (!room || $stop.hasClass('loading')) {
                return;
            }

            endStream($stop).done(async function (response) {

                if (!response || !response.success || !room) {
                    return;
                }

                endedByHost = true;

                // Let "ended" reach the viewers before leaving; disconnecting
                // straight after publishing drops it.
                try {
                    await sendData(room, 'live_end', { type: 'live_ended' });
                    await new Promise(function (resolve) { setTimeout(resolve, 500); });
                } catch (e) { }

                if (room) {
                    room.disconnect();
                }

                window.location.reload();
            });
        });

        resetStudio();
    }

    function streamKey() {

        $('#get_stream_key').on('click', function () {

            var $button = $(this);
            var regenerate = String($button.attr('data-regenerate')) === '1';

            if ($button.hasClass('loading') || (regenerate && !window.confirm($button.data('confirm')))) {
                return;
            }

            withLoader($button);

            post('jws_livekit_ingress', { id: $button.data('id'), regenerate: regenerate ? 1 : 0 })
                .done(function (response) {
                    if (!response || !response.success) {
                        notify(errorMessage(response));
                        return;
                    }

                    $('#studio-server').val(response.data.url);
                    $('#studio-key').val(response.data.stream_key);

                    // From now on the same button replaces the key.
                    $button.attr('data-regenerate', '1')
                        .removeClass('studio-btn--primary').addClass('studio-btn--ghost')
                        .contents().filter(function () { return this.nodeType === 3 && $.trim(this.nodeValue); }).first()
                        .replaceWith(document.createTextNode(t('reset_key')));

                    notify(t('key_ready'), 'success');
                })
                .fail(function (xhr) {
                    notify(errorMessage(xhr));
                })
                .always(function () {
                    stopLoader($button);
                });
        });

        $('.studio-end-rtmp').on('click', function () {
            var $button = $(this);
            if ($button.hasClass('loading')) return;
            endStream($button).done(function (response) {
                if (response && response.success) {
                    window.location.reload();
                }
            });
        });
    }

    /* ---------------------------------------------------------------------- */
    /* Viewer                                                                  */
    /* ---------------------------------------------------------------------- */

    function viewer() {

        var $join = $('#join-livestream');

        if (!$join.length) {
            return;
        }

        var $container = $('#remote-video-container');
        var $holder = $('.video-viewer-holder');
        var room = null;
        var ended = false;

        /* Back to the "Watch live" screen, with the reason. */
        function viewerNotice(text) {
            $('.vjs-loading-spinner').hide();
            $('.live-player').removeClass('is-joining');
            $join.show();
            if (text) {
                $('.live-join__hint').text(text).addClass('is-notice');
            }
        }

        /* The poster with a status over it; covers the video while shown. */
        function showStatus(title, text) {
            if (ended) {
                return;
            }

            var $status = $holder.find('.live-waiting');

            if (!$status.length) {
                $status = $('<div class="live-waiting" role="status"><span class="live-waiting__pulse"></span><h6></h6><p></p></div>').appendTo($holder);
            }

            $status.find('h6').text(title);
            $status.find('p').text(text || '');
            $holder.find('.vjs-loading-spinner').hide();
            $holder.show();
        }

        function hideStatus() {
            $holder.hide();
        }

        function hostHasVideo() {
            var host = room && room.remoteParticipants.get(HOST_IDENTITY);
            return !!host && Array.from(host.trackPublications.values()).some(function (p) { return p.kind === 'video' && p.track; });
        }

        /* Participants list */
        function addParticipant(participant, profile) {

            var id = participant.identity;
            var $list = $('#chat-participants-list ul');

            if ($list.children().filter(function () { return $(this).data('participant-id') === id; }).length) {
                return;
            }

            profile = profile || parseMetadata(participant) || {};

            var avatar = /^https?:\/\//i.test(profile.user_avatar || '') ? profile.user_avatar : '';

            $('<li>')
                .data('participant-id', id)
                .append($('<img>', { src: avatar, alt: '', width: 24, height: 24 }))
                .append(document.createTextNode(profile.user_name || participant.name || t('anonymous')))
                .appendTo($list);
        }

        function removeParticipant(id) {
            $('#chat-participants-list ul').children().filter(function () { return $(this).data('participant-id') === id; }).remove();
        }

        /* Media */
        function attachTrack(track, publication, participant) {

            if (participant.identity !== HOST_IDENTITY) {
                return;
            }

            var element = track.attach();

            if (track.kind === 'video') {
                element.className = 'live-remote-video';
                element.setAttribute('playsinline', '');
                $container.find('.live-remote-video').remove();

                // Portrait streams (a phone) fill the tall mobile frame;
                // landscape ones are letterboxed rather than cropped.
                var orient = function () {
                    if (element.videoWidth && element.videoHeight) {
                        $(element).toggleClass('is-portrait', element.videoHeight > element.videoWidth);
                    }
                };
                element.addEventListener('loadedmetadata', orient);
                element.addEventListener('resize', orient);
            }

            $container.append(element);

            if (track.kind === 'video') {
                $container.removeClass('is-camera-off');
                hideStatus();
            }
        }

        function detachTrack(track) {
            track.detach().forEach(function (element) { element.remove(); });

            if (track.kind === 'video') {
                showStatus(t('host_away_title'), t('host_away_text'));
            }
        }

        /* Browsers block sound until the page has been interacted with. */
        function syncAudio() {
            var $unmute = $('.live-unmute');

            if (room.canPlaybackAudio) {
                $unmute.remove();
                return;
            }

            if (!$unmute.length) {
                $('<button type="button" class="live-unmute"></button>')
                    .text(t('tap_unmute'))
                    .on('click', function () { room.startAudio(); })
                    .appendTo('.live-player');
            }
        }

        function streamEnded() {
            showStatus(t('live_ended'), '');
            ended = true;
            if (room) {
                room.disconnect();
            }
            setTimeout(function () { window.location.reload(); }, 3000);
        }

        $join.on('click', function () {

            $('.vjs-loading-spinner').show();
            $join.hide();
            $('.live-player').addClass('is-joining');

            $.post(cfg.ajax_url, { action: 'jws_livekit_viewer_token', id: $join.data('id') }, null, 'json')
                .done(async function (response) {

                    if (!response || !response.success) {
                        viewerNotice(errorMessage(response) || t('no_host'));
                        return;
                    }

                    var LK = window.LivekitClient;

                    room = new LK.Room({ adaptiveStream: true });

                    room
                        .on(LK.RoomEvent.TrackSubscribed, attachTrack)
                        .on(LK.RoomEvent.TrackUnsubscribed, detachTrack)
                        .on(LK.RoomEvent.ParticipantConnected, function (participant) {
                            updateViewerCount(room);
                            addParticipant(participant);
                        })
                        .on(LK.RoomEvent.ParticipantDisconnected, function (participant) {
                            updateViewerCount(room);
                            removeParticipant(participant.identity);
                            // A host who leaves usually comes straight back; the
                            // stream is over only when the host says so.
                            if (participant.identity === HOST_IDENTITY) {
                                $container.find('.live-remote-video, audio').remove();
                                showStatus(t('host_away_title'), t('host_away_text'));
                            }
                        })
                        .on(LK.RoomEvent.DataReceived, function (data, participant, kind, topic) {
                            // Ending the stream or hiding the camera is the host's
                            // call alone; viewers publish data too, for chat.
                            if (!participant || participant.identity !== HOST_IDENTITY) {
                                return;
                            }

                            var payload;

                            try {
                                payload = JSON.parse(new TextDecoder().decode(data));
                            } catch (e) {
                                return;
                            }

                            if (topic === 'live_end' && payload.type === 'live_ended') {
                                streamEnded();
                            } else if (topic === 'camera_status') {
                                $container.toggleClass('is-camera-off', payload.type === 'off');
                            }
                        })
                        .on(LK.RoomEvent.AudioPlaybackStatusChanged, syncAudio)
                        .on(LK.RoomEvent.Reconnecting, function () { showStatus(t('reconnecting'), t('reconnecting_text')); })
                        .on(LK.RoomEvent.Reconnected, function () { if (hostHasVideo()) hideStatus(); })
                        .on(LK.RoomEvent.Disconnected, function (reason) {
                            if (reason !== LK.DisconnectReason.CLIENT_INITIATED) {
                                $container.find('.live-remote-video, audio').remove();
                                $holder.show().find('.live-waiting').remove();
                                viewerNotice(t('disconnected'));
                                room = null;
                            }
                        });

                    try {
                        await room.connect(cfg.livekit_url, response.data);
                    } catch (error) {
                        room = null;
                        viewerNotice(t('join_error'));
                        return;
                    }

                    // For add-ons that need the room.
                    $(document).trigger('jws_live_viewer_connected', [room]);

                    addParticipant(room.localParticipant, { user_name: cfg.you_name, user_avatar: cfg.you_avatar });
                    room.remoteParticipants.forEach(function (participant) { addParticipant(participant); });
                    updateViewerCount(room);
                    syncAudio();

                    // Chat works whether or not the host is on camera yet.
                    if (window.jwsLiveChat) {
                        window.jwsLiveChat.connect(room);
                    }

                    if (!hostHasVideo()) {
                        showStatus(t('waiting_title'), t('waiting_text'));
                    }
                })
                .fail(function (xhr) {
                    viewerNotice(errorMessage(xhr) || t('join_error'));
                });
        });
    }

    $(function () {
        countdown();
        hostStudio();
        streamKey();
        viewer();
    });

})(jQuery);
