<?php
/**
 * CC Restrict Content Pro BuddyPress Bridge
 *
 * @package   CC Restrict Content Pro BuddyPress Bridge
 * @author    CARES staff
 * @license   GPL-2.0+
 * @copyright 2014 CommmunityCommons.org
 */

class CC_RCPBP {

	/**
	 * Plugin version, used for cache-busting of style and script file references.
	 *
	 * @since   1.0.0
	 *
	 * @var     string
	 */
	const VERSION = '1.0.0';

	/**
	 *
	 * Unique identifier for your plugin.
	 *
	 *
	 * The variable name is used as the text domain when internationalizing strings
	 * of text. Its value should match the Text Domain file header in the main
	 * plugin file.
	 *
	 * @since    1.0.0
	 *
	 * @var      string
	 */
	protected $plugin_slug = 'rcp-bp-extension';

	/**
	 * Instance of this class.
	 *
	 * @since    1.0.0
	 *
	 * @var      object
	 */
	protected static $instance = null;

	/**
	 * Initialize the plugin by setting localization and loading public scripts
	 * and styles.
	 *
	 * @since     1.0.0
	 */
	public function __construct() {

		$this->load_dependencies();

		// Load plugin text domain
		// add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

		// Activate plugin when new blog is added
		// add_action( 'wpmu_new_blog', array( $this, 'activate_new_site' ) );

		// Load public-facing style sheet and JavaScript.
		// add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		// add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Add the Restrict Content Pro management forms to the user's BP profile.
		add_action( 'bp_setup_nav', array( $this, 'add_subscription_management_screen' ) );

		// Change the submit button label on the subscription upgrade form.
		add_filter( 'rcp_registration_register_button', array( $this, 'filter_registration_button_label' ) );

		// We park some pieces of the plugin that we're not using.
		// Don't show the "restrict content" meta box on any post.
		remove_action( 'admin_menu', 'rcp_add_meta_boxes' );
		// Don't run the "protect content" query for premium content.
		// Note that if we wanted to use this, the exclusion would probably conflict with BP Docs.
		remove_action( 'pre_get_posts', 'rcp_hide_premium_posts', 99999 );

		// Filtering some variable pieces

		add_filter( 'rcp_return_url', array( $this, 'filter_rcp_return_url' ), 10, 2 );

		// Filter the results of RCP's registration page check.
		add_filter( 'rcp_is_registration_page', array( $this, 'filter_rcp_is_registration_page' ) );

		add_action('rcp_before_registration_submit_field', array( $this, 'add_terms_of_use_field' ), 9999 );
		add_action('rcp_form_errors', array( $this, 'check_terms_of_use' ) );

		// Let's monitor the sent values
		add_filter( 'rcp_subscription_data', array( $this, 'filter_rcp_subscription_data' ) );

		// Limit which plans get shown to users.
		add_filter( 'rcp_show_subscription_level', array( $this, 'filter_rcp_show_subscription_level' ), 10, 3 );

		// Filter the rcp_can_upgrade_subscription value
		add_filter( 'rcp_can_upgrade_subscription', array( $this, 'filter_rcp_can_upgrade_subscription' ), 10, 2 );

		// Record the IPN details
		add_action( 'rcp_valid_ipn', array( $this, 'filter_rcp_valid_ipn' ), 10, 3 );

		// Since we're thinking in terms of upgrades, we want the expiration date to always be based on today.
		// add_filter( 'rcp_member_renewal_expiration', array( $this, 'filter_rcp_member_renewal_expiration' ), 10, 3 );
	}

	/**
	 * Return the plugin slug.
	 *
	 * @since    1.0.0
	 *
	 * @return    Plugin slug variable.
	 */
	public function get_plugin_slug() {
		return $this->plugin_slug;
	}

	/**
	 * Return an instance of this class.
	 *
	 * @since     1.0.0
	 *
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Plugin_Name_Loader. Orchestrates the hooks of the plugin.
	 * - Plugin_Name_i18n. Defines internationalization functionality.
	 * - Plugin_Name_Admin. Defines all hooks for the dashboard.
	 * - Plugin_Name_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {
		// Load the views file.
		require_once( dirname( __FILE__ ) . '/views-user-profile.php' );

	}

	/**
	 * Fired when the plugin is activated.
	 *
	 * @since    1.0.0
	 *
	 * @param    boolean    $network_wide    True if WPMU superadmin uses
	 *                                       "Network Activate" action, false if
	 *                                       WPMU is disabled or plugin is
	 *                                       activated on an individual blog.
	 */
	public static function activate( $network_wide ) {

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {

			if ( $network_wide  ) {

				// Get all blog ids
				$blog_ids = self::get_blog_ids();

				foreach ( $blog_ids as $blog_id ) {

					switch_to_blog( $blog_id );
					self::single_activate();
				}

				restore_current_blog();

			} else {
				self::single_activate();
			}

		} else {
			self::single_activate();
		}

	}

