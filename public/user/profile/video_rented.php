<?php
if( ! defined('ABSPATH' ) ){
    exit;
}

$current_user_id = absint(get_queried_object_id());

$videos_with_time = array();

if ( class_exists( 'Jws_PPV_Access' ) ) {

    // Already newest-first out of the table, so there is nothing left to sort.
    $rentals = Jws_PPV_Access::list_for_user( $current_user_id, Jws_PPV_Access::TYPE_RENT );

    // One query for every title on the page instead of one per row below.
    if ( $rentals ) {
        _prime_post_caches( wp_list_pluck( $rentals, 'post_id' ), false, true );
    }

    foreach ( $rentals as $rental ) {
        $videos_with_time[] = array(
            'video_id' => (int) $rental->post_id,
            'time'     => $rental->purchased_at,
            /* 'never' for a rental nobody has played yet — the column below
               still branches on that word to show the delay notice instead of
               a countdown to a date that does not exist yet. */
            'expire'   => null === $rental->starts_at ? 'never' : (string) $rental->expires_at,
            'order_id' => $rental->order_number,
            'price'    => $rental->price,
            'delay'    => (int) $rental->delay_days,
        );
    }
}

if (!empty($videos_with_time)) {
    
    echo '<div class="jws-purchased-videos-table jws-scrollbar"><table id="pmpro_invoices_table" class="pmpro_table" border="1" cellpadding="8" cellspacing="0">';
    echo '<thead>
            <tr>
                <th>'.esc_html__('Image', 'jws_streamvid').'</th>
                <th>'.esc_html__('Title', 'jws_streamvid').'</th>
                <th>'.esc_html__('Purchase Date', 'jws_streamvid').'</th>
                <th>'.esc_html__('Expiration Date', 'jws_streamvid').'</th>
                <th>'.esc_html__('Price', 'jws_streamvid').'</th>
                <th>'.esc_html__('Actions', 'jws_streamvid').'</th>
                <th>'.esc_html__('Invoice', 'jws_streamvid').'</th>
            </tr>
          </thead>';
    echo '<tbody>';
    foreach ($videos_with_time as $video_data) {
        
        $video_id = $video_data['video_id'];
        $purchase_time = $video_data['time'];
        $order_id = $video_data['order_id'];
        $price = $video_data['price'];
        $delay = $video_data['delay'];

        $video_post = get_post($video_id);
        if ($video_post) {
            $thumbnail = get_the_post_thumbnail($video_id, 'thumbnail');
            if (!$thumbnail) {
                $thumbnail = '<img src="' . esc_url( wc_placeholder_img_src() ) . '" alt="No image"> ';
            }
            echo '<tr>';
            echo '<td data-title="' . esc_html__('Image', 'jws_streamvid') . '">' . $thumbnail . '</td>';
            echo '<td data-title="' . esc_html__('Title', 'jws_streamvid') . '"><a class="link" href="' . esc_url(get_permalink($video_id)) . '" target="_blank">'. esc_html($video_post->post_title) . '</a></td>';
            echo '<td data-title="' . esc_html__('Purchase Date', 'jws_streamvid') . '">' . esc_html(date('d/m/Y H:i:s', strtotime($purchase_time))) . '</td>';

            if(!empty($video_data['expire']) && $video_data['expire'] == 'never' ) {

                ?>

                <td data-title="<?php echo esc_html__('Expiration Date', 'jws_streamvid'); ?>">
                        <?php 
                            echo '<p style="margin-bottom:0;">'.esc_html__('You have not watched the video yet', 'jws_streamvid').'</p>';
                            echo '<small>('.esc_html(sprintf(__('The expiration date will be automatically activated after %d days', 'jws_streamvid'), $delay)).')</small>';
                         ?>
                </td>

                <?php

            } else {

                ?>
                
                <td data-title="<?php echo esc_html__('Expiration Date', 'jws_streamvid'); ?>">
                <div>    
                <?php echo esc_html(date('d/m/Y H:i:s', strtotime($video_data['expire']))); ?>

                <?php

                 $timestamp = strtotime($video_data['expire']);
          
            ?>
            <div class="countdown-wrap">
                <div class="countdown-box"
                     data-date="<?php echo esc_attr($timestamp * 1000); ?>">
                     
                    <div class="countdown-item">
                        <div class="count-num days" id="days2">00</div>
                        <div class="count-label"><?php esc_html_e( 'Day', 'jws_streamvid' ); ?></div>
                    </div>
                    <span class="count-sep">:</span>
                    
                    <div class="countdown-item">
                        <div class="count-num hours" id="hours2">00</div>
                        <div class="count-label"><?php esc_html_e( 'Hrs', 'jws_streamvid' ); ?></div>
                    </div>
                    <span class="count-sep">:</span>
                    
                    <div class="countdown-item">
                        <div class="count-num minutes" id="minutes2">00</div>
                        <div class="count-label"><?php esc_html_e( 'Min', 'jws_streamvid' ); ?></div>
                    </div>
                    <span class="count-sep">:</span>
                    
                    <div class="countdown-item">
                        <div class="count-num seconds" id="seconds2">00</div>
                        <div class="count-label"><?php esc_html_e( 'Sec', 'jws_streamvid' ); ?></div>
                    </div>
                </div>
            </div>
            </div>
        </td>
            <?php

            }
            
            echo '<td data-title="' . esc_html__('Price', 'jws_streamvid') . '">' . (!empty($price) ? wc_price($price) : '-') . '</td>';
            echo '<td data-title="' . esc_html__('Watch Now', 'jws_streamvid') . '"><a class="button-view" href="' . esc_url(get_permalink($video_id)) . '" target="_blank">'.esc_html__('Watch Now', 'jws_streamvid').'</a></td>';
            echo '<td data-title="' . esc_attr__('Invoice', 'jws_streamvid') . '">';
                echo '<a class="button-default download-invoice-woo" data-invoice="' . esc_attr($order_id) . '" href="javascript:void(0)">'.esc_html__('Download', 'jws_streamvid').'</a>';
            echo '</td>';
            echo '</tr>';
        }
    }
    echo '</tbody>';
    echo '</table></div>';
} else {
    echo '<p>'.esc_html__('No videos purchased.', 'jws_streamvid').'</p>';
}
?>
<style>
 .countdown-box {
    display: flex;
    gap: 10px;
    background: var(--background-item);
    padding: 10px 20px;
    border-radius: 10px;
        margin: 0 auto;
    margin-top: 5px;
    width: fit-content;
    font-size: 14px;
 }
