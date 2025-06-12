<?php
/**
 * Functions to entertain user profile related apis. singin, signup, social login, forgot or update pass and profile.
 *
 *
 * @package Houzi Mobile Api
 * @since Houzi 1.0
 * @author Adil Soomro
 */
//-----------------------------Lightspeed exclude URLs-------------------------------------
// By default all POST URLs aren't cached
add_action('litespeed_init', function () {

  //these URLs need to be excluded from lightspeed caches
  $exclude_url_list = array (
    "profile",
    "proceed-payment"
  );
  foreach ($exclude_url_list as $exclude_url) {
    if (strpos($_SERVER['REQUEST_URI'], $exclude_url) !== FALSE) {
      do_action('litespeed_control_set_nocache', 'no-cache for rest api');
    }
  }

  //add these URLs to cache if required (even POSTs)
  $include_url_list = array (
    "sample-url",
  );
  foreach ($include_url_list as $include_url) {
    if (strpos($_SERVER['REQUEST_URI'], $exclude_url) !== FALSE) {
      do_action('litespeed_control_set_cacheable', 'cache for rest api');
    }
  }

});

// houzez-mobile-api/v1/search-properties
add_action('rest_api_init', function () {

  ///////////
  register_rest_route(
    'houzez-mobile-api/v1',
    '/fetch-users',
    array(
      'methods' => 'GET',
      'callback' => 'fetch_all_users',
      'permission_callback' => function () {
        // For public access, return true. Otherwise, check for capabilities
        return current_user_can('list_users'); // or use '__return_true' if open
      },
    )
  );
  ////
  register_rest_route(
    'houzez-mobile-api/v1',
    '/signup',
    array (
      'methods' => 'POST',
      'callback' => 'signupUser',
      'permission_callback' => '__return_true'
    )
  );
  register_rest_route(
    'houzez-mobile-api/v1',
    '/add-user',
    array (
      'methods' => 'POST',
      'callback' => 'adminAddUser',
      'permission_callback' => '__return_true'
    )
  );
  register_rest_route(
    'houzez-mobile-api/v1',
    '/signin',
    array (
      'methods' => 'POST',
      'callback' => 'signInUser',
      'permission_callback' => '__return_true'
    )
  );

  register_rest_route(
    'houzez-mobile-api/v1',
    '/try-phone-login',
    array (
      'methods' => 'POST',
      'callback' => 'tryPhoneLogin',
      'permission_callback' => '__return_true'
    )
  );

  register_rest_route(
    'houzez-mobile-api/v1',
    '/social-sign-on',
    array (
      'methods' => 'POST',
      'callback' => 'socialSignOn',
      'permission_callback' => '__return_true'
    )
  );

  register_rest_route(
    'houzez-mobile-api/v1',
    '/reset-password',
    array (
      'methods' => 'POST',
      'callback' => 'resetUserPassword',
      'permission_callback' => '__return_true'
    )
  );

  register_rest_route(
    'houzez-mobile-api/v1',
    '/profile',
    array (
      'methods' => 'GET',
      'callback' => 'fetchProfile',
      'permission_callback' => '__return_true'
    )
  );

  register_rest_route(
    'houzez-mobile-api/v1',
    '/update-profile',
    array (
      'methods' => 'POST',
      'callback' => 'editProfile',
      'permission_callback' => '__return_true'
    )
  );

  register_rest_route(
    'houzez-mobile-api/v1',
    '/fix-profile-pic',
    array (
      'methods' => 'POST',
      'callback' => 'fixProfilePicture',
      'permission_callback' => '__return_true'
    )
  );

  register_rest_route(
    'houzez-mobile-api/v1',
    '/update-profile-photo',
    array (
      'methods' => 'POST',
      'callback' => 'editProfilePhoto',
      'permission_callback' => '__return_true'
    )
  );

  register_rest_route(
    'houzez-mobile-api/v1',
    '/update-password',
    array (
      'methods' => 'POST',
      'callback' => 'updatePassword',
      'permission_callback' => '__return_true'
    )
  );

  register_rest_route(
    'houzez-mobile-api/v1',
    '/delete-user-account',
    array (
      'methods' => 'POST',
      'callback' => 'deleteUserAccount',
      'permission_callback' => '__return_true'
    )
  );

  register_rest_route(
    'houzez-mobile-api/v1',
    '/user-payment-status',
    array (
      'methods' => 'POST',
      'callback' => 'paymentStatus',
      'permission_callback' => '__return_true'
    )
  );

  register_rest_route(
    'houzez-mobile-api/v1',
    '/proceed-payment',
    array (
      'methods' => 'GET',
      'callback' => 'proceedPayment',
      'permission_callback' => '__return_true'
    )
  );

  register_rest_route(
    'houzez-mobile-api/v1',
    '/proceed-with-payment',
    array (
      'methods' => 'POST',
      'callback' => 'proceedWithPayment',
      'permission_callback' => '__return_true'
    )
  );

  register_rest_route(
    'houzez-mobile-api/v1',
    '/make-property-featured',
    array (
      'methods' => 'POST',
      'callback' => 'makePropertyFeatured',
      'permission_callback' => '__return_true'
    )
  );

  register_rest_route(
    'houzez-mobile-api/v1',
    '/remove-from-featured',
    array (
      'methods' => 'POST',
      'callback' => 'removeFromFeatured',
      'permission_callback' => '__return_true'
    )
  );

  register_rest_route(
    'houzez-mobile-api/v1',
    '/user-current-package',
    array (
      'methods' => 'POST',
      'callback' => 'userCurrentPackage',
      'permission_callback' => '__return_true'
    )
  );



  register_rest_route('contact-us/v1', 'send-message', array (
    'methods' => 'POST',
    'callback' => 'sendContactMail',
    'permission_callback' => '__return_true',
    'args' => array (
      'name' => array (
        'required' => true,
        'sanitize_callback' => 'sanitize_text_field',
      ),
      'email' => array (
        'required' => true,
        'validate_callback' => 'is_email',
        'sanitize_callback' => 'sanitize_email',
      ),
      'message' => array (
        'required' => true,
        'sanitize_callback' => 'sanitize_textarea_field',
      ),
      'source' => array (
        'required' => true,
        'sanitize_callback' => 'sanitize_text_field',
      ),
    ),
  )
  );

  register_rest_route('houzez-mobile-api/v1', '/save-searches-routine', array (
    'methods' => 'GET',
    'callback' => 'save_searches_routine_func',
    'permission_callback' => '__return_true',
  ));

});

