<?php

if( ! defined('ABSPATH' ) ){
    exit;
}

/**
*
* load the public template file
* 
* @param  string $file
* @return string file path
*
* @since  1.0.0
* 
*/




function jws_streamvid_check_owner() {
    
   $author_id = absint(get_queried_object_id());
   $current_user_id = get_current_user_id();   
   
   if($author_id != $current_user_id) {
      return false;
   }else {
      return true;
   }

}


function jws_streamvid_options($key) {
    
    $data = '';
    
    if(function_exists('jws_theme_get_option')) {
        
        $data = jws_theme_get_option($key);
        
    }
    
    
    return $data;
    

}

function jws_check_buy_rent($video_id) {
    $user_id = get_current_user_id();

    $buy_enabled  = get_post_meta($video_id, 'buy_enable', true);
    $rent_enabled = get_post_meta($video_id, 'rent_enable', true);
    $allowed      = false;

    if ($buy_enabled) {
        $allowed = false;
        $user_videos = get_user_meta($user_id, 'jws_purchased_videos', true);
        if (!empty($user_videos) && is_array($user_videos) && array_key_exists($video_id, $user_videos)) {
            return true;
        }
    }

    if ($rent_enabled) {
        $allowed = false;
        $user_videos = get_user_meta($user_id, 'jws_rented_videos', true);
        $purchase = isset($user_videos[$video_id]) ? $user_videos[$video_id] : array();
        $expired = isset($purchase['expire']) ? $purchase['expire'] : '';
        $delay = isset($purchase['delay']) ? (int) $purchase['delay'] : 0;
        $start = isset($purchase['time']) ? $purchase['time'] : '';
        $day_rent = isset($purchase['day_rent']) ? (int) $purchase['day_rent'] : 0;

        $delaying = false;

        if ($start) {
            $delay_time = strtotime($start) + ($delay * DAY_IN_SECONDS);
            $current_ts = current_time('timestamp');

            if ($expired === 'never' && $delay_time > $current_ts) {
                $delaying = true;
            } elseif ($expired === 'never' && $delay_time <= $current_ts) {
                // set actual expire time and persist
                $user_videos[$video_id]['expire'] = date('Y-m-d H:i:s', $delay_time + ($day_rent * DAY_IN_SECONDS));
                update_user_meta($user_id, 'jws_rented_videos', $user_videos);
                return false;
            }
        }

        if (!empty($user_videos) && is_array($user_videos) && array_key_exists($video_id, $user_videos)) {
            if ((!empty($expired) && strtotime($expired) > time()) || $delaying) {
                return true;
            }
        }
    }

    return $allowed;
}


