<?php
/**
 * Profile page: Dashboard > Shopping. The member's WooCommerce account
 * pages inside the profile, at /author/{user}/dashboard/shop/{page}/.
 *
 * A tab bar (the profile's own .jws-pv3__tabs) switches between Orders,
 * Downloads and Addresses; one order (shop/view-order/{id}) belongs to
 * Orders and an address form (shop/edit-address/{type}) to Addresses.
 *
 * @package Jws_Streamvid
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'WC' ) ) {
	return;
}

$jws_shop_profile = jws_streamvid()->get()->profile;
$jws_shop_user    = get_current_user_id();
$jws_shop_request = explode( '/', (string) get_query_var( 'dashboard' ) );
$jws_shop_menu    = $jws_shop_profile->get_menu_dashboard_items();
$jws_shop_menu    = isset( $jws_shop_menu['dashboard']['submenu']['shop']['submenu'] ) ? $jws_shop_menu['dashboard']['submenu']['shop']['submenu'] : array();

// dashboard=shop/{page}/...: an order (view-order) or anything unknown opens Orders.
$jws_shop_current = isset( $jws_shop_request[1] ) ? sanitize_key( $jws_shop_request[1] ) : 'orders';
if ( ! isset( $jws_shop_menu[ $jws_shop_current ] ) ) {
	$jws_shop_current = 'orders';
}
?>
<div class="jws-shop woocommerce">

	<nav class="jws-pv3__tabs jws-shop__tabs" aria-label="<?php esc_attr_e( 'Shopping', 'jws_streamvid' ); ?>">
		<?php foreach ( $jws_shop_menu as $jws_shop_key => $jws_shop_item ) : ?>
			<?php $jws_shop_on = $jws_shop_current === $jws_shop_key; ?>
			<a class="jws-pv3__tab<?php echo $jws_shop_on ? ' is-active' : ''; ?>" href="<?php echo esc_url( $jws_shop_profile->get_endpoint( $jws_shop_user, 'dashboard', 'shop/' . $jws_shop_key ) ); ?>"<?php echo $jws_shop_on ? ' aria-current="page"' : ''; ?>>
				<?php echo esc_html( $jws_shop_item['title'] ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<?php // woocommerce-MyAccount-content keeps the theme's WooCommerce form styles (labels, rows, Save button). ?>
	<div class="jws-shop__content woocommerce-MyAccount-content">
		<?php woocommerce_output_all_notices(); ?>
		<?php jws_streamvid_load_template( 'user/profile/woocommerce/' . $jws_shop_current . '.php' ); ?>
	</div>

</div>
