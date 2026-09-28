<?php
/**
 * StreamVid landing page.
 *
 * A standalone layout: it prints its own document, nav and footer instead of
 * calling get_header()/get_footer(), so the marketing page is not wrapped in the
 * site chrome that the rest of the theme uses. wp_head() and wp_footer() still
 * run, which is what keeps analytics, consent banners and any other plugin that
 * hooks them working. Swap the two for get_header()/get_footer() and delete the
 * .jl-nav and .jl-footer blocks if you would rather reuse the theme's header.
 *
 * The copy is plain HTML rather than translated strings on purpose — this page
 * is edited directly by whoever owns the site, and marketing copy in a .po file
 * is harder to change than markup. Everything in square brackets is a value
 * only you can supply.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes/landing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Every outbound link on the page, in one place. */
$jl_links = array(
	'buy'       => '#buy',
	'demo'      => '#preview',
	'app_phone' => '#mobile-app-landing',
	'app_tv'    => '#tv-app-landing',
	'docs'      => '#docs',
	'changelog' => '#changelog',
	'support'   => '#support',
);

/*
 * Every screenshot on the page, in one place.
 *
 * An entry may be a media library attachment ID (a plain number), a URL, or a
 * file you drop next to this module -- for that, use:
 *     plugin_dir_url( dirname( __FILE__ ) ) . 'assets/img/hero.jpg'
 * Leave an entry empty and the CSS mockup underneath shows through instead, so
 * the page is presentable before any of these exist. The sizes are what the
 * slot is drawn at on a 1440px screen; supply roughly double for retina.
 */
$jl_images = array(
	'hero'      => '', /* 1384 x 888, the site's home page below the browser bar */
	'demo_1'    => '', /* 564 x 344, 8 demo thumbnails, same size each */
	'demo_2'    => '',
	'demo_3'    => '',
	'demo_4'    => '',
	'demo_5'    => '',
	'demo_6'    => '',
	'demo_7'    => '',
	'demo_8'    => '',
	'drama'     => '', /* 564 x 980 portrait, sits under the unlock panel */
	'player'    => '', /* 1096 x 472, the player with a frame showing */
	'import'    => '', /* 1050 x 360, the TMDb import screen */
	'livetv'    => '', /* 1050 x 360, a channel and its guide */
	'app_phone' => '', /* 112 x 204 portrait */
	'app_tv'    => '', /* 244 x 136 landscape */
);

/* Kept out of the markup so the number is changed once, not nine times. */
$jl_version = defined( 'JWS_STREAMVID_VERSION' ) ? JWS_STREAMVID_VERSION : '';

?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'jl' ); ?>>

<!-- nav -->
<header class="jl-nav">
	<div class="jl-container jl-nav__inner">
		<a class="jl-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">STREAM<span>VID</span></a>
		<nav class="jl-nav__links">
			<a href="#demos">Demos</a>
			<a href="#revenue">Monetization</a>
			<a href="#player">Player</a>
			<a href="#catalogue">Catalogue</a>
			<a href="#apps">Apps</a>
			<a href="#pricing">Pricing</a>
		</nav>
		<a class="jl-btn jl-btn--ghost jl-btn--sm" href="<?php echo esc_url( $jl_links['demo'] ); ?>">Live preview</a>
		<a class="jl-btn jl-btn--grad jl-btn--sm" href="#pricing">Buy now</a>
		<button class="jl-nav__toggle" type="button" aria-label="Open menu">
			<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
		</button>
	</div>
</header>

