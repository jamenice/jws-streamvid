<?php
/**
 * Dashboard > Shopping > Addresses (shop/edit-address) and the address
 * form (shop/edit-address/{billing|shipping}).
 *
 * The list follows WooCommerce's my-address.php (same filters and hooks)
 * as cards; the form is WooCommerce's own, under a back link and title.
 * Saving goes through WooCommerce; Jws_Streamvid_Profile::shop_address_type()
 * tells it which address the form is for.
 *
 * @package Jws_Streamvid
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$jws_addr_profile = jws_streamvid()->get()->profile;
$jws_addr_user    = get_current_user_id();
$jws_addr_request = (string) get_query_var( 'dashboard' );
$jws_addr_base    = $jws_addr_profile->get_endpoint( $jws_addr_user, 'dashboard', 'shop/edit-address' );

$jws_addr_types = ( ! wc_ship_to_billing_address_only() && wc_shipping_enabled() )
	? array(
		'billing'  => __( 'Billing address', 'jws_streamvid' ),
		'shipping' => __( 'Shipping address', 'jws_streamvid' ),
	)
	: array(
		'billing' => __( 'Billing address', 'jws_streamvid' ),
	);
$jws_addr_types = apply_filters( 'woocommerce_my_account_get_addresses', $jws_addr_types, $jws_addr_user );

/* ------------------------------------------------------------------ *
 * The form for one address
 * ------------------------------------------------------------------ */

if ( preg_match( '#^shop/edit-address/([a-z0-9_-]+)#', $jws_addr_request, $jws_addr_match ) && isset( $jws_addr_types[ $jws_addr_match[1] ] ) ) :

	$jws_addr_type = $jws_addr_match[1];
	set_query_var( 'edit-address', $jws_addr_type );
	?>
	<a class="jws-shop__back fs-small" href="<?php echo esc_url( $jws_addr_base ); ?>">
		<i class="jws-icon-caret-left" aria-hidden="true"></i>
		<?php esc_html_e( 'Addresses', 'jws_streamvid' ); ?>
	</a>
	<h5 class="jws-shop__heading"><?php echo esc_html( $jws_addr_types[ $jws_addr_type ] ); ?></h5>
	<?php
	// The title is above; WooCommerce's own <h2> stays empty and hidden.
	add_filter( 'woocommerce_my_account_edit_address_title', '__return_empty_string' );
	ob_start();
	WC_Shortcode_My_Account::edit_address( $jws_addr_type );
	remove_filter( 'woocommerce_my_account_edit_address_title', '__return_empty_string' );

	/*
	 * data-jws-reload: Profile V3 sends forms in place and keeps the scroll,
	 * which left the "Address changed" notice out of sight above. A full
	 * load lands at the top, on the notice.
	 */
	echo preg_replace( '/<form\b/', '<form data-jws-reload', ob_get_clean(), 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce's escaped form, one attribute added.

	return;
endif;

/* ------------------------------------------------------------------ *
 * Saved addresses
 * ------------------------------------------------------------------ */
?>
<p class="jws-shop__muted jws-shop__intro">
	<?php echo wp_kses_post( apply_filters( 'woocommerce_my_account_my_address_description', esc_html__( 'The following addresses will be used on the checkout page by default.', 'jws_streamvid' ) ) ); ?>
</p>

<ul class="jws-shop__addresses">
	<?php foreach ( $jws_addr_types as $jws_addr_name => $jws_addr_title ) : ?>
		<?php $jws_addr_text = wc_get_account_formatted_address( $jws_addr_name ); ?>
		<li class="jws-shop__card jws-shop__address">
			<div class="jws-shop__card-head">
				<h6 class="jws-shop__card-title"><?php echo esc_html( $jws_addr_title ); ?></h6>
				<?php // A full load, so WooCommerce's country and state scripts come with the form (Profile V3 loads tabs in place). ?>
				<a class="button-custom jws-btn-sm" href="<?php echo esc_url( trailingslashit( $jws_addr_base ) . $jws_addr_name ); ?>" data-jws-reload aria-label="<?php echo esc_attr( sprintf( /* translators: %s: address name */ $jws_addr_text ? __( 'Edit %s', 'jws_streamvid' ) : __( 'Add %s', 'jws_streamvid' ), $jws_addr_title ) ); ?>">
					<i class="<?php echo $jws_addr_text ? 'jws-icon-pencil-line' : 'jws-icon-plus'; ?>" aria-hidden="true"></i>
					<?php echo $jws_addr_text ? esc_html__( 'Edit', 'jws_streamvid' ) : esc_html__( 'Add', 'jws_streamvid' ); ?>
				</a>
			</div>
			<?php if ( $jws_addr_text ) : ?>
				<address class="jws-shop__address-text"><?php echo wp_kses_post( $jws_addr_text ); ?></address>
			<?php else : ?>
				<p class="jws-shop__muted"><?php esc_html_e( 'You have not set up this type of address yet.', 'jws_streamvid' ); ?></p>
			<?php endif; ?>
			<?php do_action( 'woocommerce_my_account_after_my_address', $jws_addr_name ); ?>
		</li>
	<?php endforeach; ?>
</ul>
