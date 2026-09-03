<?php

$request = get_query_var( 'dashboard' );

$address = new WC_Shortcode_My_Account();

woocommerce_output_all_notices();

switch ( $request ) {
	case 'shop/edit-address/billing':
		set_query_var( 'edit-address', 'billing' );
		$address->edit_address( 'billing' );
	break;

	case 'shop/edit-address/shipping':
		
		$address->edit_address( 'shipping' );
	break;	
	
	default:
		wc_get_template( 'myaccount/my-address.php', array(
			'current_user'  => get_current_user_id(),
		) );
	break;
}