<!-- hero -->
<section class="jl-hero" id="top">
	<span class="jl-hero__glow" aria-hidden="true"></span>
	<div class="jl-container jl-hero__inner">
		<div class="jl-hero__copy">
			<span class="jl-eyebrow">Version 7.4 — coin wallet and short drama</span>
			<h1 class="jl-h1">Launch a streaming service, not just a website.</h1>
			<p class="jl-lead">Movies, series, short drama, live channels and pay-per-view — with six built-in ways to charge for them. Fill the catalogue from TMDb, lay it out in Elementor, take the money with Stripe or PayPal.</p>
			<div class="jl-cta-row">
				<a class="jl-btn jl-btn--grad" href="#pricing">Buy now — [YOUR PRICE]</a>
				<a class="jl-btn jl-btn--ghost" href="<?php echo esc_url( $jl_links['demo'] ); ?>">
					<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M8 5v14l11-7-11-7z"/></svg>
					Live preview
				</a>
			</div>
			<div class="jl-rating">
				<span class="jl-rating__stars">
					<svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><path d="M12 4l2.4 5 5.6.8-4 4 1 5.6L12 16.8 7 19.4l1-5.6-4-4 5.6-.8z"/></svg>
					<svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><path d="M12 4l2.4 5 5.6.8-4 4 1 5.6L12 16.8 7 19.4l1-5.6-4-4 5.6-.8z"/></svg>
					<svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><path d="M12 4l2.4 5 5.6.8-4 4 1 5.6L12 16.8 7 19.4l1-5.6-4-4 5.6-.8z"/></svg>
					<svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><path d="M12 4l2.4 5 5.6.8-4 4 1 5.6L12 16.8 7 19.4l1-5.6-4-4 5.6-.8z"/></svg>
					<svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><path d="M12 4l2.4 5 5.6.8-4 4 1 5.6L12 16.8 7 19.4l1-5.6-4-4 5.6-.8z"/></svg>
				</span>
				<strong>[RATING] on ThemeForest</strong>
				<i class="jl-sep"></i>
				<span>[SALES] sales</span>
				<i class="jl-sep"></i>
				<span>Updated <?php echo esc_html( date_i18n( 'M Y' ) ); ?></span>
			</div>
		</div>

		<div class="jl-browser">
			<div class="jl-browser__bar">
				<span class="jl-dot"></span><span class="jl-dot"></span><span class="jl-dot"></span>
				<span class="jl-browser__url">yoursite.com</span>
			</div>
			<div class="jl-browser__body">
			<?php Jws_Landing::shot( $jl_images['hero'], 'The StreamVid home page', array( 'lazy' => false ) ); ?>
			<div class="jl-site-nav">
				<span class="jl-site-nav__logo">STREAMVID</span>
				<span style="color:#cccdd2">Movies</span>
				<span>Series</span>
				<span>Drama</span>
				<span>Live TV</span>
				<span class="jl-site-nav__spacer"></span>
				<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg>
				<span class="jl-site-nav__vip">Go VIP</span>
			</div>
			<div class="jl-banner">
				<span class="jl-banner__scrim" aria-hidden="true"></span>
				<div class="jl-banner__copy">
					<span class="jl-badges">
						<span class="jl-badge jl-badge--accent">4K</span>
						<span class="jl-badge">HDR</span>
						<span class="jl-badge jl-badge--outline">18+</span>
					</span>
					<span class="jl-banner__title">[Featured title]</span>
					<span class="jl-banner__meta"><span>8.4</span><span>·</span><span>2026</span><span>·</span><span>1h 52m</span><span>·</span><span>Thriller</span></span>
					<span class="jl-banner__actions">
						<span class="jl-mini-btn"><svg viewBox="0 0 24 24" width="9" height="9" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>Play</span>
						<span class="jl-mini-btn jl-mini-btn--ghost">+ My list</span>
					</span>
				</div>
			</div>
			<div class="jl-rail">
				<div class="jl-rail__head"><strong>Trending now</strong><span>View all</span></div>
				<div class="jl-rail__items">
					<span class="jl-poster jl-tint-1"><span class="jl-poster__tag jl-badge--accent">4K</span><span class="jl-poster__label">[Title]</span></span>
					<span class="jl-poster jl-tint-2"><span class="jl-poster__label">[Title]</span></span>
					<span class="jl-poster jl-tint-3"><span class="jl-poster__tag jl-badge">NEW</span><span class="jl-poster__label">[Title]</span></span>
					<span class="jl-poster jl-tint-4"><span class="jl-poster__label">[Title]</span></span>
					<span class="jl-poster jl-tint-5"><span class="jl-poster__label">[Title]</span></span>
					<span class="jl-poster jl-tint-6"><span class="jl-poster__label">[Title]</span></span>
				</div>
			</div>
			</div>
		</div>
	</div>
</section>

<!-- at a glance -->
<section class="jl-stats">
	<div class="jl-container jl-stats__inner">
		<div class="jl-stat"><b>8</b><span>Home layouts, one-click import</span></div>
		<div class="jl-stat"><b>7</b><span>Content types, fully modelled</span></div>
		<div class="jl-stat"><b>53</b><span>Elementor widgets</span></div>
		<div class="jl-stat"><b class="is-accent">6</b><span>Ways to charge for it</span></div>
	</div>
</section>

