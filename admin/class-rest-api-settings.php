<?php
/**
 * Houzi preferences and api settings
 *
 *
 * @package Houzi Rest Api
 * @since Houzi 1.1.3
 * @author Adil Soomro
 */
class RestApiSettings
{
	private $houzi_rest_api_options;

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;


	private $settings;
	private $eleven;
	private $iap;
	private $notify;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct($plugin_name, $version)
	{

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		add_action('admin_menu', array($this, 'houzi_rest_api_add_plugin_page'));

		$this->settings = new RestApiAdminSettings($this->plugin_name,$this->version);
		$this->eleven = new RestApiElevenSettings($this->plugin_name,$this->version);
		$this->iap = new RestApiIAPProductIds($this->plugin_name,$this->version);
		$this->notify = new RestApiNotify($this->plugin_name, $this->version);
	}

	public function houzi_rest_api_add_plugin_page()
	{
		add_menu_page(
			'Houzi Rest Api',
			// page_title
			'Houzi Api',
			// menu_title
			'manage_options',
			// capability
			'houzi-rest-api',
			// menu_slug
			array($this, 'houzi_rest_api_create_admin_page'), // function
			HOUZI_IMAGE . 'houzi-logo.svg',
			// icon_url
			80 // position
		);
	}

	public function houzi_rest_api_create_admin_page()
	{
		$this->houzi_rest_api_options = get_option('houzi_rest_api_options');
		?>

		<div class="wrap">
			<h2>Houzi Rest Api</h2>
			<p>Extended Rest Api for mobile apps.
				<br />Developed for <a target="_blank" href="https://houzi.booleanbites.com">Houzi real estate app</a> by <a
					target="_blank" href="https://houzi.booleanbites.com">BooleanBites.com</a>
				<br />Ver:
				<?php echo HOUZI_REST_API_VERSION ?>
			</p>
			<?php settings_errors();

			$is_elevened = $this->is_elevened();
			$active_tab = $is_elevened ? 'settings' : 'p_code';

			if (isset($_GET['tab']) && $is_elevened) {
				$active_tab = $_GET['tab'];
			}
			?>
			<h2 class="nav-tab-wrapper">
			<?php if ($is_elevened) {?>
				<a href="?page=<?php echo $_GET['page']; ?>&tab=settings" class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>">Settings</a>
			<?php } ?>
			<a href="?page=<?php echo $_GET['page']; ?>&tab=iap" class="nav-tab <?php echo $active_tab == 'iap' ? 'nav-tab-active' : ''; ?>">In-App Purchase</a>

				<a href="?page=<?php echo $_GET['page']; ?>&tab=p_code" class="nav-tab <?php echo $active_tab == 'p_code' ? 'nav-tab-active' : ''; ?>">Purchase Code</a>
			</h2>
			<?php if ($is_elevened) { ?>
				<a href="?page=<?php echo $_GET['page']; ?>&tab=notify"
					class="nav-tab <?php echo $active_tab == 'notify' ? 'nav-tab-active' : ''; ?>">Push Notification</a>
			<?php } ?>

			<?php 
			if ( $active_tab == 'settings' ) {
				$this->settings->admin_settings();
			} else if ( $active_tab == 'p_code' ) {
				$this->eleven->eleven_settings();
			} else if ( $active_tab == 'iap' ) {
				$this->iap->render_settings_page();
			} else if ( $active_tab == 'notify' ) {
				$this->notify->houzi_notify_tab();
			}

			?>
		</div>
	<?php }
	private function is_elevened()
	{
		$houzi_eleven = get_option('houzi_eleven');
		$eleven_text = get_option('houzi_eleven_text');
		return !empty($houzi_eleven) && !empty($eleven_text);
	}
}