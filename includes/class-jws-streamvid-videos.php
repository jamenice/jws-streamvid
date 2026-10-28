<?php
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
 * This class defines all code for videos
 *
 * @since      1.0.0
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes
 * @author     Jws Theme <jwstheme@gmail.com>
 */
class Jws_Streamvid_Videos {
    
    private $video_type;
   
    public function filter_videos($args) { 
        
        global $wp;
        $currentURL = home_url( $wp->request );
        
  
        $cat_parts = explode('/', $wp->request);
        
        $args = wp_parse_args( $args, array(
            'label' => false,
            'year' => false,
            'category' => false,
            'post_type' => 'tv_shows'
        ) );
        extract( $args );

        $option = array(
            'title' => esc_html__('Title','jws_streamvid'),
            'date' => esc_html__('Date','jws_streamvid'),
        );
        
        
        if($post_type != 'person') {
            
           $option['likes'] = esc_html__('Likes','jws_streamvid');
           $option['views'] = esc_html__('Views','jws_streamvid');
   
        }
        
        
        $option = apply_filters( 'streamvid_filter_sortby_field', $option);
       

        echo '<form method="get" action="'.esc_url($currentURL).'" class="post-select-filter jws-scrollbar-x fs-small">';
 
        ob_start(); 
        
        
        $taxonomy = $post_type.'_cat';

        $genrest_new = jws_theme_get_option( 'enable_new_genre_system' );

        if($genrest_new) {
            if($post_type == 'movies' || $post_type == 'tv_shows') {
                $taxonomy = 'genres';
            }
        }

        /* A post type with no `{type}_cat` of its own says so here rather than
           being special-cased in this method — short drama uses genres only. */
        $taxonomy = apply_filters( 'streamvid/filter/taxonomy', $taxonomy, $post_type );
       $category_slug = isset($_GET[$taxonomy]) ? sanitize_text_field($_GET[$taxonomy]) : '';
	
          if($category) {
                 
                 global $wpdb;
                 $term_ids_with_posts = $wpdb->get_col( $wpdb->prepare(
                    "SELECT DISTINCT tt.term_id
                     FROM {$wpdb->term_taxonomy} tt
                     INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
                     INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
                     WHERE tt.taxonomy = %s
                     AND p.post_type = %s
                     AND p.post_status = 'publish'",
                    $taxonomy, $post_type
                 ) );
                 $categories = ! empty( $term_ids_with_posts )
                    ? get_terms( array(
                        'taxonomy'   => $taxonomy,
                        'include'    => $term_ids_with_posts,
                        'hide_empty' => false,
                        'orderby'    => 'name',
                    ) )
                    : array();
                
              
                 echo '<div class="fild-item">';
                    echo $label ? '<label class="fs-small cl-heading">'.esc_html__('Category','jws_streamvid').'</label>' : '';
                    echo "<select class='cat_change' data-taxonomy='" . esc_attr($taxonomy) . "'>";
                    echo '<option value="" data-url="'.get_post_type_archive_link($post_type).'">'.esc_html__('Category','jws_streamvid').'</option>';
                    foreach($categories as $value) {
                        printf(
                            '<option value="%s" %s data-url="%s">%s</option>',
                            esc_attr( $value->slug ),
                            isset($category_slug) && $category_slug == $value->slug ? 'selected=selected' : '',
                            get_post_type_archive_link($post_type),
                            $value->name
                        );
                    }
                    echo '</select>';
                
                echo '</div>';
                
            } 
        
            if($year) {
                
              global $wpdb;

              $years = $wpdb->get_results( $wpdb->prepare( "
                SELECT DISTINCT  pm.meta_value FROM {$wpdb->postmeta} pm
                LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                WHERE pm.meta_key = '%s' 
                AND p.post_type = '%s'
              ", "videos_years" , $post_type
               ) );
            
                if(!empty($years)) {
                    sort($years);       
                    echo '<div class="fild-item">';
                        echo $label ? '<label class="fs-small cl-heading">'.esc_html__('Year','jws_streamvid').'</label>' : '';
                        echo '<select class="years" name="years">';
                        echo '<option value="">'.esc_html__('Year','jws_streamvid').'</option>';
                        foreach($years as $value) {
                            if(empty($value->meta_value)) continue;
                            printf(
                                '<option value="%s" %s>%s</option>',
                                 esc_attr( $value->meta_value ),
                                isset($_GET['years']) && $_GET['years'] == $value->meta_value ? 'selected=selected' : '',
                                $value->meta_value
                            );
                            
                            
                        }
                        echo '</select>';
                    echo '</div>';
                    
                }

            }
    
        echo '<div class="fild-item">';
            echo $label ? '<label class="fs-small cl-heading">'.esc_html__('Sort by','jws_streamvid').'</label>' : '';
            echo '<select class="sortby" name="sortby">';
            echo '<option value="">'.esc_html__('Sort by','jws_streamvid').'</option>';
            foreach($option as $value => $name) {
                
                printf(
                    '<option value="%s" %s>%s</option>',
                     esc_attr( $value ),
                    isset($_GET['sortby']) && $_GET['sortby'] == $value ? 'selected=selected' : '',
                    $name
                );
                
                
          }
            echo '</select>';
        echo '</div>';
        $content = ob_get_clean();
        
        echo apply_filters( 'streamvid_filter_archive_field', $content);
        echo jws_query_string_form_fields( null, array('sortby','years',$taxonomy), '', true ); 
        echo '</form>';
        
    }
    