/**
   * Triggers the function to look for saved searches of users and start sending emails
   * and it also triggers houzez_send_notifications, which helps us send push notifications.
   */
function save_searches_routine_func() {
  //houzez_check_new_listing();
  do_action("houzez_check_new_listing_action_hook");

  $temp = array(
    'success' => true
  );

  wp_send_json($temp, 200);
  return;
}
add_filter('jwt_auth_token_before_dispatch', 'add_user_info_to_login', 10, 2);

/**
 * Adds a website parameter to the auth.
 *
 */
function add_user_info_to_login($data, $user)
{
  $userID = $user->ID;
  $data['user_id'] = $userID;
  $data['user_role'] = $user->roles;
  $data['user_phone'] = get_the_author_meta('fave_author_phone', $userID);
  $user_custom_picture = get_the_author_meta('fave_author_custom_picture', $userID);
  $author_picture_id = get_the_author_meta('fave_author_picture_id', $userID);
  if (!empty($author_picture_id)) {
    $author_picture_id = intval($author_picture_id);
    if ($author_picture_id) {
      $data['avatar'] = wp_get_attachment_image_url($author_picture_id, 'large');
    }
  } else {
    $data['avatar'] = esc_url($user_custom_picture);
  }
  $user_agent_id = get_the_author_meta('fave_author_agent_id', $userID);
  $user_agency_id = get_the_author_meta('fave_author_agency_id', $userID);

  if (!empty($user_agent_id)) {
    $data['fave_author_agent_id'] = $user_agent_id;
  } else if (!empty($user_agency_id)) {
    $data['fave_author_agency_id'] = $user_agency_id;
  }
	
	

  return $data;
}

function signInUser()
{
  if (!create_nonce_or_throw_error('login_security', 'login_nonce')) {
    return;
  }

  $nonce = $_POST['login_security'];
  if (!wp_verify_nonce($nonce, 'login_nonce')) {
    $ajax_response = array('success' => false, 'reason' => esc_html__('Security check failed!', 'houzi'));
    wp_send_json($ajax_response, 403);
    return;
  }

  if (!isset($_POST['username'])) {
    $ajax_response = array('success' => false, 'reason' => "username not provided");
    wp_send_json($ajax_response, 403);
  }
  if (!isset($_POST['password'])) {
    $ajax_response = array('success' => false, 'reason' => "password not provided");
    wp_send_json($ajax_response, 403);
  }


  $request = new WP_REST_Request('POST', '/jwt-auth/v1/token');
  $request->set_body_params(['username' => $_POST['username'], 'password' => $_POST['password']]);
  $response = rest_do_request($request);
  $server = rest_get_server();
  $data = $server->response_to_data($response, false);
  $json = wp_json_encode($data);

  //echo $json;
  wp_send_json($data, $response->get_status());
  die;
}

