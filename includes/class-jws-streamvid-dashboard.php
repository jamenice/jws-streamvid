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
if ( ! function_exists( 'jws_streamvid_email_is_placeholder' ) ) {
    /**
     * Whether a user's address is one the Streamvid API made up because the
     * sign-in gave none: phone numbers, and Facebook or Apple accounts with
     * no shared address (phone_xxx@users.noreply.example.com). Nobody reads
     * mail there, so pages say the email is not set instead of showing it.
     */
    function jws_streamvid_email_is_placeholder( $user_id ) {
        $meta_key = class_exists( 'Streamvid_API_Social_Auth' )
            ? Streamvid_API_Social_Auth::META_PLACEHOLDER_EMAIL
            : 'streamvid_social_placeholder_email';

        return (bool) get_user_meta( (int) $user_id, $meta_key, true );
    }
}

if ( ! function_exists( 'jws_streamvid_password_unknown' ) ) {
    /**
     * Whether the user has never had a password they know: accounts the
     * Streamvid API created from a phone number or a Google/Facebook/Apple
     * sign-in get a random one. Those can set a password on the profile
     * without typing a current one; everybody else must.
     */
    function jws_streamvid_password_unknown( $user_id ) {
        return (bool) get_user_meta( (int) $user_id, 'jws_no_password', true )
            || jws_streamvid_email_is_placeholder( $user_id );
    }

    add_action( 'streamvid_social_user_registered', function ( $user_id ) {
        update_user_meta( (int) $user_id, 'jws_no_password', 1 );
    } );
}

if ( ! function_exists( 'jws_streamvid_phone_otp_required' ) ) {
    /**
     * Whether the app or the login form offers phone sign-in. While one
     * does, the profile phone is a sign-in number, so changing it needs the
     * SMS code sent to the new one (Streamvid API plugin, Twilio or its OTP
     * test mode). Same rule as the app's /user/phone/* routes.
     */
    function jws_streamvid_phone_otp_required() {
        return class_exists( 'Streamvid_API_Phone_OTP' )
            && class_exists( 'Streamvid_API_Social_Auth' )
            && method_exists( 'Streamvid_API_Phone_OTP', 'profile_phone_needs_code' )
            && Streamvid_API_Phone_OTP::profile_phone_needs_code();
    }
}

if ( ! function_exists( 'jws_streamvid_phone_e164' ) ) {
    /**
     * A typed number in E.164, or '' when it is not one. "00" counts as "+",
     * and spaces, dots, dashes and brackets are dropped, as on the login form.
     */
    function jws_streamvid_phone_e164( $raw ) {
        $raw = trim( (string) $raw );
        if ( 0 === strpos( $raw, '00' ) ) {
            $raw = '+' . substr( $raw, 2 );
        }
        $raw = preg_replace( '/[\s().\-]/', '', $raw );
        return class_exists( 'Streamvid_API_Phone_OTP' ) ? Streamvid_API_Phone_OTP::normalize_phone( $raw ) : '';
    }
}

