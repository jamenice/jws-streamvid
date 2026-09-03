<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}
/**
 * Fired during plugin activation
 *
 * @link       https://jwsuperthemes.com
 * @since      1.0.0
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class use fo function advertising.
 *
 * @since      1.0.0
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes
 * @author     Jws Theme <jwstheme@gmail.com>
 */
class Jws_Streamvid_Advertising {

	public function __construct( ){
      
	}
    
    public function template_redirect(){
        
        if ( is_singular( 'advertising' ) ){ 
            
            $this->load_template();
            exit;
        }
        
        if ( is_singular( 'adsvmap' ) ){ 
            
            $this->load_template_vmap();
            exit;
        }
        
    }
    
    private function check_tag_url($tag_id) { 
        
        $ads_server = get_post_meta($tag_id,'ads_server',true);
        
        if($ads_server == 'vast' ) {
            $tag_url = get_post_meta($tag_id,'ads_vast_url',true);;
        }else {
            $tag_url = get_the_permalink($tag_id);
        }
        
        return $tag_url;
        
        
    }
    
    private function load_template_vmap() {
        
        $post_id = get_the_ID();
        
        /*
         * Arrays, not false: the loops below append with
         * $args['preroll']['ads_tag'][] = …, and appending to false is an
         * "automatic conversion" PHP 8.1 deprecates. That notice is printed
         * before the VMAP's own output, which leaves stray HTML in front of
         * the XML declaration and makes the whole document unparseable to the
         * IMA SDK — the ad break is then silently lost.
         *
         * vmap.php tests these with if($preroll), and an empty array is falsy
         * exactly as false was, so nothing downstream changes.
         */
        $args = array(
        'vmap_id'    =>  $post_id,
        'preroll'  => array(),
        'midroll'  => array(),
        'postroll' => array(),
        );
        
        
        $preroll = get_field('preroll');
        $midroll = get_field('midroll');
        $postroll = get_field('postroll');
        
        if(!empty($preroll)) {
            foreach ($preroll as $tag_pre) {
          
                $args['preroll']['ads_tag'][] = $this->check_tag_url($tag_pre['ads_tag']);
                
            }
        }
        
        if(!empty($midroll)) {
            foreach ($midroll as $tag_pre) {
                $args['midroll']['ads_tag'][] = $this->check_tag_url($tag_pre['ads_tag_mid']);
                $args['midroll']['ads_time_offset'][] = $tag_pre['time_offset'];
            }
        }
        
        if(!empty($postroll)) {
            foreach ($postroll as $tag_pre) {
                $args['postroll']['ads_tag'][] = $this->check_tag_url($tag_pre['ads_tag_end']);
            }
        }
 
        jws_streamvid_load_template('advertising/vmap.php',true,$args);
        
    }
    
    private function load_template(){  
        
        $post_id = get_the_ID();

        $ads_type = get_post_meta($post_id,'ads_type',true);
        $ads_server = get_post_meta($post_id,'ads_server',true);
        
        $args = array(
            'ads_id'    =>  $post_id,
            'ads_title' =>  get_the_title(),
            'ads_system'             =>  get_bloginfo( 'name' ),
            'ads_description'             =>  '',
            'ads_target_url'         =>  get_post_meta($post_id,'ads_target_url',true),
            'ads_server'               => $ads_server,
            'ads_type'               =>  $ads_type,
            'ads_duration'           =>  get_post_meta($post_id,'ads_duration',true),
            'ads_video'        =>  get_post_meta($post_id,'ads_video',true),
            'ads_video_source' =>  get_post_meta($post_id,'ads_video_source',true),
            'ads_video_url'    =>  get_post_meta($post_id,'ads_video_url',true),
            'ads_skippable'         =>  get_post_meta($post_id,'ads_skippable',true),
            'ads_banner_image'         =>  get_field('ads_banner'),
            'scripts_url' =>  JWS_STREAMVID_URL_PUBLIC_ASSETS . '/js/vendor/VpaidNonLinear.js'
        );
        
       if($ads_server == 'vast')  {
          
          $args['ads_vast_url'] = get_post_meta($post_id,'ads_vast_url',true);
          jws_streamvid_load_template('advertising/vast_server.php',true,$args);
        
       } else {
          
          jws_streamvid_load_template('advertising/layout/'.$ads_type.'.php',true,$args);
        
       }

    }
    
    public function query_vmap($setup , $id){  

        $post_type = get_post_type($id);
    
        if($post_type == 'episodes') {
            
           $post_type = 'tv_shows'; 
           $id = jws_episodes_check_type($id);
           
        }
        
        
        
        
        $ads_special = get_post_meta($id,'videos_ads_special',true);
        
        $cat_slug = $post_type.'_cat';
        $ads_option = 'ads_'.$post_type;
      
        $terms = wp_get_post_terms($id, $cat_slug);
        
        foreach($terms as $term) {
            
            $cat_ct[] = $term->slug;
            
        }
        
        $movies = jws_theme_get_option($ads_option);
 
        if(isset($movies['choose-ads-'.$post_type.''])) {
            foreach( $movies['choose-ads-'.$post_type.''] as $key => $value ) {
                 if(isset($movies['ads_'.$post_type.'_query_cat'][$key]) && !empty($movies['ads_'.$post_type.'_query_cat'][$key]) && !empty( $terms ) && ! is_wp_error( $terms )) {
                      $bFound = (count(array_intersect($cat_ct, $movies['ads_'.$post_type.'_query_cat'][$key]))) ? true : false;
                      if($bFound) {
                        $ads_id = $value;  
                      }
                    
                 }  else {
                    
                    $ads_id = $value;
                    
                 }
                 
            }
        }
        
       
        if(!empty($ads_special)) {
            
            $ads_id = $ads_special;
            
        }
         
        
        if(!empty($ads_id)) {
            
            return array_merge( $setup, array(
                'ads_tag_url'   =>  get_the_permalink($ads_id)
            ) );
            
        }
        
        return $setup;
        
        
    }

    
}
