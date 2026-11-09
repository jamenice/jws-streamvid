<?php
if( ! defined('ABSPATH' ) ){
    exit;
}


// Profile Version 3 lists purchases as cards (user/profile/v3/purchases.php).
if ( class_exists( 'Jws_Profile_Library' ) && Jws_Profile_Library::is_v3() ) {
    jws_streamvid_load_template( 'user/profile/v3/purchases.php', false, array( 'type' => 'buy' ) );
    return;
}
$current_user_id = absint(get_queried_object_id());

$videos_with_time = array();

if ( class_exists( 'Jws_PPV_Access' ) ) {

    // Already newest-first out of the table, so there is nothing left to sort.
    $purchases = Jws_PPV_Access::list_for_user( $current_user_id, Jws_PPV_Access::TYPE_BUY );

    // One query for every title on the page instead of one per row below.
    if ( $purchases ) {
        _prime_post_caches( wp_list_pluck( $purchases, 'post_id' ), false, true );
    }

    foreach ( $purchases as $purchase ) {

        if ( get_post_status( $purchase->post_id ) !== 'publish' ) {
            continue; // Skip if video is not published
        }

        $videos_with_time[] = array(
            'video_id' => (int) $purchase->post_id,
            'time'     => $purchase->purchased_at,
            'order_id' => $purchase->order_number,
            'price'    => $purchase->price,
        );
    }
}

if (!empty($videos_with_time)) {
    
    echo '<div class="jws-purchased-videos-table"><table id="pmpro_invoices_table" class="pmpro_table" border="1" cellpadding="8" cellspacing="0">';
    echo '<thead>
            <tr>
               
                <th>'.esc_html__('Image', 'jws_beyondtv').'</th>
                <th>'.esc_html__('Title', 'jws_beyondtv').'</th>
                <th>'.esc_html__('Purchase Date', 'jws_beyondtv').'</th>
                
                <th>'.esc_html__('Price', 'jws_beyondtv').'</th>
                <th>'.esc_html__('Actions', 'jws_beyondtv').'</th>
                 <th>'.esc_html__('Invoice', 'jws_beyondtv').'</th>
            </tr>
          </thead>';
    echo '<tbody>';
    foreach ($videos_with_time as $video_data) {
        $video_id = $video_data['video_id'];
        $purchase_time = $video_data['time'];
        $order_id = $video_data['order_id'];
        $price = $video_data['price'];

        $video_post = get_post($video_id);
        if ($video_post) {
            $thumbnail = get_the_post_thumbnail($video_id, 'thumbnail');
            if (!$thumbnail) {
                $thumbnail = '<img src="' . esc_url( wc_placeholder_img_src() ) . '" alt="No image"> ';
            }
            echo '<tr>';
            echo '<td data-title="'.esc_html__('Image', 'jws_beyondtv').'">' . $thumbnail . '</td>';
            echo '<td data-title="' . esc_attr__('Title', 'jws_beyondtv') . '"><a class="link" href="' . esc_url(get_permalink($video_id)) . '" target="_blank">' . esc_html($video_post->post_title) . '</a></td>';
            echo '<td data-title="' . esc_attr__('Purchase Date', 'jws_beyondtv') . '">' . esc_html(date('d/m/Y H:i:s', strtotime($purchase_time))) . '</td>';
            echo '<td data-title="' . esc_attr__('Price', 'jws_beyondtv') . '">' . (!empty($price) ? wc_price($price) : '-') . '</td>';
            echo '<td data-title="' . esc_attr__('Actions', 'jws_beyondtv') . '"><a class="button-view" href="' . esc_url(get_permalink($video_id)) . '" target="_blank">'.esc_html__('Watch Now', 'jws_beyondtv').'</a></td>';
            echo '<td data-title="' . esc_attr__('Invoice', 'jws_beyondtv') . '">';
                echo '<a class="button-default download-invoice-woo" data-invoice="' . esc_attr($order_id) . '" href="javascript:void(0)">'.esc_html__('Download', 'jws_beyondtv').'</a>';
            echo '</td>';
            echo '</tr>';
        }
    }
    echo '</tbody>';
    echo '</table></div>';
} else {
    echo '<p>'.esc_html__('No videos purchased.', 'jws_beyondtv').'</p>';
}
?>


<?php jws_streamvid_load_template( 'user/profile/parts/invoice-download.php', false ); ?>