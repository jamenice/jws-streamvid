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

        function loadEpisode(episodeId, push) {

            if (loading || !episodeId) {
                return;
            }

            loading = true;

            var $stage = $page.find('.sv-short-stage');

            $stage.addClass('is-loading');

            $.ajax({
                url: jwsDrama.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: { action: 'jws_drama_episode', episode_id: episodeId }
            }).done(function (response) {

                if (!response || !response.success) {
                    // Nothing sensible to show in place — let the browser do it.
                    window.location.href = $page.find('.sv-short-episode[data-episode="' + episodeId + '"]').attr('href');
                    return;
                }

                var data = response.data;

                /* Replacing the stage removes the old <video-player>; the
                   MutationObserver in jws_player_v10.js picks the new one up,
                   and the old one's save timer stops on its own once it is
                   detached. */
                $stage.html(data.stage);

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

            }).fail(function () {
                var href = $page.find('.sv-short-episode[data-episode="' + episodeId + '"]').attr('href');
                if (href) { window.location.href = href; }
            }).always(function () {
                loading = false;
                $stage.removeClass('is-loading');
            });
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

            var index = event.key === 'ArrowUp' ? 1 : (event.key === 'ArrowDown' ? 2 : 0);

            if (!index) {
                return;
            }

            var $link = $page.find('.sv-short-stage-controls a.sv-short-nav').eq(index - 1);

            if ($link.length) {
                event.preventDefault();
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