	/**
	 * Fired when the plugin is deactivated.
	 *
	 * @since    1.0.0
	 *
	 * @param    boolean    $network_wide    True if WPMU superadmin uses
	 *                                       "Network Deactivate" action, false if
	 *                                       WPMU is disabled or plugin is
	 *                                       deactivated on an individual blog.
	 */
	public static function deactivate( $network_wide ) {

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {

			if ( $network_wide ) {

				// Get all blog ids
				$blog_ids = self::get_blog_ids();

				foreach ( $blog_ids as $blog_id ) {

					switch_to_blog( $blog_id );
					self::single_deactivate();

				}

				restore_current_blog();

			} else {
				self::single_deactivate();
			}

		} else {
			self::single_deactivate();
		}

	}

	/**
	 * Fired when a new site is activated with a WPMU environment.
	 *
	 * @since    1.0.0
	 *
	 * @param    int    $blog_id    ID of the new blog.
	 */
	public function activate_new_site( $blog_id ) {

		if ( 1 !== did_action( 'wpmu_new_blog' ) ) {
			return;
		}

		switch_to_blog( $blog_id );
		self::single_activate();
		restore_current_blog();

	}

	/**
	 * Get all blog ids of blogs in the current network that are:
	 * - not archived
	 * - not spam
	 * - not deleted
	 *
	 * @since    1.0.0
	 *
	 * @return   array|false    The blog ids, false if no matches.
	 */
	private static function get_blog_ids() {

		global $wpdb;

		// get an array of blog ids
		$sql = "SELECT blog_id FROM $wpdb->blogs
			WHERE archived = '0' AND spam = '0'
			AND deleted = '0'";

		return $wpdb->get_col( $sql );

	}

	/**
	 * Fired for each blog when the plugin is activated.
	 *
	 * @since    1.0.0
	 */
	private static function single_activate() {
		// @TODO: Define activation functionality here
	}

