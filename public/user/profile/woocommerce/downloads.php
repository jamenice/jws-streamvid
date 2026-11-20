<?php
/**
 * Dashboard > Shopping > Downloads (shop/downloads).
 *
 * WooCommerce's downloads table and hooks when there are files, the
 * profile's empty state when there are none.
 *
 * @package Jws_Streamvid
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$jws_dl_files = WC()->customer ? WC()->customer->get_downloadable_products() : array();
$jws_dl_has   = (bool) $jws_dl_files;

do_action( 'woocommerce_before_account_downloads', $jws_dl_has );

if ( $jws_dl_has ) :
	do_action( 'woocommerce_before_available_downloads' );
	do_action( 'woocommerce_available_downloads', $jws_dl_files );
	do_action( 'woocommerce_after_available_downloads' );
else :
	?>
	<div class="jws-lib__empty">
		<span class="jws-lib__empty-icon" aria-hidden="true"><i class="jws-icon-arrow-line-down"></i></span>
		<h5><?php esc_html_e( 'No downloads yet', 'jws_streamvid' ); ?></h5>
		<p><?php esc_html_e( 'Files from the downloadable products you buy are listed here.', 'jws_streamvid' ); ?></p>
		<a class="button-default" href="<?php echo esc_url( apply_filters( 'woocommerce_return_to_shop_redirect', wc_get_page_permalink( 'shop' ) ) ); ?>"><?php esc_html_e( 'Browse products', 'jws_streamvid' ); ?></a>
	</div>
	<?php
endif;

do_action( 'woocommerce_after_account_downloads', $jws_dl_has );