function tryPhoneLogin()
{
  if (!create_nonce_or_throw_error('login_security', 'login_nonce')) {
    return;
  }
  $nonce = $_POST['login_security'];
  if (!wp_verify_nonce($nonce, 'login_nonce')) {
    $ajax_response = array('success' => false, 'reason' => esc_html__('Security check failed!', 'houzi'));
    wp_send_json($ajax_response, 403);
    return;
  }

  if (!isset($_POST['source'])) {
    $ajax_response = array('success' => false, 'reason' => "source not provided");
    wp_send_json($ajax_response, 403);
    return;
  }
  if (!isset($_POST['user_id'])) {
    $ajax_response = array('success' => false, 'reason' => "user_id not provided");
    wp_send_json($ajax_response, 403);
    return;
  }


  $source = $_POST['source'];
  if (strtolower($source) != 'phone') {
    $ajax_response = array('success' => false, 'reason' => "source not phone");
    wp_send_json($ajax_response, 403);
    return;
  }
  $username = $_POST['username'] ?? "";
  if (strtolower($source) == 'phone' && (!isset($_POST['username']) || empty($username))) {
    $ajax_response = array('success' => false, 'reason' => "source is phone, but phone not provided in username");
    wp_send_json($ajax_response, 403);
    return;
  }

  $user_id_social = $_POST['user_id'];

  if (strtolower($source) == 'phone') {
    $user_query = array(
      'meta_key' => 'user_id_social',
      'meta_value' => $user_id_social,
      'count' => 1,
    );
    $user_obj = get_users($user_query);
    $user = reset($user_obj);
    if ($user) {
      doJWTAuthWithSecret($$user->user_login, $user->data->user_pass);
      //we logged in, return from here.
      return;
    }
  }

  $ajax_response = array( 'success' => false , "Couldn't verify the user",  );
  wp_send_json($ajax_response, 403);    

}
function socialSignOn()
{
  if (!create_nonce_or_throw_error('login_security', 'login_nonce')) {
    return;
  }
  $nonce = $_POST['login_security'];
  if (!wp_verify_nonce($nonce, 'login_nonce')) {
    $ajax_response = array('success' => false, 'reason' => esc_html__('Security check failed!', 'houzi'));
    wp_send_json($ajax_response, 403);
    return;
  }

  if (!isset($_POST['source'])) {
    $ajax_response = array('success' => false, 'reason' => "source not provided");
    wp_send_json($ajax_response, 403);
    return;
  }
  if (!isset($_POST['user_id'])) {
    $ajax_response = array('success' => false, 'reason' => "user_id not provided");
    wp_send_json($ajax_response, 403);
    return;
  }


  $source = $_POST['source'];
  $user_id_social = $_POST['user_id'];

  //if source is apple, we need to first check if any user exist with the user_id
  //then we need to fetch the user based on apple user id
  //and try login with that.
  if (strtolower($source) == 'apple') {
    $user = reset(
      get_users(
        array(
          'meta_key' => 'user_id_social',
          'meta_value' => $user_id_social,
          'count' => 1,
        )
      )
    );
    if ($user) {
      //user_login will definitely exist on apple user. email may not.
      doJWTAuthWithSecret($user->user_login, $user->data->user_pass);
      //we logged in, return from here.
      return;
    }
  }

  $email = $_POST['email'] ?? "";
  //source wasn't apple or phone, we need email or username.
  if ((strtolower($source) != 'phone' && strtolower($source) != 'apple') && (!isset($_POST['email']) || empty($email))) {
    $ajax_response = array('success' => false, 'reason' => "email not provided");
    wp_send_json($ajax_response, 403);
    return;
  }
  $username = $_POST['username'] ?? "";
  if (strtolower($source) == 'phone' && (!isset($_POST['username']) || empty($username))) {
    $ajax_response = array('success' => false, 'reason' => "source is phone, but phone not provided in username");
    wp_send_json($ajax_response, 403);
    return;
  }

  if (strtolower($source) == 'phone') {
    $user_query = array(
      'meta_key' => 'user_id_social',
      'meta_value' => $user_id_social,
      'count' => 1,
    );
    $user_obj = get_users($user_query);
    $user = reset($user_obj);
    if ($user) {
      doJWTAuthWithSecret($username, $user->data->user_pass);
      //we logged in, return from here.
      return;
    }
  }

  if (email_exists($email)) {
    $user = get_user_by('email', $email);


    //if there does exist a user with this email, we need to update its apple social id for future logins.
    if (strtolower($source) == 'apple') {
      update_user_meta($user->ID, "user_id_social", $user_id_social);
    }

    if ($user && wp_check_password($user_id_social, $user->data->user_pass, $user->ID)) {
      //in existing houzez, for social login, $user_id_social is the password
      doJWTAuth($email, $user_id_social);
      return;
    } else {
      doJWTAuthWithSecret($email, $user->data->user_pass);
    }

    return;
  }
  // there's a chance where the apple login may not send email. so we cannot get the
  // email or generate username when there's no email.
  // so simply set the display name if provided or user_id_social as username.
  if ((strtolower($source) == 'apple') && empty($email)) {
    if (isset($_POST['display_name']) && !empty($_POST['display_name'])) {
      $display_n = $_POST['display_name'];
      $username = convert_to_valid_username($display_n);;
    } else {
      
      $username = convert_to_valid_username(substr($user_id_social, 0, 15));
      //set display_name to Apple and a username, so it doesn't make problem if the display name wasn't provided.
      $_POST['display_name'] = "Apple " . substr($username, 0, 8);
    }
  }


  if (empty($username)) {
    $username = explode('@', $email)[0];
  }

  if (username_exists($username)) {
    $user = get_user_by('login', $username);

    //if there does exist a user with this email/phonenum, we need to update its social id for future logins.
    if (strtolower($source) == 'apple' || strtolower($source) == 'phone') {
      update_user_meta($user->ID, "user_id_social", $user_id_social);
    }

    //for social login, $user_id_social is the password
    if ($user && wp_check_password($user_id_social, $user->data->user_pass, $user->ID)) {
      //in existing houzez, for social login, $user_id_social is the password
      doJWTAuth($username, $user_id_social);
      return;
    } else {
      doJWTAuthWithSecret($username, $user->data->user_pass);
      return;
    }

  }
  if (!isset($_POST['display_name'])) {
    $ajax_response = array('success' => false, 'reason' => "display_name not provided");
    wp_send_json($ajax_response, 403);
    return;
  }
  $profile_image_url = $_POST['profile_url'];
  $display_name = $_POST['display_name'];
  if (!str_contains($display_name, ' ')) {
    $display_name .= ' ';
  }

  houzez_register_user_social($email, $username, $display_name, $user_id_social, $profile_image_url);

  $wordpress_user_id = username_exists($username);
  wp_set_password($user_id_social, $wordpress_user_id);

  update_user_meta($wordpress_user_id, "user_id_social", $user_id_social);
  if (strtolower($source) == 'phone') {
    $phonenum = $username;
    if (strpos($phonenum, '+') !== 0) {
        $phonenum = '+' . $phonenum;
    }
    update_user_meta($wordpress_user_id, "fave_author_mobile", $phonenum);
  }

  doJWTAuth($username, $user_id_social);
  return;

  // $ajax_response = array( 'success' => true , 'email' => $email, 'username' => $username, 'user_id' => $user_id,  );
  // wp_send_json($ajax_response, 200);    

}
function checkPassWithSecret($user_id, $candidate_password, $hashedpass)
{
  //we want to check password with hashed password.
  //For that we append a secrte, to check only when the secret has been appended
  //with the password.
  //so when the password contains our secret, this means, we're trying to 
  //authenticate with hashed password.
  //in that case, only compare password with hashed wordpress password.
  //looking for better approach
  $secret = '<6Bk-l1hPHr*?}V?v6~!-[cxvmjm4@9emdY+jezhH5z;5{rZiUFo|$W8X6llE_Hm';

  return wp_check_password($secret . $candidate_password, $hashedpass, $user_id);
}
function doJWTAuthWithSecret($username, $password)
{
  //we want to authenticate user with hashed password.
  //For that we append a secrte, to check only when the secret has been appended
  //with the password.
  //so when the password contains our secret, this means, we're trying to 
  //authenticate with hashed password.
  //in that case, only compare password with hashed wordpress password.
  //looking for better approach
  $secret = '<6Bk-l1hPHr*?}V?v6~!-[cxvmjm4@9emdY+jezhH5z;5{rZiUFo|$W8X6llE_Hm';

  doJWTAuth($username, $secret . $password);
}
function doJWTAuth($username, $password)
{
  $request = new WP_REST_Request('POST', '/jwt-auth/v1/token');
  $request->set_body_params(['username' => $username, 'password' => $password]);
  $response = rest_do_request($request);
  $server = rest_get_server();
  $data = $server->response_to_data($response, false);
  $json = wp_json_encode($data);
  echo $json;
  die;
}
function signupUser()
{
  if (!class_exists('Houzez_login_register')) {
    wp_send_json(array('error' => 'Houzez_login_register plugin dont exist'), 403);
    return;
  }
  //create nonce for this request.
  // $nonce = wp_create_nonce('houzez_register_nonce');
  // $_REQUEST['houzez_register_security'] = $nonce;

  if (!create_nonce_or_throw_error('houzez_register_security', 'houzez_register_nonce')) {
    return;
  }

  //disable captcha for this request.
  global $houzez_options;
  $houzez_options['enable_reCaptcha'] = 0;

  if (isset( $_POST['user_id_social']) && !empty( $_POST['user_id_social'])) {
    global $user_id_social_global;
    $user_id_social_global = $_POST['user_id_social'];
    add_action('houzez_after_register', 'set_newly_created_social_id');
  }


  do_action("wp_ajax_nopriv_houzez_register");//houzez_register();

}
function set_newly_created_social_id($user_id) {
  global $user_id_social_global;
  if (isset($user_id_social_global) && !empty($user_id_social_global)) {
    update_user_meta($user_id, "user_id_social", $user_id_social_global);
    $user_id_social_global = null;
  }
  remove_action('houzez_after_register', 'set_newly_created_social_id');
}