    /** Nonce for every upload / edit / delete request. */
    private function verify_upload_request() {
        if ( ! is_user_logged_in() || ! check_ajax_referer( 'jws_upload_video', 'upload_nonce', false ) ) {
            wp_send_json_error( new WP_Error( 'expired', esc_html__( 'Your session has expired. Reload the page and try again.', 'jws_streamvid' ) ) );
        }
    }

    /**
     * Theme Options > Videos > Videos Upload: uploads turned on, and the member's level
     * is one that may upload (the same rule as the `no_upload` body class).
     */
    private function verify_can_upload() {

        $allowed = jws_theme_get_option( 'video_upload' );

        if ( $allowed && isset( jws_streamvid()->get()->live_videos ) ) {
            $allowed = jws_streamvid()->get()->live_videos->user_can_go_live();
        }

        if ( ! $allowed ) {
            wp_send_json_error( new WP_Error( 'no_permission', esc_html__( 'Your account cannot upload videos.', 'jws_streamvid' ) ) );
        }
    }

    /**
     * The "Video URL or Embed" field. Site admins keep whatever they paste.
     * Everyone else gets a URL, or embed HTML cut down to <iframe>: the player
     * prints the field as it is.
     */
    private function sanitize_video_source( $value ) {

        $value = trim( (string) wp_unslash( $value ) );

        if ( '' === $value || current_user_can( 'unfiltered_html' ) ) {
            return $value;
        }

        if ( false === strpos( $value, '<' ) ) {
            return esc_url_raw( strip_shortcodes( $value ) );
        }

        return wp_kses( $value, array(
            'iframe' => array(
                'src' => true, 'width' => true, 'height' => true, 'title' => true,
                'frameborder' => true, 'allow' => true, 'allowfullscreen' => true,
                'loading' => true, 'referrerpolicy' => true,
            ),
        ) );
    }

    /**
     * Duration of an uploaded file as "m:ss" or "h:mm:ss", the format of the
     * Duration field, or '' when it cannot be read.
     */
    private function read_duration( $attachment_id ) {

        $path = get_attached_file( $attachment_id );

        if ( ! $path || ! file_exists( $path ) ) {
            return '';
        }

        $meta = wp_read_video_metadata( $path );

        if ( ! empty( $meta['length_formatted'] ) ) {
            return $meta['length_formatted'];
        }

        $meta = wp_read_audio_metadata( $path );

        return ! empty( $meta['length_formatted'] ) ? $meta['length_formatted'] : '';
    }

