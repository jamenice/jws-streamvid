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
 * This class defines all code for live stream.
 *
 * The Go Live form creates a `videos` post with live_status "yes"; its host
 * goes live from the video page with LiveKit (includes/livestream/).
 *
 * @since      1.0.0
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes
 * @author     Jws Theme <jwstheme@gmail.com>
 */
class Jws_Streamvid_Live_Videos {


    /** The theme's page template that renders the create stream form. */
    const PAGE_TEMPLATE = 'page-go-live.php';

     /**
     *
     * Class contructor
     *
     * @since 1.0.0
     */
    public function __construct( $args = array() ){




    }


    /* ---------------------------------------------------------------------- */
    /* Popup or page                                                           */
    /* ---------------------------------------------------------------------- */

    /**
     * The first published page using the "Go Live" template, when Theme
     * Options > Videos > Videos Upload > Go Live Mode is "Page". 0 means the popup is
     * used, including when no page has the template yet.
     */
    public function get_create_page_id() {

        static $page_id = null;

        if( null !== $page_id ) {
            return $page_id;
        }

        $page_id = 0;

        if( jws_theme_get_option( 'video_live_mode' ) != 'page' ) {
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
            'meta_value'     => self::PAGE_TEMPLATE,
        ) );

        if( ! empty( $pages ) ) {
            $page_id = (int) $pages[0];
        }