</style>
<script>
jQuery(function($) {
    function pad(n) {
        return String(n).padStart(2, '0');
    }

    $('.countdown-box').each(function () {
        const $box = $(this);
        const datetimeStr = $box.data('date');
        if (!datetimeStr) return;

        const targetDate = new Date(Number(datetimeStr));
        let timer = null;

        function updateCountdown() {
            const now = new Date();
            const diff = targetDate - now;

            if (diff <= 0) {
                $box.html('<span>' + (typeof jws_script !== 'undefined' && jws_script.expired_countdown ? jws_script.expired_countdown : 'Expired') + '</span>');
                if (timer) clearInterval(timer);
                return;
            }

            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
            const hours = Math.floor((diff / (1000 * 60 * 60)) % 24);
            const minutes = Math.floor((diff / (1000 * 60)) % 60);
            const seconds = Math.floor((diff / 1000) % 60);

            $box.find('.days').text(pad(days));
            $box.find('.hours').text(pad(hours));
            $box.find('.minutes').text(pad(minutes));
            $box.find('.seconds').text(pad(seconds));
        }

        updateCountdown();
        timer = setInterval(updateCountdown, 1000);
    });
});
</script>

<div id="hidden-invoice-wrapper" style="display:none;padding:20px;position:fixed; top:-100%;left:-100%;background:white;"></div>
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