function jws_buy_rent_display($video_id) { 
    $user_id = get_current_user_id();
    $buy_enabled  = get_post_meta($video_id, 'buy_enable', true);
    $rent_enabled = get_post_meta($video_id, 'rent_enable', true);

    $access = jws_user_has_video_access($video_id);
    if($access) {
        return;
    }

    // Use the correct post id for pricing fields
    $pmpro_id = $video_id;

    ?>
    <div class="pay-buttons type-2">
        <?php if ($buy_enabled): 
            $price = get_field('buy_price', $pmpro_id); ?>
            <?php if ($price): ?>
                <a href="#" class="btn button-default jws-buy-video" data-id="<?php echo esc_attr($pmpro_id); ?>">
                    <i class="jws-icon-handbag"></i><span><?php echo esc_html__('Buy for ', 'jws_streamvid') . wc_price($price); ?></span>
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($rent_enabled): 
            $rent_price = get_field('rent_price', $pmpro_id);
            $rent_day = get_field('rent_day', $pmpro_id); ?>
            <?php if ($rent_price): ?>
                <a href="#" class="btn button-default jws-rent-video" data-type="rent" data-id="<?php echo esc_attr($pmpro_id); ?>">
                    <i class="jws-icon-handbag"></i><span><?php echo esc_html__('Rent for ', 'jws_streamvid') . wc_price($rent_price); ?></span>
                </a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Check if current user has access to a video/post.
 *
 * @param int $post_id
 * @return bool True if user has access, false otherwise.
 */
 if(!function_exists('jws_user_has_video_access')) {
    function jws_user_has_video_access( $post_id ) {
        $has_access = false;
    
        if ( ! $post_id ) {
            return false;
        }
    
        // Check PMPro membership access
        if ( function_exists('pmpro_has_membership_access') ) {
            $pmpro_id = jws_check_episodes_membership_access( get_the_ID() );
            $post_levels = pmpro_has_membership_access( $post_id, null, true );
            $level_names = isset($post_levels[2]) ? $post_levels[2] : array();
    
            if ( pmpro_has_membership_access( $pmpro_id, get_current_user_id() ) && ! empty( $level_names ) ) {
                $has_access = true;
            }
        }
    
        // Check buy/rent access if no membership
        if ( ! $has_access ) {
            $check_video_has_buy_rent = jws_check_video_has_buy_rent( $post_id );
            if ( $check_video_has_buy_rent ) {
                $check_buy_rent = jws_check_buy_rent( $post_id );
                if ( $check_buy_rent ) {
                    $has_access = true;
                }
            }
        }
        if (!$has_access) {
            $requires_membership = !empty($level_names);
            $requires_buy_rent   = jws_check_video_has_buy_rent($post_id);
        
            if (!$requires_membership && !$requires_buy_rent) {
                $has_access = true;
            }
        }
    
        return $has_access;
    }
}

/**
 * Get Buy Description
 */
function jws_get_buy_description( $video_id ) {
    $buy_des = jws_theme_get_option('buynow_payment_description');
    $days    = get_post_meta($video_id, 'rent_day', true);
    $post_type = get_post_type($video_id);

    // Fallback to excerpt if theme option is empty
    if ( empty($buy_des) ) {
        $buy_des = get_the_excerpt($video_id);
    }

    // Replace placeholders
    if ( $days ) {
        $label = ( $days == 1 ) ? esc_html__('day', 'jws_streamvid') : esc_html__('days', 'jws_streamvid');
        $buy_des = str_replace('##day##', $days . ' ' . $label, $buy_des);
    }
    if ( $post_type ) {
        $buy_des = str_replace('##post_type##', $post_type, $buy_des);
    }

    return $buy_des;
}

/**
 * Get Rent Description
 */
function jws_get_rent_description( $video_id ) {
    $rent_des = jws_theme_get_option('rent_payment_description');
    $days     = get_post_meta($video_id, 'rent_day', true);
    $rent_delay = jws_theme_get_option('rent_delay');
    $post_type = get_post_type($video_id);
    if ( empty($days) ) {
        $days = jws_theme_get_option('rent_days', 2); 
    }


    // Replace placeholders
    if ( $days ) {
        $label = ( $days == 1 ) ? esc_html__('day', 'jws_streamvid') : esc_html__('days', 'jws_streamvid');
        $label_delay = ( $rent_delay == 1 ) ? esc_html__('day', 'jws_streamvid') : esc_html__('days', 'jws_streamvid');
        
        $rent_des = str_replace(
            array('##day##', '##start_delay##'),
            array($days . ' ' . $label, $rent_delay . ' ' . $label_delay),
            $rent_des
        );
    }
    if ( $post_type ) {
        $rent_des = str_replace('##post_type##', $post_type, $rent_des);
    }

    return $rent_des;
}


function jws_check_video_has_buy_rent($video_id) { 
    $buy_enabled = get_post_meta($video_id, 'buy_enable', true );
    $rent_enabled = get_post_meta($video_id, 'rent_enable', true );

  
    if($buy_enabled || $rent_enabled) {
        return true;
    }

    return false;

} 

function  jws_video_check_start() {
   $post_id = $_POST['id'];
   $current_user_id = get_current_user_id();
   $user_videos = get_user_meta( $current_user_id, 'jws_rented_videos', true );
   if(isset($user_videos[$post_id])) {
      $purchase = $user_videos[$post_id];
      $expire = isset($purchase['expire']) ? $purchase['expire'] : 'never';
      $day_rent = isset($purchase['day_rent']) ? $purchase['day_rent'] : 0;
      $start = current_time('mysql');
      if($expire == 'never') {
         $user_videos[$post_id]['expire'] = date('Y-m-d H:i:s', strtotime($start) + ((int)$day_rent * 24 * 60 * 60));
         update_user_meta( $current_user_id, 'jws_rented_videos', $user_videos );
      }
   }
}
add_action( 'wp_ajax_jws_video_check_start', 'jws_video_check_start' );


function jws_streamvid_load_template( $file, $require_once = true, $args = array()  ){

	$_file = trailingslashit(JWS_STREAMVID_PATH_PUBLIC).$file;

	if( file_exists( $_file ) ){
		load_template( $_file, $require_once, $args  );	
	}
}

function jws_get_max_upload_image_size(){

    $max_size 		= (int)jws_streamvid_options( 'max_upload_size', 2 ) * 1024 * 1024;

    $size = $max_size;

    return apply_filters( 'streamvid_get_max_upload_image_size', $size );
}

function jws_get_gender(){ 
        
   return array(
        'male'        =>  esc_html__( 'Male', 'jws_streamvid' ),
        'female'       =>  esc_html__( 'Female', 'jws_streamvid' ),
        'other'       =>  esc_html__( 'Other', 'jws_streamvid' ),
    ); 
        
}

/*
* Function ajax filter
*/
if (!function_exists('jws_load_season')) {
    function jws_load_season()
    {
        ob_start();
        $image_size = jws_streamvid_options('tv_shows_imagesize');
        $tv_shows_seasons = get_field('tv_shows_seasons', $_POST['id']);
        if (
            isset($tv_shows_seasons[$_POST['season']]['episodes']) &&
            !empty($tv_shows_seasons[$_POST['season']]['episodes'])
        ) {
            $episodes = $tv_shows_seasons[$_POST['season']]['episodes'];
            $display = 'slider';
            if (isset($_POST['display'])) {
                $display = $_POST['display'];
            }
            $column = "jws-post-item jws-pisodes_advanced-item";
            if ($display == 'grid') {
                $column .= " col-xl-2 col-lg-3 col-6";
            } else {
                $column .= " slider-item";
            }

            if ($display == 'episodes_version2') {
                $args = array(
                    'tv_shows' => $_POST['id'],
                    'season'   => $_POST['season'] + 1,
                );
                get_template_part('template-parts/content/episodes/post', 'episodes-list', $args);
            } elseif ($display == 'v3') {
                $args = array(
                    'tv_shows' => $_POST['id'],
                    'season'   => $_POST['season'] + 1,
                );
                get_template_part('template-parts/content/movies_v2/post', 'episodes', $args);
            } else {
                foreach ($episodes as $episodes_value) {
                    $args = array(
                        'image_size' => $image_size,
                        'post_id'    => $episodes_value,
                    );
                    ?>
                    <div class="<?php echo esc_attr($column); ?>">
                        <?php
                        get_template_part('template-parts/content/episodes/layout/layout4', '', $args);
                        ?>
                    </div>
                    <?php
                }
            }
        }

        $output = ob_get_clean();

        $result = array(
            'content' => $output,
            'status'  => $_POST,
        );
        wp_send_json_success($result);
    }

    add_action('wp_ajax_jws_load_season', 'jws_load_season');
    add_action('wp_ajax_nopriv_jws_load_season', 'jws_load_season');
}

if(!function_exists('jws_custom_post_type_endpoint')) {
    
  
    
    function jws_custom_post_type_endpoint() {
        
        $episodes_slug = jws_streamvid_options('episodes_slug');   
        $episodes_slug = !empty($episodes_slug) ? $episodes_slug : 'episodes';
    
         add_rewrite_endpoint( $episodes_slug , EP_PERMALINK);
    }
    add_action( 'init', 'jws_custom_post_type_endpoint' );  
    
}



if(!function_exists('jws_check_play_tv_shows')) {

    function jws_check_play_tv_shows($tv_shows_seasons) {
   
        if(isset($tv_shows_seasons[0]['episodes'][0])) {
            return get_the_permalink($tv_shows_seasons[0]['episodes'][0]);
        }
  
    }

}

if(!function_exists('jws_check_trailer')) {

    function jws_check_trailer($post_id) {
        $url = '';
        $trailer_type = get_post_meta($post_id , 'videos_trailer_type' , true);

        if($trailer_type == 'url') {
            $url =  get_post_meta($post_id , 'videos_trailer_url' , true);
            
          
        } else {
            $video_id =  get_post_meta($post_id , 'videos_trailer_file' , true);
            $url = wp_get_attachment_url($video_id);
        }
  
        return $url;
        
    }

}


if(!function_exists('jws_episodes_check_type')) {
    function jws_episodes_check_type( $id ) { 
         
            $args = array(
                'post_type' => 'tv_shows',
                'fields' => 'ids',
                'posts_per_page' => -1,
                'orderby' => 'modified',
                'meta_query' => array(
                'relation'      => 'OR',
                    array(
                        'key' => 'tv_shows_seasons_$_episodes',
                        'value' => $id,
                        'compare' => 'LIKE'
                    )
                )
            );
            
            
            $cast = new WP_Query($args);
            $cast = $cast->posts;
            if(!empty($cast)) {
                return $cast[0];
            }
           
    
        
    }  
  
} 

if(!function_exists('jws_episodes_check_season')) {
    function jws_episodes_check_season( $args ) { 
        
    $args = wp_parse_args( $args, array(
        'id_tv'   =>  '',
        'id'   =>  get_the_ID(),
    ) );
    extract( $args );

    $tv_shows_seasons = get_field('tv_shows_seasons',$id_tv);
 
    if(empty($tv_shows_seasons)) return false;
    
    foreach($tv_shows_seasons as $season => $episodes) {
    
       foreach($episodes['episodes'] as $episode) {
       
         if($episode == $id) {
            
            return $season + 1;
            
        }
       }    
    }    
  }  
  
} 



if(!function_exists('jws_share_button')) { 
    function jws_share_button($id = '') {
        if(!jws_streamvid_options('videos_share')) return false;
        // data-share-id tells the popup which post to fetch; data-modal-jws opens it.
        $post_id = $id ? absint($id) : get_the_ID();
        ?>
        
        <div class="jws-share">
            <a href="#" data-modal-jws="#share-videos" data-share-id="<?php echo esc_attr($post_id); ?>">
                <i class="jws-icon-share-network"></i>
                <span><?php echo esc_html__('Share','jws_streamvid'); ?></span>
            </a>
        </div>
        
        <?php
   } 
}

if(!function_exists('jws_like_button')) {
    function jws_like_button($post_type, $id = '') {
        if(!jws_streamvid_options('videos_like')) return false;
        $post_id = $id ? absint($id) : get_the_ID();
        $liked = get_post_meta($post_id, 'likes', true);
        $liked_number = $liked > 0 ? $liked : '0';
        $user_id = absint(get_current_user_id());
        $class = 'like-button';
        if($user_id && Jws_Favorites::is_liked($user_id, $post_id, $post_type)) {
            $class .= ' liked';
        }
        ?>
        <div class="jws-likes">
            <a href="<?php echo get_the_permalink($post_id); ?>" class="<?php echo esc_attr($class); ?>" data-type="<?php echo esc_attr($post_type); ?>" data-post-id="<?php echo esc_attr($post_id); ?>">
                <i class="jws-icon-thumbs-up"></i>
                <span><?php printf( _n( '%s <span>like</span>', '%s <span>likes</span>', $liked_number, 'jws_streamvid' ), '<span class="likes-count">' . esc_html($liked_number) . '</span>'); ?></span>
            </a>
        </div>
        <?php
    }
}

if(!function_exists('jws_favorite_button')) {
    function jws_favorite_button($post_type, $id = '') {
        if(!jws_streamvid_options('videos_like')) return false;
        $post_id = $id ? absint($id) : get_the_ID();
        $liked = get_post_meta($post_id, 'likes', true);
        $liked_number = $liked > 0 ? $liked : '0';
        $user_id = absint(get_current_user_id());
        $class = 'like-button';
        if($user_id && Jws_Favorites::is_liked($user_id, $post_id, $post_type)) {
            $class .= ' liked';
        }
        ?>
        <div class="jws-likes">
            <a href="<?php echo get_the_permalink($post_id); ?>" class="<?php echo esc_attr($class); ?>" data-type="<?php echo esc_attr($post_type); ?>" data-post-id="<?php echo esc_attr($post_id); ?>">
                <i class="jws-icon-heart"></i>
                <span><?php printf( _n( '%s <span>Favorite</span>', '%s <span>Favorites</span>', $liked_number, 'jws_streamvid' ), '<span class="likes-count">' . esc_html($liked_number) . '</span>'); ?></span>
            </a>
        </div>
        <?php
    }
}

if(!function_exists('jws_profile_tab_label')) {
    /**
     * Label for one post type on the profile's History / Watchlist / Favorites
     * tab strip.
     *
     * Those strips used to title themselves with ucwords(str_replace('_',' ')),
     * which reads fine for "Tv Shows" but turns the short-drama post type into
     * plain "Drama" — not what the module is called anywhere else on the
     * site. Anything not named here keeps the old derivation, so adding a
     * post type needs no change at all.
     *
     * @param string $post_type
     * @return string
     */
    function jws_profile_tab_label($post_type) {

        $labels = array(
            /* Watching an episode also credits the parent `drama` post (see
               Jws_Streamvid_Public::history()), the same way a tv_shows
               episode credits its show — so the History tab here, like the
               Tv Shows tab, lists series rows, not individual episodes. */
            'drama' => esc_html__('Drama Short', 'jws_streamvid'),
        );

        if (isset($labels[$post_type])) {
            return $labels[$post_type];
        }

        return ucwords(str_replace('_', ' ', $post_type));
    }
}

if(!function_exists('jws_watchlist_check')) {
    
     function jws_watchlist_check($post_id) {
        $user_id = absint(get_current_user_id());
        if($user_id && Jws_Watchlist::is_watchlisted($user_id, $post_id)) {
            return ' watchlisted';
        }
        return '';
    }
    
}

if(!function_exists('jws_watchlist_button')) {
    function jws_watchlist_button($id = '') {
        if(!jws_streamvid_options('videos_watchlist')) return false;
        $post_id = $id ? absint($id) : get_the_ID();
        $watchlisted = jws_watchlist_check($post_id); 
        $class = 'watchlist-add'.$watchlisted
        ?>
        <div class="jws-watchlist">
            <a class="<?php echo esc_attr($class); ?>" href="<?php echo get_the_permalink($post_id); ?>" data-post-id="<?php echo esc_attr($post_id); ?>">
                <i class="jws-icon-plus"></i>
                <span><?php echo esc_html__('Watchlist', 'jws_streamvid'); ?></span>
                <span class="added"><?php echo esc_html__('Watchlisted', 'jws_streamvid'); ?></span>
            </a>
        </div>
        <?php
    }
}


if (!function_exists('jws_download_button')) {
    function jws_download_button($id = '', $type = 'type-1') {
        $post_id = $id ? absint($id) : get_the_ID();
        $enable = get_post_meta($post_id, 'download', true);
        $download_list = get_field('download_list', $post_id);

        $pmpro_id = is_singular('episodes') ? jws_episodes_check_type($post_id) : $post_id;

        if (
            function_exists('pmpro_has_membership_access') &&
            !pmpro_has_membership_access($pmpro_id, get_current_user_id()) &&
            !isset($_GET['action']) &&
            !isset($_GET['post'])
        ) {
            return false;
        }

        if (!$enable || empty($download_list)) {
            return false;
        }

        if ($type == 'type-2') {
            $text = esc_html__('Download', 'jws_streamvid');
            echo "<a href='#' class='jws-download-videos fw-700' data-id='" . esc_attr($post_id) . "'><i class='jws-icon-arrow-line-down'></i><span class='text'>$text</span></a>";
        } else {
            $text = esc_html__('Download Videos', 'jws_streamvid');
            echo "<a href='#' class='jws-download-videos fw-700' data-id='" . esc_attr($post_id) . "'><span class='text'>$text</span><i class='jws-icon-arrow-line-down'></i></a>";
        }

        if (!empty($download_list)) {
            echo '<ul class="jws-download-list">';
            foreach ($download_list as $download) {
                echo '<li><a href="#" data-url="' . esc_url($download['download_url']) . '">' . esc_html($download['download_name']) . '</a></li>';
            }
            echo '</ul>';
        }
    }
}

function person_register_meta_boxes() {
    
	add_meta_box( 'person', __( 'Person data', 'textdomain' ), 'jws_person_data', 'person' );
    
    
    
    if(function_exists( 'pmpro_page_meta' ) ){
          $cpts = array('movies','tv_shows','videos','episodes');
          if(!empty($cpts)) {
            foreach($cpts as $cpt) {
              add_meta_box('pmpro_page_meta', 'Require Membership', 'pmpro_page_meta', $cpt, 'side', 'high');
            }
          } 
    }

    
}
add_action( 'add_meta_boxes', 'person_register_meta_boxes' );


function jws_person_data( $post ) {
   
    $live_data = get_post_meta( $post->ID, 'person_data', true );
    $live_data2 = get_post_meta( $post->ID, 'person_data_crew', true );
    
  
}


function jws_custom_video_fields($form_fields, $post) {
    
    $form_fields['cloudflare_id'] = array(
        'label' => 'Cloudflare ID',
        'input' => 'text',
        'value' => get_post_meta($post->ID, 'cloudflare_id', true),
        'helps' => 'This is cloudflare id'
    );
    
    $form_fields['bunny_id'] = array(
        'label' => 'Bunny ID',
        'input' => 'text',
        'value' => get_post_meta($post->ID, 'bunny_id', true),
        'helps' => 'This is bunny id'
    );
    
    return $form_fields;
    
}


add_filter('attachment_fields_to_edit', 'jws_custom_video_fields', 10, 2);


function jws_save_custom_video_fields($post, $attachment) {
    
    if (isset($attachment['bunny_id'])) {
        update_post_meta($post['ID'], 'bunny_id', $attachment['bunny_id']);
    }
    if (isset($attachment['cloudflare_id'])) {
        update_post_meta($post['ID'], 'cloudflare_id', $attachment['cloudflare_id']);
    }
    
    return $post;
}
add_filter('attachment_fields_to_save', 'jws_save_custom_video_fields', 10, 2);


if (!function_exists('jws_ajax_sources')) {
    function jws_ajax_sources()
    {
        if(isset($_POST['id'])) {
            
            ob_start(); 
        
            $data = array();
            
            $data['id'] = $_POST['id'];
            
            if($_POST['index'] != 'main') {
     
                $sources = get_field('sources',$_POST['id']);
                $url = isset($sources[$_POST['index']]['url']) ? $sources[$_POST['index']]['url'] : '';
                $data['url'] = $url;
            }
            
            do_action('streamvid/movies/player',$data); 
        
            
            $output = ob_get_clean();
            $result = array(
               'content' => $output,
               'status' => $_POST['index'],
            );
            wp_send_json_success( $result );
        }
        
    }

    add_action('wp_ajax_jws_ajax_sources', 'jws_ajax_sources');
    add_action('wp_ajax_nopriv_jws_ajax_sources', 'jws_ajax_sources');
}

function jws_is_youtube_url($url) {
  $pattern = '/^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.be)\/.+/';
  return preg_match($pattern, $url);
}

function jws_is_vimeo_url($url) {
  $pattern = '/^(https?:\/\/)?(www\.|player\.)?vimeo\.com\/.+/';
  return preg_match($pattern, $url);
}

function jws_check_m3u8_video($video_url) {

    // Guard: skip empty, relative, or clearly invalid URLs
    if ( empty( $video_url ) || $video_url === 'many_quality' ) {
        return false;
    }

    // Relative protocol URLs (//example.com/...) — treat by extension only, no HTTP request
    if ( strpos( $video_url, '//' ) === 0 ) {
        return stripos( $video_url, '.m3u8' ) !== false;
    }

    // Quick check by extension/keyword — no HTTP request needed
    if ( stripos( $video_url, '.m3u8' ) !== false ) {
        return true;
    }

    // Must be a valid absolute URL before making a remote request
    if ( ! filter_var( $video_url, FILTER_VALIDATE_URL ) ) {
        return false;
    }

    // Only fetch the first bytes — #EXTM3U is always the first line of an HLS
    // playlist. Prevents loading large MP4 files fully into memory (fatal error).
    $response = wp_remote_get( $video_url, array(
        'timeout'             => 5,
        'sslverify'           => false,
        'limit_response_size' => 1024,
        'headers'             => array( 'Range' => 'bytes=0-1023' ),
    ) );

    if ( is_wp_error( $response ) ) {
        return false;
    }

    $body = wp_remote_retrieve_body( $response );

    return strpos( $body, '#EXTM3U' ) !== false;

}

function jws_has_iframe_in_text($text) {
  if(empty($text)) return false;  
  $dom = new DOMDocument();
  libxml_use_internal_errors(true);
  $dom->loadHTML($text);
  $iframes = $dom->getElementsByTagName('iframe');
  
  return $iframes->length > 0;
}

function jws_has_shortcode_video($text) { 
 
    $first_position = strpos($text, "[");
    $last_position = strrpos($text, "]");
    
    if ($first_position !== false && $last_position !== false) { 
        
        return true;
            
    }
    
}

function jws_check_episodes_membership_access($pmpro_id) { 
  

 if(isset($_GET['package_single'])) {
    return $pmpro_id;
 }
  
 if(jws_theme_get_option('tv_shows_package')) {
    
   $pmpro_id = jws_episodes_check_type($pmpro_id);

 }
 
 return $pmpro_id;
  
}

function jws_premium_videos($post_id) {
    $output = '';

    $has_membership = function_exists('pmpro_has_membership_access') && !pmpro_has_membership_access($post_id, get_current_user_id());
    $buy_enabled  = get_post_meta($post_id, 'buy_enable', true );
    $rent_enabled = get_post_meta($post_id, 'rent_enable', true );
    $age = get_post_meta($post_id, 'videos_age', true );
    

    if ($has_membership || $buy_enabled || $rent_enabled || !empty($age)) {
        $output .= '<div class="jws-premium-icons">';
        if ($has_membership) {
            $output .= '<span class="jws-premium jws-icon-crown-1"></span>';
        }
        if ($buy_enabled || $rent_enabled) {
            $output .= '<span class="jws-premium jws-icon-handbag-fill"></span>';
        }
        $output .= '</div>';
    }

    return $output;
}

if(!function_exists('jws_return_data_demo')) {
    
    function jws_return_data_demo() {
        
        if(jws_theme_get_option('block_user_function')) {
              wp_send_json_error(
                new WP_Error(
                    'error',
                    esc_html__( 'Demo version will limit media upload, You can turn this limit off in theme settings.', 'jws_streamvid' )
                )
             ); 
        }
         

    }
    
}

add_action('wp_ajax_jws_video_check', 'jws_video_check');    
add_action( 'wp_ajax_nopriv_jws_video_check', 'jws_video_check' );

function jws_video_check() {

    $errors = new WP_Error(); 

     if( $errors->get_error_code() ){
      
        wp_send_json_error( $errors );
           
     } else {
             $post_id = $_POST['id'];

            $url_sourse = '';
            
            if(!empty($args)) {
                
              $defaults  = array(
                 'id' => '',
                 'url' => '',
              );  
              $args = wp_parse_args( $args , $defaults );
            
              extract( $args );
                
              $post_id = !empty($id) ? $id :  $post_id; 
              $url_sourse = !empty($url) ? $url : '';
            } 
            
            
            $global_ratio = jws_theme_get_option('video_ratio');
            
            $video_radio = get_post_meta($post_id,'video_ratio',true);
            
            $is_affiliate = get_post_meta($post_id,'is_affiliate',true);
            
            
            $video_radio = !empty($video_radio) ? $video_radio : $global_ratio;
            
            $class_player = 'videos_player ratio_'.$video_radio;
            
            $attr_player = '';
            
            $bn_host_name = jws_theme_get_option('bn_host_name');
            
            $cl_host_name = jws_theme_get_option('cl_host_name');
            
            $advence_videos = jws_theme_get_option('video_advenced');
            
            $autoplay = jws_theme_get_option('video_autoplay') ? true : false;
            
            $muted = jws_theme_get_option('video_muted') ? true : false;
            
            $logo_player = jws_theme_get_option('player_logo');
            
            if(is_embed()) $class_player .= ' has-embed'; 
            
            
            $post_type = get_post_type();
            
            $poster_id = get_post_thumbnail_id();
            $featured_image_two = get_post_meta( $post_id , 'featured_image_two', true );
            
            if(!empty($featured_image_two)) {
                $poster_id  = $featured_image_two;
            }
            
            if($post_type == 'videos') {
              
              $live_data = get_post_meta( $post_id , 'live_data', true ); 
              
            }
            
            
            $image = jws_image_advanced(array('attach_id' => $poster_id, 'thumb_size' => 'full' , 'return_url' => true));
            
            
            
            $videos_type = !empty($url_sourse) ? 'url' : get_post_meta($post_id, 'videos_type',true);
            
            if($videos_type == 'url') {
                
                $video_url = !empty($url_sourse) ? $url_sourse : get_post_meta($post_id, 'videos_url',true);
            
                $type = "video/mp4";

                if(jws_is_youtube_url($video_url)) {
                    $type = 'video/youtube';

                }

                if(jws_is_vimeo_url($video_url)) {
                    $type = 'video/vimeo';

                }

                if( ! empty( $video_url ) && jws_check_m3u8_video($video_url)) {
                    $type = 'application/x-mpegURL';
                }

                $iframe = jws_has_iframe_in_text($video_url);
                
                if($iframe) {
                    $type = "iframe";
                }
                
                if(jws_has_shortcode_video($video_url)) {
                    
                      $type = "shortcode";
                      
                }
            
            
             
            }else {
              
                $video_id = get_post_meta($post_id, 'videos_file',true);
                
                $file = get_post_meta($video_id , 'encode_url' , true);
                
                $type = get_post_mime_type( $video_id );
                
                $video_url = wp_get_attachment_url($video_id);
                
                $bunny_id = get_post_meta($video_id , 'bunny_id' , true);
                
                $cloudflare_id = get_post_meta($video_id , 'cloudflare_id' , true);
                
                
                if(!empty($file) && $advence_videos == 'encode') {
                    $video_url = get_site_url().strstr($file, '/wp-content');
                    $type = 'application/x-mpegURL';
                }
                
                if(!empty($bunny_id) && $advence_videos == 'bunny') {
                    $video_url = "//$bn_host_name/$bunny_id/playlist.m3u8";
                    $type = 'application/x-mpegURL';
                }  
                
                if(!empty($cloudflare_id) && $advence_videos == 'cloudflare') {
                    $video_url = "//$cl_host_name/$cloudflare_id/manifest/video.m3u8";
                    $type = 'application/x-mpegURL';
                } 
            
            }
            
            if(isset($live_data['uid'])) {
                
                $attr_player .= 'data-live-uid='.$live_data['uid'].'';
            
                $live_stream_url =  jws_streamvid()->get()->live_videos->get_live_stream_url($live_data['uid']);
             
                $video_url = $live_stream_url;
                
                $type = 'application/x-mpegURL';
                
            }
            
            if(empty($video_url)) {
             
             $default_video_type = jws_theme_get_option('video_player_default_type');   
             $default_video_url = jws_theme_get_option('video_player_default_url'); 
             
             if($default_video_type == 'm3u8') {
                 $type = 'application/x-mpegURL';
             } elseif($default_video_type == 'youtube') {
                 $type = 'video/youtube';
             } elseif($default_video_type == 'vimeo') {
                 $type = 'video/vimeo';
             } else {
                 $type = "video/mp4";
             }
                
                
             
             $video_url = $default_video_url;  


            }

            $video_url = jws_get_security_video_url($video_url); 
       
            $quality_lists = get_field( "quality_lists", $post_id );

            $quality_array = array();

            if(!empty($quality_lists) && $videos_type == 'many_quality') {
                        
                foreach($quality_lists as $key => $quality) {
                    $type_qua = "video/mp4";

                    if(jws_is_youtube_url($quality['quality_url'])) {
                        $type_qua = 'video/youtube';

                    }

                    if(jws_is_vimeo_url($quality['quality_url'])) {
                        $type_qua = 'video/vimeo';

                    }

                    if( ! empty( $quality['quality_url'] ) && jws_check_m3u8_video($quality['quality_url'])) {
                        $type_qua = 'application/x-mpegURL';
                    }

                    $quality['quality_url'] = jws_get_security_video_url($quality['quality_url']);

                    $quality_array[] = array(
                      'url' => base64_encode($quality['quality_url']),
                      'label' => base64_encode($quality['label']),
                      'type' => base64_encode($type_qua)
                    );

                }
                
                if(empty($video_url)) $video_url = 'many_quality';
                
                
            } 


       
            
            $setup = array(
            	'token'		=>	array(
            		array(         
            			'item'		=>	base64_encode($video_url),
            			'item_type'		=>	base64_encode($type),
                        'item_quality'	 => $quality_array,
            		)
            	),
            );
             wp_send_json_success($setup);
     }
    
    
}


function jws_get_security_video_url($video_url) {
    
        $securityKey = jws_theme_get_option('bn_token_key');

        if(is_bunnycdn_url($video_url) && !empty($securityKey)) {

            return jws_sign_bcdn_url(
                $video_url,
                $securityKey,
                3600,             
                null,             
                true,            
            );

        }

    return $video_url; 
    
}


function jws_sign_bcdn_url($url, $securityKey, $expiration_time = 3600, $user_ip = NULL, $is_directory_token = false, $path_allowed = NULL, $countries_allowed = NULL, $countries_blocked = NULL, $referers_allowed = NULL)
{    
	if(!is_null($countries_allowed))
	{
		$url .= (parse_url($url, PHP_URL_QUERY) == "") ? "?" : "&";
		$url .= "token_countries={$countries_allowed}";
	}
	if(!is_null($countries_blocked))
	{
		$url .= (parse_url($url, PHP_URL_QUERY) == "") ? "?" : "&";
		$url .= "token_countries_blocked={$countries_blocked}";
	}
	if(!is_null($referers_allowed))
	{
		$url .= (parse_url($url, PHP_URL_QUERY) == "") ? "?" : "&";
		$url .= "token_referer={$referers_allowed}";
	}

	$url_scheme = parse_url($url, PHP_URL_SCHEME);
    $url_host = parse_url($url, PHP_URL_HOST);
    $url_path = parse_url($url, PHP_URL_PATH);
	$url_query = parse_url($url, PHP_URL_QUERY);

    $parsed_path = parse_url($url, PHP_URL_PATH);
    $directory_path = rtrim(dirname($parsed_path), '/');


	$parameters = array();
	parse_str($url_query, $parameters);

    // Check if the path is specified and ovewrite the default
    $signature_path = $directory_path;

    $parameters["token_path"] = $signature_path;

    // Expiration time
    $expires = time() + $expiration_time; 

    // Construct the parameter data
	ksort($parameters); // Sort alphabetically, very important
    $parameter_data = "";
    $parameter_data_url = "";
    if(sizeof($parameters) > 0)
    {
    	foreach ($parameters as $key => $value) 
    	{
    		if(strlen($parameter_data) > 0)
    			$parameter_data .= "&";

    		$parameter_data_url .= "&";

    		$parameter_data .= "{$key}=" . $value;
    		$parameter_data_url .= "{$key}=" . urlencode($value); // URL encode everything but slashes for the URL data
		}
    }

    // Generate the toke
    $hashableBase = $securityKey.$signature_path.$expires;

    // If using IP validation
    if(!is_null($user_ip))
    {
        $hashableBase .= $user_ip;
    }

    $hashableBase .= $parameter_data;

    // Generate the token
    $token = hash('sha256', $hashableBase, true);
	$token = base64_encode($token);
	$token = strtr($token, '+/', '-_');
	$token = str_replace('=', '', $token); 

	if($is_directory_token)
	{
		return "{$url_scheme}://{$url_host}/bcdn_token={$token}&expires={$expires}{$parameter_data_url}{$url_path}";
	}
	else 
	{
		return "{$url_scheme}://{$url_host}{$url_path}?token={$token}{$parameter_data_url}&expires={$expires}";
	}
}

function is_bunnycdn_url($url) {
    $host = parse_url($url, PHP_URL_HOST);
    return $host !== null && str_ends_with($host, '.b-cdn.net');
}

function is_cloudflare_stream_url($url) {
    $host = parse_url($url, PHP_URL_HOST);
    return $host !== null && str_contains($host, '.cloudflarestream.com');
}


function get_cloudflare_signed_url($url) {
    
    $parts = parse_url($url);
    if (empty($parts['path'])) return $url;

    $pathSegments = explode('/', trim($parts['path'], '/'));
    $video_id = $pathSegments[0] ?? null;
    if (!$video_id) return $url;

    
    $account_id     = jws_theme_get_option('cloudflare_id');
    $api_token      = jws_theme_get_option('cloudflare_key');
    $account_domain = jws_theme_get_option('cl_host_name');

    
    if (!$account_id || !$api_token || !$account_domain) return $url;

    
    $api_url = "https://api.cloudflare.com/client/v4/accounts/$account_id/stream/$video_id/token";
    
    $headers = [
        "Authorization" => "Bearer $api_token",
        "Content-Type"  => "application/json;charset=UTF-8",
    ];

    $post_data = json_encode([
        "exp" => time() + 3600, 
        // "downloadable" => true,
        // "accessRules" => [
        //     ["type" => "ip.geoip.country", "country" => ["VN"], "action" => "allow"],
        //     ["type" => "any", "action" => "block"]
        // ]
    ]);

    $response = wp_remote_post($api_url, [
        'headers' => $headers,
        'body'    => $post_data,
        'timeout' => 10,
    ]);

    if (is_wp_error($response)) {
        error_log('[Cloudflare Signed URL Error] ' . $response->get_error_message());
        return $url;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    $token = $body['result']['token'] ?? null;

    return $token
        ? "https://$account_domain/$token/manifest/video.m3u8"
        : $url;
}



function jws_pmpro_lifter_membership_content_filter( $filtered_content, $original_content ) {
      if ( is_singular('movies') || is_singular('tv_shows') || is_singular('episodes') || is_singular('videos') ) {
        return $original_content;
    }
    return $filtered_content;
}
add_filter( 'pmpro_membership_content_filter', 'jws_pmpro_lifter_membership_content_filter', 10, 2 );

/**
 * AJAX handler for loading favorites
 */
if (!function_exists('jws_load_favorites')) {
    function jws_load_favorites() {
        ob_start();
        $current_filter = isset($_POST['favorites_filter']) ? sanitize_text_field($_POST['favorites_filter']) : 'movies';
        include plugin_dir_path(__FILE__) . '../public/user/profile/favorites-list.php';
    }
    add_action('wp_ajax_jws_load_favorites', 'jws_load_favorites');
    add_action('wp_ajax_nopriv_jws_load_favorites', 'jws_load_favorites');
}