<!-- demos -->
<section class="jl-section" id="demos">
	<div class="jl-container">
		<div class="jl-head">
			<div class="jl-head__main">
				<span class="jl-label"><span class="jl-label__num">01</span><i class="jl-label__rule"></i>Ready-made layouts</span>
				<h2 class="jl-h2">Eight home pages. Pick one and start filling it.</h2>
			</div>
			<p class="jl-head__aside">Every demo imports with its content, menus, widgets and theme options in one click — then it is ordinary Elementor, so nothing is locked away.</p>
		</div>

		<div class="jl-demos">
			<article class="jl-demo">
				<div class="jl-demo__thumb">
					<span class="jl-demo__hero jl-tint-1" style="height:76px"></span>
					<span class="jl-demo__row"><span></span><span></span><span></span><span></span></span>
					<span class="jl-demo__row"><span></span><span></span><span></span><span></span></span>
					<?php Jws_Landing::shot( $jl_images['demo_1'], 'Home 01 demo' ); ?>
				</div>
				<div class="jl-demo__body"><h3>Home 01</h3><span>[One-line description]</span></div>
			</article>
			<article class="jl-demo">
				<div class="jl-demo__thumb jl-demo__thumb--row">
					<span class="jl-demo__hero jl-tint-2" style="width:40%"></span>
					<span class="jl-demo__col"><i class="jl-demo__bar" style="width:65%"></i><span></span><span></span></span>
					<?php Jws_Landing::shot( $jl_images['demo_2'], 'Home 02 demo' ); ?>
				</div>
				<div class="jl-demo__body"><h3>Home 02</h3><span>[One-line description]</span></div>
			</article>
			<article class="jl-demo">
				<div class="jl-demo__thumb">
					<span class="jl-demo__hero jl-tint-3" style="height:54px"></span>
					<span class="jl-demo__grid jl-demo__grid--3"><span></span><span></span><span></span></span>
					<?php Jws_Landing::shot( $jl_images['demo_3'], 'Home 03 demo' ); ?>
				</div>
				<div class="jl-demo__body"><h3>Home 03</h3><span>[One-line description]</span></div>
			</article>
			<article class="jl-demo">
				<div class="jl-demo__thumb">
					<span class="jl-demo__hero jl-tint-4" style="height:64px"></span>
					<span class="jl-demo__grid jl-demo__grid--5"><span></span><span></span><span></span><span></span><span></span></span>
					<?php Jws_Landing::shot( $jl_images['demo_4'], 'Home 04 demo' ); ?>
				</div>
				<div class="jl-demo__body"><h3>Home 04</h3><span>[One-line description]</span></div>
			</article>
			<article class="jl-demo">
				<div class="jl-demo__thumb">
					<i class="jl-demo__bar" style="width:42%"></i>
					<span class="jl-demo__row"><span class="jl-tint-1"></span><span></span></span>
					<span class="jl-demo__row"><span></span><span class="jl-tint-2"></span></span>
					<?php Jws_Landing::shot( $jl_images['demo_5'], 'Home 05 demo' ); ?>
				</div>
				<div class="jl-demo__body"><h3>Home 05</h3><span>[One-line description]</span></div>
			</article>
			<article class="jl-demo">
				<div class="jl-demo__thumb">
					<span class="jl-demo__hero jl-tint-5" style="flex:1 1 0"></span>
					<span class="jl-demo__row" style="flex:0 0 28px"><span style="max-width:28px;border-radius:50%" class="jl-tint-2"></span><span style="max-width:28px;border-radius:50%"></span><span style="max-width:28px;border-radius:50%"></span><span></span></span>
					<?php Jws_Landing::shot( $jl_images['demo_6'], 'Home 06 demo' ); ?>
				</div>
				<div class="jl-demo__body"><h3>Home 06</h3><span>[One-line description]</span></div>
			</article>
			<article class="jl-demo">
				<div class="jl-demo__thumb jl-demo__thumb--row">
					<span class="jl-demo__col" style="flex:0 0 24px"><span class="jl-tint-2" style="flex:0 0 16px"></span><span style="flex:0 0 10px"></span><span style="flex:0 0 10px"></span><span style="flex:0 0 10px"></span></span>
					<span class="jl-demo__grid jl-demo__grid--3"><span class="jl-tint-1"></span><span></span><span></span><span></span><span class="jl-tint-5"></span><span></span></span>
					<?php Jws_Landing::shot( $jl_images['demo_7'], 'Home 07 demo' ); ?>
				</div>
				<div class="jl-demo__body"><h3>Home 07</h3><span>[One-line description]</span></div>
			</article>
			<article class="jl-demo jl-demo--accent">
				<div class="jl-demo__thumb">
					<span class="jl-demo__hero jl-demo__strip jl-tint-2"></span>
					<span class="jl-demo__hero jl-demo__strip" style="background:#3a2a5e"></span>
					<span class="jl-demo__hero jl-demo__strip" style="background:#241d52"></span>
					<?php Jws_Landing::shot( $jl_images['demo_8'], 'Home 08 demo' ); ?>
				</div>
				<div class="jl-demo__body"><h3>Home 08</h3><span>Built for short drama</span></div>
			</article>
		</div>

		<div class="jl-chips">
			<span class="jl-chips__label">Inner pages included</span>
			<span class="jl-chip">Browse</span>
			<span class="jl-chip">Genres</span>
			<span class="jl-chip">Topic</span>
			<span class="jl-chip">Pricing</span>
			<span class="jl-chip">Request a title</span>
			<span class="jl-chip">Wishlist</span>
			<span class="jl-chip">Account area</span>
			<span class="jl-chip">Shop</span>
			<span class="jl-chip">Blog, 4 styles</span>
			<span class="jl-chip">404</span>
		</div>
	</div>
</section>

