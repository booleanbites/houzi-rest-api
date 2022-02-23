<?php



// houzez-mobile-api/v1/search-properties
add_action( 'rest_api_init', function () {

    register_rest_route( 'houzez-mobile-api/v1', '/signup', array(
      'methods' => 'POST',
      'callback' => 'signupUser',
    ));

    register_rest_route( 'houzez-mobile-api/v1', '/reset-password', array(
      'methods' => 'POST',
      'callback' => 'resetUserPassword',
    ));

    register_rest_route( 'houzez-mobile-api/v1', '/profile', array(
      'methods' => 'GET',
      'callback' => 'fetchProfile',
    ));

    register_rest_route( 'houzez-mobile-api/v1', '/update-profile', array(
      'methods' => 'POST',
      'callback' => 'editProfile',
    ));

    register_rest_route( 'houzez-mobile-api/v1', '/fix-profile-pic', array(
      'methods' => 'POST',
      'callback' => 'fixProfilePicture',
    ));

    register_rest_route( 'houzez-mobile-api/v1', '/update-profile-photo', array(
      'methods' => 'POST',
      'callback' => 'editProfilePhoto',
    ));

    register_rest_route( 'contact-us/v1', 'send-message', array(
      'methods'             => 'POST',
      'callback'            => 'sendContactMail',
      'permission_callback' => '__return_true',
      'args'                => array(
          'name'    => array(
              'required'          => true,
              'sanitize_callback' => 'sanitize_text_field',
          ),
          'email'   => array(
              'required'          => true,
              'validate_callback' => 'is_email',
              'sanitize_callback' => 'sanitize_email',
          ),
          'message' => array(
              'required'          => true,
              'sanitize_callback' => 'sanitize_textarea_field',
          ),
          'source' => array(
            'required'          => true,
            'sanitize_callback' => 'sanitize_text_field',
          ),
      ),
  ) );
  
  });
  add_filter( 'jwt_auth_token_before_dispatch', 'add_user_info_to_login', 10, 2 );

  /**
   * Adds a website parameter to the auth.
   *
   */
  function add_user_info_to_login( $data, $user ) {

    $data['user_id'] = $user->ID;
    $data['user_role'] = $user->roles;
    $user_custom_picture    =   get_the_author_meta( 'fave_author_custom_picture' , $user->ID );
    $author_picture_id      =   get_the_author_meta( 'fave_author_picture_id' , $user->ID );
    if( !empty( $author_picture_id ) ) {
      $author_picture_id = intval( $author_picture_id );
      if ( $author_picture_id ) {
        $data['avatar'] = wp_get_attachment_image_url( $author_picture_id, 'large');
      }
    } else {
      $data['avatar'] = esc_url( $user_custom_picture );
    }
    //$data['avatar'] = get_avatar_url( $user->ID, 32 );
    return $data;
  }

  
  function signupUser(){
    if( !class_exists('Houzez_login_register') ) {
      wp_send_json(array('error'=>'Houzez_login_register plugin dont exist'), 403); 
      return;
    }
    //create nonce for this request.
    $nonce = wp_create_nonce('houzez_register_nonce');
    $_REQUEST['houzez_register_security'] = $nonce;
    
    //disable captcha for this request.
    global $houzez_options;
    $houzez_options['enable_reCaptcha'] = 0;
    
    do_action("wp_ajax_nopriv_houzez_register");//houzez_register();
  }

  function resetUserPassword(){
    //create nonce for this request.
    $nonce = wp_create_nonce('fave_resetpassword_nonce');
    $_REQUEST['security'] = $nonce;
    
    do_action("wp_ajax_nopriv_houzez_reset_password");//houzez_reset_password();
    
  }

  function sendContactMail( WP_REST_Request $request ) {
    $response = array(
        'status'  => 304,
        'message' => 'There was an error sending the form.'
    );

    $siteName = wp_strip_all_tags( trim( get_option( 'blogname' ) ) );
    $source = $request['source'];
    $contactName = $request['name'];
    $contactEmail = $request['email'];
    $contactMessage = $request['message'];

    $subject = "[$source] New message from $contactName";

    $body = "<p><b>Name:</b> $contactName</p>";
    $body .= "<p><b>Email:</b> $contactEmail</p>";
    $body .= "<p><b>Message:</b> $contactMessage</p>";

    $to = get_option( 'admin_email' );
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        "Reply-To: $contactName <$contactEmail>",
    );

    if ( wp_mail( $to, $subject, $body, $headers ) ) {
        $response['status'] = 200;
        $response['message'] = 'Message sent successfully.';
        //$response['test'] = $body;
    }

    return new WP_REST_Response( $response );
  }

  if(!function_exists('houzez_get_profile_thumb')) {
    function houzez_get_profile_thumb($user_id = null) {

        if(empty($user_id)) {
            $user_id = get_the_author_meta( 'ID' );
        }
        
        $author_picture_id   =   get_the_author_meta( 'fave_author_picture_id' , $user_id );
        $user_custom_picture =   get_the_author_meta( 'fave_author_custom_picture', $user_id );

        if( !empty( $author_picture_id ) ) {
            $author_picture_id = intval( $author_picture_id );
            if ( $author_picture_id ) {
                $img = wp_get_attachment_image_src( $author_picture_id, 'thumbnail' );
                $user_custom_picture = $img[0];

            }
        }

        if($user_custom_picture =='' ) {
            $user_custom_picture = HOUZEZ_IMAGE. 'profile-avatar.png';
        }

        return $user_custom_picture;
    }
}