if ( ! function_exists( 'jws_streamvid_phone_owner' ) ) {
    /**
     * The account that signs in with this number, or 0.
     */
    function jws_streamvid_phone_owner( $e164 ) {
        if ( '' === $e164 || ! class_exists( 'Streamvid_API_Social_Auth' ) ) {
            return 0;
        }
        $ids = get_users( array(
            'meta_key'   => Streamvid_API_Social_Auth::provider_meta_key( 'phone' ),
            'meta_value' => $e164,
            'number'     => 1,
            'fields'     => 'ID',
        ) );
        return $ids ? (int) $ids[0] : 0;
    }
}

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
        
        
        // Display name lives in the personal information modal; email,
        // phone and password each have their own (form_profile()).
        $modals = array(
            'user_login' => '#edit-personal',
            'user_email' => '#edit-email',
            'user_phone' => '#edit-phone',
            'user_pass'  => '#edit-password',
        );

        foreach($field as $id) {
            $form = 'data-modal-jws="' . esc_attr( $modals[ $id ] ) . '"';
            switch($id) {
                case 'user_login':

                    $label = esc_html__('Display name:', 'jws_streamvid');
                    $value = esc_html( wp_get_current_user()->display_name );
                    $edit = esc_html__('Change','jws_streamvid');

                    break;
                case 'user_email':
                
                    $label = esc_html__('Email:', 'jws_streamvid');
                    if ( jws_streamvid_email_is_placeholder( get_current_user_id() ) ) {
                        $value = esc_html__('Email not set', 'jws_streamvid');
                        $edit  = esc_html__('Add', 'jws_streamvid');
                    } else {
                        $value = esc_html( wp_get_current_user()->user_email );
                        $edit  = esc_html__('Change','jws_streamvid');
                    }
                    
                    break;
                case 'user_phone':
                
                    $label = esc_html__('Phone:', 'jws_streamvid');
                    $phone = get_user_meta( get_current_user_id(), 'user_phone', true );
                    $value = !empty($phone) ? esc_html( $phone ) : esc_html__('not update', 'jws_streamvid');
                    $edit = !empty($phone) ? esc_html__('Change', 'jws_streamvid') : esc_html__('Add', 'jws_streamvid');
                    
                    break;
                case 'user_pass':
                
                    $label = esc_html__('Password:', 'jws_streamvid');
                    if ( jws_streamvid_password_unknown( get_current_user_id() ) ) {
                        $value = esc_html__('Password not set', 'jws_streamvid');
                        $edit  = esc_html__('Add', 'jws_streamvid');
                    } else {
                        $value = '******';
                        $edit  = esc_html__('Change','jws_streamvid');
                    }
                    
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
    
    
    /**
     * AJAX jws_send_email_change_code: mails a code to the address the user
     * wants to change to. save_profile() will not change the email without it.
     */
    public function send_email_change_code() {
        jws_return_data_demo();

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( new WP_Error( 'not_login', esc_html__( 'You need to login to use this feature.', 'jws_streamvid' ) ) );
        }
        if ( ! check_ajax_referer( 'edit_profile_nonce', 'edit_profile_nonce', false ) ) {
            wp_send_json_error( new WP_Error( 'invalid_nonce', esc_html__( 'Invalid nonce.', 'jws_streamvid' ) ) );
        }
        if ( ! function_exists( 'jws_email_otp_send' ) ) {
            wp_send_json_error( new WP_Error( 'unavailable', esc_html__( 'Email verification is not available.', 'jws_streamvid' ) ) );
        }

        $user_id = get_current_user_id();
        $email   = sanitize_email( wp_unslash( $_POST['user_email'] ?? '' ) );

        if ( ! is_email( $email ) ) {
            wp_send_json_error( new WP_Error( 'invalid_email', esc_html__( 'Please enter a valid email address.', 'jws_streamvid' ) ) );
        }
        if ( strcasecmp( $email, wp_get_current_user()->user_email ) === 0 ) {
            wp_send_json_error( new WP_Error( 'same_email', esc_html__( 'This is already your email address.', 'jws_streamvid' ) ) );
        }
        $owner = email_exists( $email );
        if ( $owner && (int) $owner !== $user_id ) {
            wp_send_json_error( new WP_Error( 'email_exists', esc_html__( 'This email is already used by another account.', 'jws_streamvid' ) ) );
        }

        $sent = jws_email_otp_send( 'change_email', $email, $user_id );
        if ( is_wp_error( $sent ) ) {
            wp_send_json_error( $sent );
        }

        $message = sprintf(
            /* translators: %s: email address */
            esc_html__( 'A verification code was sent to %s.', 'jws_streamvid' ),
            esc_html( $email )
        );
        wp_send_json_success( compact( 'message' ) );
    }

    /**
     * AJAX jws_send_phone_change_code: texts a code to the number the user
     * wants to change to, while phone sign-in is on. save_profile() will not
     * change the number without it.
     */
    public function send_phone_change_code() {
        jws_return_data_demo();

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( new WP_Error( 'not_login', esc_html__( 'You need to login to use this feature.', 'jws_streamvid' ) ) );
        }
        if ( ! check_ajax_referer( 'edit_profile_nonce', 'edit_profile_nonce', false ) ) {
            wp_send_json_error( new WP_Error( 'invalid_nonce', esc_html__( 'Invalid nonce.', 'jws_streamvid' ) ) );
        }
        if ( ! jws_streamvid_phone_otp_required() ) {
            wp_send_json_error( new WP_Error( 'unavailable', esc_html__( 'Phone verification is not available.', 'jws_streamvid' ) ) );
        }

        $user_id = get_current_user_id();
        $phone   = jws_streamvid_phone_e164( sanitize_text_field( wp_unslash( $_POST['user_phone'] ?? '' ) ) );

        if ( '' === $phone ) {
            wp_send_json_error( new WP_Error( 'invalid_phone', esc_html__( 'Enter your phone number with the country code, for example +1 555 123 4567.', 'jws_streamvid' ) ) );
        }
        if ( $phone === jws_streamvid_phone_e164( get_user_meta( $user_id, 'user_phone', true ) ) ) {
            wp_send_json_error( new WP_Error( 'same_phone', esc_html__( 'This is already your phone number.', 'jws_streamvid' ) ) );
        }
        $owner = jws_streamvid_phone_owner( $phone );
        if ( $owner && $owner !== $user_id ) {
            wp_send_json_error( new WP_Error( 'phone_exists', esc_html__( 'This phone number is already used by another account.', 'jws_streamvid' ) ) );
        }

        $sent = Streamvid_API_Phone_OTP::send_code( $phone, strtolower( substr( determine_locale(), 0, 2 ) ) );
        if ( is_wp_error( $sent ) ) {
            wp_send_json_error( new WP_Error( $sent->get_error_code(), wp_strip_all_tags( $sent->get_error_message() ) ) );
        }

        $test_code = Streamvid_API_Phone_OTP::test_code_sent();
        if ( '' !== $test_code ) {
            // Test mode: no SMS went out, so the form shows the code.
            $message = sprintf(
                /* translators: %s: the 6-digit test code */
                esc_html__( 'Test mode: no SMS was sent. Use code %s.', 'jws_streamvid' ),
                $test_code
            );
        } else {
            $message = sprintf(
                /* translators: %s: phone number */
                esc_html__( 'A verification code was sent to %s.', 'jws_streamvid' ),
                esc_html( $phone )
            );
        }
        wp_send_json_success( compact( 'message', 'phone', 'test_code' ) );
    }

    /**
     * AJAX edit_profile: saves one of the email, phone or password modals
     * (form_profile()), named by the profile_section field.
     */
    public function save_profile(){
        
            jws_return_data_demo(); 
            $errors = new WP_Error();  

            if ( ! check_ajax_referer( 'edit_profile_nonce', 'edit_profile_nonce', false ) ) {
                wp_send_json_error( new WP_Error( 'invalid_nonce', esc_html__( 'Invalid nonce.', 'jws_streamvid' ) ) );
            }

            if ( ! is_user_logged_in() ) {
                wp_send_json_error( new WP_Error( 'not_login', esc_html__( 'You need to login to use this feature.', 'jws_streamvid' ) ) );
            }

            $user_id = get_current_user_id();
            $section = sanitize_key( wp_unslash( $_POST['profile_section'] ?? '' ) );

            switch ( $section ) {
                case 'email':
                    $this->save_profile_email( $user_id, $errors );
                    break;
                case 'phone':
                    $this->save_profile_phone( $user_id, $errors );
                    break;
                case 'password':
                    $this->save_profile_password( $user_id, $errors );
                    break;
                default:
                    $errors->add( 'invalid_section', esc_html__( 'Please enter full information.', 'jws_streamvid' ) );
            }

            if( $errors->get_error_code() ){
                wp_send_json_error( $errors );
            } 

            $message = esc_html__( 'Your information has been updated', 'jws_streamvid' );
            
            wp_send_json_success( compact( 'message' ) );
        
    }

    /**
     * A new address only after the code mailed to it comes back: proves it
     * is typed right and belongs to this person.
     */
    private function save_profile_email( $user_id, WP_Error $errors ) {
        $user_email = sanitize_email( wp_unslash( $_POST['user_email'] ?? '' ) );
        $email_otp  = sanitize_text_field( wp_unslash( $_POST['email_otp'] ?? '' ) );
        $old_email  = wp_get_current_user()->user_email;
        // An account signed up by phone has a made-up address until its
        // owner adds a real one.
        $placeholder_email = jws_streamvid_email_is_placeholder( $user_id );

        if ( empty( $user_email ) ) {
            $errors->add( 'miss_info', esc_html__( 'Please enter full information.', 'jws_streamvid' ) );
            return;
        }
        if ( ! is_email( $user_email ) ) {
            $errors->add( 'invalid_email', esc_html__( 'Please enter a valid email address.', 'jws_streamvid' ) );
            return;
        }
        if ( strcasecmp( $user_email, $old_email ) === 0 ) {
            $errors->add( 'same_email', esc_html__( 'This is already your email address.', 'jws_streamvid' ) );
            return;
        }
        if ( ! function_exists( 'jws_email_otp_check' ) ) {
            $errors->add( 'unavailable', esc_html__( 'Email verification is not available.', 'jws_streamvid' ) );
            return;
        }

        $otp = jws_email_otp_check( 'change_email', $user_email, $email_otp, $user_id, false );
        if ( is_wp_error( $otp ) ) {
            $errors->add( $otp->get_error_code(), $otp->get_error_message() );
            return;
        }

        $result = wp_update_user( array(
            'ID'         => $user_id,
            'user_email' => $user_email,
        ) );
        if ( is_wp_error( $result ) ) {
            $errors->add( $result->get_error_code(), $result->get_error_message() );
            return;
        }

        // A real address replaced the made-up one.
        if ( $placeholder_email ) {
            delete_user_meta( $user_id, class_exists( 'Streamvid_API_Social_Auth' ) ? Streamvid_API_Social_Auth::META_PLACEHOLDER_EMAIL : 'streamvid_social_placeholder_email' );
        }

        if ( function_exists( 'jws_email_otp_forget' ) ) {
            jws_email_otp_forget( 'change_email', $user_email, $user_id );
        }
    }

    /**
     * While phone sign-in is on, a new number only after the SMS code sent
     * to it comes back, and it becomes the number this account signs in with.
     */
    private function save_profile_phone( $user_id, WP_Error $errors ) {
        $user_phone = sanitize_text_field( wp_unslash( $_POST['user_phone'] ?? '' ) );
        $old_phone  = (string) get_user_meta( $user_id, 'user_phone', true );

        if ( '' === trim( $user_phone ) ) {
            $errors->add( 'miss_info', esc_html__( 'Please enter full information.', 'jws_streamvid' ) );
            return;
        }

        if ( ! jws_streamvid_phone_otp_required() ) {
            update_user_meta( $user_id, 'user_phone', $user_phone );
            return;
        }

        $new_phone        = jws_streamvid_phone_e164( $user_phone );
        $owner            = jws_streamvid_phone_owner( $new_phone );
        $phone_verify_key = 'jws_phone_change_ok_' . $user_id;

        if ( '' === $new_phone ) {
            $errors->add( 'invalid_phone', esc_html__( 'Enter your phone number with the country code, for example +1 555 123 4567.', 'jws_streamvid' ) );
            return;
        }
        if ( $new_phone === jws_streamvid_phone_e164( $old_phone ) ) {
            $errors->add( 'same_phone', esc_html__( 'This is already your phone number.', 'jws_streamvid' ) );
            return;
        }
        if ( $owner && $owner !== $user_id ) {
            $errors->add( 'phone_exists', esc_html__( 'This phone number is already used by another account.', 'jws_streamvid' ) );
            return;
        }

        // Twilio spends a code on its first right answer, so a save that
        // failed after it keeps the result for a retry instead of asking
        // for another SMS.
        if ( get_transient( $phone_verify_key ) !== $new_phone ) {
            $phone_code = preg_replace( '/\D/', '', (string) wp_unslash( $_POST['phone_otp'] ?? '' ) );
            $verified   = '' === $phone_code
                ? new WP_Error( 'phone_otp', esc_html__( 'Enter the 6-digit code we sent to your new phone number.', 'jws_streamvid' ) )
                : Streamvid_API_Phone_OTP::check_code( $new_phone, $phone_code );
            if ( is_wp_error( $verified ) ) {
                $errors->add( $verified->get_error_code(), wp_strip_all_tags( $verified->get_error_message() ) );
                return;
            }
            set_transient( $phone_verify_key, $new_phone, 10 * MINUTE_IN_SECONDS );
        }

        update_user_meta( $user_id, 'user_phone', $new_phone );
        update_user_meta( $user_id, Streamvid_API_Social_Auth::provider_meta_key( 'phone' ), $new_phone );
        delete_transient( $phone_verify_key );
    }

    /**
     * Someone at an unlocked computer must not be able to lock the owner
     * out: a new password needs the current one, except on accounts that
     * never had a password they know.
     */
    private function save_profile_password( $user_id, WP_Error $errors ) {
        $user_pass         = (string) wp_unslash( $_POST['user_pass'] ?? '' );
        $user_confirm_pass = (string) wp_unslash( $_POST['user_confirm_pass'] ?? '' );
        $user_current_pass = (string) wp_unslash( $_POST['user_current_pass'] ?? '' );

        if ( '' === $user_pass ) {
            $errors->add( 'miss_info', esc_html__( 'Please enter full information.', 'jws_streamvid' ) );
            return;
        }
        if ( $user_pass !== $user_confirm_pass ) {
            $errors->add( 'pass_match', esc_html__( 'Password does not match.', 'jws_streamvid' ) );
        }
        if ( strlen( $user_pass ) < 6 ) {
            $errors->add( 'pass_short', esc_html__( 'The new password must be at least 6 characters.', 'jws_streamvid' ) );
        }
        if ( ! jws_streamvid_password_unknown( $user_id ) ) {
            $current = wp_get_current_user();
            if ( '' === $user_current_pass || ! wp_check_password( $user_current_pass, $current->user_pass, $current->ID ) ) {
                $errors->add( 'current_pass', esc_html__( 'Your current password is not correct.', 'jws_streamvid' ) );
            }
        }
        if ( $errors->get_error_code() ) {
            return;
        }

        wp_set_password( $user_pass, $user_id );
        delete_user_meta( $user_id, 'jws_no_password' );
        // The login cookie carries part of the old password hash, so the
        // page would reload signed out without a fresh one.
        wp_set_auth_cookie( $user_id, true, is_ssl() );
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
                   <select name="jws_gender" id="jws_gender" class="jws-no-select2">
                      
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
    
    
    /**
     * The hidden fields and buttons every account modal ends with. Printed
     * by hand instead of wp_nonce_field() so three forms on one page do not
     * repeat the same element id.
     */
    private function profile_form_button( $section ) {
        static $nonce = null;
        if ( null === $nonce ) {
            $nonce = wp_create_nonce( 'edit_profile_nonce' );
        }
        ?>
              <div class="form-button">
                  <input name="action" type="hidden" value="edit_profile"/>
                  <input name="profile_section" type="hidden" value="<?php echo esc_attr( $section ); ?>"/>
                  <input name="edit_profile_nonce" type="hidden" value="<?php echo esc_attr( $nonce ); ?>"/>
                  <a class="cancel-modal button-custom" href="#"><?php echo esc_html__('Cancel','jws_streamvid'); ?></a>
                  <button class="btn-main button-default" type="submit"><?php echo esc_html__('Save Update','jws_streamvid'); ?></button> 
             </div>
        <?php
    }


    /**
     * Calling codes shared by several countries, and the one to pick when a
     * number only says the code (as phone-login.js in the theme).
     */
    private const PHONE_MAIN_COUNTRY = array(
        '1' => 'US', '7' => 'RU', '39' => 'IT', '44' => 'GB', '47' => 'NO', '61' => 'AU',
        '212' => 'MA', '262' => 'RE', '290' => 'SH', '358' => 'FI', '590' => 'GP', '599' => 'CW',
    );

    /**
     * The phone modal's number field: the country picker of the login
     * form's SMS sign-in (the theme's jws_phone_countries()) in front of the
     * number. profile.js puts the calling code in front before sending. A
     * theme without the list gets the plain field, typed with the code.
     */
    private function profile_phone_field( $phone ) {
        $countries = function_exists( 'jws_phone_countries' ) ? jws_phone_countries() : array();

        if ( empty( $countries ) ) {
            ?>
            <input type="tel" name="user_phone" id="user_phone" value="" placeholder="+1 555 123 4567" autocomplete="tel" required>
            <?php
            return;
        }

        // The current number's country, else the admin's default, else the
        // site language's; the browser's languages are tried in profile.js
        // when none of those decided it.
        $selected = $this->profile_phone_country( $phone, $countries );
        $forced   = function_exists( 'jws_phone_default_country' ) ? jws_phone_default_country() : '';
        $detect   = '' === $selected && '' === $forced;

        if ( '' === $selected ) {
            $selected = $forced;
        }
        if ( '' === $selected && function_exists( 'jws_phone_locale_country' ) ) {
            $selected = jws_phone_locale_country( determine_locale() );
        }
        if ( '' === $selected || ! isset( $countries[ $selected ] ) ) {
            $selected = 'US';
        }

        $languages = function_exists( 'jws_phone_language_countries' ) ? jws_phone_language_countries() : array();
        ?>
        <div class="jws-profile-phone-row">
            <select name="phone_country" id="user_phone_country" class="jws-profile-phone-country jws-no-select2"
                aria-label="<?php esc_attr_e( 'Country', 'jws_streamvid' ); ?>"
                data-detect="<?php echo $detect ? '1' : '0'; ?>"
                data-lang-country="<?php echo esc_attr( wp_json_encode( $languages ) ); ?>"
                data-search="<?php esc_attr_e( 'Search country or code', 'jws_streamvid' ); ?>"
                data-invalid="<?php esc_attr_e( 'Enter a valid phone number.', 'jws_streamvid' ); ?>">
                <?php foreach ( $countries as $iso => $country ) : ?>
                    <option value="<?php echo esc_attr( $iso ); ?>" data-dial="<?php echo esc_attr( $country[1] ); ?>"<?php selected( $iso, $selected ); ?>><?php echo esc_html( $country[0] . ' (+' . $country[1] . ')' ); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="tel" name="user_phone" id="user_phone" value="" placeholder="<?php esc_attr_e( 'Phone number', 'jws_streamvid' ); ?>" autocomplete="tel-national" inputmode="tel" required>
        </div>
        <?php
    }

    /**
     * The country of a saved E.164 number, or ''. Calling codes are
     * prefix-free, so the one the number starts with is the only match.
     */
    private function profile_phone_country( $phone, array $countries ) {
        $phone = trim( (string) $phone );
        if ( '' === $phone || '+' !== $phone[0] ) {
            return '';
        }
        $digits = preg_replace( '/\D/', '', $phone );

        foreach ( $countries as $iso => $country ) {
            if ( 0 === strpos( $digits, $country[1] ) ) {
                $main = self::PHONE_MAIN_COUNTRY[ $country[1] ] ?? '';
                return isset( $countries[ $main ] ) ? $main : $iso;
            }
        }
        return '';
    }


    /**
     * The email, phone and password modals opened from account_security().
     * Display name is edited in form_personal().
     */
    public function form_profile(){  

        $user_id           = get_current_user_id();
        $placeholder_email = jws_streamvid_email_is_placeholder( $user_id );
        $phone             = (string) get_user_meta( $user_id, 'user_phone', true );
        $phone_otp         = jws_streamvid_phone_otp_required();
        $password_unknown  = jws_streamvid_password_unknown( $user_id );
        
        ?>
        
        <div id="edit-email" class="mfp-hide jws-account-modal">
            <div class="form-head">
                <h5 class="title"><?php echo $placeholder_email ? esc_html__('Add email','jws_streamvid') : esc_html__('Change email','jws_streamvid'); ?></h5>
                <?php if ( ! $placeholder_email ) : ?>
                <p class="fs-small"><?php
                    printf(
                        /* translators: %s: current email address */
                        esc_html__( 'Current email: %s', 'jws_streamvid' ),
                        '<strong>' . esc_html( wp_get_current_user()->user_email ) . '</strong>'
                    );
                ?></p>
                <?php endif; ?>
            </div>
            <div class="form-body">
              <form class="form-edit-profile form-edit-email">  
                <p class="field-item">
                    <label for="user_email"><?php echo esc_html__('New email','jws_streamvid'); ?> *</label>
                    <input type="email" name="user_email" id="user_email" value="" autocomplete="email" required>
                </p>

                <div class="field-item jws-email-otp">
                    <label for="email_otp"><?php echo esc_html__('Verification code','jws_streamvid'); ?> *</label>
                    <div class="jws-email-otp-row">
                        <input type="text" name="email_otp" id="email_otp" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="000000" required>
                        <button type="button" class="jws-email-otp-send"><?php echo esc_html__('Send code','jws_streamvid'); ?></button>
                    </div>
                    <p class="jws-email-otp-note fs-small"><?php echo esc_html__('Enter the 6-digit code we send to the new address.','jws_streamvid'); ?></p>
                </div>

                <?php $this->profile_form_button( 'email' ); ?>
             </form>
            </div> 
        </div>

        <div id="edit-phone" class="mfp-hide jws-account-modal">
            <div class="form-head">
                <h5 class="title"><?php echo '' === $phone ? esc_html__('Add phone number','jws_streamvid') : esc_html__('Change phone number','jws_streamvid'); ?></h5>
                <?php if ( '' !== $phone ) : ?>
                <p class="fs-small"><?php
                    printf(
                        /* translators: %s: current phone number */
                        esc_html__( 'Current phone: %s', 'jws_streamvid' ),
                        '<strong>' . esc_html( $phone ) . '</strong>'
                    );
                ?></p>
                <?php endif; ?>
            </div>
            <div class="form-body">
              <form class="form-edit-profile form-edit-phone">  
                <div class="field-item">
                    <label for="user_phone"><?php echo '' === $phone ? esc_html__('Phone','jws_streamvid') : esc_html__('New phone','jws_streamvid'); ?> *</label>
                    <?php $this->profile_phone_field( $phone ); ?>
                </div>

                <?php if ( $phone_otp ) : ?>
                <div class="field-item jws-phone-otp">
                    <label for="phone_otp"><?php echo esc_html__('SMS code','jws_streamvid'); ?> *</label>
                    <div class="jws-phone-otp-row">
                        <input type="text" name="phone_otp" id="phone_otp" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="000000" required>
                        <button type="button" class="jws-phone-otp-send"><?php echo esc_html__('Send code','jws_streamvid'); ?></button>
                    </div>
                    <p class="jws-phone-otp-note fs-small"><?php echo esc_html__('Your phone number signs you in. Enter it with the country code and type the 6-digit code we text to it.','jws_streamvid'); ?></p>
                </div>
                <?php endif; ?>

                <?php $this->profile_form_button( 'phone' ); ?>
             </form>
            </div> 
        </div>

        <div id="edit-password" class="mfp-hide jws-account-modal">
            <div class="form-head">
                <h5 class="title"><?php echo $password_unknown ? esc_html__('Set password','jws_streamvid') : esc_html__('Change password','jws_streamvid'); ?></h5>
            </div>
            <div class="form-body">
              <form class="form-edit-profile form-edit-password">  
                <?php if ( ! $password_unknown ) : ?>
                <p class="field-item">
                    <label for="user_current_pass"><?php echo esc_html__('Current Password','jws_streamvid'); ?> *</label>
                    <input type="password" name="user_current_pass" id="user_current_pass" autocomplete="current-password" required>
                </p>
                <?php endif; ?>

                <p class="field-item">
                    <label for="user_pass"><?php echo esc_html__('New Password','jws_streamvid'); ?> *</label>
                    <input type="password" name="user_pass" id="user_pass" autocomplete="new-password" minlength="6" required>
                </p>
                
                <p class="field-item">
                    <label for="user_confirm_pass"><?php echo esc_html__('Confirm Password','jws_streamvid'); ?> *</label>
                    <input type="password" name="user_confirm_pass" id="user_confirm_pass" autocomplete="new-password" minlength="6" required>
                </p>

                <?php $this->profile_form_button( 'password' ); ?>
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