function adminAddUser()
{
  if (!class_exists('Houzez_login_register')) {
    wp_send_json(array('error' => 'Houzez_login_register plugin dont exist'), 403);
    return;
  }

  global $current_user;
  wp_get_current_user();


  if (in_array('administrator', $current_user->roles)) {
    // If the user is an admin, check the nonce and add new user
    // checking nonce:
    if (!create_nonce_or_throw_error('houzez_register_security', 'houzez_register_nonce')) {
      return;
    }

    //disable captcha for this request.
    global $houzez_options;
    $houzez_options['enable_reCaptcha'] = 0;

    do_action("wp_ajax_nopriv_houzez_register");//houzez_register();
  } else {
    // If the user is not an admin, return
    return;
  }
}

function resetUserPassword()
{
  //create nonce for this request.
  // $nonce = wp_create_nonce('fave_resetpassword_nonce');
  // $_REQUEST['security'] = $nonce;

  if (!create_nonce_or_throw_error('security', 'fave_resetpassword_nonce')) {
    return;
  }

  do_action("wp_ajax_nopriv_houzez_reset_password");//houzez_reset_password();

}

function updatePassword()
{
  //create nonce for this request.
  // $nonce = wp_create_nonce('fave_resetpassword_nonce');
  // $_REQUEST['security'] = $nonce;
  // $nonce = wp_create_nonce('houzez_pass_ajax_nonce');
  // $_REQUEST['houzez-security-pass'] = $nonce;

  if (!create_nonce_or_throw_error('houzez-security-pass', 'houzez_pass_ajax_nonce')) {
    return;
  }

  //newpass, confirmpass
  do_action("wp_ajax_houzez_ajax_password_reset");//houzez_reset_password();

}

function deleteUserAccount()
{
  require_once (ABSPATH . 'wp-admin/includes/user.php');
  do_action("wp_ajax_houzez_delete_account");//houzez_reset_password();

}

function sendContactMail(WP_REST_Request $request)
{
  $response = array(
    'status' => 304,
    'message' => 'There was an error sending the form.'
  );

  $siteName = wp_strip_all_tags(trim(get_option('blogname')));
  $source = $request['source'];
  $contactName = $request['name'];
  $contactEmail = $request['email'];
  $contactMessage = $request['message'];
  $website = $request['website'];
  $phone = $request['phone'];

  $subject = (get_option('houzi_contact_form_subject') ?: "[$source] New message from $contactName");

  $body = "<p><b>Name:</b> $contactName</p>";
  $body .= "<p><b>Email:</b> $contactEmail</p>";

  if (!empty($phone)) {
    $body .= "<p><b>Phone:</b> $phone</p>";
  }
  if (!empty($website)) {
    $body .= "<p><b>Website:</b> $website</p>";
  }

  $body .= "<p><b>Message:</b> $contactMessage</p>";
  $body .= "<p><b>Source:</b> $source</p>";

  $to = get_option( 'houzi_contact_form_email' ) ? get_option( 'houzi_contact_form_email' ) : get_option( 'admin_email' );
  $headers = array(
    'Content-Type: text/html; charset=UTF-8',
    "Reply-To: $contactName <$contactEmail>",
  );

  if (wp_mail($to, $subject, $body, $headers)) {
    $response['status'] = 200;
    $response['message'] = 'Message sent successfully.';
    //$response['test'] = $body;
  }

  return new WP_REST_Response($response);
}

if (!function_exists('houzez_get_profile_thumb')) {
  function houzez_get_profile_thumb($user_id = null)
  {

    if (empty($user_id)) {
      $user_id = get_the_author_meta('ID');
    }

    $author_picture_id = get_the_author_meta('fave_author_picture_id', $user_id);
    $user_custom_picture = get_the_author_meta('fave_author_custom_picture', $user_id);

    if (!empty($author_picture_id)) {
      $author_picture_id = intval($author_picture_id);
      if ($author_picture_id) {
        $img = wp_get_attachment_image_src($author_picture_id, 'thumbnail');
        $user_custom_picture = $img[0];

      }
    }

    if ($user_custom_picture == '') {
      $user_custom_picture = HOUZEZ_IMAGE . 'profile-avatar.png';
    }

    return $user_custom_picture;
  }
}

