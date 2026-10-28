/**
 * Live Studio (Jws_Livestream_Frontend::admin_view) — the host's panel.
 *
 * Streaming itself stays in livekit.js. This only adds the interface around
 * it: the Webcam / Streaming software tabs, copy buttons, show/hide for the
 * stream key, and the Live / Offline status, which follows the .open class
 * livekit.js puts on .webcam-player while the webcam is on air.
 */
(function ($) {
    'use strict';

    $(function () {

        var $studio = $('.jws-studio');

        if (!$studio.length) {
            return;
        }

        /* Tabs */
        var $tabs = $studio.find('.studio-tab');

        function selectTab($tab) {
            $tabs.each(function () {
                var selected = this === $tab.get(0);
                $(this).toggleClass('is-active', selected).attr('aria-selected', selected ? 'true' : 'false').attr('tabindex', selected ? '0' : '-1');
                $('#' + this.getAttribute('aria-controls')).prop('hidden', !selected);
            });
        }

        $tabs.on('click', function () {
            selectTab($(this));
        }).on('keydown', function (e) {
            if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') {
                return;
            }
            var index = $tabs.index(this) + (e.key === 'ArrowRight' ? 1 : -1);
            var $next = $tabs.eq((index + $tabs.length) % $tabs.length);
            selectTab($next);
            $next.trigger('focus');
        });

        /* Copy */
        function copyText(text) {
            if (navigator.clipboard && window.isSecureContext) {
                return navigator.clipboard.writeText(text);
            }
            return new Promise(function (resolve, reject) {
                var area = document.createElement('textarea');
                area.value = text;
                area.setAttribute('readonly', '');
                area.style.position = 'fixed';
                area.style.opacity = '0';
                document.body.appendChild(area);
                area.select();
                try {
                    document.execCommand('copy') ? resolve() : reject();
                } catch (err) {
                    reject(err);
                }
                document.body.removeChild(area);
            });
        }

        $studio.on('click', '.studio-copy', function () {

            var $btn = $(this);
            var from = $btn.data('copy-from');
            var text = from ? $(from).val() : $btn.data('copy');

            if (!text) {
                return;
            }

            copyText(String(text)).then(function () {
                $btn.addClass('is-copied');
                if (typeof jwsThemeModule !== 'undefined' && jwsThemeModule.show_notification) {
                    jwsThemeModule.show_notification($studio.data('copied'), 'success');
                }
                setTimeout(function () { $btn.removeClass('is-copied'); }, 1500);
            });
        });

        /* Show / hide stream key */
        $studio.on('click', '.studio-reveal', function () {
            var $input = $($(this).data('target'));
            var show = $input.attr('type') === 'password';
            $input.attr('type', show ? 'text' : 'password');
            $(this).attr('aria-pressed', show ? 'true' : 'false').toggleClass('is-active', show);
        });

        /* Live status follows the webcam player */
        var player = $studio.find('.webcam-player').get(0);
        var $status = $studio.find('.studio-status');

        function syncStatus() {
            var live = player.classList.contains('open');
            $studio.toggleClass('is-live', live);
            $status.find('.studio-status__text').text(live ? $status.data('live') : $status.data('offline'));
        }

        if (player && 'MutationObserver' in window) {
            new MutationObserver(syncStatus).observe(player, { attributes: true, attributeFilter: ['class'] });
        }
    });

})(jQuery);
