<div class="recent-orders">
<?php

$request = trailingslashit( get_query_var( 'dashboard' ) );

preg_match("/([0-9]+)/", trailingslashit( get_query_var( 'dashboard' ) ), $matches );

if( strpos( $request, 'view-order' ) && $matches ){

	$order = wc_get_order($matches[0]);

	if( $order && $order->get_customer_id() == get_current_user_id() ){
		wc_get_template( 'myaccount/view-order.php', array(
			'order'			=>	$order ,
			'order_id'		=>	$matches[0]
		) );
	}else{
		?>
		<div class="woocommerce-error">
			<?php esc_html_e( 'Sorry, you can not view this order.', 'streamtube-core' );?>
		</div>
		<?php
	}
}
else{
	$current_page = $matches ? $matches[0] : 1;

	$customer_orders = wc_get_orders(
		apply_filters(
			'woocommerce_my_account_my_orders_query',
			array(
				'customer'	=>	get_current_user_id(),
				'page'		=>	$current_page,
				'paginate'	=>	true
			)
		)
	);
	wc_get_template( 'myaccount/orders.php', array(
		'has_orders'		=>	0 < $customer_orders->total,
		'customer_orders'	=>	$customer_orders,
		'current_page'		=>	absint( $current_page )
	) );
}

?>
</div>