    public function upload_video() {
        jws_return_data_demo();
        $this->verify_upload_request();

        $args = wp_parse_args( $_POST, array(
            'description'  =>  '',
            'name'  =>  '',
            'videos_type'  =>  'file',
            'videos_cat'  =>  '',
            'videos_tag'  =>  '',
            'videos_playlist' => '',
            'attachment_id'  => 0,
            'videos_url' => '',
            'post_id' => ''
        ) );

        $post_id         = (int) $args['post_id'];
        $attachment_id   = (int) $args['attachment_id'];
        $name            = sanitize_text_field( wp_unslash( $args['name'] ) );
        $description     = wp_kses_post( wp_unslash( $args['description'] ) );
        $videos_type     = in_array( $args['videos_type'], array_keys( $this->get_videos_type() ), true ) ? $args['videos_type'] : 'file';
        $videos_url      = $this->sanitize_video_source( $args['videos_url'] );
        $videos_cat      = is_array( $args['videos_cat'] ) ? array_map( 'intval', $args['videos_cat'] ) : array();
        $videos_playlist = is_array( $args['videos_playlist'] ) ? array_map( 'intval', $args['videos_playlist'] ) : array();
        $videos_tag      = is_array( $args['videos_tag'] ) ? array_map( 'sanitize_title', wp_unslash( $args['videos_tag'] ) ) : array();

        // Editing: only your own video. Creating: only if you may upload.
        if ( $post_id ) {
            if ( 'videos' !== get_post_type( $post_id ) || ! $this->_is_owner( $post_id ) ) {
                wp_send_json_error( new WP_Error( 'no_permission', esc_html__( 'You can only edit your own videos.', 'jws_streamvid' ) ) );
            }
        } else {
            $this->verify_can_upload();
        }

        // Only a file you uploaded yourself can become your video.
        if ( 'file' === $videos_type && $attachment_id
            && ( 'attachment' !== get_post_type( $attachment_id ) || (int) get_post_field( 'post_author', $attachment_id ) !== get_current_user_id() ) ) {
            wp_send_json_error( new WP_Error( 'file_not_yours', esc_html__( 'Upload the video file again.', 'jws_streamvid' ) ) );
        }

        $errors = new WP_Error();

        $images_thumbail = $this->add_videos_thmbnail($errors);

        if( empty($name) ){
			$errors->add(
				'name_empty',
				esc_html__( 'Video title is required.', 'jws_streamvid' )
			);
		}

        if( $errors->get_error_code() ){
             wp_send_json_error( $errors );
        }

        if( is_wp_error( $images_thumbail ) ){

			wp_send_json_error( $images_thumbail );

		}

        $post =  array(
			'post_title'	=>	$name,
			'post_content'	=>	$description,
			'post_type'		=>	'videos',
			'post_status'	=>	'publish'
		);

        if(empty($post_id)) {
           $post_id = wp_insert_post( $post , true );
        } else {
           // Edits used to keep the old title.
           $post['ID'] = $post_id;
           unset( $post['post_type'], $post['post_status'] );
           wp_update_post( $post );
        }


		if( is_wp_error( $post_id ) ){

		  wp_send_json_error( $post_id );

		}

        $is_live = class_exists( 'Jws_Livestream' ) && Jws_Livestream::is_live_post( $post_id );

        if ( $is_live ) {

            // A LiveKit stream has no file or URL; only its schedule changes.
            $start = '';

            if ( ! empty( $_POST['live_start_date'] ) ) {
                $value = sanitize_text_field( wp_unslash( $_POST['live_start_date'] ) );
                $date  = date_create_immutable_from_format( 'Y-m-d\TH:i', $value, wp_timezone() );
                $date  = $date ? $date : date_create_immutable_from_format( 'Y-m-d\TH:i:s', $value, wp_timezone() );
                $start = $date ? $date->format( 'Y-m-d H:i:s' ) : '';
            }

            if ( $start ) {
                update_post_meta( $post_id, 'live_start_date', $start );
            } else {
                delete_post_meta( $post_id, 'live_start_date' );
            }

        } else {

            update_post_meta( $post_id , 'videos_type'  , $videos_type );

            if($videos_type ==  'file') {

                $previous = (int) get_post_meta( $post_id, 'videos_file', true );

                update_post_meta( $post_id , 'videos_file'  , $attachment_id );

                // Fill Duration from the file, unless it is the same file and
                // a duration is already set.
                if ( $attachment_id && ( $previous !== $attachment_id || ! get_post_meta( $post_id, 'videos_time', true ) ) ) {
                    $duration = $this->read_duration( $attachment_id );
                    if ( $duration ) {
                        update_post_meta( $post_id, 'videos_time', $duration );
                    }
                }

            }elseif($videos_type == 'url' && !empty($videos_url)) {

              update_post_meta( $post_id , 'videos_url'  , $videos_url );

            }
        }

        if($images_thumbail == 'video_thumbnail') {

            $thumbnail_id = media_handle_upload( $images_thumbail , 0, array( '' ), array('test_form' => FALSE) );

            if( ! is_wp_error( $thumbnail_id ) ){
                set_post_thumbnail( $post_id, $thumbnail_id );
            }

        }

        wp_set_post_terms( $post_id, $videos_playlist, 'videos_playlist');

        wp_set_post_terms( $post_id, $videos_cat, 'videos_cat');

        wp_set_post_terms( $post_id, $videos_tag, 'videos_tag' );


        $liked = get_post_meta($post_id, 'likes', true);
        $views = get_post_meta($post_id, 'views', true);

        if (empty($views)) {
            update_post_meta( $post_id, 'views', 0 );
        }
        if (empty($liked)) {
            update_post_meta( $post_id, 'likes', 0 );
        }

        $message = empty( $args['post_id'] ) ? esc_html__( 'Your video has been published.', 'jws_streamvid' ) : esc_html__( 'Your changes have been saved.', 'jws_streamvid' );
        $output = get_the_permalink($post_id);
        wp_send_json_success(compact( 'output','message' ));

    }


   public function add_videos_thmbnail($errors) {

            if( isset( $_FILES[ 'video_thumbnail' ]['error'] ) && $_FILES[ 'video_thumbnail' ]['error'] == 0 ){
                 $file = $_FILES[ 'video_thumbnail' ];

                  if( $file['error'] != 0 ){
                    $errors->add(
                        'file_broken',
                        esc_html__( 'File was not found or broken', 'jws_streamvid' )
                    );
                }

                 $type = array_key_exists( 'type' , $file ) ? $file['type'] : '';
                 if ( 0 !== strpos( $type, 'image/' ) ) {
                    $errors->add(
                        'file_not_accepted',
                        esc_html__( 'File format is not accepted.', 'jws_streamvid' )
                    );
                }
                $max_size = (int)jws_get_max_upload_image_size();

                if( $file['size'] > $max_size ){
                    $errors->add(
                        'file_size_not_allowed',
                        sprintf(
                            esc_html__( 'File size has to be smaller than %s', 'jws_streamvid' ),
                            size_format( $max_size )
                        )
                    );
                }

                if( $errors->get_error_code() ){
                    return $errors;
                } else {
                    return 'video_thumbnail';
                }

            }

            return '';

   }