<!-- monetization -->
<section class="jl-section jl-section--alt" id="revenue">
	<div class="jl-container">
		<div class="jl-head jl-head--center">
			<div class="jl-head__main">
				<span class="jl-label"><span class="jl-label__num">02</span><i class="jl-label__rule"></i>Monetization</span>
				<h2 class="jl-h2">Six ways to get paid, all in the theme</h2>
			</div>
			<p class="jl-head__aside">Run one of them or all six at once. Each has its own admin screen, its own page in the viewer's account, and its own records.</p>
		</div>

		<div class="jl-cards">
			<article class="jl-card">
				<span class="jl-card__icon"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 5 6v6c0 4 3 6.5 7 8 4-1.5 7-4 7-8V6z"/><path d="M9 12l2 2 4-4"/></svg></span>
				<h3>Subscriptions</h3>
				<p>Paid Memberships Pro levels gate any title, season or episode. A level also sets the device limit and switches ads off.</p>
			</article>
			<article class="jl-card">
				<span class="jl-card__icon"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9a2 2 0 0 0 0 6v3h18v-3a2 2 0 0 1 0-6V6H3z"/><path d="M12 8v8"/></svg></span>
				<h3>Pay-per-view</h3>
				<p>Sell a single title outright. Buyers keep it in a Purchased list in their account, with no subscription at all.</p>
			</article>
			<article class="jl-card">
				<span class="jl-card__icon"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
				<h3>Rentals</h3>
				<p>Set a price and a window per title. Access expires on its own, and the rental shows the time it has left.</p>
			</article>
			<article class="jl-card jl-card--accent">
				<span class="jl-card__top">
					<span class="jl-card__icon"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="9.5" cy="9.5" r="5.5"/><circle cx="15" cy="15" r="5.5"/></svg></span>
					<span class="jl-pill">New in 7.4</span>
				</span>
				<h3>Coin wallet</h3>
				<p>Viewers top up coins, then spend them one episode at a time. Six packages from 300 to 10,000 coins, bonuses from +15% to +100%.</p>
			</article>
			<article class="jl-card">
				<span class="jl-card__icon"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11v2a1 1 0 0 0 1 1h2l4 4V6L6 10H4a1 1 0 0 0-1 1z"/><path d="M16 8a5 5 0 0 1 0 8"/></svg></span>
				<h3>Advertising</h3>
				<p>VAST and VMAP breaks, self-hosted ad video, banners and per-category rules. A viewer can also unlock an episode by watching one.</p>
			</article>
			<article class="jl-card">
				<span class="jl-card__icon"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8h12l-1 12H7z"/><path d="M9 8a3 3 0 0 1 6 0"/></svg></span>
				<h3>Merch store</h3>
				<p>A full WooCommerce shop with wishlist, mini-cart, filters and product questions — styled to match the rest of the site.</p>
			</article>
		</div>

		<div class="jl-chips jl-chips--center">
			<span class="jl-chips__label">Takes payment through</span>
			<span class="jl-chip jl-chip--solid">Stripe</span>
			<span class="jl-chip jl-chip--solid">Apple Pay</span>
			<span class="jl-chip jl-chip--solid">Google Pay</span>
			<span class="jl-chip jl-chip--solid">PayPal</span>
			<span class="jl-chip jl-chip--solid">Square</span>
			<span class="jl-chip jl-chip--solid">WooCommerce Payments</span>
		</div>
	</div>
</section>

<!-- short drama -->
<section class="jl-section">
	<div class="jl-container jl-drama">
		<div class="jl-drama__copy">
			<span class="jl-label"><span class="jl-label__num">03</span><i class="jl-label__rule"></i>Short drama</span>
			<h2 class="jl-h2">The vertical format, paywalled per episode</h2>
			<p>Short drama is its own post type with its own episodes, archive and templates. Give away the first few episodes, charge coins for the rest, and let a VIP plan skip the wallet entirely.</p>
			<div class="jl-ticks">
				<span class="jl-tick"><svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>Portrait player — swipe up or down for the next episode</span>
				<span class="jl-tick"><svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>Free episodes and coins per episode, per title or site-wide</span>
				<span class="jl-tick"><svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>One modal holds VIP plans and the top-up shelf — nobody leaves the player</span>
				<span class="jl-tick"><svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>Balance, transactions and unlocked episodes in the account area</span>
			</div>
		</div>

		<div class="jl-phone">
			<div class="jl-phone__screen">
				<?php Jws_Landing::shot( $jl_images['drama'], 'A short drama episode on a phone', array( 'class' => 'jl-shot--under' ) ); ?>
				<span class="jl-phone__pill"></span>
				<div class="jl-unlock">
					<span class="jl-unlock__head">
						<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
						Episode 6 is locked
					</span>
					<span class="jl-unlock__balance">Your balance <b>1,200 coins</b></span>
					<button class="jl-btn jl-btn--grad" type="button">Unlock for 60 coins</button>
					<button class="jl-btn jl-btn--ghost" type="button">Go VIP — all episodes</button>
					<span class="jl-unlock__note">or watch an ad to unlock this one</span>
				</div>
			</div>
		</div>
	</div>
</section>

