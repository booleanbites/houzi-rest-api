<?php
/*
Plugin Name: Houzez Mobile Api
Plugin URI:  https://github.com/adilSoomro/houzez-mobile-api
Description: Enhance Rest Api for mobiles
Version:     1.1.2
Author:      BooleanBites
Author URI:  https://booleanbites.com/
License:     GPL2
*/

class Houzez_mobile_api {

	/**
     * Constructor
     *
     * @since 1.0
     *
    */
    public function __construct() {

        global $wp;
        $this->houzez_mobile_constants();
    	$this->houzez_mobile_inc_files();
        $this->setup_actions();
      //   $original_user_id = get_current_user_id(); 
    }

    /**
     * Define constants
     *
     * @since 1.0
     *
    */
    protected function houzez_mobile_constants() {
        /**
         * Plugin Path
         */
        define( 'HOUZEZ_MOBILE_FUNC_PATH', plugin_dir_path( __FILE__ ) );
    }

    /**
     * include files
     *
     * @since 1.0
     *
    */
    function houzez_mobile_inc_files() {
        
       
        require_once( HOUZEZ_MOBILE_FUNC_PATH . 'functions/property_search_functions.php');
        require_once( HOUZEZ_MOBILE_FUNC_PATH . 'functions/property_functions.php');
        require_once( HOUZEZ_MOBILE_FUNC_PATH . 'functions/property_data_functions.php');
        require_once( HOUZEZ_MOBILE_FUNC_PATH . 'functions/touch_base_functions.php');
        
        require_once( HOUZEZ_MOBILE_FUNC_PATH . 'functions/agent_agency_functions.php');
        require_once( HOUZEZ_MOBILE_FUNC_PATH . 'functions/crm_dashboard.php');
        require_once( HOUZEZ_MOBILE_FUNC_PATH . 'functions/user.php');
        require_once( HOUZEZ_MOBILE_FUNC_PATH . 'functions/property_review_functions.php');
        
    }

    /**
     * Sets up initial actions.
     *
     * @since  1.0.0
     * @access private
     * @return void
     */
    private function setup_actions() {
    }
}

/**
 * Instantiate the Class
 *
 * @since     1.0
 * @global    object
 */
$Houzez_mobile_api = new Houzez_mobile_api();
