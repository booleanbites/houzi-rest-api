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