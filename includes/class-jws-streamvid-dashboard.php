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
 * This class defines all code for dashboard
 *
 * @since      1.0.0
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes
 * @author     Jws Theme <jwstheme@gmail.com>
 */
class Jws_Streamvid_Dashboard {
    
    
   
    
    
    public function account_security(){  
        
        
        $field = array(
         'user_login',
         'user_email',
         'user_phone',
         'user_pass',
        )
        
        ?>
        
        <h6><?php echo esc_html__('Account and security','jws_streamvid'); ?></h6>
        <div class="setting-security">
        <?php 
        
        
        $form = 'data-modal-jws="#edit-profile"';
        $phone = get_user_meta( get_current_user_id(), 'user_phone', true );
        
        foreach($field as $id) {
            switch($id) {
                case 'user_login':
                
                    $label = esc_html__('Display name:', 'jws_streamvid');
                    $value = wp_get_current_user()->display_name;
                    $edit = esc_html__('Change','jws_streamvid');
                    
                    break;
                case 'user_email':
                
                    $label = esc_html__('Email:', 'jws_streamvid');
                    $value = wp_get_current_user()->user_email;
                    $edit = esc_html__('Change','jws_streamvid');
                    
                    break;
                case 'user_phone':
                
                    $label = esc_html__('Phone:', 'jws_streamvid');
                    $phone = get_user_meta( get_current_user_id(), 'user_phone', true );
                    $value = !empty($phone) ? $phone : esc_html__('not update', 'jws_streamvid');
                    $edit = !empty($phone) ? esc_html__('Change', 'jws_streamvid') : esc_html__('Add', 'jws_streamvid');
                    
                    break;
                case 'user_pass':
                
                    $label = esc_html__('Password:', 'jws_streamvid');
                    $value = '******';
                    $edit = esc_html__('Change','jws_streamvid');
                    
                    break;
            }
            
            printf(
                '<div class="field-user"><label>%s</label>%s<button %s>%s</button></div>',
                $label,
                $value,
                $form,
                $edit
            );
        }
        
        ?> 
        </div>
        <?php
        
    }
    
    
    public function save_profile(){  
        
            jws_return_data_demo(); 
            $errors = new WP_Error();  
            $args = wp_parse_args( $_POST, array(
                'description'  =>  '',
                'name'  =>  '',
                'video_type'  =>  '',
                'videos_cat'  =>  '',
                'videos_tag'  =>  '',
                'videos_playlist' => '',
                'attachment_id'  => 0,
                'video_url' => '',
                'video_thumbnail' => ''
            ) );
       

            if ( ! is_user_logged_in() ) {
                $errors->add(
                        'not_login',
                        esc_html__( 'You need to login to use this feature.', 'jws_streamvid' )
                );
            }
            
            if ( ! check_ajax_referer( 'edit_profile_nonce', 'edit_profile_nonce', false ) ) {
                $errors->add(
                        'invalid_nonce',
                        esc_html__( 'Invalid nonce.', 'jws_streamvid' )
                );
            }
        
  
            $display_name = sanitize_text_field( $_POST['display_name'] );
            $user_email = sanitize_email( $_POST['user_email'] );
            $user_phone = sanitize_text_field( $_POST['user_phone'] );
            $user_pass = $_POST['user_pass'];
            $user_confirm_pass = $_POST['user_confirm_pass'];
            
            
            if ( empty( $display_name ) || empty( $user_email ) || empty( $user_phone ) ) {
                 $errors->add(
                        'miss_info',
                        esc_html__( 'Please enter full information.', 'jws_streamvid' )
                );
            }
            
            if ( ! empty( $user_pass ) ) {
                if ( $user_pass !== $user_confirm_pass ) {
                    $errors->add(
                        'pass_match',
                        esc_html__( 'Password does not match.', 'jws_streamvid' )
                    );
                }
            }
            
            if( $errors->get_error_code() ){
                wp_send_json_error( $errors );
            } 
            
        
            $user_id = get_current_user_id();
            $user_data = array(
                'ID' => $user_id,
                'user_email' => $user_email,
                'display_name' => $display_name,
            );
            
            
            $result = wp_update_user( $user_data );
 
        
            if ( is_wp_error( $result ) ) {
                wp_send_json_error( $result );
            }
            
            update_user_meta( $user_id, 'user_phone', $user_phone );
            
            if ( ! empty( $user_pass ) ) {
                wp_set_password( $user_pass, $user_id );
            }
            
            
            $message = esc_html__( 'Your information has been updated', 'jws_streamvid' );
            
            
            wp_send_json_success( compact( 'message' ) );
        
    }
    
    
    public function save_personal(){  
            
            jws_return_data_demo(); 
            
            $errors = new WP_Error();  
            
            if ( ! is_user_logged_in() ) {
                $errors->add(
                        'not_login',
                        esc_html__( 'You need to login to use this feature.', 'jws_streamvid' )
                );
            }
   
            if ( ! check_ajax_referer( 'edit_personal_nonce', 'edit_personal_nonce', false ) ) {
                $errors->add(
                        'invalid_nonce',
                        esc_html__( 'Invalid nonce.', 'jws_streamvid' )
                );
            }
        
  
            $display_name = sanitize_text_field( $_POST['display_name'] );
            $jws_date_of_birth = sanitize_text_field( $_POST['jws_date_of_birth'] );
            $jws_gender = sanitize_text_field( $_POST['jws_gender'] );
            $jws_postcode = sanitize_text_field( $_POST['jws_postcode'] );
       
        
            if ( empty( $display_name ) ) {
                 $errors->add(
                        'miss_info',
                        esc_html__( 'Please enter full information.', 'jws_streamvid' )
                );
            }
            
             if( $errors->get_error_code() ){
                wp_send_json_error( $errors );
            } 
            
        
        
            $user_id = get_current_user_id();
            
            $user_data = array(
                'ID' => $user_id,
                'display_name' => $display_name,
            );
            
            
            $result = wp_update_user( $user_data );
 
        
             if( $errors->get_error_code() ){
                wp_send_json_error( $errors );
            } 
            
            
            update_user_meta( $user_id, 'jws_date_of_birth', $jws_date_of_birth );
            update_user_meta( $user_id, 'jws_gender', $jws_gender );
            update_user_meta( $user_id, 'jws_postcode', $jws_postcode );
            
            
          if( isset( $_FILES[ 'user_avatar' ]['error'] ) && $_FILES[ 'user_avatar' ]['error'] == 0 ){ 
                 $file = $_FILES[ 'user_avatar' ];
                 
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
                $max_size = jws_get_max_upload_image_size();
                    
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
                } 
                      
                $attachment_id = media_handle_upload( 'user_avatar', 0, array( '' ), array('test_form' => FALSE) );
                
                                                
          
                if( ! is_wp_error( $attachment_id ) ){
             
                   update_user_meta( $user_id, 'mm_sua_attachment_id', $attachment_id );
                }
      
            }
            
  
            $message = esc_html__( 'Your information has been updated', 'jws_streamvid' );
            
            
            wp_send_json_success( compact( 'message' ) );
        
    }
    
    public function get_gender(){ 
        
       return jws_get_gender();
        
    }
    
    
    public function form_personal(){   
        
        
        ?>
        
        <div id="edit-personal" class="mfp-hide">
            <div class="form-head">
            
                <h5 class="title">
                    <?php 
                      
                      echo esc_html__('Personal information','jws_streamvid'); 
                        
                    ?>
                </h5>

            </div>
            <div class="form-body">
              <form class="form-edit-personal">  
       
                
                 <div class="field-item">
                        <div class="user-avatar">
                            <?php  printf(
                                '%s',
                                get_avatar( get_current_user_id() , 96, null, null, array(
                                    'class' =>  'img-thumbnail avatar'
                                ) )
                            );?>
                             <label class="change-avatar" for="user_avatar"><i class="jws-icon-pencil-line"></i></label>
                        </div>
                        <input id="user_avatar" type="file" name="user_avatar" accept=".jpg,.jpeg,.png,.gif,.bmp,.tiff">
                       
                    </div>
                 
                
                 <p class="field-item">
                
                    <label for="display_name"><?php echo esc_html__('Display Name','jws_streamvid'); ?>  *</label>
                    <input type="text" id="display_name" name="display_name"  value="<?php echo esc_attr( wp_get_current_user()->display_name ); ?>" required/>
                
                </p>
                
                
                <p class="field-item">
                <?php 
                
                $date = get_user_meta( get_current_user_id(), 'jws_date_of_birth', true );
                if(!empty($date)) {
                    $date = date("d-m-Y", strtotime($date));
                }
                

                 ?>
                    <label for="jws_date_of_birth"><?php echo esc_html__('Date of Birth','jws_streamvid'); ?></label>
                    <input type="text" id="jws_date_of_birth" name="jws_date_of_birth"  value="<?php if(!empty($date)) echo esc_attr( $date ); ?>" placeholder="<?php echo esc_attr_x( 'dd/mm/yy', 'placeholder', 'meathouse' ); ?>" required autocomplete="off" />
                  
                </p>
                
                
                <p class="field-item">
                
                    <label for="jws_gender"><?php echo esc_html__('Gender','jws_streamvid'); ?></label>
                   <select name="jws_gender" id="jws_gender">
                      
                         <?php 
                         $gender = get_user_meta( get_current_user_id(), 'jws_gender', true );
                                                 
                         echo '<option value="">'.esc_html__( 'None', 'jws_streamvid' ).'</option>';
                         foreach ( $this->get_gender() as $key => $value ) {
                        
                            printf(
                                '<option value="%s" %s>%s</option>',
                                esc_attr( $key ),
                                $gender == $key ? 'selected="selected"' : '',
                                esc_html( $value )
                            );
        
                        }?>
                      
                    </select>
                </p>
                
                <p class="field-item">
                
                    <label for="jws_postcode"><?php echo esc_html__('Zipcode','jws_streamvid'); ?></label>
                    <input type="text" id="jws_postcode" name="jws_postcode"  value="<?php echo esc_attr( get_user_meta( get_current_user_id(), 'jws_postcode', true )); ?>" required/>
                
                </p>
                
                
                
  

                
              <div class="form-button">
                  <input name="action" type="hidden" value="edit_personal"/>  
                   <?php wp_nonce_field( 'edit_personal_nonce', 'edit_personal_nonce' ); ?>
                  <a class="cancel-modal button-custom" href="#"><?php echo esc_html__('Cancel','jws_streamvid'); ?></a>
                  <button class="btn-main button-default" type="submit"><?php echo esc_html__('Save Update','jws_streamvid'); ?></button> 
             </div>
             </form>
            </div> 
        </div>
        
        
        <?php
        
        
    }
    
    
    public function form_profile(){  
        
        ?>
        
        <div id="edit-profile" class="mfp-hide">
            <div class="form-head">
            
                <h5 class="title">
                    <?php 
                      
                      echo esc_html__('User Profile','jws_streamvid'); 
                        
                    ?>
                </h5>

            </div>
            <div class="form-body">
              <form class="form-edit-profile">  
                 <p class="field-item">
                
                    <label for="display_name"><?php echo esc_html__('Display Name','jws_streamvid'); ?> *</label>
                    <input type="text" id="display_name" name="display_name"  value="<?php echo esc_attr( wp_get_current_user()->display_name ); ?>" required/>
                
                </p>
                
                <p class="field-item">
                
                    <label for="user_email"><?php echo esc_html__('Email','jws_streamvid'); ?> *</label>
                    <input type="email" name="user_email" id="user_email" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" required>
                
                </p>
                
                <p class="field-item">
                
                    <label for="user_phone"><?php echo esc_html__('Phone','jws_streamvid'); ?> *</label>
                    <input type="tel" name="user_phone" id="user_phone" value="<?php echo esc_attr( get_user_meta( get_current_user_id(), 'user_phone', true ) ); ?>">
                
                </p>
                
                <p class="field-item">
                    
                 <label for="user_pass"><?php echo esc_html__('Password','jws_streamvid'); ?></label>
                 <input type="password" name="user_pass" id="user_pass">
                 
                </p>
                
                <p class="field-item">

                    <label for="user_confirm_pass"><?php echo esc_html__('Confirm Password','jws_streamvid'); ?></label>
                    <input type="password" name="user_confirm_pass" id="user_confirm_pass">
                 
                </p>
                
              <div class="form-button">
                  <input name="action" type="hidden" value="edit_profile"  />  
                   <?php wp_nonce_field( 'edit_profile_nonce', 'edit_profile_nonce' ); ?>
                  <button class="btn-main button-default" type="submit"><?php echo esc_html__('Save Update','jws_streamvid'); ?></button> 
             </div>
             </form>
            </div> 
        </div>
        
        <?php
       
        
    }


    public function personal_information(){ 
        $user_id = get_current_user_id();
        $date_of_birth = get_user_meta( $user_id, 'jws_date_of_birth', true );
        $jws_gender = get_user_meta( $user_id, 'jws_gender', true );
        $jws_postcode = get_user_meta( $user_id, 'jws_postcode', true ); 
      
       ?>
       
        <h6><?php echo esc_html__('Personal information','jws_streamvid'); ?></h6>
        
        <div class="information">
            <div class="user-content">
         
            <div class="user-avatar">
                <?php  printf(
                    '%s',
                    get_avatar( $user_id , 96, null, null, array(
                        'class' =>  'img-thumbnail avatar'
                    ) )
                  
                );
                ?>
            </div>
            
             <div class="user-info">
                <?php 
                printf(
                    '<h5>%s</h5>',
                    esc_html( wp_get_current_user()->display_name )
                );
                ?>
                <div class="user-meta fs-small">
                <?php
                if(!empty($jws_gender))
                printf(
                    '<div class="gender"><label>%s</label>%s</div>',
                    esc_html__('Gender:','jws_streamvid'),
                    esc_html( $this->get_gender()[$jws_gender] )
                );
             
                if(!empty($date_of_birth)) {
                     
                    $date_of_birth = date("d-m-Y", strtotime($date_of_birth));
       
                    printf(
                        '<div class="date-of-birth"><label>%s</label>%s</div>',
                        esc_html__('Date of Birth:','jws_streamvid'),
                        $date_of_birth
                    );
                }
                
              
                if(!empty($jws_postcode))
                printf(
                    '<div class="postcode"><label>%s</label>%s</div>',
                    esc_html__('Postcode:','jws_streamvid'),
                    esc_html( $jws_postcode )
                );
                ?>
                </div>
            </div>
            </div>
            
            <?php echo '<div><button class="button-custom" data-modal-jws="#edit-personal"><i class="jws-icon-pencil-line"></i>'.esc_html__('Edit','jws_streamvid').'</button></div>'; ?>
        
        </div>
       
       <?php
        
    } 
    
    
}