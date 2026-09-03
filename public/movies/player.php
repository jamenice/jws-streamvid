<?php 

$post_id = get_the_ID();

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

$video_ratio_tv = jws_theme_get_option('video_ratio_tv','');
$video_ratio_videos = jws_theme_get_option('video_ratio_videos','');

if(is_singular('episodes') && !empty($video_ratio_tv)) {
    $video_radio = $video_ratio_tv; 
}

if(is_singular('videos') && !empty($video_ratio_videos)) {
    $video_radio = $video_ratio_videos;
}



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

$image = function_exists('jws_poster_banner_url') ? jws_poster_banner_url($post_id, $image_size = 'full') : '';

$featured_image_two = function_exists('jws_backdrop_banner_url') ? jws_backdrop_banner_url($post_id, $image_size = 'full') : '';

if(!empty($featured_image_two)) {
   $image   = $featured_image_two; 
}

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

    if(jws_check_m3u8_video($video_url)) {
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

        if(jws_check_m3u8_video($quality['quality_url'])) {
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
	'controls'			=>	true,
	'muted'				=>	$muted,
	'autoplay'			=>	$autoplay,
	'preload'			=>	'auto',
    'playbackRates' => array(0.5, 1, 1.5, 2),
    'logo' => array('url'=>''),
	'sources'			=>	array(
		array(         
			'src'		=>	base64_encode($video_url),
			'type'		=>	$type,
            'item_quality' => $quality_array
		)
	),
);

if(!empty($image)) {
    $setup['poster'] = $image;
}

if(isset($logo_player['url']) && !empty($logo_player['url'])) {
    
    $setup['logo']['url'] = $logo_player['url'];
    
}



/* Check Current Time */
$current_time = '';
if( is_user_logged_in() ){ 
          
        $user_id = get_current_user_id();

        $time_id = is_singular( 'episodes' ) ? jws_episodes_check_type($post_id) : $post_id;
        $item = Jws_History::get_item($user_id, $time_id);

        if(is_singular( 'episodes' ) && isset($item['episodes']) && $item['episodes'] == $post_id ) {

            $current_time = $item['time'];

        } elseif(!is_singular( 'episodes' ) && !empty($item)) {

            $current_time = $item['time'];

        } else {
            
            $current_time = '';
            
        }
    
} 
  
$setup['current_time'] = $current_time;


$has_access = false;


$pmpro_id = $post_id;



if(is_singular('episodes')) {
   $pmpro_id = jws_check_episodes_membership_access($pmpro_id);
}


if ( function_exists( 'pmpro_has_membership_access' ) ) {
    $post_levels = pmpro_has_membership_access( $pmpro_id, NULL, true );
    $level_names = $post_levels[2];
} else {
    $post_levels = array();
    $level_names = array();
}


$has_membership_levels = !empty($level_names);
$has_membership_access = false;

if (function_exists('pmpro_has_membership_access') && $has_membership_levels) {
    $has_membership_access = pmpro_has_membership_access($pmpro_id, get_current_user_id());
}


$check_video_has_buy_rent = jws_check_video_has_buy_rent($pmpro_id);
$has_buy_rent_access = false;

if ($check_video_has_buy_rent) {
    $check_buy_rent = jws_check_buy_rent($pmpro_id); 
    if ($check_buy_rent) {
        $has_buy_rent_access = true; 
    }
}


$is_author = (is_user_logged_in() && get_current_user_id() == get_post_field('post_author', $post_id));


if ($is_author) {
    $has_access = true;
} elseif ($has_membership_levels && $has_membership_access) {
    $has_access = true;
} elseif ($has_buy_rent_access) {
    $has_access = true;
} elseif (!$has_membership_levels && !$check_video_has_buy_rent) {
   $has_access = true;
} else {
    $has_access = false;
}

if(!$has_access && !isset($_GET['action']) && !isset($_GET['post'])) {
    $type = 'blocked';
}


$setup = apply_filters( 'streamvid/player/setup', $setup , $post_id );



if(is_admin()) {
    $setup['autoplay'] = false;
}

$class_player .= ' '.$type;

?>
<div class="<?php echo esc_attr($class_player); ?> vjs-waiting" <?php echo esc_attr($attr_player); ?> data-playerid="<?php echo esc_attr($post_id); ?>">

<?php  

if(isset($live_data['uid'])) { 
    
    ?>
        <div class="player-overlay">
        
            <div class="overlay-inner">
                  <div class="spinner">
                        <?php 
                            for ($i = 1; $i <= 8; $i++) {
                                echo '<div class="spinner-blade"></div>';
                            }
                        ?>
                 </div> 
                 <div class="message">
                    <?php 

                       echo esc_html__('Stream is starting soon.','jws_streamvid'); 

                    ?>
                </div>
                
            </div>
           
        </div>
        
    <?php    
    
}

$subtitles = get_field( "sub_titles", $post_id );


if($type == 'blocked'){

    if ( function_exists( 'pmpro_has_membership_access' ) ) {
        $post_levels = pmpro_has_membership_access( $pmpro_id, NULL, true );
        $level_names = $post_levels[2];
    } else {
        $post_levels = array();
        $level_names = array();
    }


    $buy_enable = get_field('buy_enable', $pmpro_id);
    $rent_enable = get_field('rent_enable', $pmpro_id);

    $has_bought = false;
    $has_rented = false;

    if(function_exists('jws_check_buy_rent')) {
        $has_bought = jws_check_buy_rent($pmpro_id, 'buy');
        $has_rented = jws_check_buy_rent($pmpro_id, 'rent');
    }

    $has_membership = !empty($level_names);

    if($has_membership && ($buy_enable || $rent_enable)) {
        ?>
        <div style="background:url(<?php echo esc_url($image); ?>),#000000" class="videos-message">
            <div class="message-inner">
                <ul class="jws-tabs">
                    <li class="active" data-tab="tab-subscriber"><?php echo esc_html__('Subscription', 'jws_streamvid'); ?></li>
                    <li data-tab="tab-payperview"><?php echo esc_html__('Pay Per View', 'jws_streamvid'); ?></li>
                </ul>
                <div class="jws-tab-content tab-subscriber active">
                    <?php
                    if(!pmpro_has_membership_access( $pmpro_id, get_current_user_id())) {
                        $post = get_post($pmpro_id); 
                        setup_postdata($post); 
                        echo jws_pmpro_no_access_message_html('', $pmpro_id, $level_names);
                        wp_reset_postdata(); 
                    }
                    ?>
                </div>
                <div class="jws-tab-content tab-payperview">
                    <?php echo esc_html__('You can purchase access to this video without a subscription.', 'jws_streamvid'); ?>
                    <div class="pay-buttons">
                        <?php if($buy_enable && !$has_bought): 
                            $price = get_field('buy_price', $pmpro_id); ?> 
                            
                                <?php if($price): ?>
                                    <a href="#" class="btn btn-primary jws-buy-video" data-id="<?php echo esc_attr($pmpro_id); ?>">
                                        <span><?php echo esc_html__('Buy for ', 'jws_streamvid') . wc_price($price); ?></span>
                                    </a>
                                <?php endif; ?>
                           
                        <?php endif; ?>

                        <?php if($rent_enable && !$has_rented): 
                            $rent_price = get_field('rent_price', $pmpro_id); 
                            $rent_day = get_field('rent_day', $pmpro_id); ?>
                           
                                <?php if($rent_price): ?>
                                    <a href="#" class="btn btn-primary jws-rent-video" data-type="rent" data-id="<?php echo esc_attr($pmpro_id); ?>">
                                        <span><?php echo esc_html__('Rent for ', 'jws_streamvid') . wc_price($rent_price); ?></span>
                                    </a>
                                <?php endif; ?>
                            
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <script>
        jQuery(function($){
            $('.jws-tabs li').on('click', function(){
                var tab = $(this).data('tab');
                $('.jws-tabs li').removeClass('active');
                $(this).addClass('active');
                $('.jws-tab-content').removeClass('active');
                $('.'+tab).addClass('active');
            });
        });
        </script>
        <?php
    } else {
     
        ?>
        <div style="background:url(<?php echo esc_url($image); ?>),#000000" class="videos-message">
            <div class="message-inner">
                <?php
                if(function_exists('pmpro_has_membership_access') && !pmpro_has_membership_access( $pmpro_id, get_current_user_id())) {
                    $post = get_post($pmpro_id); 
                    setup_postdata($post); 
                    echo jws_pmpro_no_access_message_html('', $pmpro_id, $level_names);
                    wp_reset_postdata(); 
                }
                ?>
                <?php
                if($buy_enable || $rent_enable) {
                    echo esc_html__('You can purchase access to this video.', 'jws_streamvid');
                } ?>
                <div class="pay-buttons">
                    <?php if($buy_enable && !$has_bought): 
                        $price = get_field('buy_price', $pmpro_id); ?>
                        
                            <?php if($price): ?>
                                <a href="#" class="btn btn-primary jws-buy-video" data-id="<?php echo esc_attr($pmpro_id); ?>">
                                    <span><?php echo esc_html__('Buy for ', 'jws_streamvid') . wc_price($price); ?></span>
                                </a>
                            <?php endif; ?>
                       
                    <?php endif; ?>

                    <?php if($rent_enable && !$has_rented): 
                        $rent_price = get_field('rent_price', $pmpro_id);
                        $rent_day = get_field('rent_day', $pmpro_id); ?>
                       
                            <?php if($rent_price): ?>
                                <a href="#" class="btn btn-primary jws-rent-video" data-type="rent" data-id="<?php echo esc_attr($pmpro_id); ?>">
                                    <span><?php echo esc_html__('Rent for ', 'jws_streamvid') . wc_price($rent_price); ?></span>
                                </a>
                            <?php endif; ?>
                       
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
} elseif($type == 'iframe' || $type == 'shortcode') {
    
    echo do_shortcode($video_url);
      
} elseif($is_affiliate) {
    
     $affiliate = '<div style="background:url('.esc_url($image).'),#000000" class="videos-affiliate">';
     $affiliate .= '<a target="_blank" href="'.esc_url($video_url).'"><i class="jws-icon-play-circle"></i></a>';   
     $affiliate .= '</div>'; 
       
     echo $affiliate;
    
} elseif( class_exists( 'Jws_Streamvid_Player_Engine' ) && Jws_Streamvid_Player_Engine::is_v10() ) {

    /* ------------------------------------------------------------------ *
     * Video.js 10 (@videojs/html web components).
     *
     * $setup has already been through the streamvid/player/setup filter, so
     * anything a site added there still applies. Only the markup differs:
     * v10 composes <video-player> > <video-skin> > a media element, and picks
     * the media element by source type rather than by a techOrder list.
     * ------------------------------------------------------------------ */

    $v10_source = isset( $setup['sources'][0] ) ? $setup['sources'][0] : array();
    $v10_src    = isset( $v10_source['src'] ) ? base64_decode( $v10_source['src'] ) : '';
    $v10_type   = isset( $v10_source['type'] ) ? $v10_source['type'] : 'video/mp4';

    /* v10 reads renditions off the HLS manifest, so a hand-built quality list
       has no menu to feed. Play the first entry — the one legacy marked default. */
    $v10_qualities = array();

    if ( ! empty( $v10_source['item_quality'] ) && is_array( $v10_source['item_quality'] ) ) {
        foreach ( $v10_source['item_quality'] as $quality ) {
            $v10_qualities[] = array(
                'src'   => base64_decode( $quality['url'] ),
                'label' => base64_decode( $quality['label'] ),
                'type'  => base64_decode( $quality['type'] ),
            );
        }
    }

    if ( ! empty( $v10_qualities ) ) {
        $v10_src  = $v10_qualities[0]['src'];
        $v10_type = $v10_qualities[0]['type'];
    }

    switch ( $v10_type ) {
        case 'video/youtube':
            $v10_tag    = 'youtube-video';
            $v10_module = 'media/youtube-video.js';
            break;
        case 'video/vimeo':
            $v10_tag    = 'vimeo-video';
            $v10_module = 'media/vimeo-video.js';
            break;
        case 'application/x-mpegURL':
        case 'application/vnd.apple.mpegurl':
            /* hlsjs-video, not hls-video: StreamVid's own encoder and the Bunny /
               Cloudflare outputs serve MPEG-TS segments, which only the hls.js
               engine plays. It still hands off to native HLS on Safari/iOS. */
            $v10_tag    = 'hlsjs-video';
            $v10_module = 'media/hlsjs-video.js';
            break;
        default:
            $v10_tag    = 'video';
            $v10_module = '';
    }

    $v10_config = array(
        'postId'      => $post_id,
        'src'         => $v10_src,
        'type'        => $v10_type,
        'mediaTag'    => $v10_tag,
        'mediaModule' => $v10_module,
        'poster'      => isset( $setup['poster'] ) ? $setup['poster'] : '',
        'logo'        => isset( $setup['logo']['url'] ) ? $setup['logo']['url'] : '',
        'autoplay'    => ! empty( $setup['autoplay'] ),
        'muted'       => ! empty( $setup['muted'] ),
        'currentTime' => isset( $setup['current_time'] ) && '' !== $setup['current_time'] ? (float) $setup['current_time'] : 0,
        'qualities'   => $v10_qualities,
        /* Surfaced so a site can tell that IMA never ran on this engine. */
        'adsTagUrl'   => isset( $setup['ads_tag_url'] ) ? $setup['ads_tag_url'] : '',
    );
    ?>

    <video-player id="videos_player"
            class="jws_player jws_player_v10"
            data-playerid="<?php echo esc_attr($post_id); ?>"
            data-jws-v10='<?php echo esc_attr( wp_json_encode( $v10_config ) ); ?>'
            <?php if ( ! empty( $v10_config['poster'] ) ) : ?>poster="<?php echo esc_url( $v10_config['poster'] ); ?>"<?php endif; ?>
        >
        <video-skin>
            <<?php echo esc_html( $v10_tag ); ?>
                src="<?php echo esc_url( $v10_src ); ?>"
                playsinline
                preload="auto"
                <?php echo $v10_config['autoplay'] ? 'autoplay' : ''; ?>
                <?php /* Autoplay implies muted: an unmuted autoplay is simply
                         refused by every current browser, and the legacy player
                         muted for the same reason. */ ?>
                <?php echo ( $v10_config['muted'] || $v10_config['autoplay'] ) ? 'muted' : ''; ?>
            >
                <?php
                if ( ! empty( $subtitles ) ) {

                    foreach ( $subtitles as $key => $subtitle ) {
                        $default = $key == '0' ? 'default' : '';
                        $url     = isset( $subtitle['vtt_file']['url'] ) ? $subtitle['vtt_file']['url'] : '';
                        if ( ! empty( $subtitle['vtt_url'] ) ) $url = $subtitle['vtt_url'];

                        echo ! empty( $url ) ? '<track label="'.esc_attr($subtitle['language']).'" kind="subtitles" srclang="'.esc_attr($subtitle['language']).'" src="'.esc_url($url).'" '.$default.' />' : '';
                    }

                }
                ?>
            </<?php echo esc_html( $v10_tag ); ?>>
            <?php if ( ! empty( $v10_config['poster'] ) ) : ?>
                <img slot="poster" src="<?php echo esc_url( $v10_config['poster'] ); ?>" alt="" />
            <?php endif; ?>
        </video-skin>
    </video-player>

    <?php
} else {
    ?>

    <video id="videos_player"
            class="jws_player video-js vjs-default-skin"
            data-playerid="<?php echo esc_attr($post_id); ?>"
            data-player='<?php echo esc_attr( wp_json_encode( $setup ) ); ?>'
            poster="<?php if(isset($setup['poster'])) echo esc_url( $setup['poster'] ); ?>"
        >
         <?php
         
         if(!empty($subtitles)) {
            
            foreach($subtitles as $key => $subtitle) {
                 $default = $key == '0' ? 'default' : ''; 
                 $url = isset($subtitle['vtt_file']['url']) ? $subtitle['vtt_file']['url'] : '';
                 if(!empty($subtitle['vtt_url'])) $url = $subtitle['vtt_url'];
                 
                 echo !empty($url) ? '<track label="'.esc_attr($subtitle['language']).'" kind="subtitles" srclang="'.esc_attr($subtitle['language']).'" src="'.esc_url($url).'" '.$default.' />' : '';
            }
            
         }        
        ?>
    </video>
    <div class="vjs-loading-spinner"></div>
    
    <?php
} ?>


</div>