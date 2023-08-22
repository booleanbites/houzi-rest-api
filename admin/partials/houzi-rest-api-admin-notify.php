<?php

use onesignal\client\api\DefaultApi;
use onesignal\client\Configuration;
use onesignal\client\model\Notification;
use onesignal\client\model\StringMap;

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to show notification configuration area of the plugin
 *
 * @link       https://booleanbites.com
 * @since      1.1.5
 *
 * @package    Houzi_Rest_Api
 * @subpackage Houzi_Rest_Api/admin/partials
 * @author Hasnain Somro
 * Feb 17, 2023
 */
class RestApiNotify
{
    private $houzi_notify_options;

    /**
     * The ID of this plugin.
     *
     * @since    1.1.5
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.1.5
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.1.5
     * @param      string    $plugin_name       The name of this plugin.
     * @param      string    $version    The version of this plugin.
     */
    public function __construct($plugin_name, $version)
    {

        $this->plugin_name = $plugin_name;
        $this->version = $version;

        add_action('admin_init', array($this, 'houzi_notify_page_init'));

        add_action('wp_ajax_test_notification', array($this, 'test_notification'));

        add_action('send_notification', array($this, 'parse_notification_data'), 10, 1);

        add_action('wp_mail', array($this, 'houzi_notify_email_handler'), 10, 1);

        add_action('update_option_houzi_notify_options', function ($old_value, $value) {
            do_action('litespeed_purge_all');
        }, 10, 2);

        $this->houzi_notify_options = get_option('houzi_notify_options');
    }

    function houzi_notify_email_handler($args)
    {
        error_log(json_encode(array_keys($args)));


        // $this->send_push_notification($args["subject"], $args["subject"], $args["to"]);
    }

    function parse_notification_data($args)
    {
        switch ($args['type']) {
            case 'rating':
                $author_id = get_post_field('post_author', $args['listing_id']);
                $author_email = get_the_author_meta('user_email', $author_id);

                error_log($author_email);

                break;
            case 'lead':
                error_log(json_encode($args));
                break;
        }

        // error_log(json_encode($args));
    }

    public function houzi_notify_page_init()
    {

        register_setting(
            'houzi_notify_option_group', // option_group
            'houzi_notify_options', // option_name
            array($this, 'houzi_notify_sanitize') // sanitize_callback
        );
        add_settings_section(
            'notify', // id
            'OneSignal Configurations', // title
            array($this, 'houzi_notify_section_info'), // callback
            'houzi-rest-api&tab=notify' // page
        );
        add_settings_field(
            'onesingnal_app_id', // id
            'OneSingnal App ID', // title
            array($this, 'onesingnal_app_id_callback'), // callback
            'houzi-rest-api&tab=notify', // page
            'notify' // section
        );
        add_settings_field(
            'onesingnal_api_key_token', // id
            'OneSingnal API Key Token', // title
            array($this, 'onesingnal_api_key_token_callback'),// callback
            'houzi-rest-api&tab=notify', // page
            'notify' // section
        );
        add_settings_field(
            'onesingnal_user_key_token', // id
            'OneSingnal User Key Token', // title
            array($this, 'onesingnal_user_key_token_callback'), // callback
            'houzi-rest-api&tab=notify', // page
            'notify' // section
        );
    }

    public function houzi_notify_sanitize($input)
    {
        if (isset($input['onesingnal_app_id'])) {
            $sanitary_values['onesingnal_app_id'] = sanitize_text_field($input['onesingnal_app_id']);
        }
        if (isset($input['onesingnal_api_key_token'])) {
            $sanitary_values['onesingnal_api_key_token'] = sanitize_text_field($input['onesingnal_api_key_token']);
        }
        if (isset($input['onesingnal_user_key_token'])) {
            $sanitary_values['onesingnal_user_key_token'] = sanitize_text_field($input['onesingnal_user_key_token']);
        }

        return $sanitary_values;
    }
    
    public function houzi_notify_section_info()
    {
    }

    public function onesingnal_app_id_callback()
    {
        printf(
            '<input class="regular-text" type="text" name="houzi_notify_options[onesingnal_app_id]" id="onesingnal_app_id" value="%s" placeholder="xxxxxxxx-xxx-xxxx-xxxx-xxxxxxxxxxxx" required> <br><p>Go to <a href="https://app.onesignal.com/apps/">https://app.onesignal.com/apps/</a> page, find your application, and open it. You can find the APP ID in the URL.<br>Or go to settings tab and find in Keys and Ids section of settings.</p>',
            isset($this->houzi_notify_options['onesingnal_app_id']) ? esc_attr($this->houzi_notify_options['onesingnal_app_id']) : ''
        );
    }

