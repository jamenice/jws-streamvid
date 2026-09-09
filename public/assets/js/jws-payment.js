/**
 * The unified checkout page.
 *
 * Card, PayPal, the hosted page, Apple Pay and Google Pay all share one
 * radio list and one "Pay Now" button now. Card is the one row that expands
 * in place instead of sending the buyer anywhere — selecting it reveals the
 * card form right there, and Pay Now confirms it on the page. PayPal and the
 * hosted page are a round trip: ask the server to make the order, go where
 * it says.
 *
 * Apple Pay and Google Pay start hidden: neither exists as a plain checkbox
 * anywhere else, because whether the browser can actually offer either one
 * is something only Stripe's own canMakePayment() can answer, and answering
 * takes a moment. Once it does, the row that turned out to be usable is
 * revealed — never auto-checked, so becoming usable can never silently
 * change what Pay Now is about to charge. Paying with one still goes
 * through the same Pay Now button as everything else, using
 * stripe.paymentRequest() rather than Stripe's pre-built Express Checkout
 * button so the row can look and behave like every other method here; the
 * sheet itself is still the browser's own native one, opened synchronously
 * on the click the way Apple's and Google's own payment-sheet rules require.
 *
 * Stripe Elements runs in deferred mode — given the amount up front, the
 * PaymentIntent itself only made when the buyer actually commits — so
 * browsing to the checkout and leaving does not litter the site with
 * abandoned orders.
 */
