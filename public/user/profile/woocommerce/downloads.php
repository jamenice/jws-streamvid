<?php

wc_get_template( 'myaccount/downloads.php', array(
	'current_user'  => get_user_by( 'id', get_current_user_id() ),
	'order_count'   => get_option( 'posts_per_page' )
) );