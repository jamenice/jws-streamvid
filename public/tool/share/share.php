<?php
/**
 * The share popup's shell.
 *
 * Only the empty box goes out with the page; share-content.php is fetched into
 * .share-body over AJAX the first time someone opens the popup. The contents are
 * per-post — permalink, embed code, poster, excerpt — so on an archive there is
 * no one post to print, and on a single page it is exactly the sort of markup a
 * full-page cache serves to the wrong visitor. Nothing is requested until a
 * share button is actually clicked.
 *
 * Which post it fills with comes from the data-share-id on the button, so one
 * shell serves every card on the page.
 *
 * @package Jws_Streamvid
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}
?>
<?php /* "loading" is the theme's own state class: it is what turns the spinner on. */ ?>
<div id="share-videos" class="mfp-hide loading">
	<div class="loader">
		<svg class="circular" viewBox="25 25 50 50">
			<circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/>
		</svg>
	</div>
	<div class="share-body"></div>
</div>