<!-- player -->
<section class="jl-section jl-section--alt" id="player">
	<div class="jl-container">
		<div class="jl-head">
			<div class="jl-head__main">
				<span class="jl-label"><span class="jl-label__num">04</span><i class="jl-label__rule"></i>The player</span>
				<h2 class="jl-h2">A player that already handles the hard parts</h2>
			</div>
		</div>

		<div class="jl-split">
			<div class="jl-featgrid">
				<div class="jl-feat"><b>Two engines</b><span>Video.js 7 or Video.js 10, switched in theme options.</span></div>
				<div class="jl-feat"><b>Multi-quality and HLS</b><span>Several sources per title, .m3u8 streams, quality switching.</span></div>
				<div class="jl-feat"><b>Subtitles</b><span>A VTT track per language, uploaded or by URL.</span></div>
				<div class="jl-feat"><b>Chromecast and AirPlay</b><span>Throw a title to the TV from the same controls.</span></div>
				<div class="jl-feat"><b>Continue watching</b><span>Resume where they stopped, with a prompt on return.</span></div>
				<div class="jl-feat"><b>Episodes in the player</b><span>Change episode without leaving the page. Auto-next optional.</span></div>
				<div class="jl-feat"><b>Hotkeys and skip</b><span>Keyboard control and a fast-forward button you can turn off.</span></div>
				<div class="jl-feat"><b>Your watermark</b><span>A logo over the video, plus thumbnail previews on the bar.</span></div>
			</div>

			<div class="jl-player-wrap">
				<div class="jl-player">
					<div class="jl-player__stage">
						<?php Jws_Landing::shot( $jl_images['player'], 'The StreamVid player' ); ?>
						<span class="jl-player__ad">Ad · skip in 5s</span>
						<span class="jl-player__logo">STREAMVID</span>
						<span class="jl-player__play"><svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M9 5.5v13l10-6.5z"/></svg></span>
					</div>
					<div class="jl-player__bar">
						<div class="jl-progress"><span class="jl-progress__fill"></span><span class="jl-progress__knob"></span></div>
						<div class="jl-player__controls">
							<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
							<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6v12M8 12l9-6v12z"/></svg>
							<span class="jl-player__time">18:24 / 47:10</span>
							<span class="jl-player__spacer"></span>
							<span class="jl-player__quality">1080p</span>
							<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M7 11h3M7 14h6M14 11h3"/></svg>
							<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7a3 3 0 0 1 3-3h12a3 3 0 0 1 3 3v9a3 3 0 0 1-3 3h-5"/><path d="M3 13a6 6 0 0 1 6 6"/></svg>
							<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 9V5h4M20 9V5h-4M4 15v4h4M20 15v4h-4"/></svg>
						</div>
					</div>
				</div>
				<div>
					<span class="jl-subhead">Where the files live</span>
					<div class="jl-chips" style="margin:11px 0">
						<span class="jl-chip jl-chip--solid">Bunny CDN</span>
						<span class="jl-chip jl-chip--solid">Cloudflare Stream</span>
						<span class="jl-chip jl-chip--solid">Self-hosted</span>
						<span class="jl-chip jl-chip--solid">Embed or iframe</span>
						<span class="jl-chip jl-chip--solid">YouTube</span>
						<span class="jl-chip jl-chip--solid">FFmpeg encoding</span>
					</div>
					<p class="jl-note">Signed URLs expire on a timer for Bunny and Cloudflare, so a copied link stops working.</p>
				</div>
			</div>
		</div>
	</div>
</section>

<!-- catalogue -->
<section class="jl-section" id="catalogue">
	<div class="jl-container">
		<div class="jl-head">
			<div class="jl-head__main">
				<span class="jl-label"><span class="jl-label__num">05</span><i class="jl-label__rule"></i>The catalogue</span>
				<h2 class="jl-h2">Seven content types, already modelled</h2>
			</div>
			<p class="jl-head__aside">Each one arrives with its own admin fields, archive, single template and taxonomies. No page-builder gymnastics to fake a series.</p>
		</div>

		<div class="jl-types">
			<article class="jl-type"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M8 4v16M16 4v16"/></svg><h3>Movies</h3><span>Sources, qualities, cast, trailer, downloads</span></article>
			<article class="jl-type"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 3 8l9 5 9-5z"/><path d="M3 13l9 5 9-5"/></svg><h3>TV shows</h3><span>Seasons and episodes, per-season artwork</span></article>
			<article class="jl-type jl-type--accent"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 19h2"/></svg><h3>Short drama</h3><span>Vertical episodes behind a coin paywall</span></article>
			<article class="jl-type"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20V9m0 0 4 4m-4-4-4 4"/><path d="M4 5h16"/></svg><h3>Videos</h3><span>Standalone clips, uploaded by you or members</span></article>
			<article class="jl-type"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="13" rx="2"/><path d="M8 21h8"/></svg><h3>Live TV</h3><span>Channels with a stream and a programme guide</span></article>
			<article class="jl-type"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M5 21c0-4 3.1-6 7-6s7 2 7 6"/></svg><h3>People</h3><span>Cast and crew pages, credits both ways</span></article>
			<article class="jl-type"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8h12l-1 12H7z"/><path d="M9 8a3 3 0 0 1 6 0"/></svg><h3>Blog and shop</h3><span>Editorial posts and a WooCommerce store</span></article>
			<article class="jl-type jl-type--soft"><span class="jl-subhead">Sorted by</span><span>Genres · Countries · Age ratings · Topics · Badges</span></article>
		</div>
	</div>
