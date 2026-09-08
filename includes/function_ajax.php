<?php 

/**
 * AJAX: Load episode player HTML for in-player episode switching
 */
if (!function_exists('jws_ajax_episode_player')) {
    function jws_ajax_episode_player() {
        $post_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$post_id || get_post_type($post_id) !== 'episodes') {
            wp_send_json_error(['message' => 'Invalid episode']);
        }

        // Switch global post context so player.php works correctly
        global $post;
        $post = get_post($post_id);
        setup_postdata($post);

        ob_start();
        do_action('streamvid/movies/player', ['id' => $post_id]);
        $player_html = ob_get_clean();

        // Also get sources HTML for source switcher
        ob_start();
        $sources = get_field('sources', $post_id);
        if (!empty($sources)) {
            echo '<div class="sources-videos" data-id="' . esc_attr($post_id) . '">';
            echo '<ul>';
            foreach ($sources as $index => $source) {
                $label = !empty($source['label']) ? $source['label'] : esc_html__('Server', 'jws_streamvid') . ' ' . ($index + 1);
                echo '<li class="' . ($index === 0 ? 'active' : '') . '">';
                echo '<button data-index="' . esc_attr($index) . '">' . esc_html($label) . '</button>';
                echo '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
        $sources_html = ob_get_clean();

        // Capture episodes section HTML based on layout version
        $episodes_section_html = '';
        $episodes_selector     = '';
        if (function_exists('jws_episodes_check_type') && function_exists('jws_episodes_check_season')) {
            $tv_shows_id = jws_episodes_check_type($post_id);
            $season_num  = jws_episodes_check_season(['id_tv' => $tv_shows_id]);
            $version     = isset($_POST['version']) ? sanitize_text_field($_POST['version']) : 'v1';

            if ($tv_shows_id && $season_num) {
                $orig_post = $post;
                $post = get_post($tv_shows_id);
                setup_postdata($post);

                $args = [
                    'tv_shows' => $tv_shows_id,
                    'season'   => $season_num,
                ];

                ob_start();
                if ($version === 'v3') {
                    // Layout v3 (movies_v2 style) — wrapper is <div class="episodes-list">
                    // which contains post-seasons.php + post-episodes.php
                    global $global_id;
                    $global_id = $tv_shows_id;
                    // Pass seasion so post-seasons.php highlights the correct season
                    $args['seasion']    = $season_num;
                    // Pass current episode ID so post-episodes.php can add active class correctly
                    $args['current_ep'] = $post_id;
                    echo '<div class="episodes-list">';
                    include jws_override_template('/template-parts/content/movies_v2/post-seasons.php');
                    include jws_override_template('/template-parts/content/movies_v2/post-episodes.php');
                    echo '</div>';
                    $episodes_selector = '.episodes-list';
                } elseif ($version === 'v2') {
                    // Layout v2 — sidebar list (post-episodes-list.php or post-season-list.php)
                    // Pass episodes_view from JS so sidebar-list keeps its list/grid state
                    if (!empty($_POST['episodes_view'])) {
                        $args['episodes_view'] = sanitize_text_field($_POST['episodes_view']);
                    }
                    $list_right = function_exists('jws_theme_get_option') ? jws_theme_get_option('select-episodes-single-list') : '';
                    if ($list_right === 'season') {
                        get_template_part('template-parts/content/episodes/post', 'season-list', $args);
                        $episodes_selector = '.jws-episodes_advanced-element';
                    } else {
                        get_template_part('template-parts/content/episodes/post', 'episodes-list', $args);
                        $episodes_selector = '.sidebar-list';
                    }
                } else {
                    // Layout v1 — default slider (post-episodes.php)
                    // Pass seasion so post-seasion.php highlights the correct season
                    $args['seasion'] = $season_num;
                    get_template_part('template-parts/content/episodes/post', 'episodes', $args);
                    $episodes_selector = '.global-episodes';
                }
                $episodes_section_html = ob_get_clean();

                $post = $orig_post;
                setup_postdata($post);
            }
        }

        wp_reset_postdata();

        wp_send_json_success([
            'player'           => $player_html,
            'sources'          => $sources_html,
            'episodes_section' => $episodes_section_html,
            'episodes_selector' => $episodes_selector,
            'title'            => get_the_title($post_id),
            'link'             => get_the_permalink($post_id),
        ]);
    }
    add_action('wp_ajax_jws_ajax_episode_player', 'jws_ajax_episode_player');
    add_action('wp_ajax_nopriv_jws_ajax_episode_player', 'jws_ajax_episode_player');
}

add_action('wp_ajax_jws_load_history', 'jws_load_history_callback');
add_action('wp_ajax_nopriv_jws_load_history', 'jws_load_history_callback');
function jws_load_history_callback() {
    $user_id = get_current_user_id();
    $video_progress_data = Jws_History::get_all($user_id);
    $current_filter = isset($_POST['history_filter']) ? sanitize_text_field($_POST['history_filter']) : 'movies';
    include plugin_dir_path( dirname( __FILE__ ) ) . 'public/user/profile/history-list.php';
    wp_die();
}

add_action('wp_ajax_jws_load_watchlist', 'jws_load_watchlist_callback');
add_action('wp_ajax_nopriv_jws_load_watchlist', 'jws_load_watchlist_callback');
function jws_load_watchlist_callback() {
    $user_id = get_current_user_id();
    include plugin_dir_path( dirname( __FILE__ ) ) . 'public/user/profile/watchlist-list.php';
    wp_die();
}

add_action('wp_ajax_get_invoice_html_pmpro', function() {
    if ( ! current_user_can('read') || empty($_POST['invoice']) ) {
        wp_send_json_error('Permission denied or missing invoice code'); 
    }

    $invoice_code = sanitize_text_field($_POST['invoice']);
    
    // Get order by code
    $order = new MemberOrder($invoice_code);
    
    if ( ! $order || ! $order->id ) {
        wp_send_json_error('Order not found');
    }

    // Get the level
    $order->getMembershipLevel();
    
    // Get shop info
    $shop_name = get_bloginfo('name');
    $business_address = get_option('pmpro_business_address');
    
    // Get billing info from PMPro order
    $user_id = $order->user_id;
    $billing_name = $order->user->display_name;
    $billing_phone = !empty($order->billing->phone) ? $order->billing->phone : '';
    $billing_email = $order->user->user_email;

    $order_date = date_i18n('Y-m-d H:i', $order->getTimestamp());
    $order_number = $order->code;
    $payment_method = $order->payment_type ?: $order->gateway;
    
    // Determine status display
    if ( in_array($order->status, array('', 'success', 'cancelled')) ) {
        $order_status = __('Paid', 'paid-memberships-pro');
    } elseif ( $order->status == 'pending' ) {
        $order_status = __('Pending', 'paid-memberships-pro');
    } elseif ( $order->status == 'refunded' ) {
        $order_status = __('Refunded', 'paid-memberships-pro');
    }

    $shop_logo = get_theme_mod('custom_logo');
    $logo_url = $shop_logo ? wp_get_attachment_image_url($shop_logo, 'full') : '';

    ob_start();
    ?>
    <style>
        .invoice-pdf-container {
            font-family: Arial, sans-serif;
            max-width: 700px;
            margin: 0 auto;
            background: #fff;
            color: #222;
            border: 1px solid #e5e5e5;
            padding: 32px 40px 40px 40px;
            border-radius: 12px;
        }
        .invoice-header {
            display: flex;
            align-items: center;
            margin-bottom: 32px;
        }
        .invoice-logo {
            margin-right: 32px;
        }
        .invoice-shop-info {
            font-size: 15px;
            line-height: 1.6;
        }
        .invoice-title {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 8px;
            color: #2d3e50;
        }
        .invoice-meta {
            margin-bottom: 24px;
            font-size: 15px;
        }
        .invoice-meta span {
            display: inline-block;
            min-width: 120px;
            font-weight: 500;
        }
        .invoice-section-title {
            font-size: 18px;
            font-weight: 600;
            margin: 24px 0 8px 0;
            color: #2d3e50;
        }
        .invoice-address {
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 8px;
        }
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin: 24px 0;
        }
        .invoice-table th, .invoice-table td {
            border: 1px solid #e5e5e5;
            padding: 10px 12px;
            text-align: left;
        }
        .invoice-table th {
            background: #f7f7f7;
            font-weight: 600;
        }
        .invoice-table tfoot td {
            font-weight: bold;
            background: #fafafa;
        }
        .invoice-footer {
            margin-top: 40px;
            font-size: 13px;
            color: #888;
            text-align: center;
        }
    </style>
    <div class="invoice-pdf-container">
        <div class="invoice-header">
            <?php if ($logo_url): ?>
                <img src="<?php echo esc_url($logo_url); ?>" class="invoice-logo" alt="Logo" />
            <?php endif; ?>
            <div class="invoice-shop-info">
                <div class="invoice-title"><?php esc_html_e('INVOICE', 'paid-memberships-pro'); ?></div>
            </div>
        </div>
        <div class="invoice-meta">
            <div><span><?php esc_html_e('Invoice #', 'paid-memberships-pro'); ?></span> <?php echo esc_html($order_number); ?></div>
            <div><span><?php esc_html_e('Date:', 'paid-memberships-pro'); ?></span> <?php echo esc_html($order_date); ?></div>
            <div><span><?php esc_html_e('Status:', 'paid-memberships-pro'); ?></span> <?php echo esc_html($order_status); ?></div>
            <div><span><?php esc_html_e('Payment:', 'paid-memberships-pro'); ?></span> <?php echo esc_html($payment_method); ?></div>
        </div>

        <div class="invoice-section-title"><?php esc_html_e('Pay to', 'paid-memberships-pro'); ?></div>
        
         <?php 
        if (!empty($business_address)) {
            echo '<div>' . esc_html($business_address['name']) . '</div>';
            echo '<div>' . esc_html($business_address['street']) . '</div>';
            echo '<div>' . esc_html($business_address['city']) . ', ' . esc_html($business_address['state']) . ' ' . esc_html($business_address['zip']) . '</div>';
            if ($business_address['phone']) {
                echo '<div>' . esc_html__('Phone:', 'paid-memberships-pro') . ' ' . esc_html($business_address['phone']) . '</div>';
            }
        }
        ?>
        <div class="invoice-section-title"><?php esc_html_e('Billed To', 'paid-memberships-pro'); ?></div>
        <div class="invoice-address">
            <?php if (!empty($order->billing->name)) : ?><?php echo esc_html($order->billing->name); ?><br><?php endif; ?>
            <?php if (!empty($order->billing->street)) : ?><?php echo esc_html($order->billing->street); ?><br><?php endif; ?>
            <?php if (!empty($order->billing->street2)) : ?><?php echo esc_html($order->billing->street2); ?><br><?php endif; ?>
            <?php if (!empty($order->billing->city) || !empty($order->billing->state) || !empty($order->billing->zip)) : ?>
                <?php echo esc_html(trim($order->billing->city . ', ' . $order->billing->state . ' ' . $order->billing->zip)); ?><br>
            <?php endif; ?>
            <?php if (!empty($order->billing->country)) : ?><?php echo esc_html($order->billing->country); ?><br><?php endif; ?>
            <?php if (!empty($order->billing->phone)) : ?><?php echo esc_html__('Phone:', 'paid-memberships-pro') . ' ' . esc_html($order->billing->phone); ?><br><?php endif; ?>
            <?php if (!empty($billing_email)) : ?><?php echo esc_html__('Email:', 'paid-memberships-pro') . ' ' . esc_html($billing_email); ?><br><?php endif; ?>
        </div>
        <div class="invoice-section-title"><?php esc_html_e('Membership Details', 'paid-memberships-pro'); ?></div>
        <table class="invoice-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('ID', 'paid-memberships-pro'); ?></th>
                    <th><?php esc_html_e('Description', 'paid-memberships-pro'); ?></th>
                    <th><?php esc_html_e('Amount', 'paid-memberships-pro'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php echo esc_html($order->membership_level->id); ?></td>
                    <td>
                        <?php 
                        echo esc_html(
                            sprintf(
                                __('%1$s for order #%2$s', 'paid-memberships-pro'),
                                $order->membership_level->name,
                                $order->code
                            )
                        );
                        ?>
                    </td>
                    <td><?php echo pmpro_escape_price($order->get_formatted_subtotal()); ?></td>
                </tr>
            </tbody>
            <?php if ((float)$order->total > 0): ?>
            <tfoot>
                <?php
                $pmpro_price_parts = pmpro_get_price_parts($order, 'array');
                if ($order->status == 'refunded') {
                    $pmpro_price_parts['refunded']['label'] = __('Refunded', 'paid-memberships-pro');
                    $pmpro_price_parts['refunded']['value'] = $pmpro_price_parts['total']['value'];
                }
                foreach ($pmpro_price_parts as $pmpro_price_part): ?>
                    <tr>
                        <th colspan="2"><?php echo esc_html($pmpro_price_part['label']); ?></th>
                        <td><?php echo esc_html($pmpro_price_part['value']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tfoot>
            <?php endif; ?>
        </table>
        <?php if ($order->getDiscountCode()): ?>
            <p><?php echo esc_html(sprintf(__('Discount Code: %s', 'paid-memberships-pro'), $order->discount_code->code)); ?></p>
        <?php endif; ?>
        <div class="invoice-footer">
            <?php esc_html_e('This invoice was generated automatically. If you have any questions, please contact our support.', 'jws_lovedate_custom'); ?>
        </div>
    </div>
    <?php
    $html = ob_get_clean();

    wp_send_json_success(['html' => $html]);
});


add_action('wp_ajax_get_invoice_html_woo', function() {
    if ( ! current_user_can('read') || empty($_POST['order_id']) ) {
        wp_send_json_error('Permission denied or missing order_id');
    }

    $order_id = intval($_POST['order_id']);
    $order = wc_get_order($order_id);

    if ( ! $order ) {
        wp_send_json_error('Order not found');
    }

    /*
     * An invoice carries the buyer's name, address, phone and email. Without
     * this check any logged-in visitor could walk the order ids and read every
     * customer's details, because current_user_can('read') is true for every
     * subscriber on the site.
     */
    if ( (int) $order->get_user_id() !== get_current_user_id() && ! current_user_can('manage_woocommerce') ) {
        wp_send_json_error('Order not found');
    }


    $shop_name = get_bloginfo('name');
    $shop_address = get_option('woocommerce_store_address') . ', ' . get_option('woocommerce_store_city') . ', ' . get_option('woocommerce_store_postcode');
    $shop_phone = get_option('woocommerce_store_phone');
    $shop_email = get_option('woocommerce_store_email');
    $shop_logo = get_theme_mod('custom_logo');
    $logo_url = $shop_logo ? wp_get_attachment_image_url($shop_logo, 'full') : '';

    
    // Get billing info from user ID instead of order
    $user_id = $order->get_user_id();
    $billing_first_name = get_user_meta($user_id, 'billing_first_name', true);
    $billing_last_name = get_user_meta($user_id, 'billing_last_name', true);
    $billing_name = trim($billing_first_name . ' ' . $billing_last_name);
    $billing_address = get_user_meta($user_id, 'billing_address_1', true);
    $billing_phone = get_user_meta($user_id, 'billing_phone', true);
    $billing_email = get_user_meta($user_id, 'billing_email', true);
    $billing_company = get_user_meta($user_id, 'billing_company', true);

    $order_date = $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i') : '';
    $order_number = $order->get_order_number();
    $payment_method = $order->get_payment_method_title();
    $order_status = wc_get_order_status_name($order->get_status());

    ob_start();
    ?>
    <style>
        .invoice-pdf-container {
            font-family: Arial, sans-serif;
            max-width: 700px;
            margin: 0 auto;
            background: #fff;
            color: #222;
            border: 1px solid #e5e5e5;
            padding: 32px 40px 40px 40px;
            border-radius: 12px;
        }
        .invoice-header {
            display: flex;
            align-items: center;
            margin-bottom: 32px;
        }
        .invoice-logo {
            margin-right: 32px;
        }
        .invoice-shop-info {
            font-size: 15px;
            line-height: 1.6;
        }
        .invoice-title {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 8px;
            color: #2d3e50;
        }
        .invoice-meta {
            margin-bottom: 24px;
            font-size: 15px;
        }
        .invoice-meta span {
            display: inline-block;
            min-width: 120px;
            font-weight: 500;
        }
        .invoice-section-title {
            font-size: 18px;
            font-weight: 600;
            margin: 24px 0 8px 0;
            color: #2d3e50;
        }
        .invoice-address, .invoice-customer {
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 8px;
        }
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin: 24px 0;
        }
        .invoice-table th, .invoice-table td {
            border: 1px solid #e5e5e5;
            padding: 10px 12px;
            text-align: left;
        }
        .invoice-table th {
            background: #f7f7f7;
            font-weight: 600;
        }
        .invoice-table tfoot td {
            font-weight: bold;
            background: #fafafa;
        }
        .invoice-summary {
            margin-top: 24px;
            font-size: 16px;
        }
        .invoice-footer {
            margin-top: 40px;
            font-size: 13px;
            color: #888;
            text-align: center;
        }
    </style>
    <div class="invoice-pdf-container">
        <div class="invoice-header">
            <?php if ($logo_url): ?>
                <img src="<?php echo esc_url($logo_url); ?>" class="invoice-logo" alt="Logo" />
            <?php endif; ?>
            <div class="invoice-shop-info">
                <div class="invoice-title"><?php echo esc_html($shop_name); ?></div>
                <?php if ($shop_phone): ?><div><?php echo esc_html__('Phone:', 'woocommerce') . ' ' . esc_html($shop_phone); ?></div><?php endif; ?>
                <?php if ($shop_email): ?><div><?php echo esc_html__('Email:', 'woocommerce') . ' ' . esc_html($shop_email); ?></div><?php endif; ?>
            </div>
        </div>
        <div class="invoice-meta">
            <div><span><?php esc_html_e('Order #', 'woocommerce'); ?></span> <?php echo esc_html($order_number); ?></div>
            <div><span><?php esc_html_e('Date:', 'woocommerce'); ?></span> <?php echo esc_html($order_date); ?></div>
            <div><span><?php esc_html_e('Status:', 'woocommerce'); ?></span> <?php echo esc_html($order_status); ?></div>
            <div><span><?php esc_html_e('Payment:', 'woocommerce'); ?></span> <?php echo esc_html($payment_method); ?></div>
        </div>
        <div class="invoice-section-title"><?php esc_html_e('Pay to', 'woocommerce'); ?></div>
        <div class="invoice-address">
            <?php 
            // Get billing info from user ID = 1 (admin/company info)
            $admin_billing_company = get_user_meta(1, 'billing_company', true);
            $admin_billing_address_1 = get_user_meta(1, 'billing_address_1', true);
            $admin_billing_address_2 = get_user_meta(1, 'billing_address_2', true);
            $admin_billing_city = get_user_meta(1, 'billing_city', true);
            $admin_billing_state = get_user_meta(1, 'billing_state', true);
            $admin_billing_postcode = get_user_meta(1, 'billing_postcode', true);
            $admin_billing_country = get_user_meta(1, 'billing_country', true);
            $admin_billing_phone = get_user_meta(1, 'billing_phone', true);
            $admin_billing_email = get_user_meta(1, 'billing_email', true);
            
            // Build the pay_to string following the same order as orders-print.php
            echo $admin_billing_company ? esc_html($admin_billing_company) . '<br />' : '';
            echo $admin_billing_address_1 ? esc_html($admin_billing_address_1) . '<br />' : '';
            if ($admin_billing_address_2) {
                // Uncomment if needed: echo esc_html($admin_billing_address_2) . '<br />';
            }
            echo $admin_billing_city ? esc_html($admin_billing_city) . '<br />' : '';
            // Uncomment if needed: echo $admin_billing_state ? esc_html($admin_billing_state) . '<br />' : '';
            echo $admin_billing_postcode ? esc_html($admin_billing_postcode) . '<br />' : '';
            echo $admin_billing_country ? esc_html($admin_billing_country) . '<br />' : '';
            echo $admin_billing_phone ? esc_html($admin_billing_phone) . '<br />' : '';
            echo $admin_billing_email ? esc_html($admin_billing_email) . '<br />' : '';
            ?>
        </div>
        <div class="invoice-section-title"><?php esc_html_e('Billed To', 'woocommerce'); ?></div>
        <div class="invoice-address">
            <?php echo wp_kses_post($billing_name); ?><br>
    
            <?php if ($billing_address): ?><?php echo esc_html__('Address:', 'woocommerce') . ' ' . esc_html($billing_address); ?><br><?php endif; ?>

            <?php if ($billing_company): ?><?php echo esc_html__('Company:', 'woocommerce') . ' ' . esc_html($billing_company); ?><br><?php endif; ?>
            <?php if ($billing_phone): ?><?php echo esc_html__('Phone:', 'woocommerce') . ' ' . esc_html($billing_phone); ?><br><?php endif; ?>
            <?php if ($billing_email): ?><?php echo esc_html__('Email:', 'woocommerce') . ' ' . esc_html($billing_email); ?><br><?php endif; ?>
        </div>
        <div class="invoice-section-title"><?php esc_html_e('Order Items', 'woocommerce'); ?></div>
        <table class="invoice-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Product', 'woocommerce'); ?></th>
                    <th><?php esc_html_e('Quantity', 'woocommerce'); ?></th>
                    <th><?php esc_html_e('Price', 'woocommerce'); ?></th>
                    <th><?php esc_html_e('Total', 'woocommerce'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $order->get_items() as $item ) : ?>
                    <tr>
                        <td><?php echo esc_html($item->get_name()); ?></td>
                        <td><?php echo esc_html($item->get_quantity()); ?></td>
                        <td><?php echo wc_price($item->get_subtotal() / max(1, $item->get_quantity())); ?></td>
                        <td><?php echo wc_price($item->get_total()); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" style="text-align:right;"><?php esc_html_e('Subtotal', 'woocommerce'); ?></td>
                    <td><?php echo wc_price($order->get_subtotal()); ?></td>
                </tr>
                <?php if ( $order->get_total_discount() > 0 ) : ?>
                <tr>
                    <td colspan="3" style="text-align:right;"><?php esc_html_e('Discount', 'woocommerce'); ?></td>
                    <td>-<?php echo wc_price($order->get_total_discount()); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ( $order->get_total_tax() > 0 ) : ?>
                <tr>
                    <td colspan="3" style="text-align:right;"><?php esc_html_e('Tax', 'woocommerce'); ?></td>
                    <td><?php echo wc_price($order->get_total_tax()); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ( $order->get_shipping_total() > 0 ) : ?>
                <tr>
                    <td colspan="3" style="text-align:right;"><?php esc_html_e('Shipping', 'woocommerce'); ?></td>
                    <td><?php echo wc_price($order->get_shipping_total()); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td colspan="3" style="text-align:right;font-size:18px;"><?php esc_html_e('Total', 'woocommerce'); ?></td>
                    <td style="font-size:18px;"><?php echo wc_price($order->get_total()); ?></td>
                </tr>
            </tfoot>
        </table>
        <div class="invoice-summary">
            <?php esc_html_e('Thank you for your purchase!', 'jws_lovedate_custom'); ?>
        </div>
        <div class="invoice-footer">
            <?php esc_html_e('This invoice was generated automatically. If you have any questions, please contact our support.', 'jws_lovedate_custom'); ?>
        </div>
    </div>
    <?php
    $html = ob_get_clean();

    wp_send_json_success(['html' => $html]);
});