function fetchProfile()
{
  do_action('litespeed_control_set_nocache', 'nocache due to logged in');

  if (!is_user_logged_in()) {
    $ajax_response = array('success' => false, 'reason' => 'Please provide user auth.');
    wp_send_json($ajax_response, 403);
    return;
  }
  $userID = get_current_user_id();
  if (isset($_GET["user_id"]) && !empty($_GET["user_id"])) {
    $userID = $_GET["user_id"];
  }
  $response = array();

  $response['success'] = true;

  $user = get_user_by('id', $userID);

  $user_custom_picture = get_the_author_meta('fave_author_custom_picture', $userID);
  $author_picture_id = get_the_author_meta('fave_author_picture_id', $userID);
  if (!empty($author_picture_id)) {
    $author_picture_id = intval($author_picture_id);
    if ($author_picture_id) {
      $user->profile = wp_get_attachment_image_url($author_picture_id, 'large');
    }
  } else {
    $user->profile = esc_url($user_custom_picture);

  }

  $user->username = get_the_author_meta('user_login', $userID);
  $user->user_title = get_the_author_meta('fave_author_title', $userID);
  $user->first_name = get_the_author_meta('first_name', $userID);
  $user->last_name = get_the_author_meta('last_name', $userID);
  $user->user_email = get_the_author_meta('user_email', $userID);
  $user->user_mobile = get_the_author_meta('fave_author_mobile', $userID);
  $user->user_whatsapp = get_the_author_meta('fave_author_whatsapp', $userID);
  $user->user_phone = get_the_author_meta('fave_author_phone', $userID);
  $user->description = get_the_author_meta('description', $userID);
  $user->userlangs = get_the_author_meta('fave_author_language', $userID);
  $user->user_company = get_the_author_meta('fave_author_company', $userID);
  $user->tax_number = get_the_author_meta('fave_author_tax_no', $userID);
  $user->fax_number = get_the_author_meta('fave_author_fax', $userID);
  $user->user_address = get_the_author_meta('fave_author_address', $userID);
  $user->service_areas = get_the_author_meta('fave_author_service_areas', $userID);
  $user->specialties = get_the_author_meta('fave_author_specialties', $userID);
  $user->license = get_the_author_meta('fave_author_license', $userID);
  $user->gdpr_agreement = get_the_author_meta('gdpr_agreement', $userID);
  $user->facebook = get_the_author_meta('fave_author_facebook', $userID);
  $user->twitter = get_the_author_meta('fave_author_twitter', $userID);
  $user->linkedin = get_the_author_meta('fave_author_linkedin', $userID);
  $user->instagram = get_the_author_meta('fave_author_instagram', $userID);
  $user->youtube = get_the_author_meta('fave_author_youtube', $userID);
  $user->telegram = get_the_author_meta('fave_author_telegram', $userID);
  $user->line_id = get_the_author_meta('fave_author_line_id', $userID);
  $user->website = get_the_author_meta('user_url', $userID);
  $user->author_picture_id = $author_picture_id;

  unset($user->user_pass);
  unset($user->user_activation_key);
  unset($user->allcaps);

  $user_agent_id = get_the_author_meta('fave_author_agent_id', $userID);
  $user_agency_id = get_the_author_meta('fave_author_agency_id', $userID);

  if (!empty($user_agent_id)) {
    $user->fave_author_agent_id = $user_agent_id;

  } else if (!empty($user_agency_id)) {
    $user->user_agency_id = $user_agency_id;
  }

  $public_display = array();
  $public_display[] = $user->user_login;
  $public_display[] = $user->nickname;

  if (!empty($user->first_name)) {
    $public_display[] = $user->first_name;
  }

  if (!empty($user->last_name)) {
    $public_display[] = $user->last_name;
  }

  if (!empty($user->first_name) && !empty($user->last_name)) {
    $public_display[] = $user->first_name . ' ' . $user->last_name;
    $public_display[] = $user->last_name . ' ' . $user->first_name;
  }

  if (!in_array($user->display_name, $public_display)) {
    $public_display[] = $user->display_name;

    //$public_display = array_unique( $public_display );
  }
  $user->display_name_options = $public_display;

  $response["user"] = $user;



  wp_send_json($response, 200);
}

function editProfile()
{
  if (!is_user_logged_in()) {
    $ajax_response = array('success' => false, 'reason' => 'Please provide user auth.');
    wp_send_json($ajax_response, 403);
    return;
  }
  // $userID = get_current_user_id();
  global $current_user;
  wp_get_current_user();
  $userID = $current_user->ID;

  $user_agent_id = get_the_author_meta('fave_author_agent_id', $userID);
  $user_agency_id = get_the_author_meta('fave_author_agency_id', $userID);

  if (!empty($user_agent_id)) {
    //purge light-speed cache for this agent post type.
    do_action('litespeed_purge_post', $user_agent_id);
  } else if (!empty($user_agency_id)) {
    //purge light-speed cache for this agency post type.
    do_action('litespeed_purge_post', $user_agency_id);
  }


  // $nonce = wp_create_nonce('houzez_profile_ajax_nonce');
  // $_REQUEST['houzez-security-profile'] = $nonce;

  if (!create_nonce_or_throw_error('houzez-security-profile', 'houzez_profile_ajax_nonce')) {
    return;
  }

  do_action("wp_ajax_houzez_ajax_update_profile");
}
// on some houzez instances, post type agent or agency photo get cleared after profile fields update (eg, name phone etc).
// not sure about the reason, too complex to edit theme, so fix on app side.
// so after editing profile via editProfile(), call this web service from app.
function fixProfilePicture()
{
  if (!is_user_logged_in()) {
    $ajax_response = array('success' => false, 'reason' => 'Please provide user auth.');
    wp_send_json($ajax_response, 403);
    return;
  }
  $userID = get_current_user_id();

  $profile_attach_id = get_the_author_meta('fave_author_picture_id', $userID);
  $thumbnail_url = wp_get_attachment_image_src($profile_attach_id, 'large');

  update_user_meta($userID, 'fave_author_picture_id', $profile_attach_id);
  update_user_meta($userID, 'fave_author_custom_picture', $thumbnail_url[0]);

  $user_agent_id = get_the_author_meta('fave_author_agent_id', $userID);
  $user_agency_id = get_the_author_meta('fave_author_agency_id', $userID);

  if (!empty($user_agent_id)) {
    update_post_meta($user_agent_id, '_thumbnail_id', $profile_attach_id);

  } else if (!empty($user_agency_id)) {
    update_post_meta($user_agency_id, '_thumbnail_id', $profile_attach_id);
  }
  $ajax_response = array('success' => true, 'data' => array("pic_id" => $profile_attach_id, "url" => $thumbnail_url[0]));
  wp_send_json($ajax_response, 200);
}

function editProfilePhoto()
{
  if (!is_user_logged_in()) {
    $ajax_response = array('success' => false, 'reason' => 'Please provide user auth.');
    wp_send_json($ajax_response, 403);
    return;
  }

  if (!isset($_FILES['houzez_file_data_name'])) {
    $ajax_response = array('success' => false, 'reason' => 'Please provide photo houzez_file_data_name');

    wp_send_json($ajax_response, 400);
    return;
  }
  $userID = get_current_user_id();

  $user_agent_id = get_the_author_meta('fave_author_agent_id', $userID);
  $user_agency_id = get_the_author_meta('fave_author_agency_id', $userID);

  if (!empty($user_agent_id)) {
    //purge light-speed cache for this agent post type.
    do_action('litespeed_purge_post', $user_agent_id);
  } else if (!empty($user_agency_id)) {
    //purge light-speed cache for this agency post type.
    do_action('litespeed_purge_post', $user_agency_id);
  }

  // $nonce = wp_create_nonce('houzez_upload_nonce');
  // $_REQUEST['verify_nonce'] = $nonce;

  if (!create_nonce_or_throw_error('verify_nonce', 'houzez_upload_nonce')) {
    return;
  }

  $_REQUEST['user_id'] = $userID;

  require_once (ABSPATH . "wp-admin" . '/includes/image.php');

  do_action("wp_ajax_houzez_user_picture_upload");

}




