/**
 * The unified checkout page.
 *
 * Two shapes of payment live here. The hosted ones (PayPal, Stripe's own
 * checkout page) are a round trip: ask the server to make the order, go where
 * it says. The on-page ones (the card form, Apple Pay, Google Pay) confirm
 * against Stripe from this page and then tell the server to look.
 *
 * Stripe Elements runs in deferred mode — it is given the amount up front and
 * the PaymentIntent only when the buyer actually commits — so browsing to the
 * checkout and leaving does not litter the site with abandoned orders.
 */
(function ($) {
	'use strict';

	var config = window.jws_payment || {};

	function Checkout($root) {
		this.$root = $root;
		this.$error = $root.find('.jws-checkout-error');
		this.busy = false;

		this.amount = parseInt($root.data('amount'), 10) || 0;
		this.currency = String($root.data('currency') || 'usd');
		this.mode = String($root.data('mode') || 'payment');

		this.stripe = null;
		this.elements = null;

		this.bindRedirectButtons();
		this.initStripe();
	}

	/* ---------------------------------------------------------------------- */
	/* Chrome                                                                  */
	/* ---------------------------------------------------------------------- */

	Checkout.prototype.fail = function (message) {
		this.busy = false;
		this.$root.removeClass('is-busy');
		this.$root.find('.jws-checkout-button').prop('disabled', false).each(function () {
			var $button = $(this);
			if ($button.data('label')) {
				$button.text($button.data('label'));
			}
		});
		this.$error.text(message || config.i18n.generic).prop('hidden', false);
	};

	Checkout.prototype.start = function ($button) {
		this.busy = true;
		this.$root.addClass('is-busy');
		this.$error.prop('hidden', true).text('');

		if ($button && $button.length) {
			if (!$button.data('label')) {
				$button.data('label', $button.text());
			}
			$button.prop('disabled', true).text(config.i18n.processing);
		}

		this.$root.find('.jws-checkout-button').prop('disabled', true);
	};

	/* ---------------------------------------------------------------------- */
	/* Server                                                                  */
	/* ---------------------------------------------------------------------- */

	/** Asks the server to create the order and whatever the gateway needs. */
	Checkout.prototype.createOrder = function (method) {
		return $.ajax({
			url: config.ajax_url,
			method: 'POST',
			dataType: 'json',
			data: {
				action: 'jws_payment_start',
				nonce: config.nonce,
				method: method
			}
		});
	};

	/**
	 * Tells the server the browser is done, and asks it to check with Stripe.
	 *
	 * The webhook is what really settles an order; this only saves the buyer
	 * waiting for it, and is the sole path that works at all on a site whose
	 * webhooks cannot be reached from the internet.
	 */
	Checkout.prototype.settle = function (token) {
		var self = this;

		return $.ajax({
			url: config.ajax_url,
			method: 'POST',
			dataType: 'json',
			data: {
				action: 'jws_payment_settle',
				nonce: config.nonce,
				token: token
			}
		}).then(function (response) {
			if (response && response.success && response.data && response.data.redirect) {
				window.location.href = response.data.redirect;
				return;
			}

			self.fail(response && response.data ? response.data.message : null);
		});
	};

	/* ---------------------------------------------------------------------- */
	/* Hosted gateways                                                         */
	/* ---------------------------------------------------------------------- */

	Checkout.prototype.bindRedirectButtons = function () {
		var self = this;

		this.$root.on('click', '.jws-checkout-button--alt', function (event) {
			event.preventDefault();

			if (self.busy) {
				return;
			}

			var $button = $(this);

			self.start($button);

			self.createOrder($button.data('method')).done(function (response) {
				if (response && response.success && response.data && response.data.redirect) {
					$button.text(config.i18n.redirect);
					window.location.href = response.data.redirect;
					return;
				}

				self.fail(response && response.data ? response.data.message : null);
			}).fail(function () {
				self.fail();
			});
		});
	};

	/* ---------------------------------------------------------------------- */
	/* Stripe on-page                                                          */
	/* ---------------------------------------------------------------------- */

	Checkout.prototype.initStripe = function () {
		var hasCard = this.$root.find('#jws-card-element').length > 0;
		var hasWallets = this.$root.find('#jws-express-checkout').length > 0;

		if (!config.publishable || !window.Stripe || (!hasCard && !hasWallets) || this.amount <= 0) {
			return;
		}

		this.stripe = window.Stripe(config.publishable);

		this.elements = this.stripe.elements({
			mode: this.mode,
			amount: this.amount,
			currency: this.currency,
			locale: config.locale || 'auto',
			appearance: { theme: 'night' }
		});

		if (hasWallets) {
			this.mountExpress();
		}

		if (hasCard) {
			this.mountCard();
		}
	};

	Checkout.prototype.mountCard = function () {
		var self = this;

		this.elements.create('payment', { layout: 'tabs' }).mount('#jws-card-element');

		this.$root.on('click', '.jws-checkout-button[data-method="card"]', function (event) {
			event.preventDefault();

			if (self.busy) {
				return;
			}

			self.start($(this));
			self.payOnPage('card');
		});
	};

	/**
	 * Apple Pay and Google Pay, through the Express Checkout Element.
	 *
	 * Stripe decides which of the two the browser can actually offer, so the
	 * block stays hidden until it says at least one is available — an empty
	 * wallet slot reads as a broken page.
	 */
	Checkout.prototype.mountExpress = function () {
		var self = this;
		var express = this.elements.create('expressCheckout', {
			buttonHeight: 48
		});

		express.on('ready', function (event) {
			if (event && event.availablePaymentMethods) {
				self.$root.find('.jws-checkout-wallets').prop('hidden', false);
			}
		});

		express.on('confirm', function (event) {
			if (self.busy) {
				return;
			}

			self.start(null);
			self.payOnPage(self.walletMethod(event));
		});

		express.mount('#jws-express-checkout');
	};

	/** Which of our method keys an express-checkout confirm belongs to. */
	Checkout.prototype.walletMethod = function (event) {
		var type = event && event.expressPaymentType ? event.expressPaymentType : '';

		if (type === 'apple_pay' || type === 'google_pay') {
			return type;
		}

		/* Link and anything Stripe adds later are card payments as far as this
		   site is concerned, and `card` is the method that describes them. */
		return 'card';
	};

	/**
	 * The on-page payment, whichever element started it.
	 *
	 * Elements is validated first, then the order is created, then the intent
	 * is confirmed — that order matters: creating the order first would leave a
	 * pending row behind every time someone mistypes a card number.
	 */
	Checkout.prototype.payOnPage = function (method) {
		var self = this;

		this.elements.submit().then(function (result) {
			if (result.error) {
				self.fail(result.error.message);
				return null;
			}

			return self.createOrder(method).then(function (response) {
				if (!response || !response.success || !response.data || !response.data.clientSecret) {
					self.fail(response && response.data ? response.data.message : null);
					return null;
				}

				var order = response.data;

				return self.stripe.confirmPayment({
					elements: self.elements,
					clientSecret: order.clientSecret,
					confirmParams: { return_url: order.returnUrl },
					/*
					 * Stay here when the card needs no bank redirect, which is
					 * the common case — the buyer sees the result without a
					 * page load. Anything that does need one (3-D Secure) is
					 * sent away and comes back through the return handler.
					 */
					redirect: 'if_required'
				}).then(function (confirmation) {
					if (confirmation.error) {
						self.fail(confirmation.error.message);
						return null;
					}

					return self.settle(order.token);
				});
			});
		}).catch(function () {
			self.fail();
		});
	};

	$(function () {
		$('.jws-checkout[data-type]').each(function () {
			new Checkout($(this));
		});
	});
})(jQuery);