function fetchProfile() {
  do_action( 'litespeed_control_set_nocache', 'nocache due to logged in' );
  
  if (!is_user_logged_in() ) {
    $ajax_response = array( 'success' => false, 'reason' => 'Please provide user auth.' );
    wp_send_json($ajax_response, 403);
    return; 
  }
  $userID = get_current_user_id();
  if(isset($_GET["user_id"]) && !empty($_GET["user_id"])) {
    $userID = $_GET["user_id"];
  }
  $response = array();
  
  $response['success'] = true;
  
  $user = get_user_by('id', $userID);

  $user_custom_picture    =   get_the_author_meta( 'fave_author_custom_picture' , $userID );
  $author_picture_id      =   get_the_author_meta( 'fave_author_picture_id' , $userID );
  if( !empty( $author_picture_id ) ) {
    $author_picture_id = intval( $author_picture_id );
    if ( $author_picture_id ) {
      $user->profile = wp_get_attachment_image_url( $author_picture_id, 'large');
    }
  } else {
    $user->profile = esc_url( $user_custom_picture );

  }

  $user->username               =   get_the_author_meta( 'user_login' , $userID );
  $user->user_title             =   get_the_author_meta( 'fave_author_title' , $userID );
  $user->first_name             =   get_the_author_meta( 'first_name' , $userID );
  $user->last_name              =   get_the_author_meta( 'last_name' , $userID );
  $user->user_email             =   get_the_author_meta( 'user_email' , $userID );
  $user->user_mobile            =   get_the_author_meta( 'fave_author_mobile' , $userID );
  $user->user_whatsapp          =   get_the_author_meta( 'fave_author_whatsapp' , $userID );
  $user->user_phone             =   get_the_author_meta( 'fave_author_phone' , $userID );
  $user->description            =   get_the_author_meta( 'description' , $userID );
  $user->userlangs              =   get_the_author_meta( 'fave_author_language' , $userID );
  $user->user_company           =   get_the_author_meta( 'fave_author_company' , $userID );
  $user->tax_number             =   get_the_author_meta( 'fave_author_tax_no' , $userID );
  $user->fax_number             =   get_the_author_meta( 'fave_author_fax' , $userID );
  $user->user_address           =   get_the_author_meta( 'fave_author_address' , $userID );
  $user->service_areas          =   get_the_author_meta( 'fave_author_service_areas' , $userID );
  $user->specialties            =   get_the_author_meta( 'fave_author_specialties' , $userID );
  $user->license                =   get_the_author_meta( 'fave_author_license' , $userID );
  $user->gdpr_agreement         =   get_the_author_meta( 'gdpr_agreement' , $userID );
  $user->author_picture_id         =   $author_picture_id;
  
  unset($user->user_pass);
  unset($user->user_activation_key);
  unset($user->allcaps);

  $user_agent_id = get_the_author_meta('fave_author_agent_id', $userID);
  $user_agency_id = get_the_author_meta('fave_author_agency_id', $userID);
  
  if( !empty($user_agent_id) ) {
    $user->fave_author_agent_id = $user_agent_id;

  } else if( !empty($user_agency_id) ) {
    $user->user_agency_id = $user_agency_id;
  }

  $public_display = array();
  $public_display[]  = $user->user_login;
  $public_display[]  = $user->nickname;
  
  if(!empty($user->first_name)) {
      $public_display[] = $user->first_name;
  }
  
  if(!empty($user->last_name)) {
      $public_display[] = $user->last_name;
  }
  
  if(!empty($user->first_name) && !empty($user->last_name) ) {
      $public_display[] = $user->first_name . ' ' . $user->last_name;
      $public_display[] = $user->last_name . ' ' . $user->first_name;
  }
  
  if(!in_array( $user->display_name, $public_display)) {
      $public_display[] = $user->display_name;
      
      //$public_display = array_unique( $public_display );
  }
  $user->display_name_options = $public_display;

  $response["user"] = $user;
  
  

  wp_send_json($response, 200);
}

