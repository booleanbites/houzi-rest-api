<?php
function addProperty(){
    $user_id  = 1; 
    // $user_id  = $request->get_header('user_id');
    // if($userID){
    //     wp_set_current_user($userID);
    // }
    $user = get_user_by( 'id', $user_id ); 
    if( $user ) {
        wp_set_current_user( $user_id, $user->user_login );
        wp_set_auth_cookie( $user_id );
        do_action( 'wp_login', $user->user_login, $user );
    }
    $new_property['post_status']    = 'publish';
    $new_property                   = apply_filters( 'houzez_submit_listing', $new_property );
    wp_send_json(['prop_id' => $new_property ],200);
}