(function ($) {
	'use strict';

	var config = window.jws_payment || {};

	/* Matches the CSS breakpoint that reorders the two panels and pins Pay
	   Now to the bottom of the screen — see @media (max-width: 767px) in
	   jws-payment.css. */
	var MOBILE_QUERY = '(max-width: 767px)';

	function Checkout($root) {
		this.$root = $root;
		this.$error = $root.find('.jws-checkout-pay > .jws-checkout-error');
		this.$submit = $root.find('#jws-checkout-submit');
		this.$cardPanel = $root.find('.jws-checkout-card-inline');
		this.busy = false;

		/* Captured once, before anything can overwrite it — includes the
		   amount, which is what makes it worth remembering rather than
		   re-translating a generic "Pay Now" on every reset. */
		this.payNowLabel = this.$submit.find('.jws-checkout-pay-label').text();

		this.itemLabel = String($root.data('label') || '');
		this.amount = parseInt($root.data('amount'), 10) || 0;
		this.currency = String($root.data('currency') || 'usd');
		this.mode = String($root.data('mode') || 'payment');
		this.selected = String($root.data('default-method') || '');

		this.stripe = null;
		this.elements = null;
		this.paymentRequest = null;

		this.bindMethodSelection();
		this.bindSubmit();
		this.initStripe();
	}

	/* ---------------------------------------------------------------------- */
	/* Chrome                                                                  */
	/* ---------------------------------------------------------------------- */

	/**
	 * Puts the page back to idle — button re-enabled, "Processing…" gone,
	 * whole-page pointer-events lock lifted.
	 *
	 * The part fail() and a wallet sheet being dismissed both need: an error
	 * is one more thing on top of this, closing the sheet without paying is
	 * exactly this and nothing else. Skipping the distinction and always
	 * showing an error would tell someone who just changed their mind that
	 * something went wrong.
	 */
	Checkout.prototype.reset = function () {
		this.busy = false;
		this.$root.removeClass('is-busy');
		this.setSubmitLabel(this.payNowLabel);
		this.$submit.prop('disabled', false);
	};

	Checkout.prototype.fail = function (message) {
		this.reset();
		this.$error.text(message || config.i18n.generic).prop('hidden', false);
	};

	Checkout.prototype.start = function () {
		this.busy = true;
		this.$root.addClass('is-busy');
		this.$error.prop('hidden', true).text('');
		this.$submit.prop('disabled', true);
		this.setSubmitLabel(config.i18n.processing);
	};

	Checkout.prototype.setSubmitLabel = function (text) {
		if (text) {
			this.$submit.find('.jws-checkout-pay-label').text(text);
		}
	};

	/* ---------------------------------------------------------------------- */
	/* The radio list                                                          */
	/* ---------------------------------------------------------------------- */

	Checkout.prototype.bindMethodSelection = function () {
		var self = this;

		this.$root.on('click', '.jws-checkout-method', function () {
			$(this).find('input[type="radio"]').prop('checked', true);
			self.selectMethod(String($(this).data('method')));
		});

		/*
		 * Also on `change`, not just click: arrow-keying between radios in a
		 * native radiogroup moves the browser's own checked state without a
		 * click ever firing, and the card form's shown/hidden state has to
		 * follow that exactly as it follows a mouse click.
		 */
		this.$root.on('change', '.jws-checkout-method input[type="radio"]', function () {
			self.selectMethod(String($(this).val()));
		});
	};

	Checkout.prototype.selectMethod = function (method) {
		this.selected = method;
		this.$cardPanel.prop('hidden', 'card' !== method);

		/* Whatever badge the chosen row is showing — its own image, or the
		   plain card glyph when it has none — copied onto Pay Now rather
		   than duplicated, so the two can never show two different icons for
		   the same method. */
		var $icon = this.$root.find('.jws-checkout-method[data-method="' + method + '"] .jws-checkout-method-icon');
		this.$submit.find('.jws-checkout-pay-icon').html($icon.length ? $icon.html() : '');

		/*
		 * On mobile the summary panel — where the card form and, above it,
		 * the total — sits above the payment methods, so picking one down
		 * there leaves whatever just changed off the top of the screen.
		 * Scrolling the whole checkout back into view is what puts it back
		 * in front of the buyer instead of leaving them to find it.
		 */
		if (window.matchMedia && window.matchMedia(MOBILE_QUERY).matches) {
			this.$root.get(0).scrollIntoView({ behavior: 'smooth', block: 'start' });
		}
	};

	/* ---------------------------------------------------------------------- */
	/* The shared Pay Now button                                              */
	/* ---------------------------------------------------------------------- */

	Checkout.prototype.bindSubmit = function () {
		var self = this;

		this.$submit.on('click', function (event) {
			event.preventDefault();

			if (self.busy || !self.selected) {
				return;
			}

			if ('card' === self.selected) {
				self.start();
				self.payOnPage('card');
				return;
			}

			if ('apple_pay' === self.selected || 'google_pay' === self.selected) {
				/*
				 * self.start() first: everything it does is a synchronous DOM
				 * change, and paymentRequest.show() right after it still runs
				 * inside the same click — Apple Pay and Google Pay refuse to
				 * open at all once anything asynchronous (a network request,
				 * a promise tick) comes between the tap and the call that
				 * opens them.
				 */
				self.start();
				self.paymentRequest.show();
				return;
			}

			self.payByRedirect(self.selected);
		});
	};

	/** PayPal, the hosted page — anything that sends the buyer away and back. */
	Checkout.prototype.payByRedirect = function (method) {
		var self = this;

		this.start();

		this.createOrder(method).done(function (response) {
			if (response && response.success && response.data && response.data.redirect) {
				self.setSubmitLabel(config.i18n.redirect);
				window.location.href = response.data.redirect;
				return;
			}

			self.fail(response && response.data ? response.data.message : null);
		}).fail(function () {
			self.fail();
		});
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
	/* Stripe: card                                                            */
	/* ---------------------------------------------------------------------- */

	Checkout.prototype.initStripe = function () {
		var hasCard = this.$root.find('#jws-card-element').length > 0;
		var hasWallets = this.$root.find(
			'.jws-checkout-method[data-method="apple_pay"], .jws-checkout-method[data-method="google_pay"]'
		).length > 0;

		if (!config.publishable || !window.Stripe || (!hasCard && !hasWallets) || this.amount <= 0) {
			return;
		}

		this.stripe = window.Stripe(config.publishable);

		if (hasCard) {
			this.elements = this.stripe.elements({
				mode: this.mode,
				amount: this.amount,
				currency: this.currency,
				locale: config.locale || 'auto',
				appearance: { theme: 'night' }
			});

			this.mountCard();
		}

		if (hasWallets) {
			this.mountWallets();
		}
	};

	/**
	 * The card form, mounted whether or not its row starts out selected —
	 * mounting is cheap, and doing it now means the fields are already ready
	 * the moment the card row is picked rather than a beat later.
	 */
	Checkout.prototype.mountCard = function () {
		this.elements.create('payment', { layout: 'tabs' }).mount('#jws-card-element');
	};

	/**
	 * The on-page card payment, from the card row's own Pay Now.
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

	/* ---------------------------------------------------------------------- */
	/* Stripe: Apple Pay / Google Pay                                          */
	/* ---------------------------------------------------------------------- */

	/**
	 * Sets up the native wallet sheet and reveals whichever row it turns out
	 * to answer for.
	 *
	 * stripe.paymentRequest() rather than the Express Checkout Element: the
	 * Element renders its own pre-built button and pays the instant that
	 * button is tapped, which cannot be made to look like a plain radio row
	 * or wait for a separate Pay Now click. This lower-level API hands back
	 * the same native Apple Pay / Google Pay sheet with none of that
	 * built-in chrome, at the cost of this file being the one driving it.
	 */
	Checkout.prototype.mountWallets = function () {
		var self = this;

		this.paymentRequest = this.stripe.paymentRequest({
			country: String(config.country || 'US').toUpperCase(),
			currency: this.currency,
			total: {
				label: this.itemLabel || 'Total',
				amount: this.amount
			},
			requestPayerName: false,
			requestPayerEmail: false
		});

		this.paymentRequest.canMakePayment().then(function (result) {
			if (!result) {
				return;
			}

			/*
			 * canMakePayment() only ever distinguishes Apple Pay explicitly;
			 * anything else it confirms is available is Google Pay — the two
			 * are mutually exclusive per browser in practice (Apple Pay only
			 * in Safari, Google Pay only in Chromium), which is what makes
			 * this safe rather than a guess.
			 */
			var method = result.applePay ? 'apple_pay' : 'google_pay';
			var $row = self.$root.find('.jws-checkout-method[data-method="' + method + '"]');

			if (!$row.length) {
				return;
			}

			/* Revealed, not selected — becoming usable should not silently
			   change what Pay Now is about to charge out from under whatever
			   the buyer already has selected (or the default the page
			   rendered with). */
			$row.prop('hidden', false);
		});

		this.paymentRequest.on('paymentmethod', function (event) {
			self.confirmWalletPayment(event);
		});

		/*
		 * Fires when the payer closes the sheet — the X in Apple Pay's
		 * dialog, the back arrow in Google Pay's — without approving
		 * anything. self.start() already ran when Pay Now opened the sheet;
		 * without this the page stays on "Processing…", disabled and
		 * unclickable, forever, since no other event was ever going to fire
		 * to undo it.
		 */
		this.paymentRequest.on('cancel', function () {
			self.reset();
		});
	};

	/**
	 * Charges the card the native sheet handed back.
	 *
	 * The order is created only after the payer has approved in the sheet,
	 * not before — creating it earlier would leave a pending row behind every
	 * time someone opens the sheet and dismisses it. confirmCardPayment()
	 * with handleActions:false is what the sheet itself needs: it settles
	 * whether anything more (3-D Secure) is required without trying to show
	 * its own UI for that, since the native sheet has already closed and
	 * would have nowhere to show it.
	 */
	Checkout.prototype.confirmWalletPayment = function (event) {
		var self = this;
		var method = ('apple_pay' === event.walletName || 'google_pay' === event.walletName)
			? event.walletName
			: self.selected;

		this.createOrder(method).then(function (response) {
			if (!response || !response.success || !response.data || !response.data.clientSecret) {
				event.complete('fail');
				self.fail(response && response.data ? response.data.message : null);
				return null;
			}

			var order = response.data;

			return self.stripe.confirmCardPayment(
				order.clientSecret,
				{ payment_method: event.paymentMethod.id },
				{ handleActions: false }
			).then(function (confirmation) {
				if (confirmation.error) {
					event.complete('fail');
					self.fail(confirmation.error.message);
					return null;
				}

				/* The sheet itself is done regardless of what happens next —
				   3-D Secure, if it is needed, runs in its own overlay after
				   the sheet has already closed. */
				event.complete('success');

				if (confirmation.paymentIntent && 'requires_action' === confirmation.paymentIntent.status) {
					return self.stripe.confirmCardPayment(order.clientSecret).then(function (result) {
						if (result.error) {
							self.fail(result.error.message);
							return null;
						}

						return self.settle(order.token);
					});
				}

				return self.settle(order.token);
			});
		}, function () {
			event.complete('fail');
			self.fail();
		});
	};

	$(function () {
		$('.jws-checkout[data-type]').each(function () {
			new Checkout($(this));
		});
	});
})(jQuery);
