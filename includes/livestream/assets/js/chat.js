/**
 * Live chat (templates/viewer.php).
 *
 * Messages go to everyone in the room as LiveKit data messages (topic
 * "message") and are saved for late joiners. livekit.js calls
 * jwsLiveChat.connect(room) once the viewer has joined.
 *
 * Also the chat chrome: Chat / Participants tabs, hide chat (remembered),
 * the settings menu and a small emoji picker.
 */
(function ($) {
    'use strict';

    if (typeof jwsLivestream === 'undefined') {
        return;
    }

    var cfg = jwsLivestream;
    var MAX_LENGTH = 300;
    var isGuest = !parseInt(cfg.you_id, 10);

    var $messages = $('#chat-messages');
    var $input = $('#chat-message');
    var $send = $('#send-message');

    function label(key, fallback) {
        return (cfg.i18n && cfg.i18n[key]) || fallback;
    }

    /* Only http(s) URLs reach an <img src>. */
    function safeUrl(url) {
        return typeof url === 'string' && /^https?:\/\//i.test(url) ? url : '';
    }

    function scrollDown() {
        if ($messages.length) {
            $messages.scrollTop($messages[0].scrollHeight);
        }
    }

    /*
     * Who sent a data message, from the participant's metadata. The server
     * signs that into the token, so unlike a name in the message itself it
     * cannot be made up.
     */
    function senderOf(participant) {
        var profile = {};

        try {
            profile = participant && participant.metadata ? JSON.parse(participant.metadata) : {};
        } catch (e) {
            profile = {};
        }

        return {
            name: profile.user_name || label('anonymous', 'Guest'),
            avatar: profile.user_avatar || '',
            isHost: !!participant && participant.identity === 'user_' + cfg.host_id
        };
    }

    /* Built as nodes with text content, never as an HTML string. */
    function appendMessage(sender, text) {
        $('<div>').toggleClass('host-message', sender.isHost)
            .append($('<img>', { src: safeUrl(sender.avatar), alt: '' }))
            .append($('<div>').append($('<strong>').text(sender.name + ':')).append(document.createTextNode(' ' + text)))
            .appendTo($messages);

        scrollDown();
    }

    $send.prop('disabled', true);

    $input.on('input', function () {
        $send.prop('disabled', $.trim($(this).val()) === '');
    });

    /* Chatting needs an account; the input opens the theme's login form. */
    if (isGuest) {
        $input.prop('readonly', true)
            .attr('placeholder', label('login_to_chat', 'Log in to chat'))
            .on('click focus', function () {
                $('.jws-form-login-popup').addClass('open');
            });
        $('#send-emoji').hide();
    }

    var connected = false;

    function connect(room) {

        if (connected) {
            return;
        }

        connected = true;

        $('.chat-sidebar-inner').addClass('chat_open');
        scrollDown();

        room.on(window.LivekitClient.RoomEvent.DataReceived, function (data, participant, kind, topic) {

            if (topic !== 'message') {
                return;
            }

            var payload;

            try {
                payload = JSON.parse(new TextDecoder().decode(data));
            } catch (e) {
                return;
            }

            if (payload && typeof payload.message === 'string') {
                appendMessage(senderOf(participant), payload.message.slice(0, MAX_LENGTH));
            }
        });

        $send.on('click', function () {

            if (isGuest) {
                return;
            }

            var message = $.trim($input.val()).slice(0, MAX_LENGTH);

            if (message === '') {
                return;
            }

            room.localParticipant.publishData(new TextEncoder().encode(JSON.stringify({ type: 'message', message: message })), { reliable: true, topic: 'message' });
            appendMessage(senderOf(room.localParticipant), message);

            $.post(cfg.ajax_url, { action: 'jws_livekit_chat', nonce: cfg.nonce, post_id: cfg.post_id, message: message }, null, 'json')
                .done(function (response) {
                    if (response && !response.success && response.data && response.data.message && typeof jwsThemeModule !== 'undefined') {
                        jwsThemeModule.show_notification($('<div>').text(response.data.message).html(), 'error');
                    }
                });

            $input.val('');
            $send.prop('disabled', true);
        });

        $input.on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $send.trigger('click');
            }
        });
    }

    window.jwsLiveChat = { connect: connect };

    $(function () {

        /* Chat / Participants */
        $('.chat-header button').on('click', function () {
            var tab = $(this).data('tab');
            $('.chat-content').toggle(tab === 'chat');
            $('.chat-participants').toggle(tab === 'participants');
            $('.chat-header button').removeClass('active');
            $(this).addClass('active');
        });

        /* Settings menu */
        $('.controls-left .settings-btn').on('click', function (e) {
            e.stopPropagation();
            $('.jws-chat-setting').toggleClass('open');
        });

        $(document).on('click', function (e) {
            if (!$(e.target).closest('.jws-chat-setting, .settings-btn').length) {
                $('.jws-chat-setting').removeClass('open');
            }
        });

        /* Hide / show chat. Messages keep arriving underneath. */
        (function chatVisibility() {

            var $chat = $('.live-chat');
            var $toggle = $('#jws-hide-chat');
            var KEY = 'jws_live_chat_hidden';

            if (!$chat.length || !$toggle.length) {
                return;
            }

            function setHidden(hidden, remember) {
                $chat.toggleClass('is-chat-hidden', hidden);
                $toggle.attr('aria-pressed', hidden ? 'true' : 'false');

                if (!hidden) {
                    scrollDown();
                }

                if (remember) {
                    try { localStorage.setItem(KEY, hidden ? '1' : '0'); } catch (e) { }
                }
            }

            try { setHidden(localStorage.getItem(KEY) === '1', false); } catch (e) { }

            $toggle.on('click', function () {
                setHidden(!$chat.hasClass('is-chat-hidden'), true);
                $('.jws-chat-setting').removeClass('open');
            });

            $('.chat-hidden-note').on('click', function () {
                setHidden(false, true);
            });
        })();

        /* Emoji picker: inserts a character at the caret. */
        (function emojiPicker() {

            var $wrap = $('#send-emoji');
            var input = $input.get(0);

            if (!$wrap.length || !input || isGuest) {
                return;
            }

            var EMOJIS = '😀 😂 🤣 😊 😍 🥰 😘 😎 🤩 🥳 😇 🙂 😉 😜 🤔 🤗 😮 😱 😢 😭 😡 🥺 😅 😴 👍 👎 👏 🙌 🙏 💪 👋 🤝 ❤️ 🧡 💛 💚 💙 💜 🖤 💯 🔥 ✨ 🎉 🎁 🌹 ⭐ 👀 💥'.split(' ');
            var text = label('emoji', 'Emoji');

            var $button = $('<button type="button" class="live-emoji-btn" aria-haspopup="true" aria-expanded="false"></button>')
                .attr({ title: text, 'aria-label': text })
                .text('😊');
            var $panel = $('<div class="live-emoji-panel" role="dialog" hidden></div>').attr('aria-label', text);

            // The character lives in data-emoji: wp-emoji swaps emoji text
            // for <img class="emoji">, which would leave nothing to insert.
            EMOJIS.forEach(function (emoji) {
                $('<button type="button" class="live-emoji"></button>').attr('data-emoji', emoji).text(emoji).appendTo($panel);
            });

            $wrap.empty().append($button, $panel);

            function toggle(open) {
                $panel.prop('hidden', !open);
                $button.attr('aria-expanded', open ? 'true' : 'false');
                $wrap.toggleClass('active', open);
            }

            $button.on('click', function (e) {
                e.stopPropagation();
                toggle($panel.prop('hidden'));
            });

            $panel.on('click', '.live-emoji', function (e) {
                e.stopPropagation();
                var emoji = this.getAttribute('data-emoji') || '';

                if (!emoji) {
                    return;
                }

                var start = input.selectionStart != null ? input.selectionStart : input.value.length;
                var end = input.selectionEnd != null ? input.selectionEnd : input.value.length;
                input.value = (input.value.slice(0, start) + emoji + input.value.slice(end)).slice(0, MAX_LENGTH);
                input.focus();
                input.setSelectionRange(start + emoji.length, start + emoji.length);
                $input.trigger('input');
                toggle(false);
            });

            $(document).on('click', function (e) {
                if (!$wrap[0].contains(e.target)) {
                    toggle(false);
                }
            }).on('keydown', function (e) {
                if (e.key === 'Escape') {
                    toggle(false);
                }
            });
        })();
    });

})(jQuery);