</section>

<!-- import and live tv -->
<section class="jl-section jl-section--alt">
	<div class="jl-container jl-panels">
		<div class="jl-panel">
			<span class="jl-label" style="gap:0">Import</span>
			<h2>Fill the catalogue in an afternoon</h2>
			<p>Pull titles from TMDb with filters and a language of your choice — posters, backdrops, trailers, cast and crew come with them. Import everything an actor appeared in from one cast ID, or bring clips over from YouTube.</p>
			<div class="jl-mock">
				<?php Jws_Landing::shot( $jl_images['import'], 'Importing titles from TMDb' ); ?>
				<div class="jl-mock__head">
					<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg>
					Search TMDb
					<span class="jl-mock__cta">Import 24</span>
				</div>
				<div class="jl-mock__row"><span class="jl-mock__poster jl-tint-1"></span><span class="jl-mock__lines"><i style="width:55%"></i><i style="width:30%"></i></span><span class="jl-mock__state is-ready">Ready</span></div>
				<div class="jl-mock__row"><span class="jl-mock__poster jl-tint-2"></span><span class="jl-mock__lines"><i style="width:68%"></i><i style="width:24%"></i></span><span class="jl-mock__state is-ready">Ready</span></div>
				<div class="jl-mock__row"><span class="jl-mock__poster jl-tint-3"></span><span class="jl-mock__lines"><i style="width:46%"></i><i style="width:34%"></i></span><span class="jl-mock__state is-pending">Pending</span></div>
			</div>
		</div>

		<div class="jl-panel">
			<span class="jl-label" style="gap:0">Live TV</span>
			<h2>Channels with a real schedule</h2>
			<p>Each channel carries its stream, its category and a programme guide, so viewers can see what is on now and what is next.</p>
			<div class="jl-mock">
				<?php Jws_Landing::shot( $jl_images['livetv'], 'A live channel and its programme guide' ); ?>
				<div class="jl-mock__row" style="background:#0a0d24"><span class="jl-mock__logo jl-tint-2"></span><span class="jl-mock__lines"><i style="width:42%"></i><i style="width:62%"></i></span><span class="jl-onair">ON AIR</span></div>
				<div class="jl-epg">
					<span class="jl-epg__slot is-live">20:00<i></i></span>
					<span class="jl-epg__slot">21:00<i></i></span>
					<span class="jl-epg__slot">22:00<i></i></span>
				</div>
				<div class="jl-mock__row"><span class="jl-mock__logo jl-tint-3"></span><span class="jl-mock__lines"><i style="width:36%"></i><i style="width:52%"></i></span><span class="jl-mock__state is-next">Next 20:45</span></div>
			</div>
		</div>
	</div>
</section>

<!-- comparison -->
<section class="jl-section">
	<div class="jl-container">
		<div class="jl-head">
			<div class="jl-head__main">
				<span class="jl-label"><span class="jl-label__num">06</span><i class="jl-label__rule"></i>Why this one</span>
				<h2 class="jl-h2">What a video theme usually leaves you to build</h2>
			</div>
		</div>

		<table class="jl-table">
			<thead>
				<tr>
					<th scope="col">Capability</th>
					<th scope="col" class="jl-col-them">Typical video theme</th>
					<th scope="col" class="jl-col-us is-mine">StreamVid</th>
				</tr>
			</thead>
			<tbody>
				<tr><td>Series with seasons and episodes</td><td class="jl-col-them">Posts and categories</td><td class="jl-col-us"><svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" role="img" aria-label="Included"><path d="M20 6 9 17l-5-5"/></svg></td></tr>
				<tr><td>Vertical short drama, per-episode paywall</td><td class="jl-col-them">Not available</td><td class="jl-col-us"><svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" role="img" aria-label="Included"><path d="M20 6 9 17l-5-5"/></svg></td></tr>
				<tr><td>Coin wallet, top-ups and transaction history</td><td class="jl-col-them">Not available</td><td class="jl-col-us"><svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" role="img" aria-label="Included"><path d="M20 6 9 17l-5-5"/></svg></td></tr>
				<tr><td>Pay-per-view and timed rentals</td><td class="jl-col-them">A plugin to buy</td><td class="jl-col-us"><svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" role="img" aria-label="Included"><path d="M20 6 9 17l-5-5"/></svg></td></tr>
				<tr><td>Live channels with a programme guide</td><td class="jl-col-them">An embed at best</td><td class="jl-col-us"><svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" role="img" aria-label="Included"><path d="M20 6 9 17l-5-5"/></svg></td></tr>
				<tr><td>Bulk TMDb import with cast and crew</td><td class="jl-col-them">Manual entry</td><td class="jl-col-us"><svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" role="img" aria-label="Included"><path d="M20 6 9 17l-5-5"/></svg></td></tr>
				<tr><td>Device limit per membership level</td><td class="jl-col-them">Not available</td><td class="jl-col-us"><svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" role="img" aria-label="Included"><path d="M20 6 9 17l-5-5"/></svg></td></tr>
				<tr><td>Companion mobile and TV apps</td><td class="jl-col-them">Not available</td><td class="jl-col-us"><svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" role="img" aria-label="Included"><path d="M20 6 9 17l-5-5"/></svg></td></tr>
			</tbody>
		</table>
	</div>