    public function onesingnal_api_key_token_callback()
    {


        printf(
            '<input class="regular-text" type="password" name="houzi_notify_options[onesingnal_api_key_token]" id="onesingnal_api_key_token" value="%s" placeholder="••••••••••••••••••••••••••••••••••••••••••••" required><button type="button" id="hide-api-key" class="button button-secondary wp-hide-pw hide-if-no-js" data-toggle="0" aria-label="Show password"><span class="dashicons dashicons-visibility" aria-hidden="true"></span></button> <br><p>Go to https://app.onesignal.com/apps/YOUR_APP_ID/settings/keys_and_ids page.<br>If you don\'t have a Rest API Key already generated, please generate a new one by clicking the "Generate New API Key" button.</p>',
            isset($this->houzi_notify_options['onesingnal_api_key_token']) ? $this->houzi_notify_options['onesingnal_api_key_token'] : ''
        );
    }

    public function onesingnal_user_key_token_callback()
    {
        printf(
            '<input class="regular-text" type="password" name="houzi_notify_options[onesingnal_user_key_token]" id="onesingnal_user_key_token" value="%s" placeholder="••••••••••••••••••••••••••••••••••••••••••••" required><button type="button" id="hide-token-key" class="button button-secondary wp-hide-pw hide-if-no-js" data-toggle="0" aria-label="Show password"><span class="dashicons dashicons-visibility" aria-hidden="true"></span></button> <br><p>Go to <a href="https://app.onesignal.com/profile">https://app.onesignal.com/profile</a> page and scroll down to the "User Auth Key" section.<br>If you don\'t have a key already generated, please generate a new one by clicking the "Generate New User Auth Key" button.</p>',
            isset($this->houzi_notify_options['onesingnal_user_key_token']) ? $this->houzi_notify_options['onesingnal_user_key_token'] : ''
        );
    }

    public function houzi_notify_tab()
    {
        ?>
        <p>
            This tab facilitates communication between the Houzez website and the Houzi mobile app by
            enabling notifications to be sent to the app. This plugin leverages OneSignal as a notification sending manager,
            allowing for seamless and reliable delivery of notifications to users on the mobile app.
        </p>

        <form id="notification-tab-form" method="post" action="options.php">
            <?php
            settings_fields('houzi_notify_option_group');
            do_settings_sections('houzi-rest-api&tab=notify');
            ?>
            <?php
            submit_button();
            ?>
        </form>

        <hr style="border-top: 1px solid #bbb;">

        <div>
            <h2>Send OneSignal Notification Message (to Admins only)</h2>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">Notification Title</th>
                        <td>
                            <input class="regular-text" type="text" name="houzi_notify_options[notification_title]"
                                id="notification_title" value="Houzi Test Notification"
                                placeholder="Enter notification title here." required="">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Notification Message</th>
                        <td>
                            <input class="regular-text" type="text" name="houzi_notify_options[notification_message]"
                                id="notification_message" value="This a test notification from your WordPress website"
                                placeholder="Enter notification message here." required="">
                        </td>
                    </tr>
                </tbody>
            </table>
            <button id="test-one-signal-button" type="button" class="button button-primary">
                <?php esc_html_e('Send Notification Message', 'houzi'); ?>
            </button>
        </div>

        <?php
    }

    public function test_notification()
    {
        error_log("test notification");

        $config = Configuration::getDefaultConfiguration()
            ->setAppKeyToken($this->houzi_notify_options['onesingnal_api_key_token'])
            ->setUserKeyToken($this->houzi_notify_options['onesingnal_user_key_token']);

        $apiInstance = new DefaultApi(
            new GuzzleHttp\Client(),
            $config
        );

        $dataArray = $_POST['data'];

        $admin_users = get_users(
            array(
                'role__in' => array('administrator'),
                // Specify the role(s) of the admin user(s)
                'fields' => array('user_email'),
                // Retrieve only the email field
            )
        );

        // Loop through the admin users and retrieve their email addresses
        $admin_emails = array();
        foreach ($admin_users as $admin_user) {
            $admin_emails[] = sha1($admin_user->user_email);
        }

        error_log(implode($admin_emails));

        $notification = $this->createNotification($dataArray["title"], $dataArray["message"], $admin_emails);

        $result = $apiInstance->createNotification($notification);
        error_log($result);
    }

    function createNotification($enHeading, $enContent, $externalIds): Notification
    {
        $headingContent = new StringMap();
        $headingContent->setEn($enHeading);

        $messageContent = new StringMap();
        $messageContent->setEn($enContent);

        $notification = new Notification();
        $notification->setAppId($this->houzi_notify_options['onesingnal_app_id']);
        $notification->setHeadings($headingContent);
        $notification->setContents($messageContent);
        // $notification->setIncludedSegments(['Subscribed Users']);
        $notification->setIncludeExternalUserIds($externalIds);
        $notification->setChannelForExternalUserIds("push");

        return $notification;
    }

    public function send_push_notification($title, $message, $email)
    {
        $config = Configuration::getDefaultConfiguration()
            ->setAppKeyToken($this->houzi_notify_options['onesingnal_api_key_token'])
            ->setUserKeyToken($this->houzi_notify_options['onesingnal_user_key_token']);

        $apiInstance = new DefaultApi(
            new GuzzleHttp\Client(),
            $config
        );

        $notification = $this->createNotification($title, $message, array(sha1($email)));

        $result = $apiInstance->createNotification($notification);
        error_log($result);
    }
}