    public function add_media_videos() {
     jws_return_data_demo();
     $this->verify_upload_request();
     $this->verify_can_upload();

        $errors = new WP_Error();

        // Check size
		$allow_size = (int)jws_get_max_upload_image_size();

		if( ! isset( $_FILES['videos_file'] ) || (int)$_FILES['videos_file']['error'] != 0 ){
			wp_send_json_error( new WP_Error(
				'file_error',
				esc_html__( 'File was not found or empty.', 'jws_streamvid' )
			) );
		}

		if( $allow_size < (int)$_FILES['videos_file']['size'] ){
			$errors->add(
				'file_size_not_allowed',
				esc_html__( 'The upload file exceeds the maximum allow file size.', 'jws_streamvid' )
			);
		}

		$file_type = wp_check_filetype( $_FILES['videos_file']['name'] );

        $type = explode( '/' , (string) $file_type['type'] );

		if( ! $file_type['type'] || count( $type ) != 2 || ! in_array( $type[0], array( 'video', 'audio' )) ){
			$errors->add(
				'file_type_not_allowed',
				esc_html__( 'File Type is not allowed.', 'jws_streamvid' )
			);
		}

		if( $type[0] == 'video' && ! in_array( strtolower( $file_type['ext'] ) , wp_get_video_extensions() ) ){
			$errors->add(
				'video_format_not_allowed',
				esc_html__( 'Video Format is not allowed.', 'jws_streamvid' )
			);
		}

		if( $type[0] == 'audio' && ! in_array( $file_type['ext'] , wp_get_audio_extensions() ) ){
			$errors->add(
				'audio_format_not_allowed',
				esc_html__( 'Audio Format is not allowed.', 'jws_streamvid' )
			);
		}

        if( $errors->get_error_code() ){
		   wp_send_json_error( $errors );
		}


        $attachment_id = media_handle_upload( 'videos_file', 0, array( '' ), array('test_form' => FALSE) );

		if( is_wp_error( $attachment_id ) ){

			wp_send_json_error( $attachment_id );

		}

        $message = esc_html__( 'Video file uploaded.', 'jws_streamvid' );

         wp_send_json_success(compact( 'attachment_id','message' ));
   }


   public function get_taxonomy($slug) {


        $query = array(
            'taxonomy' => $slug,
            'hide_empty' => false,
        );

        if($slug == 'videos_playlist') {
            $query['meta_query'] = array(
                [
                    'key' => 'user',
                    'value' => get_current_user_id()
                ]
            );
        }


        $result = get_terms($query);

        return is_wp_error( $result ) ? array() : $result;

   }

    /* ---------------------------------------------------------------------- */
    /* Popup or page                                                           */
    /* ---------------------------------------------------------------------- */

    /** The theme's page template that renders the submit video form. */
    const UPLOAD_PAGE_TEMPLATE = 'page-upload-video.php';

    /**
     * The first published page using the "Upload Video" template, when Theme
     * Options > Videos > Videos Upload > Upload Video Mode is "Page". 0 means the popup
     * is used, including when no page has the template yet.
     */
    public function get_upload_page_id() {

        static $page_id = null;

        if ( null !== $page_id ) {
            return $page_id;
        }

        $page_id = 0;

        if ( jws_theme_get_option( 'video_upload_mode' ) != 'page' ) {
            return $page_id;
        }

        $pages = get_posts( array(
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'meta_key'       => '_wp_page_template',
            'meta_value'     => self::UPLOAD_PAGE_TEMPLATE,
        ) );

        if ( ! empty( $pages ) ) {
            $page_id = (int) $pages[0];
        }

        return $page_id;
    }

    public function get_upload_page_url() {
        $page_id = $this->get_upload_page_id();
        return $page_id ? get_permalink( $page_id ) : '';
    }

    /**
     * Attributes for an "Upload Video" link or button: the page in page mode,
     * the popup otherwise. Escaped, ready to print inside the tag.
     */
    public function upload_trigger_attrs() {
        $url = $this->get_upload_page_url();
        return $url ? 'href="' . esc_url( $url ) . '"' : 'href="#" data-modal-jws="#upload-videos"';
    }

    /** Body of the "Upload Video" page template (themes/streamvid/page-upload-video.php). */
    public function render_upload_page() {

        if ( ! jws_theme_get_option( 'video_upload' ) ) {

            echo '<div class="jws-live-page-message">' . esc_html__( 'Video upload is turned off.', 'jws_streamvid' ) . '</div>';

        } elseif ( ! is_user_logged_in() ) {

            printf(
                '<div class="jws-live-page-message">%s <a href="%s">%s</a></div>',
                esc_html__( 'Log in to upload a video.', 'jws_streamvid' ),
                esc_url( wp_login_url( get_permalink() ) ),
                esc_html__( 'Log in', 'jws_streamvid' )
            );

        } elseif ( isset( jws_streamvid()->get()->live_videos ) && ! jws_streamvid()->get()->live_videos->user_can_go_live() ) {

            // The same membership rule (Theme Options > Videos > Videos Upload > Choose
            // Level) covers live streaming and uploads.
            echo '<div class="jws-live-page-message">' . esc_html__( 'Your membership level does not include video upload.', 'jws_streamvid' ) . '</div>';

        } else {

            echo '<div class="user-popup jws-upload-page">';
            $this->form_upload( array( 'type' => 'create', 'in_page' => true ) );
            echo '</div>';

        }
    }