// Used when doing social login.
add_filter('check_password', 'check_with_hashed_password', 10, 4);

/** 
 *      we want to authenticate user with hashed password, 
 *      For that we append a secrte, to check only when the secret has been appended
 *      with the password.
 *      so when the password contains our secret, this means, we're trying to 
 *      authenticate with hashed password.
 *      in that case, only compare password (already hashed) with wordpress hashed password.
 *      looking for better approach
 * 
 * Hooks into check_password filter, mostly copied from md5 upgrade function with pluggable.php/wp_check_password
 *
 * @param string $check
 * @param string $password
 * @param string $hash
 * @param string $user_id
 * @return results of sha1 hash comparison, or $check if $password is not a SHA1 hash
 */

function check_with_hashed_password($check, $password, $hash, $user_id)
{
  $secret = '<6Bk-l1hPHr*?}V?v6~!-[cxvmjm4@9emdY+jezhH5z;5{rZiUFo|$W8X6llE_Hm';

  //check if password contains our secret.
  if (strpos($password, $secret) !== FALSE) {
    //remove our secret from password.
    $password = str_replace($secret, "", $password);

    //compare wordpress provided hash with (already hashed) password.
    $check = ($hash == $password);

    if ($check && $user_id) {
      // Allow login
      return true;
    } else {

      //provided hash doesn't meet wordpress hash pass.
      return false;
    }
  }
  //echo 'is not hash';
  //not appended with our hash, so bailing.
  return $check;
}

/**
 * Check if provided string is a SHA1 hash
 */
function is_sha1($str)
{
  return (bool) preg_match('/^[0-9a-f]{40}$/i', $str);
}

function paymentStatus($request)
{
  if (!is_user_logged_in()) {
    $ajax_response = array('success' => false, 'reason' => 'Please provide user auth.');
    wp_send_json($ajax_response, 403);
    return;
  }
  $userID = get_current_user_id();
  $agent_agency_id = houzez_get_agent_agency_id( $userID );

  if( $agent_agency_id ) {
    $userID = $agent_agency_id;
  }
  $enable_paid_submission = houzez_option('enable_paid_submission');
  $remaining_listings = houzez_get_remaining_listings($userID);
  $featured_remaining_listings = houzez_get_featured_remaining_listings($userID);

  $payment_page = houzez_get_template_link('template/template-payment.php');

  $user_has_membership = houzez_user_has_membership($userID);
  $response['enable_paid_submission'] = $enable_paid_submission;
  $response['remaining_listings'] = $remaining_listings;
  $response['featured_remaining_listings'] = $featured_remaining_listings;
  $response['payment_page'] = $payment_page;
  $response['user_has_membership'] = $user_has_membership;
  $response['user_had_free_package'] = houzez_user_had_free_package($userID);
  $response['agent_agency_id'] = !$agent_agency_id ? "" : $agent_agency_id;
  wp_send_json($response, 200);
}

function proceedPayment($request)
{
  // if (!is_user_logged_in() ) {
  //   $ajax_response = array( 'success' => false, 'reason' => 'Please provide user auth.' );
  //   wp_send_json($ajax_response, 403);
  //   return; 
  // }
  // if(empty($_POST["listing_id"])) {
  //   $ajax_response = array( 'success' => false, 'reason' => 'Please provide property id in listing_id.' );
  //   wp_send_json($ajax_response, 404);
  //   return;
  // }
  $user_id = $_GET['user_id'];
  $user = get_user_by('id', $user_id);
  if ($user) {
    wp_set_current_user($user_id, $user->user_login);
    wp_set_auth_cookie($user_id);
    do_action('wp_login', $user->user_login, $user);
  }
  // $payment_page = houzez_get_template_link('template/template-payment.php');
  // $payment_page_link = add_query_arg( 'prop-id', $post_id, $payment_page );
  // $payment_page_link_featured = add_query_arg( 'upgrade_id', $post_id, $payment_page );

  // $payment_status = get_post_meta( get_the_ID(), 'fave_payment_status', true );
  // wp_send_json($response, 200);
  //wp_redirect( houzez_get_template_link('template/template-payment.php') ); exit;
  wp_redirect(home_url());
  exit;
}

