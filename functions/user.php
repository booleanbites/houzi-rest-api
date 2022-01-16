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
    $data['avatar'] = get_avatar_url( $user->ID, 32 );
    return $data;
  }

  
  function signupUser(){
    if( !class_exists('Houzez_login_register') ) {
      wp_send_json(array('error'=>'class dont exist'), 403); 
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