   /**
    * The submit popup (#upload-videos), the edit popup (#edit-videos, filled
    * by video_editor()) and the Upload Video page share this shell; the
    * fields are videos_form(). It uses the Go Live form's components
    * (go_live.css), hence the jws-golive class.
    */
   public function form_upload($args) {
       $args = wp_parse_args( $args, array(
            'type'    =>  'create',
            'in_page' =>  false,
       ) );

       $type    = $args['type'];
       $in_page = $args['in_page'];

       // Page mode: the form lives on the Upload Video page, so no hidden
       // popup copy. The edit popup is unaffected.
       if ( 'create' === $type && ! $in_page && $this->get_upload_page_id() ) {
           return;
       }

       $id_popup = $type == 'edit' ? 'edit-videos' : 'upload-videos';
       $classes  = array( 'jws-golive', 'jws-upload', $in_page ? 'jws-upload-create' : 'mfp-hide' );

       ?>

       <div id="<?php echo esc_attr($id_popup); ?>" class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">

         <div class="form-head golive-head">
            <?php if ( $type == 'edit' ) : ?>
                <h5 class="title"><?php echo esc_html__( 'Edit video', 'jws_streamvid' ); ?></h5>
                <p><?php echo esc_html__( 'Changes are saved when you click Save changes.', 'jws_streamvid' ); ?></p>
            <?php else : ?>
                <h5 class="title"><?php echo esc_html__( 'Upload a video', 'jws_streamvid' ); ?></h5>
                <p><?php echo esc_html__( 'Add your video, then give it a title and a thumbnail.', 'jws_streamvid' ); ?></p>
            <?php endif; ?>
        </div>
        <div class="form-body">
            <?php if($type == 'create') $this->videos_form($args); ?>
        </div>
        </div>

       <?php

    }