function proceedWithPayment($request)
{
  if (!is_user_logged_in()) {
    $ajax_response = array('success' => false, 'reason' => 'Please provide user auth.');
    wp_send_json($ajax_response, 403);
    return;
  }

  $userID = get_current_user_id();
  $admin_email = get_bloginfo('admin_email');
  $listings_admin_approved = houzez_option('listings_admin_approved');
  $enable_paid_submission = houzez_option('enable_paid_submission');
  $is_prop_featured = intval($_POST['is_prop_featured']) ?? 0;
  $prop_id = $request['prop_id'] ?? '';
  $paymentMethod = $request['iap'] ?? '';
  $iap_response = $request['iap_response'] ?? [];
  $pack_id = $request['pack_id'] ?? '';
  $pack_price = get_post_meta($pack_id, 'fave_package_price', true);
  $is_featured = 0;
  $is_upgrade = 0;
  $time = time();
  $date = date('Y-m-d H:i:s', $time);
  // $total_taxes = 0;
  // $pack_tax = get_post_meta( $selected_package_id, 'fave_package_tax', true );

  if ($enable_paid_submission == 'free_paid_listing') {

    if (empty($prop_id)) {
      $ajax_response = array('success' => false, 'reason' => 'Please provide property id.');
      wp_send_json($ajax_response, 403);
      return;
    }

    if (empty($paymentMethod)) {
      $ajax_response = array('success' => false, 'reason' => 'Please provide payment method.');
      wp_send_json($ajax_response, 403);
      return;
    }

    $invoiceID = houzez_generate_invoice('Upgrade to Featured', 'one_time', $prop_id, $date, $userID, 0, 1, '', $paymentMethod);
    update_post_meta($invoiceID, 'invoice_payment_status', 1);
    update_post_meta($prop_id, 'fave_featured', 1);
    update_post_meta($prop_id, 'houzez_featured_listing_date', current_time('mysql'));

    $args = array(
      'listing_title' => get_the_title($prop_id),
      'listing_id' => $prop_id,
      'invoice_no' => $invoiceID,
      'listing_url' => get_permalink($prop_id),
    );


    if (!empty($iap_response)) {
      $jsonData = json_decode($iap_response, true);
      foreach ($jsonData as $key => $value) {
        $args[$key] = $value;
      }
    }
    try {
      houzez_email_type($admin_email, 'admin_featured_submission_listing', $args);
    } catch(Exception $e) {  
      $args["email_error"] = $e->getMessage();  
    } 
    
    $ajax_response = array('success' => true, 'message' => 'Congratulation! your property is featured now', "args" => $args);
    wp_send_json($ajax_response, 200);

  } else if ($enable_paid_submission == 'per_listing') {
    if (empty($prop_id)) {
      $ajax_response = array('success' => false, 'reason' => 'Please provide property id.');
      wp_send_json($ajax_response, 403);
      return;
    }

    if (empty($paymentMethod)) {
      $ajax_response = array('success' => false, 'reason' => 'Please provide payment method.');
      wp_send_json($ajax_response, 403);
      return;
    }

    if ($is_prop_featured == 1) {
      update_post_meta($prop_id, 'fave_featured', 1);
      $invoiceID = houzez_generate_invoice('Listing with updated to Featured', 'one_time', $prop_id, $date, $userID, 1, 0, '', $paymentMethod);
    } else {
      update_post_meta($prop_id, 'fave_payment_status', 'paid');
      // if ($listings_admin_approved != 'yes') {
      $post = array(
        'ID' => $prop_id,
        'post_status' => 'publish'
      );

      $post_id = wp_update_post($post);
      // } else {
      //     $post = array(
      //         'ID'            => $prop_id,
      //         'post_status'   => 'pending'
      //     );

      //     $post_id = wp_update_post($post);
      // }
      $invoiceID = houzez_generate_invoice('Listing', 'one_time', $prop_id, $date, $userID, 0, 0, '', $paymentMethod);
    }

    update_post_meta($invoiceID, 'invoice_payment_status', 1);

    $args = array(
      'listing_title' => get_the_title($prop_id),
      'listing_id' => $prop_id,
      'invoice_no' => $invoiceID,
      'listing_url' => get_permalink($prop_id),
    );
    if (!empty($iap_response)) {
      $jsonData = json_decode($iap_response, true);
      foreach ($jsonData as $key => $value) {
        $args[$key] = $value;
      }
    }

    try {
      houzez_email_type($admin_email, 'admin_paid_submission_listing', $args);
    } catch(Exception $e) {  
      $args["email_error"] = $e->getMessage();  
    } 

    $message = ($is_prop_featured == 1) ? 'Congratulation! your property is featured now' : 'Congratulation! your property is published now';
    $ajax_response = array('success' => true, 'message' => $message, "args" => $args);
    wp_send_json($ajax_response, 200);

  } else if ($enable_paid_submission == 'membership') {
    if (empty($pack_id)) {
      $ajax_response = array('success' => false, 'reason' => 'Please provide package id.');
      wp_send_json($ajax_response, 403);
      return;
    }

    // Will handle tax in future
    // if( !empty($pack_tax) && !empty($pack_price) ) {
    //   $total_taxes = intval($pack_tax)/100 * $pack_price;
    //   $total_taxes = round($total_taxes, 2);
    // }


    // if( !empty($total_taxes) ) {
    //   $pack_price = $pack_price + $total_taxes;
    // }

    // First check if price zero then it's a trial package.
    $check_if_price_zero = floatval($pack_price);
    if ($check_if_price_zero > 0) {
      houzez_save_user_packages_record($userID, $pack_id);

      if (houzez_check_user_existing_package_status($userID, $pack_id)) {
        houzez_downgrade_package($userID, $pack_id);
        houzez_update_membership_package($userID, $pack_id);
      } else {
        houzez_update_membership_package($userID, $pack_id);
      }

      $invoiceID = houzez_generate_invoice('package', 'one_time', $pack_id, $date, $userID, $is_featured, $is_upgrade, '', $paymentMethod, 1);
      update_post_meta($invoiceID, 'invoice_payment_status', 1);
      update_user_meta($userID, 'houzez_is_recurring_membership', 0);
      update_user_meta($userID, 'houzez_payment_method', $paymentMethod);

      $jsonData = json_decode($iap_response, true);
      $args = array();
      if (!empty($iap_response)) {
        $jsonData = json_decode($iap_response, true);
        foreach ($jsonData as $key => $value) {
          $args[$key] = $value;
        }
      }

      // houzez_email_type($user_email, 'purchase_activated_pack', $args);
      $ajax_response = array('success' => true, 'message' => 'Congratulation! membership added.');
      wp_send_json($ajax_response, 200);
    } else {
      if (houzez_user_had_free_package($userID)) {

        $invoiceID = houzez_generate_invoice('package', 'one_time', $pack_id, $date, $userID, $is_featured, $is_upgrade, '', $paymentMethod, 1);

        houzez_save_user_packages_record($userID, $pack_id);

        houzez_update_membership_package($userID, $pack_id);
        update_post_meta($invoiceID, 'invoice_payment_status', 1);
        update_user_meta($userID, 'user_had_free_package', 'yes');

        $ajax_response = array('success' => true, 'message' => 'Congratulation! Free trial activated');
        wp_send_json($ajax_response, 200);
        return;
      } else {

        $ajax_response = array('success' => false, 'reason' => 'You have already used your free package, please choose different package.');
        wp_send_json($ajax_response, 403);
        return;
      }
    }
  }

}

function makePropertyFeatured()
{
  if (!is_user_logged_in()) {
    $ajax_response = array('success' => false, 'reason' => 'Please provide user auth.');
    wp_send_json($ajax_response, 403);
    return;
  }

  do_action("wp_ajax_houzez_make_prop_featured");
}