	/**
	 * Fired for each blog when the plugin is deactivated.
	 *
	 * @since    1.0.0
	 */
	private static function single_deactivate() {
		// @TODO: Define deactivation functionality here
	}

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		$domain = $this->plugin_slug;
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );

		load_textdomain( $domain, trailingslashit( WP_LANG_DIR ) . $domain . '/' . $domain . '-' . $locale . '.mo' );
		load_plugin_textdomain( $domain, FALSE, basename( plugin_dir_path( dirname( __FILE__ ) ) ) . '/languages/' );

	}

	/**
	 * Register and enqueue public-facing style sheet.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		// wp_enqueue_style( $this->plugin_slug . '-plugin-styles', plugins_url( 'css/public.css', __FILE__ ), array(), self::VERSION );
	}

	/**
	 * Register and enqueue public-facing JavaScript files.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_edit_scripts() {
	}


	/**
	 * Add the Restrict Content Pro management forms to the user's BP profile.
	 *
	 * @since    1.0.0
	 */
	public function add_subscription_management_screen() {
		bp_core_new_subnav_item( array(
			'name' 		  => __( 'Manage Your Subscription', $this->get_plugin_slug() ),
			'slug' 		  => 'subscription-management',
			'parent_slug'     => bp_get_settings_slug(),
			'parent_url' 	  => trailingslashit( bp_loggedin_user_domain() . bp_get_settings_slug() ),
			'screen_function' => array( $this, 'subscription_details' ),
			'position' 	  => 70,
			'user_has_access' => bp_is_my_profile() // Only the logged in user can access this on his/her profile
			)
		);
	}

	/**
	 * Add the Restrict Content Pro management forms to the user's BP profile.
	 * subscription_details() work with BP's component loader and theme-compat.
	 * subscription_details_screen_content() actually outputs the content.
	 *
	 * @since    1.0.0
	 */
	public function subscription_details() {
        //add title and content here - last is to call the members plugin.php template
        add_action( 'bp_template_content', array( $this, 'subscription_details_screen_content' ) );
        bp_core_load_template( apply_filters( 'bp_core_template_plugin', 'members/single/plugins' ) );
	}

	public function subscription_details_screen_content() {
		if ( $this->is_upgrade_screen() ) {
			echo rcp_registration_form();
		} else {
			// This is the root screen.
			if ( function_exists( 'rcp_get_template_part' ) ) {
				rcp_get_template_part( 'subscription' );
			}
		}
	}

	/**
	 * Are we viewing the user's upgrade screen?
	 *
	 * @since    1.0.0
	 * @return   bool
	 */
	public function is_upgrade_screen() {
		$retval = false;
		if ( bp_is_action_variable( 'upgrade' ) ) {
			$retval = true;
		}
		return $retval;
	}

	/**
	 * Change the submit button label on the subscription upgrade form.
	 *
	 * @since    1.0.0
	 * @param    string $label The default label text for RCP's "register" button.
	 * @return   string
	 */
	public function filter_registration_button_label( $label ) {
		$label = 'Finish Checkout via PayPal';

		return $label;
	}

	/**
	 * Filter the results of RCP's registration page check..
	 *
	 * @since    1.0.0
	 * @param    bool $is_reg Does RCP think this is the registration page?.
	 * @return   string
	 */
	public function filter_rcp_is_registration_page( $is_reg ) {
		if ( bp_is_my_profile() && $this->is_upgrade_screen() ) {
			$is_reg = true;
		}

		return $is_reg;
	}

	/**
	 * Filter RCP's global variables.
	 *
	 * @since    1.0.0
	 * @param    array $options The de-serialized RCP options array.
	 * @return   string
	 */
	public function filter_rcp_options_global_variable( $options ) {
		$towrite = PHP_EOL . '$rcp_options: ' . print_r($options, TRUE);
		$fp = fopen('rcp_global_variable.txt', 'a');
		fwrite($fp, $towrite);
		fclose($fp);

		return $options;
	}

	/**
	 * Filter RCP's global variables.
	 *
	 * @since    1.0.0
	 * @param    string $redirect The calculated redirect location.
	 * @param    int $user_id The current user id.
	 * @return   string
	 */
	public function filter_rcp_return_url( $redirect, $user_id ) {
		$towrite = PHP_EOL . '$redirect, before: ' . print_r( $redirect, TRUE );
		$towrite .= PHP_EOL . '$user_id: ' . print_r( $user_id, TRUE );

		$redirect = trailingslashit( bp_loggedin_user_domain() . bp_get_settings_slug() . '/subscription-management');
		$towrite .= PHP_EOL . '$redirect, after: ' . print_r( $redirect, TRUE );
		$fp = fopen('rcp_global_variable.txt', 'a');
		fwrite($fp, $towrite);
		fclose($fp);

		return $redirect;
	}

	// Add a terms & conditions checkbox
	public function add_terms_of_use_field() {
	?>
		<p style="width:100%; margin-bottom:1em;">
		<input name="rcp_terms_agreement" id="rcp_terms_agreement" class="required" type="checkbox"/>
		<label style="width:90%" for="rcp_terms_agreement">Read and Agree to <a href="/terms-of-service" target="_blank">Our Terms, Conditions and Privacy Policy</a> <em>(required)</em></label>
		</p>
	<?php
	}

	public function check_terms_of_use( $posted ) {
	    if( ! isset( $posted['rcp_terms_agreement'] ) ) {
	        // the field was not checked
	        rcp_errors()->add('agree_to_terms', __('You must agree to our Terms, Conditions and Privacy Policy', 'rcp'), 'register');
		}
	}


	/**
	 * Filter RCP's subscription data for troubleshooting.
	 *
	 * @since    1.0.0
	 * @param    string $redirect The calculated redirect location.
	 * @param    int $user_id The current user id.
	 * @return   string
	 */
	public function filter_rcp_subscription_data( $subscription_data ) {
		$towrite = PHP_EOL . '$subscription_data: ' . print_r( $subscription_data, TRUE );
		$fp = fopen('rcp_subscription_data.txt', 'a');
		fwrite($fp, $towrite);
		fclose($fp);

		return $subscription_data;
	}


	/**
	 * Filter the value calculated in rcp_can_upgrade_subscription().
	 *
	 * @since    1.0.0
	 * @param    bool $retval Value passed by RCP.
	 * @param    int $user_id The current user id.
	 * @return   bool
	 */
	public function filter_rcp_can_upgrade_subscription( $retval, $user_id ){
		if ( empty( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		if( ! rcp_is_active( $user_id ) || ! rcp_is_recurring( $user_id ) || $this->user_can_upgrade( $user_id ) ) {
			$retval = true;
		}

		return $retval;
	}


	/**
	 * Does this user have any plan he or she could upgrade to?
	 *
	 * @since    1.0.0
	 * @param    int $user_id The current user id.
	 * @return   bool
	 */
	public function user_can_upgrade( $user_id = 0 ){
		$retval = false;
		if ( empty( $user_id ) ) {
			$user_id = get_current_user_id();
		}
		$path = $this->user_upgrade_path( $user_id );

		if ( ! empty( $path ) ) {
			$retval = true;
		}

		return $retval;
	}

	/**
	 * Which plans can the user upgrade to?
	 *
	 * @since    1.0.0
	 * @param    int $user_id The current user id.
	 * @return   array of plan ids
	 */
	public function user_upgrade_path( $user_id = 0 ) {
		if ( empty( $user_id ) ) {
			$user_id = get_current_user_id();
		}
		$path = array();
		$plan_id = 0;
		$status = rcp_get_status( $user_id );

		if ( ! rcp_is_expired( $user_id ) && in_array( $status, array( 'active', 'cancelled' ) ) ) {
			$plan_id = rcp_get_subscription_id( $user_id );
		}

		if ( $plan_id ) {
			$path = $this->upgrade_paths( $plan_id );
		} else {
			// User isn't currently enrolled.
			$path = array( 1, 2, 3, 4 );
		}

		return $path;
	}

	/**
	 * See which plans are available to upgrade to from a particular plan.
	 *
	 * @since    1.0.0
	 * @param    int|string $plan Pass the ID of the plan you want to check.
	 *                            Or, send nothing or "all" to get the whole array.
	 * @param    int $user_id The current user id.
	 * @return   string
	 */
	public function upgrade_paths( $plan = 'all' ) {
		// 1 is uploader, monthly
		// 2 is data builder, monthly (DB has more capabilities than uploader)
		// 3 is uploader, yearly
		// 4 is data builder, yearly
		$paths = array(
			1 => array( 2, 3, 4 ),
			2 => array( 4 ),
			3 => array( 4 ),
			4 => array()
			);

		if ( in_array( $plan, array( 1, 2, 3, 4 ) ) ) {
			$retval = $paths[$plan];
		} else {
			$retval = $paths;
		}

		return $retval;
	}

	/**
	 * Filter which plans are shown to a user on the registration form.
	 *
	 * @since    1.0.0
	 * @param    bool $show     Whether to show this plan.
 	 * @param    int  $level_id Plan id
	 * @param    int  $user_id  The current user id.
	 * @return   string
	 */
	public function filter_rcp_show_subscription_level( $show, $level_id, $user_id ) {

		if ( bp_is_action_variable( 'upgrade' ) ) {

			if ( true == $show ) {
				// What plans are available to the user?
				$upgrade_paths = $this->user_upgrade_path( $user_id );

				$current_plan = rcp_get_subscription_id( $user_id );
				if ( ! empty( $current_plan ) ) {
					$upgrade_paths[] = $current_plan;
				}

				if ( ! in_array( $level_id, $upgrade_paths ) ) {
					$show = false;
				}
			}
		}

		return $show;
	}

	/**
	 * Record IPN data for troubleshooting.
	 *
	 * @since    1.0.0
	 * @return   string
	 */
	public function filter_rcp_valid_ipn( $payment_data, $user_id, $posted ) {
		$towrite = PHP_EOL . '$payment_data: ' . print_r($payment_data, TRUE);
		$towrite .= PHP_EOL . '$user_id: ' . print_r($user_id, TRUE);
		$towrite .= PHP_EOL . '$posted: ' . print_r($posted, TRUE);
		$fp = fopen('rcp_valid_ipn.txt', 'a');
		fwrite($fp, $towrite);
		fclose($fp);

	}

	/**
	 * Since we're thinking in terms of upgrades, we want the expiration date to always be based on today.
	 *
	 * @since    1.0.0
	 * @param    string $expiration     Expiration date.
 	 * @param    object  $subscription Plan id
	 * @param    int  $user_id  The current user id.
	 * @return   string
	 */
	public function filter_rcp_member_renewal_expiration( $expiration, $subscription, $user_id ) {
		$base_date  = current_time( 'timestamp' );

		if( $subscription->duration > 0 ) {

			$last_day       = cal_days_in_month( CAL_GREGORIAN, date( 'n', $base_date ), date( 'Y', $base_date ) );
			$expiration     = date( 'Y-m-d H:i:s', strtotime( '+' . $subscription->duration . ' ' . $subscription->duration_unit . ' 23:59:59', $base_date ) );

			if( date( 'j', $base_date ) == $last_day && 'day' != $subscription->duration_unit ) {
				$expiration = date( 'Y-m-d H:i:s', strtotime( $expiration . ' +2 days' ) );
			}

		} else {
			$expiration = 'none';
		}

		return $expiration;
	}


} // End class