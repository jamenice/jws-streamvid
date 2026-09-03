<?php
if( ! defined('ABSPATH' ) ){
    exit;
}

$current_user_id = absint(get_queried_object_id());
$user_videos = get_user_meta( $current_user_id, 'jws_purchased_videos', true );

$videos_with_time = array();

if (!empty($user_videos) && is_array($user_videos)) {
    foreach ($user_videos as $video_id => $purchase) {
       $video_status = get_post_status($video_id);
       if ($video_status !== 'publish') {
            continue; // Skip if video is not published
       } 
       if (isset($purchase['time'])) {
                    $videos_with_time[] = array(
                        'video_id' => $video_id,
                        'time' => $purchase['time'],
                        'order_id' => isset($purchase['order_id']) ? $purchase['order_id'] : '',
                        'price' => isset($purchase['price']) ? $purchase['price'] : ''
                    );
                }
    } 
}

usort($videos_with_time, function($a, $b) {
    return strtotime($b['time']) - strtotime($a['time']);
});

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


<div id="hidden-invoice-wrapper" style="display:none;padding:20px;position:fixed;top:-100%;left:-100%;background:white;"></div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://unpkg.com/pdf-lib/dist/pdf-lib.min.js"></script>
<script>
jQuery(document).ready(function($) {
 $(".download-invoice-woo").on("click", function (e) {
            e.preventDefault();

            var postId = $(this).data("invoice");

            $.ajax({
                url: jws_script.ajax_url,
                type: "POST",
                data: {
                    action: "get_invoice_html_woo",
                    order_id: postId
                },
                success: function (response) {

                    if (response.success) {

                        $("#hidden-invoice-wrapper").html(response.data.html).show();


                        html2canvas(document.querySelector("#hidden-invoice-wrapper"), { scale: 2 }).then(async function (canvas) {
                            const imgData = canvas.toDataURL("image/png", 1.0);

                            const { PDFDocument } = PDFLib;
                            const pdfDoc = await PDFDocument.create();


                            const pageWidth = 595.28;
                            const pageHeight = 841.89;

                            const page = pdfDoc.addPage([pageWidth, pageHeight]);
                            const pngImage = await pdfDoc.embedPng(imgData);


                            const imgWidth = pageWidth;
                            const imgHeight = (canvas.height * pageWidth) / canvas.width;

                            page.drawImage(pngImage, {
                                x: 0,
                                y: pageHeight - imgHeight,
                                width: imgWidth,
                                height: imgHeight
                            });

                            const pdfBytes = await pdfDoc.save();
                            const blob = new Blob([pdfBytes], { type: "application/pdf" });
                            const link = document.createElement("a");
                            link.href = URL.createObjectURL(blob);
                            link.download = "invoice-" + postId + ".pdf";
                            link.click();

                            $("#hidden-invoice-wrapper").hide();
                        });
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function () {
                    alert("Error loading invoice");
                }
            });
        });
});
</script>