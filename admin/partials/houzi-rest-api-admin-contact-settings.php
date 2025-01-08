<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to show settings area of the plugin
 *
 * @link       https://booleanbites.com
 * @since      1.4.3.2
 *
 * @package    Houzi_Rest_Api
 * @subpackage Houzi_Rest_Api/admin/partials
 * @author     Adil Soomro
 * Jan 8, 2025
 */
class RestApiFormsSettings {
    private $plugin_name;
    private $version;

    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        //add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    public function add_settings_page() {
        add_options_page(
            'Contacts Settings',
            'Contacts Settings',
            'manage_options',
            'form_settings',
            array( $this, 'render_settings_page' )
        );
    }

    public function register_settings() {
        // Inquiry Form settings
        register_setting( 'form_settings_group', 'houzi_inquiry_form_subject', [ 'default' => 'New Property Request' ] );
        register_setting( 'form_settings_group', 'houzi_inquiry_form_email', [ 'default' => get_option( 'admin_email' ) ] );

        // Contact Form settings
        register_setting( 'form_settings_group', 'houzi_contact_form_subject', [ 'default' => 'New message from app' ] );
        register_setting( 'form_settings_group', 'houzi_contact_form_email', [ 'default' => get_option( 'admin_email' ) ] );
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
        <p>
            Configure email settings for your app's forms. Use the subject and recipient email in each section to ensure that form submissions are routed appropriately.
        </p>
            <form method="post" action="options.php">
                <?php settings_fields( 'form_settings_group' ); ?>
                <?php do_settings_sections( 'form_settings_group' ); ?>
                <table class="form-table">
                    <!-- Contact Form Section -->
                    
                    <th scope="row" colspan="3">Contact Us Form Settings</th>
                    <tr valign="top">
                        <th scope="row">Subject</th>
                        <td><input type="text" name="houzi_contact_form_subject" value="<?php echo esc_attr( get_option( 'houzi_contact_form_subject' ) ); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Email</th>
                        <td><input type="email" name="houzi_contact_form_email" value="<?php echo esc_attr( get_option( 'houzi_contact_form_email' ) ); ?>" /></td>
                    </tr>

                    <!-- Inquiry Form Section -->
                     
                    
                    <th scope="row" colspan="3">Inquiry Form Settings</th>
                    
                    <tr valign="top">
                        <th scope="row">Subject</th>
                        <td><input type="text" name="houzi_inquiry_form_subject" value="<?php echo esc_attr( get_option( 'houzi_inquiry_form_subject' ) ); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Email</th>
                        <td><input type="email" name="houzi_inquiry_form_email" value="<?php echo esc_attr( get_option( 'houzi_inquiry_form_email' ) ); ?>" /></td>
                    </tr>

                    
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}

?>