</section>

<!-- builder -->
<section class="jl-section jl-section--alt" id="builder">
	<div class="jl-container">
		<div class="jl-head">
			<div class="jl-head__main">
				<span class="jl-label"><span class="jl-label__num">07</span><i class="jl-label__rule"></i>Build and restyle</span>
				<h2 class="jl-h2">Everything is yours to rearrange</h2>
			</div>
			<p class="jl-head__aside">Streaming-specific Elementor widgets, a header and footer builder, and theme options deep enough that most sites never touch a template file.</p>
		</div>

		<div class="jl-cards">
			<article class="jl-card"><span class="jl-price-card__amount" style="font-size:30px">53</span><h3 style="font-size:16px">Elementor widgets</h3><p style="font-size:13px">23 built for streaming — sliders, rails, filters, watchlist, membership levels — plus 30 general ones.</p></article>
			<article class="jl-card"><span class="jl-price-card__amount" style="font-size:30px">41</span><h3 style="font-size:16px">Header and footer templates</h3><p style="font-size:13px">Built visually, assigned per page, with side and off-canvas variants.</p></article>
			<article class="jl-card"><span class="jl-price-card__amount" style="font-size:30px">49</span><h3 style="font-size:16px">Theme option groups</h3><p style="font-size:13px">Colours, typography, buttons, image sizes and a custom slug for every post type.</p></article>
			<article class="jl-card"><span class="jl-price-card__amount" style="font-size:30px">22</span><h3 style="font-size:16px">Card and page skins</h3><p style="font-size:13px">Six movie skins, four for series, ten genre cards, plus four single-page layouts.</p></article>
			<article class="jl-card"><span class="jl-price-card__amount" style="font-size:30px">RTL</span><h3 style="font-size:16px">Translation ready</h3><p style="font-size:13px">Right-to-left support and translatable strings throughout.</p></article>
			<article class="jl-card jl-card--accent"><span class="jl-price-card__amount" style="font-size:30px;color:#b9acff">No ACF</span><h3 style="font-size:16px">Native admin fields</h3><p style="font-size:13px">Since 7.4 the metaboxes are the theme's own — one paid plugin fewer to buy.</p></article>
		</div>
	</div>
</section>

<!-- accounts and security -->
<section class="jl-section">
	<div class="jl-container">
		<h2 class="jl-h2 jl-h2--sm" style="max-width:700px;margin-bottom:30px">Accounts your viewers keep, and a paywall that holds</h2>
		<div class="jl-panels">
			<div class="jl-panel" style="padding:27px">
				<h3>For the viewer</h3>
				<div class="jl-panel__list">
					<span>Watchlist, favourites, playlists and history that survives a reload</span>
					<span>Sign-up with OTP and reCAPTCHA, or straight in with Google, Facebook or Apple</span>
					<span>Reviews and threaded comments with ratings and likes</span>
					<span>A form to request the title they cannot find</span>
				</div>
			</div>
			<div class="jl-panel" style="padding:27px">
				<h3>For you</h3>
				<div class="jl-panel__list">
					<span>A device limit per membership level, with remote sign-out</span>
					<span>Expiring signed URLs, so a shared link dies</span>
					<span>WooCommerce HPOS ready, CSRF-protected admin actions, escaped output</span>
					<span>Orders, invoices and a ledger for every kind of purchase</span>
				</div>
			</div>
		</div>
	</div>
</section>