        return $page_id;

    }

    public function get_create_page_url() {

        $page_id = $this->get_create_page_id();

        return $page_id ? get_permalink( $page_id ) : '';

    }

    /**
     * Attributes for a "Go live" link or button: the page in page mode, the
     * popup otherwise. Escaped, ready to print inside the tag.
     */
    public function create_trigger_attrs() {
        $url = $this->get_create_page_url();
        return $url ? 'href="' . esc_url( $url ) . '"' : 'href="#" data-modal-jws="#upload-videos-live"';
    }

    /**
     * Same rule as the `no_upload` body class: a member whose level is not in
     * Theme Options > Videos > Videos Upload > Choose Level cannot upload or go live.
     */
    public function user_can_go_live() {

        if( ! is_user_logged_in() ) {
            return false;
        }

        if( function_exists( 'pmpro_hasMembershipLevel' ) && pmpro_hasMembershipLevel() ) {

            $video_package = jws_theme_get_option( 'video_package' );
            $current_pack  = pmpro_getMembershipLevelForUser( get_current_user_id() );

            if( ! empty( $video_package ) && $current_pack && ! in_array( $current_pack->id, (array) $video_package ) ) {
                return false;
            }
        }

        return true;

    }

    /** Body of the "Go Live" page template (themes/streamvid/page-go-live.php). */
    public function render_create() {

        if( ! jws_theme_get_option( 'video_live' ) ) {

            echo '<div class="jws-live-page-message">'.esc_html__( 'Live streaming is turned off.', 'jws_streamvid' ).'</div>';

        } elseif( ! is_user_logged_in() ) {

            printf(
                '<div class="jws-live-page-message">%s <a href="%s">%s</a></div>',
                esc_html__( 'Log in to create a live stream.', 'jws_streamvid' ),
                esc_url( wp_login_url( get_permalink() ) ),
                esc_html__( 'Log in', 'jws_streamvid' )
            );

        } elseif( ! $this->user_can_go_live() ) {

            echo '<div class="jws-live-page-message">'.esc_html__( 'Your membership level does not include live streaming.', 'jws_streamvid' ).'</div>';

        } else {

            echo '<div class="user-popup jws-live-page">';
            $this->form_upload( true );
            echo '</div>';

        }

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
                    'value' => get_queried_object_id()
                ]
            );
        }

        $result = get_terms($query);

        return $result;

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

   }

    public function start_stream() {
        jws_return_data_demo();

        if ( ! check_ajax_referer( 'jws_upload_video', 'upload_nonce', false ) ) {
            wp_send_json_error( new WP_Error( 'expired', esc_html__( 'Your session has expired. Reload the page and try again.', 'jws_streamvid' ) ) );
        }

        if ( ! jws_theme_get_option( 'video_live' ) || ! $this->user_can_go_live() ) {
            wp_send_json_error( new WP_Error( 'no_permission', esc_html__( 'Your account cannot create live streams.', 'jws_streamvid' ) ) );
        }

        $name        = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
        $description = isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : '';
        $videos_cat  = isset( $_POST['videos_cat'] ) && is_array( $_POST['videos_cat'] ) ? array_map( 'intval', $_POST['videos_cat'] ) : array();
        $videos_tag  = isset( $_POST['videos_tag'] ) && is_array( $_POST['videos_tag'] ) ? array_map( 'sanitize_title', wp_unslash( $_POST['videos_tag'] ) ) : array();

        $errors = new WP_Error();


        $images_thumbail = $this->add_videos_thmbnail($errors);

        if( empty($name) ){
			$errors->add(
				'name_empty',
				esc_html__( 'Video title is required.', 'jws_streamvid' )
			);
		}

        // datetime-local, wall-clock time in the site's timezone.
        $start_date = '';

        if ( ! empty( $_POST['live_start_date'] ) ) {

            $value = sanitize_text_field( wp_unslash( $_POST['live_start_date'] ) );
            $start = date_create_immutable_from_format( 'Y-m-d\TH:i', $value, wp_timezone() );
            $start = $start ? $start : date_create_immutable_from_format( 'Y-m-d\TH:i:s', $value, wp_timezone() );

            if ( ! $start ) {
                $errors->add( 'date_invalid', esc_html__( 'Choose a valid start date and time.', 'jws_streamvid' ) );
            } elseif ( $start->getTimestamp() > time() ) {
                $start_date = $start->format( 'Y-m-d H:i:s' );
            }
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
			'post_status'	=>	'publish',
		);

        $post_id = wp_insert_post( $post , true );

		if( is_wp_error( $post_id ) ){

		  wp_send_json_error( $post_id );

		}

        update_post_meta( $post_id, 'live_status', 'yes' );

        if ( $start_date ) {
            update_post_meta( $post_id, 'live_start_date', $start_date );
        }


        if($images_thumbail == 'video_thumbnail') {

            $attachment_id = media_handle_upload( $images_thumbail , 0, array( '' ), array('test_form' => FALSE) );

            if( ! is_wp_error( $attachment_id ) ){
                set_post_thumbnail( $post_id, $attachment_id );
            }


        }

        if( ! empty( $videos_cat ) ) {

           wp_set_post_terms( $post_id, $videos_cat, 'videos_cat');

        }

        if( ! empty( $videos_tag ) ) {

           wp_set_post_terms( $post_id, $videos_tag, 'videos_tag' );

        }

        update_post_meta( $post_id, 'views', 0 );
        update_post_meta( $post_id, 'likes', 0 );


        $message = esc_html__( 'Your live stream has been created.', 'jws_streamvid' );
        $output = get_the_permalink($post_id);
        wp_send_json_success(compact( 'output','message' ));

    }

    /**
     * The create stream form: the "Go live" popup, or the body of the Go Live
     * page template when $in_page is true.
     *
     * Sent by the generic form.form-videos handler in upload_videos.js; the
     * interaction (counters, schedule reveal, thumbnail, validation) is
     * go_live.js. "Start now" disables the date input instead of clearing it,
     * so the browser leaves live_start_date out of the request.
     */
    public function form_upload( $in_page = false ) {

        // Page mode: the form lives on the Go Live page, so no hidden popup copy.
        if( ! $in_page && $this->get_create_page_id() ) {
            return;
        }

        $max_size    = jws_get_max_upload_image_size();
        $max_size_mb = number_format_i18n( ceil( $max_size / 1048576 ) );
        $tags        = $this->get_taxonomy( 'videos_tag' );
        $categorys   = $this->get_taxonomy( 'videos_cat' );

        $classes = array( 'jws-golive', $in_page ? 'jws-live-create' : 'mfp-hide' );
       ?>

        <div id="upload-videos-live" class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">

         <div class="form-head golive-head">

            <span class="golive-badge"><span class="golive-dot"></span><?php echo esc_html__( 'Live', 'jws_streamvid' ); ?></span>

            <h5 class="title"><?php echo esc_html__( 'Create a live stream', 'jws_streamvid' ); ?></h5>

            <p><?php echo esc_html__( 'Fill in the details below. On the next screen you go live from your webcam or connect a streaming app such as OBS.', 'jws_streamvid' ); ?></p>

            <ol class="golive-steps">
                <li class="is-active"><span>1</span><?php echo esc_html__( 'Stream details', 'jws_streamvid' ); ?></li>
                <li><span>2</span><?php echo esc_html__( 'Go live', 'jws_streamvid' ); ?></li>
            </ol>

        </div>
         <div class="form-body">

            <form class="form-videos golive-form" novalidate>

            <div class="golive-grid">

                <div class="golive-main">

                    <section class="golive-section">

                        <h6 class="golive-section__title"><?php echo esc_html__( 'Basic info', 'jws_streamvid' ); ?></h6>

                        <div class="field-item golive-field">
                            <label for="golive-name"><?php echo esc_html__( 'Title', 'jws_streamvid' ); ?> <span class="golive-required">*</span></label>
                            <input type="text" id="golive-name" name="name" value="" maxlength="100" required
                                placeholder="<?php echo esc_attr__( 'Give your stream a clear title', 'jws_streamvid' ); ?>"
                                data-counter="#golive-name-count" aria-describedby="golive-name-error" />
                            <div class="golive-field__meta">
                                <span class="golive-error" id="golive-name-error" role="alert"></span>
                                <span class="golive-count" id="golive-name-count">0/100</span>
                            </div>
                        </div>

                        <div class="field-item golive-field">
                            <label for="golive-description"><?php echo esc_html__( 'Description', 'jws_streamvid' ); ?></label>
                            <textarea id="golive-description" name="description" rows="4" maxlength="5000"
                                placeholder="<?php echo esc_attr__( 'Tell viewers what your stream is about', 'jws_streamvid' ); ?>"
                                data-counter="#golive-description-count"></textarea>
                            <div class="golive-field__meta">
                                <span></span>
                                <span class="golive-count" id="golive-description-count">0/5000</span>
                            </div>
                        </div>

                    </section>

                    <section class="golive-section">

                        <h6 class="golive-section__title"><?php echo esc_html__( 'Schedule', 'jws_streamvid' ); ?></h6>

                        <div class="golive-options" role="radiogroup" aria-label="<?php echo esc_attr__( 'Schedule', 'jws_streamvid' ); ?>">
                            <label class="golive-option">
                                <input type="radio" name="golive_when" value="now" checked data-toggle-target="#golive-schedule" data-toggle-show="0" />
                                <span class="golive-option__box">
                                    <i class="jws-icon-video-camera"></i>
                                    <strong><?php echo esc_html__( 'Start now', 'jws_streamvid' ); ?></strong>
                                    <small><?php echo esc_html__( 'Go live as soon as it is created', 'jws_streamvid' ); ?></small>
                                </span>
                            </label>
                            <label class="golive-option">
                                <input type="radio" name="golive_when" value="later" data-toggle-target="#golive-schedule" data-toggle-show="1" />
                                <span class="golive-option__box">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                    <strong><?php echo esc_html__( 'Schedule', 'jws_streamvid' ); ?></strong>
                                    <small><?php echo esc_html__( 'Pick a date and time', 'jws_streamvid' ); ?></small>
                                </span>
                            </label>
                        </div>

                        <div class="field-item golive-field golive-reveal" id="golive-schedule" hidden>
                            <label for="golive-start-date"><?php echo esc_html__( 'Start date and time', 'jws_streamvid' ); ?></label>
                            <input type="datetime-local" name="live_start_date" id="golive-start-date" value="" class="acf-live-date" disabled
                                aria-describedby="golive-start-date-error" />
                            <div class="golive-field__meta">
                                <span class="golive-error" id="golive-start-date-error" role="alert"></span>
                            </div>
                        </div>

                    </section>

                    <?php if( ! empty( $categorys ) || ! empty( $tags ) ) : ?>
                    <section class="golive-section">

                        <h6 class="golive-section__title"><?php echo esc_html__( 'Discovery', 'jws_streamvid' ); ?></h6>

                        <?php if( ! empty( $categorys ) ) : ?>
                        <div class="field-item golive-field">
                            <label><?php echo esc_html__( 'Categories', 'jws_streamvid' ); ?></label>
                            <ul class="golive-chips">
                            <?php
                            foreach( $categorys as $category ) {
                                printf(
                                    '<li><label class="golive-chip"><input type="checkbox" name="videos_cat[]" value="%1$s"><span>%2$s</span></label></li>',
                                    esc_attr( $category->term_id ),
                                    esc_html( $category->name )
                                );
                            }
                            ?>
                            </ul>
                        </div>
                        <?php endif; ?>

                        <?php if( ! empty( $tags ) ) : ?>
                        <div class="field-item golive-field">
                            <label for="golive-tags"><?php echo esc_html__( 'Tags', 'jws_streamvid' ); ?></label>
                            <select multiple id="golive-tags" name="videos_tag[]" data-placeholder="<?php echo esc_attr__( 'Choose tags', 'jws_streamvid' ); ?>">
                                <?php
                                foreach( $tags as $tag ) {
                                    echo '<option value="'.esc_attr( $tag->slug ).'">'.esc_html( $tag->name ).'</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <?php endif; ?>

                    </section>
                    <?php endif; ?>

                </div>

                <aside class="golive-side">

                    <section class="golive-section">

                        <h6 class="golive-section__title"><?php echo esc_html__( 'Thumbnail', 'jws_streamvid' ); ?></h6>

                        <div class="golive-drop" data-max-size="<?php echo esc_attr( $max_size ); ?>">

                            <input name="video_thumbnail" type="file" accept=".jpg,.jpeg,.png,.gif,.bmp,.tiff,image/*" id="golive-thumbnail" class="golive-drop__input"
                                aria-describedby="golive-thumbnail-note golive-thumbnail-error" />

                            <label for="golive-thumbnail" class="golive-drop__zone">
                                <i class="jws-icon-image"></i>
                                <strong><?php echo esc_html__( 'Drag an image here or click to upload', 'jws_streamvid' ); ?></strong>
                                <small id="golive-thumbnail-note">
                                    <?php
                                    printf(
                                        /* translators: %s: maximum file size in MB */
                                        esc_html__( 'JPG, PNG or GIF, 16:9 recommended, max %sMB', 'jws_streamvid' ),
                                        esc_html( $max_size_mb )
                                    );
                                    ?>
                                </small>
                            </label>

                            <div class="golive-drop__preview" hidden>
                                <img src="" alt="" />
                                <div class="golive-drop__actions">
                                    <label for="golive-thumbnail" class="golive-drop__btn"><i class="jws-icon-upload"></i> <?php echo esc_html__( 'Change', 'jws_streamvid' ); ?></label>
                                    <button type="button" class="golive-drop__btn golive-drop__remove"><i class="jws-icon-trash"></i> <?php echo esc_html__( 'Remove', 'jws_streamvid' ); ?></button>
                                </div>
                            </div>

                            <span class="golive-error" id="golive-thumbnail-error" role="alert"
                                data-type="<?php echo esc_attr__( 'Choose an image file.', 'jws_streamvid' ); ?>"
                                data-size="<?php echo esc_attr( sprintf( __( 'The image must be smaller than %sMB.', 'jws_streamvid' ), $max_size_mb ) ); ?>"></span>

                        </div>

                    </section>

                    <div class="golive-tips">
                        <h6><i class="jws-icon-info-light"></i> <?php echo esc_html__( 'Before you go live', 'jws_streamvid' ); ?></h6>
                        <ul>
                            <li><?php echo esc_html__( 'Use a stable internet connection (5 Mbps upload or more).', 'jws_streamvid' ); ?></li>
                            <li><?php echo esc_html__( 'A clear title and thumbnail help viewers find you.', 'jws_streamvid' ); ?></li>
                            <li><?php echo esc_html__( 'You can start from your webcam or a streaming app on the next screen.', 'jws_streamvid' ); ?></li>
                        </ul>
                    </div>

                </aside>

            </div>

             <div class="form-button golive-actions">
              <input name="action" type="hidden" value="upload_video_live" />
              <?php wp_nonce_field( 'jws_upload_video', 'upload_nonce', false ); ?>
              <?php if( ! $in_page ) : ?>
                <button type="button" class="button-default golive-cancel"><?php echo esc_html__( 'Cancel', 'jws_streamvid' ); ?></button>
              <?php endif; ?>
              <button class="btn-main button-default golive-submit" type="submit"><i class="jws-icon-video-camera"></i> <span class="text"><?php echo esc_html__( 'Create Stream', 'jws_streamvid' ); ?></span></button>
            </div>

            <span class="golive-error golive-error--form" role="alert"
                data-required="<?php echo esc_attr__( 'Enter a title for your stream.', 'jws_streamvid' ); ?>"
                data-date="<?php echo esc_attr__( 'Choose a time in the future.', 'jws_streamvid' ); ?>"></span>

            </form>
         </div>

        </div>

       <?php

    }

    /**
     * @deprecated The Cloudflare live box is gone. Kept empty so a theme copy
     *             that still calls it (post-live-stream.php) keeps working.
     */
    public function stream_frontend() {}
}