    /**
     * The video fields, for both creating and editing.
     *
     * Kept for upload_videos.js: form.form-videos, input[name="videos_file"]
     * (uploaded on select, its id stored in data-video), and every field name
     * upload_video() reads. The drop zones are <label for> their file input, so
     * the browser opens the file picker itself, with no script involved. The source not chosen disables its input, so the browser leaves
     * it out of the request.
     */
    public function videos_form($args){
       $args = wp_parse_args( $args, array(
            'type'    =>  'create',
            'id'      =>  '',
            'in_page' =>  false,
       ) );

        $type     = $args['type'];
        $in_page  = $args['in_page'];
        $id       = (int) $args['id'];
        $is_live  = $id && class_exists( 'Jws_Livestream' ) && Jws_Livestream::is_live_post( $id );
        $max_size = jws_get_max_upload_image_size();
        $max_mb   = number_format_i18n( ceil( $max_size / 1048576 ) );
        // The edit form arrives over AJAX, where wp_unique_id() starts again at 1 and
        // would repeat the ids of the upload popup already on the page.
        $uid      = $id ? 'jws-edit-' . $id : wp_unique_id( 'jws-up-' );

        $video_upload_file = jws_theme_get_option('video_up_file');
        $video_up_embed    = jws_theme_get_option('video_up_embed');
        $types             = $this->get_videos_type();

        $this->video_type = $video_upload_file ? 'file' : 'url';
        if ( $id ) {
            $saved_type = get_post_meta( $id, 'videos_type', true );
            if ( isset( $types[ $saved_type ] ) ) {
                $this->video_type = $saved_type;
            }
        }

        $title       = $id ? get_the_title( $id ) : '';
        $description = $id ? get_post_field( 'post_content', $id ) : '';
        $thumb_url   = $id ? get_the_post_thumbnail_url( $id, 'medium' ) : '';
        $video_id    = $id ? (int) get_post_meta( $id, 'videos_file', true ) : 0;
        $video_name  = $video_id ? basename( (string) get_attached_file( $video_id ) ) : '';

        // get_the_terms() is false for a video with none; foreach used to warn.
        $term_values = function ( $taxonomy, $field ) use ( $id ) {
            $terms = $id ? get_the_terms( $id, $taxonomy ) : false;
            return ( $terms && ! is_wp_error( $terms ) ) ? wp_list_pluck( $terms, $field ) : array();
        };

        $tags      = $this->get_taxonomy('videos_tag');
        $categorys = $this->get_taxonomy('videos_cat');
        $playlists = $this->get_taxonomy('videos_playlist');
        $tags_add  = $term_values( 'videos_tag', 'slug' );
        $cat_add   = $term_values( 'videos_cat', 'term_id' );
        $play_add  = $term_values( 'videos_playlist', 'term_id' );

        $chips = function ( $terms, $name, $selected ) {
            echo '<ul class="golive-chips">';
            foreach ( $terms as $term ) {
                printf(
                    '<li><label class="golive-chip"><input type="checkbox" name="%1$s[]" value="%2$s" %3$s><span>%4$s</span></label></li>',
                    esc_attr( $name ),
                    esc_attr( $term->term_id ),
                    checked( in_array( $term->term_id, $selected ), true, false ),
                    esc_html( $term->name )
                );
            }
            echo '</ul>';
        };

        ?>

            <form class="form-videos jws-upload-form" novalidate>

            <div class="golive-grid">

                <div class="golive-main">

                    <section class="golive-section">
                        <h6 class="golive-section__title"><?php esc_html_e( 'Details', 'jws_streamvid' ); ?></h6>

                        <div class="field-item golive-field">
                            <label for="<?php echo esc_attr( $uid ); ?>-name"><?php esc_html_e( 'Title', 'jws_streamvid' ); ?> <span class="golive-required">*</span></label>
                            <input type="text" id="<?php echo esc_attr( $uid ); ?>-name" class="upload-title" name="name" value="<?php echo esc_attr( $title ); ?>" maxlength="100" required
                                placeholder="<?php echo esc_attr__( 'A clear, searchable title', 'jws_streamvid' ); ?>" data-counter="1" />
                            <div class="golive-field__meta">
                                <span class="golive-error" role="alert"></span>
                                <span class="golive-count"><?php echo esc_html( mb_strlen( $title ) ); ?>/100</span>
                            </div>
                        </div>

                        <div class="field-item golive-field">
                            <label for="<?php echo esc_attr( $uid ); ?>-desc"><?php esc_html_e( 'Description', 'jws_streamvid' ); ?></label>
                            <textarea id="<?php echo esc_attr( $uid ); ?>-desc" name="description" rows="5" maxlength="5000" data-counter="1"
                                placeholder="<?php echo esc_attr__( 'What is this video about?', 'jws_streamvid' ); ?>"><?php echo esc_textarea( $description ); ?></textarea>
                            <div class="golive-field__meta">
                                <span></span>
                                <span class="golive-count"><?php echo esc_html( mb_strlen( $description ) ); ?>/5000</span>
                            </div>
                        </div>
                    </section>

                    <?php if ( ! $is_live ) : ?>

                    <section class="golive-section">
                        <h6 class="golive-section__title"><?php esc_html_e( 'Video', 'jws_streamvid' ); ?></h6>

                        <?php if ( count( $types ) > 1 ) : ?>
                        <div class="golive-options" role="radiogroup" aria-label="<?php esc_attr_e( 'Video source', 'jws_streamvid' ); ?>">
                            <label class="golive-option">
                                <input type="radio" name="videos_type" value="file" <?php checked( $this->video_type, 'file' ); ?> />
                                <span class="golive-option__box">
                                    <i class="jws-icon-upload"></i>
                                    <strong><?php esc_html_e( 'Upload a file', 'jws_streamvid' ); ?></strong>
                                    <small><?php echo esc_html( sprintf( __( 'From your device, max %sMB', 'jws_streamvid' ), $max_mb ) ); ?></small>
                                </span>
                            </label>
                            <label class="golive-option">
                                <input type="radio" name="videos_type" value="url" <?php checked( $this->video_type, 'url' ); ?> />
                                <span class="golive-option__box">
                                    <i class="jws-icon-link"></i>
                                    <strong><?php esc_html_e( 'Link or embed', 'jws_streamvid' ); ?></strong>
                                    <small><?php esc_html_e( 'YouTube, Vimeo, an .m3u8 or an iframe', 'jws_streamvid' ); ?></small>
                                </span>
                            </label>
                        </div>
                        <?php elseif ( $types ) : ?>
                            <input type="hidden" name="videos_type" value="<?php echo esc_attr( $this->video_type ); ?>" />
                        <?php endif; ?>

                        <?php if ( $video_upload_file ) : ?>
                        <div class="upload-panel" data-source="file" <?php if ( $this->video_type != 'file' ) echo 'hidden'; ?>>
                            <div class="golive-drop upload-drop upload-drop--video<?php if ( $video_name ) echo ' has-file'; ?>">
                                <input id="<?php echo esc_attr( $uid ); ?>-video" name="videos_file" type="file" accept="video/*" class="golive-drop__input" data-video="<?php echo esc_attr( $video_id ? $video_id : '' ); ?>">
                                <label for="<?php echo esc_attr( $uid ); ?>-video" class="golive-drop__zone">
                                    <i class="jws-icon-upload"></i>
                                    <strong class="upload-drop__title"><?php esc_html_e( 'Drag a video here or click to choose', 'jws_streamvid' ); ?></strong>
                                    <small><?php echo esc_html( sprintf( __( 'MP4, MOV or WebM, max %sMB. The upload starts right away.', 'jws_streamvid' ), $max_mb ) ); ?></small>
                                </label>
                                <?php if ( $video_name ) : ?>
                                    <div class="upload-filename"><?php echo esc_html( sprintf( __( 'Current file: %s. Choose another to replace it.', 'jws_streamvid' ), $video_name ) ); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ( $video_up_embed ) : ?>
                        <div class="field-item golive-field upload-panel" data-source="url" <?php if ( $this->video_type != 'url' ) echo 'hidden'; ?>>
                            <label for="video-url-<?php echo esc_attr( $uid ); ?>"><?php esc_html_e( 'Video URL or embed code', 'jws_streamvid' ); ?></label>
                            <textarea id="video-url-<?php echo esc_attr( $uid ); ?>" class="upload-url" name="videos_url" rows="3" <?php if ( $this->video_type != 'url' ) echo 'disabled'; ?>
                                placeholder="https://www.youtube.com/watch?v="><?php if ( $id ) echo esc_textarea( get_post_meta( $id, 'videos_url', true ) ); ?></textarea>
                            <div class="golive-field__meta"><span class="golive-error" role="alert"></span></div>
                        </div>
                        <?php endif; ?>
                    </section>

                    <?php else : ?>

                    <section class="golive-section">
                        <h6 class="golive-section__title"><?php esc_html_e( 'Schedule', 'jws_streamvid' ); ?></h6>
                        <?php $live_start_date = get_post_meta( $id, 'live_start_date', true ); ?>
                        <div class="field-item golive-field">
                            <label for="<?php echo esc_attr( $uid ); ?>-start"><?php esc_html_e( 'Start date and time', 'jws_streamvid' ); ?></label>
                            <input type="datetime-local" name="live_start_date" id="<?php echo esc_attr( $uid ); ?>-start" class="acf-live-date"
                                value="<?php echo $live_start_date ? esc_attr( str_replace( ' ', 'T', substr( $live_start_date, 0, 16 ) ) ) : ''; ?>" />
                            <div class="golive-field__meta"><span><?php esc_html_e( 'Leave empty to go live at any time.', 'jws_streamvid' ); ?></span></div>
                        </div>
                    </section>

                    <?php endif; ?>

                    <?php if ( ! empty( $categorys ) || ! empty( $playlists ) || ! empty( $tags ) ) : ?>
                    <section class="golive-section">
                        <h6 class="golive-section__title"><?php esc_html_e( 'Discovery', 'jws_streamvid' ); ?></h6>

                        <?php if ( ! empty( $categorys ) ) : ?>
                        <div class="field-item golive-field">
                            <label><?php esc_html_e( 'Categories', 'jws_streamvid' ); ?></label>
                            <?php $chips( $categorys, 'videos_cat', $cat_add ); ?>
                        </div>
                        <?php endif; ?>

                        <?php if ( ! empty( $playlists ) ) : ?>
                        <div class="field-item golive-field">
                            <label><?php esc_html_e( 'Add to playlists', 'jws_streamvid' ); ?></label>
                            <?php // Selected playlists used to never show as ticked when editing. ?>
                            <?php $chips( $playlists, 'videos_playlist', $play_add ); ?>
                        </div>
                        <?php endif; ?>

                        <?php if ( ! empty( $tags ) ) : ?>
                        <div class="field-item golive-field">
                            <label for="<?php echo esc_attr( $uid ); ?>-tags"><?php esc_html_e( 'Tags', 'jws_streamvid' ); ?></label>
                            <select multiple id="<?php echo esc_attr( $uid ); ?>-tags" name="videos_tag[]">
                                <?php foreach ( $tags as $tag ) : ?>
                                    <option value="<?php echo esc_attr( $tag->slug ); ?>" <?php selected( in_array( $tag->slug, $tags_add ) ); ?>><?php echo esc_html( $tag->name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                    </section>
                    <?php endif; ?>

                </div>

                <aside class="golive-side">

                    <section class="golive-section">
                        <h6 class="golive-section__title"><?php esc_html_e( 'Thumbnail', 'jws_streamvid' ); ?></h6>

                        <div class="golive-drop upload-drop upload-drop--thumb" data-max-size="<?php echo esc_attr( $max_size ); ?>">
                            <input id="<?php echo esc_attr( $uid ); ?>-thumb" name="video_thumbnail" type="file" accept=".jpg,.jpeg,.png,.gif,.bmp,.tiff,image/*" class="golive-drop__input">
                            <label for="<?php echo esc_attr( $uid ); ?>-thumb" class="golive-drop__zone" <?php if ( $thumb_url ) echo 'hidden'; ?>>
                                <i class="jws-icon-image"></i>
                                <strong><?php esc_html_e( 'Drag an image here or click to upload', 'jws_streamvid' ); ?></strong>
                                <small><?php echo esc_html( sprintf( __( 'JPG, PNG or GIF, 16:9, max %sMB', 'jws_streamvid' ), $max_mb ) ); ?></small>
                            </label>
                            <div class="golive-drop__preview" <?php if ( ! $thumb_url ) echo 'hidden'; ?>>
                                <img src="<?php echo esc_url( $thumb_url ); ?>" alt="" />
                                <div class="golive-drop__actions">
                                    <label for="<?php echo esc_attr( $uid ); ?>-thumb" class="golive-drop__btn upload-drop__change"><i class="jws-icon-upload"></i> <?php esc_html_e( 'Change', 'jws_streamvid' ); ?></label>
                                </div>
                            </div>
                            <span class="golive-error" role="alert"
                                data-type="<?php echo esc_attr__( 'Choose an image file.', 'jws_streamvid' ); ?>"
                                data-size="<?php echo esc_attr( sprintf( __( 'The image must be smaller than %sMB.', 'jws_streamvid' ), $max_mb ) ); ?>"></span>
                        </div>
                    </section>

                    <div class="golive-tips">
                        <h6><i class="jws-icon-info-light"></i> <?php esc_html_e( 'Tips', 'jws_streamvid' ); ?></h6>
                        <ul>
                            <li><?php esc_html_e( 'A 1280x720 thumbnail looks sharp everywhere.', 'jws_streamvid' ); ?></li>
                            <li><?php esc_html_e( 'The duration is read from the uploaded file.', 'jws_streamvid' ); ?></li>
                            <li><?php esc_html_e( 'Wait for the upload to finish before you publish.', 'jws_streamvid' ); ?></li>
                        </ul>
                    </div>

                </aside>

            </div>

            <div class="form-button golive-actions">
              <input name="action" type="hidden" value="upload_video" />
              <input name="post_id" type="hidden" value="<?php if ( $id ) echo esc_attr( $id ); ?>" />
              <?php wp_nonce_field( 'jws_upload_video', 'upload_nonce', false ); ?>
              <?php if ( $type == 'edit' ) : ?>
                  <a href="#" class="button-custom delete-videos golive-cancel"><span class="text"><?php esc_html_e( 'Delete video', 'jws_streamvid' ); ?></span></a>
              <?php elseif ( ! $in_page ) : ?>
                  <button type="button" class="button-default golive-cancel upload-cancel"><?php esc_html_e( 'Cancel', 'jws_streamvid' ); ?></button>
              <?php endif; ?>
              <button class="btn-main button-default golive-submit" type="submit"><i class="jws-icon-upload"></i> <span class="text"><?php echo $type == 'edit' ? esc_html__( 'Save changes', 'jws_streamvid' ) : esc_html__( 'Publish video', 'jws_streamvid' ); ?></span></button>
             </div>

             <span class="golive-error--form" hidden
                data-required="<?php echo esc_attr__( 'Enter a title.', 'jws_streamvid' ); ?>"
                data-file="<?php echo esc_attr__( 'Upload the video file first, or wait for the upload to finish.', 'jws_streamvid' ); ?>"
                data-url="<?php echo esc_attr__( 'Paste a video link or embed code.', 'jws_streamvid' ); ?>"></span>

            </form>

        <?php

     }

     public function get_videos_type(){

        $data = array();

        $video_upload_file = jws_theme_get_option('video_up_file');
        $video_up_embed = jws_theme_get_option('video_up_embed');

        if($video_upload_file) {
            $data['file']  =  esc_html__( 'Upload Video', 'jws_streamvid' );
        }
        if($video_up_embed) {
            $data['url']  =  esc_html__( 'Video URL or Embed', 'jws_streamvid' );
        }
        return $data;
    }


    public function video_editor(){

       $this->verify_upload_request();

       $id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;

       // The edit form carries the video's settings; owners only.
       if ( ! $id || 'videos' !== get_post_type( $id ) || ! $this->_is_owner( $id ) ) {
           wp_send_json_error( new WP_Error( 'no_permission', esc_html__( 'You can only edit your own videos.', 'jws_streamvid' ) ) );
       }

       ob_start();

       $this->videos_form( array(
            'type'   =>  'edit',
            'id'     =>  $id,
       ) );

       wp_send_json_success( ob_get_clean() );

    }


    public function delete_videos(){
      jws_return_data_demo();
      $this->verify_upload_request();

      $id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;

      if( ! $id || 'videos' !== get_post_type( $id ) || ! $this->_is_owner( $id ) ){
            wp_send_json_error( new WP_Error(
                'no_permission',
                esc_html__( 'You can only delete your own videos.', 'jws_streamvid' )
            ) );
      }

      if ( class_exists( 'Jws_Livestream' ) ) {
          Jws_Livestream::finish( $id );
      }

      $results = wp_delete_post( $id );

      if( ! $results ){

			wp_send_json_error( new WP_Error( 'delete_failed', esc_html__( 'The video could not be deleted.', 'jws_streamvid' ) ) );

	  }

      $message = esc_html__( 'Video deleted.', 'jws_streamvid' );

      wp_send_json_success( compact( 'message' ) );

    }

     public function _is_owner( $post_id = 0, $user_id = 0 ){

        $user_id = (int)$user_id;

        if( ! $user_id ){
            $user_id = get_current_user_id();
        }

        if( ! $user_id ){
            return false;
        }

        $author_id = get_post_field( 'post_author', $post_id );

        if( $author_id && $author_id == $user_id ){
            return true;
        }

        return false;
    }

}