function removeFromFeatured()
{
  if (!is_user_logged_in()) {
    $ajax_response = array('success' => false, 'reason' => 'Please provide user auth.');
    wp_send_json($ajax_response, 403);
    return;
  }

  do_action("wp_ajax_houzez_remove_prop_featured");
}

function userCurrentPackage()
{
  if (!is_user_logged_in()) {
    $ajax_response = array('success' => false, 'reason' => 'Please provide user auth.');
    wp_send_json($ajax_response, 403);
    return;
  }
  $user_id = get_current_user_id();

  $agent_agency_id = houzez_get_agent_agency_id( $user_id );

  if( $agent_agency_id ) {
    $user_id = $agent_agency_id;
  }
	
  $remaining_listings = houzez_get_remaining_listings($user_id);
  $pack_featured_remaining_listings = houzez_get_featured_remaining_listings($user_id);
  $package_id = houzez_get_user_package_id($user_id);
  $packages_page_link = houzez_get_template_link('template/template-packages.php');
  // if( $remaining_listings == -1 ) {
  //   $remaining_listings = esc_html__('Unlimited', 'houzez');
  //   $ajax_response = array(
  //     'success' => true,
  //     'remaining_listings' => $remaining_listings,
  //   );

  //   wp_send_json($ajax_response, 200);
  // }

  if (!empty($package_id)) {
    $seconds = 0;
    $pack_title = get_the_title($package_id);
    $pack_listings = get_post_meta($package_id, 'fave_package_listings', true);
    $pack_unlimited_listings = get_post_meta($package_id, 'fave_unlimited_listings', true);
    $pack_featured_listings = get_post_meta($package_id, 'fave_package_featured_listings', true);
    $pack_billing_period = get_post_meta($package_id, 'fave_billing_time_unit', true);
    $pack_billing_frequency = get_post_meta($package_id, 'fave_billing_unit', true);
    $pack_date = strtotime(get_user_meta($user_id, 'package_activation', true));

    switch ($pack_billing_period) {
      case 'Day':
        $seconds = 60 * 60 * 24;
        break;
      case 'Week':
        $seconds = 60 * 60 * 24 * 7;
        break;
      case 'Month':
        $seconds = 60 * 60 * 24 * 30;
        break;
      case 'Year':
        $seconds = 60 * 60 * 24 * 365;
        break;
    }

    $pack_time_frame = $seconds * $pack_billing_frequency;
    $expired_date = $pack_date + $pack_time_frame;
    $expired_date = date_i18n(get_option('date_format'), $expired_date);

    $ajax_response = array(
      'success' => true,
      'remaining_listings' => $remaining_listings,
      'pack_featured_remaining_listings' => $pack_featured_remaining_listings,
      'package_id' => $package_id,
      'packages_page_link' => $packages_page_link,
      'pack_title' => $pack_title,
      'pack_listings' => $pack_listings,
      'pack_unlimited_listings' => $pack_unlimited_listings,
      'pack_featured_listings' => $pack_featured_listings,
      'pack_billing_period' => $pack_billing_period,
      'pack_billing_frequency' => $pack_billing_frequency,
      'pack_date' => $pack_date,
      'expired_date' => $expired_date,
      'agent_agency_id' => !$agent_agency_id ? "" : $agent_agency_id,
    );

    wp_send_json($ajax_response, 200);
  } else {
    $ajax_response = array(
      'success' => false,
      'reason' => "You don't have any membership.",
    );
    wp_send_json($ajax_response, 200);
  }
}

function convert_to_valid_username($string) {
  // Convert the string to lowercase
  $username = strtolower($string);
  
  // Remove all characters except letters, numbers, underscores, and dashes
  $username = preg_replace('/[^a-z0-9_\-]/', '', $username);
  
  // Optionally, replace spaces with underscores (or you could use dashes)
  $username = str_replace(' ', '_', $username);

  // Trim underscores or dashes from the beginning or end
  $username = trim($username, '_-');
  
  return $username;
}

//////////
//
//
add_filter( 'rest_prepare_user', 'custom_add_user_fields_to_rest', 10, 3 );

function custom_add_user_fields_to_rest( $response, $user, $request ) {
    $data = $response->get_data();
    
    // Check if user approval system is enabled
    $is_enabled = false;
    if (function_exists('houzez_login_option')) {
        $is_enabled = (int) houzez_login_option('enable_user_approval', 0) === 1;
    }

    if ($is_enabled) {
        // Include Houzez approval status
        $houzez_status = get_user_meta( $user->ID, 'houzez_account_approved', true );
        $data['houzez_account_approved'] = ($houzez_status !== '') ? intval($houzez_status) : 1;
        
        // Include user roles
        $data['roles'] = $user->roles;
        
        // Include email only if allowed
        if (current_user_can('list_users')) {
            $data['email'] = $user->user_email;
        } else {
            unset($data['email']); 
        }
    } else {
        // Remove fields when approval system is disabled
        unset($data['houzez_account_approved']);
        unset($data['roles']);
        unset($data['email']);
    }

    $response->set_data($data);
    return $response;
}

function fetch_all_users() {
    // Ensure REST server is available
    if (!function_exists('rest_get_server')) {
        require_once ABSPATH . 'wp-includes/rest-api.php';
    }

    // Check if approval system is enabled
    $is_enabled = false;
    if (function_exists('houzez_login_option')) {
        $is_enabled = (int) houzez_login_option('enable_user_approval', 0) === 1;
    }

    // Make REST request to get users
    $request = new WP_REST_Request('GET', '/wp/v2/users');
    $request->set_query_params(array(
        'per_page' => 100,
    ));

    $server = rest_get_server();
    $response = $server->dispatch($request);

    if (is_wp_error($response) || $response->is_error()) {
        return new WP_Error('fetch_failed', 'Unable to fetch users', array('status' => 500));
    }

    $users = $response->get_data();
    
    // If approval system is disabled, remove sensitive fields
    if (!$is_enabled) {
        foreach ($users as &$user) {
            unset($user['houzez_account_approved']);
            unset($user['roles']);
            unset($user['email']);
        }
    }

    return $users;
}