<!-- apps -->
<section class="jl-section jl-section--alt" id="apps">
	<div class="jl-container">
		<div class="jl-head">
			<div class="jl-head__main">
				<span class="jl-label"><span class="jl-label__num">08</span><i class="jl-label__rule"></i>Apps</span>
				<h2 class="jl-h2 jl-h2--sm">Put the same catalogue on phones and TVs</h2>
			</div>
			<p class="jl-head__aside">Both apps talk to this site over the same REST API. Sold separately on CodeCanyon.</p>
		</div>

		<div class="jl-apps">
			<a class="jl-app" href="<?php echo esc_url( $jl_links['app_phone'] ); ?>">
				<span class="jl-device--phone"><span class="jl-device__screen"><?php Jws_Landing::shot( $jl_images['app_phone'], 'The StreamVid mobile app' ); ?></span></span>
				<span class="jl-app__body">
					<h3>Mobile app</h3>
					<span class="jl-app__meta">iOS and Android · Flutter source</span>
					<p>Native player, offline downloads, push notifications, coin top-ups in the app.</p>
					<span class="jl-app__more">View the mobile app
						<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h13M13 6l6 6-6 6"/></svg>
					</span>
				</span>
			</a>
			<a class="jl-app" href="<?php echo esc_url( $jl_links['app_tv'] ); ?>">
				<span class="jl-device--tv"><div><span class="jl-device__screen"><?php Jws_Landing::shot( $jl_images['app_tv'], 'The StreamVid TV app' ); ?></span></div><span></span></span>
				<span class="jl-app__body">
					<h3>TV app</h3>
					<span class="jl-app__meta">Android TV and Google TV · Flutter source</span>
					<p>Remote-first layout, on-screen keyboard, sign in by scanning a code on a phone.</p>
					<span class="jl-app__more">View the TV app
						<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h13M13 6l6 6-6 6"/></svg>
					</span>
				</span>
			</a>
		</div>
	</div>
</section>

<!-- pricing -->
<section class="jl-section jl-pricing" id="pricing">
	<span class="jl-pricing__glow" aria-hidden="true"></span>
	<div class="jl-container jl-pricing__inner">
		<div class="jl-pricing__copy">
			<span class="jl-label"><span class="jl-label__num">09</span><i class="jl-label__rule"></i>What you get</span>
			<h2 class="jl-h2 jl-h2--sm">One purchase, the whole stack</h2>
			<div class="jl-includes">
				<span class="jl-tick"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>StreamVid theme and child theme</span>
				<span class="jl-tick"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>JWS StreamVid core plugin</span>
				<span class="jl-tick"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>TMDb and YouTube importer</span>
				<span class="jl-tick"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>All eight demos with their content</span>
				<span class="jl-tick"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>Documentation and setup videos</span>
				<span class="jl-tick"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>[SUPPORT TERM] support, lifetime updates</span>
			</div>
		</div>

		<div class="jl-price-card">
			<span class="jl-price-card__label">Regular license</span>
			<span class="jl-price-card__amount">[YOUR PRICE]</span>
			<p>One end product, as Envato's licence defines it. An extended licence covers a paid, multi-user service.</p>
			<a class="jl-btn jl-btn--grad jl-btn--block" href="<?php echo esc_url( $jl_links['buy'] ); ?>">Buy on ThemeForest</a>
			<a class="jl-btn jl-btn--ghost jl-btn--block" href="<?php echo esc_url( $jl_links['demo'] ); ?>">Open the live demo</a>
			<span class="jl-price-card__foot">
				<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 5 6v6c0 4 3 6.5 7 8 4-1.5 7-4 7-8V6z"/></svg>
				Tested up to WordPress 7.1.1
			</span>
		</div>
	</div>
</section>

<!-- footer -->
<footer class="jl-footer">
	<div class="jl-container">
		<div class="jl-footer__cols">
			<div class="jl-footer__brand">
				<span class="jl-logo">STREAM<span>VID</span></span>
				<span>A WordPress streaming theme by JWSThemes.</span>
			</div>
			<div class="jl-footer__col">
				<span class="jl-footer__head">Product</span>
				<a href="#demos">Demos</a>
				<a href="#revenue">Monetization</a>
				<a href="#pricing">Pricing</a>
			</div>
			<div class="jl-footer__col">
				<span class="jl-footer__head">Apps</span>
				<a href="<?php echo esc_url( $jl_links['app_phone'] ); ?>">Mobile app</a>
				<a href="<?php echo esc_url( $jl_links['app_tv'] ); ?>">TV app</a>
			</div>
			<div class="jl-footer__col">
				<span class="jl-footer__head">Help</span>
				<a href="<?php echo esc_url( $jl_links['docs'] ); ?>">Documentation</a>
				<a href="<?php echo esc_url( $jl_links['changelog'] ); ?>">Changelog</a>
				<a href="<?php echo esc_url( $jl_links['support'] ); ?>">Support</a>
			</div>
			<form class="jl-subscribe" method="post" action="#">
				<label class="jl-footer__head" for="jl-notify">Release notes</label>
				<div class="jl-subscribe__row">
					<input id="jl-notify" type="email" name="email" placeholder="you@example.com" autocomplete="email">
					<button type="submit">Subscribe</button>
				</div>
			</form>
		</div>
		<div class="jl-footer__legal">
			<span>&copy; <?php echo esc_html( date_i18n( 'Y' ) ); ?> JWSThemes. All rights reserved.</span>
			<?php if ( $jl_version ) : ?>
				<span>Version <?php echo esc_html( $jl_version ); ?></span>
			<?php endif; ?>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