function editProfile() {
  if (!is_user_logged_in() ) {
    $ajax_response = array( 'success' => false, 'reason' => 'Please provide user auth.' );
    wp_send_json($ajax_response, 403);
    return; 
  }
  $userID = get_current_user_id();
  
  $user_agent_id = get_the_author_meta('fave_author_agent_id', $userID);
  $user_agency_id = get_the_author_meta('fave_author_agency_id', $userID);
  
  if( !empty($user_agent_id) ) {
      //purge light-speed cache for this agent post type.
      do_action( 'litespeed_purge_post', $user_agent_id );
  } else if( !empty($user_agency_id) ) {
      //purge light-speed cache for this agency post type.
      do_action( 'litespeed_purge_post', $user_agency_id );
  }
  

  $nonce = wp_create_nonce('houzez_profile_ajax_nonce');
  $_REQUEST['houzez-security-profile'] = $nonce;

  do_action("wp_ajax_houzez_ajax_update_profile");
}
// on some houzez instances, post type agent or agency photo get cleared after profile fields update (eg, name phone etc).
// not sure about the reason, too complex to edit theme, so fix on app side.
// so after editing profile via editProfile(), call this web service from app.
function fixProfilePicture() {
  if (!is_user_logged_in() ) {
    $ajax_response = array( 'success' => false, 'reason' => 'Please provide user auth.' );
    wp_send_json($ajax_response, 403);
    return; 
  }
  $userID = get_current_user_id();
  
  $profile_attach_id    =   get_the_author_meta( 'fave_author_picture_id' , $userID );
  $thumbnail_url = wp_get_attachment_image_src( $profile_attach_id, 'large' );

  update_user_meta( $userID, 'fave_author_picture_id', $profile_attach_id );
  update_user_meta( $userID, 'fave_author_custom_picture', $thumbnail_url[0] );

  $user_agent_id = get_the_author_meta('fave_author_agent_id', $userID);
  $user_agency_id = get_the_author_meta('fave_author_agency_id', $userID);
  
  if( !empty($user_agent_id) ) {
      update_post_meta( $user_agent_id, '_thumbnail_id', $profile_attach_id );

  } else if( !empty($user_agency_id) ) {
      update_post_meta( $user_agency_id, '_thumbnail_id', $profile_attach_id );
  }
  $ajax_response = array( 'success' => true, 'data' => array("pic_id"=>$profile_attach_id, "url"=>$thumbnail_url[0]) );
  wp_send_json($ajax_response, 200);
}

function editProfilePhoto() {
  if (!is_user_logged_in() ) {
    $ajax_response = array( 'success' => false, 'reason' => 'Please provide user auth.' );
    wp_send_json($ajax_response, 403);
    return; 
  }
  
  if(!isset( $_FILES['houzez_file_data_name']) ) {
    $ajax_response = array( 'success' => false, 'reason' => 'Please provide photo houzez_file_data_name' );
    
    wp_send_json($ajax_response, 400);
    return;
  }
  $userID = get_current_user_id();
  
  $user_agent_id = get_the_author_meta('fave_author_agent_id', $userID);
  $user_agency_id = get_the_author_meta('fave_author_agency_id', $userID);
  
  if( !empty($user_agent_id) ) {
      //purge light-speed cache for this agent post type.
      do_action( 'litespeed_purge_post', $user_agent_id );
  } else if( !empty($user_agency_id) ) {
      //purge light-speed cache for this agency post type.
      do_action( 'litespeed_purge_post', $user_agency_id );
  }

  $nonce = wp_create_nonce('houzez_upload_nonce');
  $_REQUEST['verify_nonce'] = $nonce;
  $_REQUEST['user_id'] = $userID;

  require_once(ABSPATH . "wp-admin" . '/includes/image.php');

  do_action("wp_ajax_houzez_user_picture_upload");

}