<?php
/*
Plugin Name: Total Slider
Plugin URI: http://www.totalslider.com/
Description: The best experience for building sliders, with true WYSIWYG, drag & drop and more!
Version: 2.0-alpha
Author: Peter Upfold
Author URI: http://www.vanpattenmedia.com/
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: total_slider
/* ----------------------------------------------*/

/*  Copyright (C) 2011-2015 Peter Upfold.

    This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


/**
 * A constant used to avoid the direct browser access of Total Slider plugin PHP files.
 */
define( 'TOTAL_SLIDER_IN_FUNCTIONS', true );

/**
 * Defines the WordPress capability needed to manage Total Slider slides. This is attached to a WP role in the plugin's Settings page.
 */
define( 'TOTAL_SLIDER_REQUIRED_CAPABILITY', 'total_slider_manage_slides' ); 

/**
 * The maximum number of slide groups supported.
 */
define( 'TOTAL_SLIDER_MAX_SLIDE_GROUPS', 24 );

/**
 * The default pixel width used for cropping slide background images.
 */
define( 'TOTAL_SLIDER_DEFAULT_CROP_WIDTH', 600 );

/**
 * The default pixel height used for cropping slide background images.
 */
define( 'TOTAL_SLIDER_DEFAULT_CROP_HEIGHT', 300 );

/**
 * The current version of the Total Slider data format. Used to determine if a data format upgrade is needed.
 */
define( 'TOTAL_SLIDER_DATAFORMAT_VERSION', '2.0' );

/*VPM_4x_CONDITIONAL*/
if ( version_compare( get_bloginfo( 'version' ), '4.0', '>=' ) ) {
	/**
	 * If this is WordPress 4.0, we should load the extended media JavaScript includes.
	 */
	define( 'TOTAL_SLIDER_SHOULD_LOAD_EXTENDED_MEDIA_JS', true );
}


require_once( dirname(__FILE__) . '/includes/class.total-slide-group.php' );
require_once( dirname(__FILE__) . '/includes/class.total-slider-template.php' ); //TODO efficiency -- conditional on 'page'? What about the widget?

/******************************************** Total_Slider main class ********************************************/

/**
 * Class: The main Total Slider class used for initialisation and routing. 
 *
 */
class Total_Slider {


	/**
	 * Holds the slug of the Slide Group currently being worked on, or rendered.
	 *
	 * @var string|boolean
	 */
	public $slug = false;

	/**
	 * Holds the Template object of the Template currently being worked on, or rendered.
	 *
	 * @var Total_Slider_Template|boolean
	 */
	public $template = false;

	/**
	 * Holds any template errors that have occurred.
	 *
	 * @var Exception|boolean
	 */
	public $tpl_error = false;

	/**
	 * The list of allowed template locations -- 'builtin','theme','downloaded','legacy'
	 *
	 * @var array
	 */
	public static $allowed_template_locations = array(
		'builtin',
		'theme',
		'downloaded',
		'legacy'
	);

	/**
	 * The list of allowed Total Slider post statuses -- 'publish', or 'draft'
	 *
	 * @var array
	 */
	public static $allowed_post_statuses = array(
		'publish',
		'draft'
	);

	/**
	 * The full list of capabilities needed for a user role to manipulate Total Slider slides.
	 *
	 * @var array
	 */
	public static $required_capabilities = array(
		TOTAL_SLIDER_REQUIRED_CAPABILITY,
		'edit_total_slider_slides',
		'edit_others_total_slider_slides',
		'edit_published_total_slider_slides',
		'publish_total_slider_slides',	
		'delete_total_slider_slides',
		'delete_published_total_slider_slides',
		'delete_others_total_slider_slides',
		'edit_private_total_slider_slides',
		'delete_private_total_slider_slides',
		'read_private_total_slider_slides',
	);


	/* data structure

		a serialized array stored as a wp_option


		total_slider_slides_[slug]

			[0]
				id				[string] (generated by str_replace('.', '_', uniqid('', true)); )
				title			[string]
				description		[string]
				background		[string]
				link			[string]
				title_pos_x		[int]
				title_pos_y		[int]

			[1]
				id				[string] (generated by str_replace('.', '_', uniqid('', true)); )
				title			[string]
				description		[string]
				background		[string]
				link			[string]
				title_pos_x		[int]
				title_pos_y		[int]

			[2] ...

	*/

	/***********	// !Registration, first-time, etc.	***********/

	/**
	 * Constructor, which runs add_action for various WP hooks, etc.
	 *
	 * @return void
	 */
	public function __construct() {

		register_activation_hook( __FILE__, array ($this, 'create_slides_option_field' ) );
		add_action( 'init', array( $this, 'bootstrap_tinymce_plugin' ) );
		add_action( 'init', array( $this, 'initialize' ) );
		add_action( 'admin_menu', array( $this, 'add_admin_submenu' ) );
		add_action( 'admin_head', array( $this, 'print_admin_css' ) );
		add_action( 'widgets_init', array( $this, 'register_as_widget' ) );
		add_action( 'admin_init', array( $this, 'pass_control_to_ajax_handler' ) );
		add_action( 'admin_head-media-upload-popup', array( $this, 'print_uploader_javascript' ) );


		add_shortcode( 'totalslider', 'total_slider_shortcode' );
	}


	/**
	 * Upon plugin activation, creates the total_slider_slide_groups option in wp_options/
	 *
	 * This creates the total_slider_slide_groups option in wp_options if it does not already
	 * exist. Also set up a base capability for administrator to access the Slider Admin page,
	 * and configure some default general options.
	 *
	 * @return void
	 */
	public function create_slides_option_field() {
	
		global $current_user;
	
		$no_slide_groups = false;

		if ( ! get_option('total_slider_slide_groups') ) {
			$no_slide_groups = true;
			add_option( 'total_slider_slide_groups', array( ) ); // create with a blank array

		}

		// set the capability for administrator so they can visit the options page
		$this->set_capability_for_roles( array( 'administrator' ), 'preserve_existing' );
		
		get_currentuserinfo();
		
		// ensure that the current user can manage the plugin once installed (references #49)
		if ( current_user_can( 'install_plugins' ) ) {
			foreach( Total_Slider::$required_capabilities as $cap ) {
				$current_user->add_cap( $cap );
			}
		}

		// set up default general options

		if ( ! get_option('total_slider_general_options') )
		{
			add_option( 'total_slider_general_options', array(
				'should_enqueue_template'	=> 	'1',
				'should_show_tinymce_button' => '1'
			) );
		}
		
		
		/* Do not create the data format version if we are upgrading from v1.0.x, but upgrade() hasn't been called --
		   for example, the plugin has been removed and reactivated.
		   In this instance, we should run upgrade(), which will set the data format version and run other upgrade
		   tasks.
		*/
		if ( ! get_option('total_slider_dataformat_version') && !$no_slide_groups ) {
			return $this->upgrade();
		}
		
		if ( ! get_option('total_slider_dataformat_version') ) {
			add_option( 'total_slider_dataformat_version', TOTAL_SLIDER_DATAFORMAT_VERSION );
		}

	}
	
	/**
	 * Check to see if an upgrade to the data format is required, and run it if necessary.
	 *
	 * @return void
	 */
	public function upgrade() {

		$ts_class = &$this;

		if ( ! get_option('total_slider_dataformat_version') ) {
			// Total Slider has not been data-upgraded since before the version was introduced (1.0.x)
			
			// run an upgrade
			require_once( dirname(__FILE__) . '/includes/upgrade/v1.0.x-to-v1.1.php' );	
			
		}

		if ( version_compare( get_option( 'total_slider_dataformat_version' ), '2.0', '<' ) ) {
			// run upgrade to 2.0
			require_once( dirname(__FILE__) . '/includes/upgrade/v1.1.x-to-v2.0.php' );
		}
	
	}

	/**
	 * Register the Total Slider widget, so it can be used in a theme later.
	 *
	 * @return void
	 */
	public function register_as_widget() {

		register_widget( 'Total_Slider_Widget' );

	}

	/**
	 * Perform initialization, including loading the GetText domain and registering our post type.
	 *
	 * @return void
	 */
	public function initialize() {
		$this->load_text_domain();
		$this->register_cpt();
		$this->upgrade(); // upgrade wasn't otherwise being called soon enough, leaving possible blank slides during DF upgrade!

	}

	/**
	 * Load the GetText domain for this plugin's translatable strings.
	 *
	 * @return void
	 */
	private function load_text_domain() {

		load_plugin_textdomain( 'total-slider', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

	}

	/**
	 * Register the Total Slider Slide custom post type with WordPress.
	 *
	 * @return void
	 */
	private function register_cpt() {

		$labels = array(
			'name'              => _x( 'Groups', 'taxonomy general name', 'total-slider' ),
			'singular_name'     => _x( 'Group', 'taxonomy singular name', 'total-slider' ),
			'search_items'      => __( 'Search Groups', 'total-slider' ),
			'all_items'         => __( 'All Groups', 'total-slider' ),
			'parent_item'       => __( 'Parent Group', 'total-slider' ),
			'parent_item_colon' => __( 'Parent Group:', 'total-slider' ),
			'edit_item'         => __( 'Edit Group', 'total-slider' ),
			'update_item'       => __( 'Update Group', 'total-slider' ),
			'add_new_item'      => __( 'Add New Group', 'total-slider' ),
			'new_item_name'     => __( 'New Group Name', 'total-slider' ),
			'menu_name'         => __( 'Group', 'total-slider' ),
		);

		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => false,
			'show_admin_column' => false,
			'public'            => false,
			'query_var'         => false,
			'rewrite'           => array( 'slug' => 'total_slider_slide_group' ),
		);

		register_taxonomy( 'total_slider_slide_group', array( 'total_slider_slide' ), $args );	

		$labels = array(

			'name'               => _x( 'Slides', 'post type general name', 'total-slider' ),
			'singular_name'      => _x( 'Slide', 'post type singular name', 'total-slider' ),
			'menu_name'          => _x( 'Slides', 'admin menu', 'total-slider' ),
			'name_admin_bar'     => _x( 'Slide', 'add new on admin bar', 'total-slider' ),
			'add_new'            => _x( 'Add New', 'book', 'total-slider' ),
			'add_new_item'       => __( 'Add New Slide', 'total-slider' ),
			'new_item'           => __( 'New Slide', 'total-slider' ),
			'edit_item'          => __( 'Edit Slide', 'total-slider' ),
			'view_item'          => __( 'View Slide', 'total-slider' ),
			'all_items'          => __( 'All Slides', 'total-slider' ),
			'search_items'       => __( 'Search Slides', 'total-slider' ),
			'parent_item_colon'  => __( 'Parent Slides:', 'total-slider' ),
			'not_found'          => __( 'No slides found.', 'total-slider' ),
			'not_found_in_trash' => __( 'No slides found in Trash.', 'total-slider' )

		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => false,
			'show_in_menu'       => false,
			'query_var'          => false,
			'rewrite'            => array( 'slug' => 'total_slider_slide' ),
			'capability_type'    => 'total_slider_slide',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => null,
			'supports'           => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments', 'custom-fields' )
		);

		register_post_type( 'total_slider_slide', $args );
		
	}

	/***********	Utility Functions	***********/

	/**
	 * Sanitize a slide group slug, for accessing the wp_option row with that slug name.
	 *
	 * A wp_option name cannot be greater than 64 chars, so we truncate after 42 chars (63 - length of our option prefix),
	 * so as not to request a too-long wp_option name from MySQL.
	 * The create routine will handle if there is an existing name conflict due to the truncation.
	 *
	 * @param string $slug The slug to sanitize.
	 * @return void
	 */
	public static function sanitize_slide_group_slug( $slug ) {
	/*
		
	
	*/
		return substr ( preg_replace( '/[^a-zA-Z0-9_\-]/', '', $slug ), 0, ( 63 - strlen('total_slider_slides_' ) ) );
	}


	/**
	 * Filter a uniqid() derived string for output to the admin interface HTML.
	 *
	 * This removes any characters that are not alphanumeric or an underscore from the returned uniqid().
	 *
	 * @param string $id_to_filter The uniqid() derived string
	 * @return string
	 */
	private function id_filter( $id_to_filter ) {

		return preg_replace( '[^0-9a-zA-Z_]', '', $id_to_filter );

	}

	/**
	 * Redirect, from within the admin panel for this plugin, back to the plugin's main page.
	 *
	 * As the function name suggests, this is an undesirable hack.
	 *
	 * @param string $location Redirect to 'root' or 'edit-slide-group' pages.
	 * @param string $data The slide group slide to redirect, if 'edit-slide-group' is the $location.
	 * @return void
	 */
	public function ugly_js_redirect( $location, $data = false ) {
		switch ( $location ) {

			case 'root':
				$url = 'admin.php?page=total-slider';
			break;

			case 'edit-slide-group':
				$url = 'admin.php?page=total-slider&group=';
				$url .= esc_attr( $this->sanitize_slide_group_slug( $data ) );
			break;

			default:
				$url = 'admin.php?page=total-slider';
			break;

		}

		// erm, just a little bit of an ugly hack :(

		?><script type="text/javascript">window.location.replace('<?php echo $url; ?>');</script>
		<noscript><h1><a href="<?php echo esc_url($url); ?>"><?php _e( 'Please visit this page to continue', 'total-slider' ); ?></a></h1></noscript><?php
		die();

	}
	
	/**
	 * Determine which template to use, and its location, from the slide group's template attribute. 
	 *
	 * @return string
	 */
	public function determine_template() {

		if ( $this->template ) {
			return $this->template;
		}
		
		if ( ! $this->slug ) {
			if ( ! array_key_exists ('group', $_GET ) ) {
				return false;
			}
			
			$this->slug = $this->sanitize_slide_group_slug( $_GET['group'] );
		}
		
		$slide_group = new Total_Slide_Group($this->slug);
		
		if ( ! $slide_group->load() ) {
			return false;
		}
		
		$slug = $slide_group->template;
		$location = $slide_group->templateLocation;
		
		try {
			$this->template = new Total_Slider_Template( $slug, $location );
		}
		catch (Exception $e) {
			$this->tpl_error = $e;
		}
		
		return $this->template;
	
	}

	/**
	 * Set the TOTAL_SLIDER_REQUIRED_CAPABILITY capability against this role, so this role can manage slides.
	 *
	 * Will clear out the capability from all roles, then add it to both administrator and the specified roles.
	 * Administrators are always given access by this function.
	 *
	 * @param array $roles_to_set An array containing the roles to set, as strings.
	 * @param string $should_clear_first Either 'should_clear_first', or 'preserve_existing' -- whether to remove existing role assignments before setting new ones
	 * @return boolean
	 */
	public function set_capability_for_roles( $roles_to_set, $should_clear_first = 'should_clear_first' ) {
	
		global $wp_roles;

		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		$all_roles = get_editable_roles();
		$valid_roles = array_keys( $all_roles );

		if ( ! is_array( $all_roles ) || count( $all_roles ) < 1 ) {
			return false;
		}

		// clear the capability from all roles first
		if ( $should_clear_first == 'should_clear_first' ) {
			foreach ( $all_roles as $r_name => $r ) {
				foreach( Total_Slider::$required_capabilities as $cap ) {
					$wp_roles->remove_cap( $r_name, $cap );
				}
			}
		}

		// add the capability to 'administrator', which can always manage slides
		foreach( Total_Slider::$required_capabilities as $cap ) {
			$wp_roles->add_cap( 'administrator', $cap );
		}

		// add the capability to the specified $roles_to_set
		if ( is_array( $roles_to_set ) && count( $roles_to_set ) > 0 ) {
			
			foreach($roles_to_set as $the_role) {
				if ( in_array( $the_role, $valid_roles ) ) {
					foreach( Total_Slider::$required_capabilities as $cap ) {
						$wp_roles->add_cap( $the_role, $cap );
					}
				}
			}
		}

		return true;

	}
	
	/**
	 * Create a Total Slider WP_Widget from a [totalslider] shortcode in the post body and return its contents.
	 *
	 * @param array $atts An array containing the shortcode attributes. See WordPress Codex documentation. We desire a string, 'group', containing the slide group slug.
	 * @param string $content Not used by our shortcode handler. 
	 * @param string $tag Not used by our shortcode handler.
	 * @return string
	 */
	public function shortcode_handler($atts, $content, $tag) {

		extract( shortcode_atts( array(
			'group' => NULL
		), $atts ));
		
		// require a slide group
		if ( empty($group) ) {
			return __( '<strong>Total Slider:</strong> No slide group selected to show.', 'total-slider' );
		}
		
		// require a valid slide group
		if ( ! get_option( 'total_slider_slides_' . Total_Slider::sanitize_slide_group_slug( $group ) ) )
		{
			return __( '<strong>Total Slider:</strong> Could not find the selected slide group to show. Does it still exist?', 'total-slider' );
		}
		
		ob_start();
		
		the_widget( 'Total_Slider_Widget', array(
			'groupSlug' => Total_Slider::sanitize_slide_group_slug($group)
		) );
		
		$output = ob_get_contents();
		ob_end_clean();
		
		return $output;
	
	}

	/***********	// !Control passing, runtime UI setup, enqueuing etc.	***********/

	/**
	 * Intended to hook 'init', this function passes control to ajax_interface.php if a Total Slider action was requested.
	 *
	 * Note that this should use the admin_ajax XML interface in time, for improved adherence to WordPress
	 * standards.
	 *
	 * @return void
	 */
	public function pass_control_to_ajax_handler() {
		if (
			array_key_exists( 'page', $_GET ) &&
			'total-slider' == $_GET['page'] &&
			array_key_exists( 'total-slider-ajax', $_GET ) &&
			'true' == $_GET['total-slider-ajax']
		) {
			require_once( dirname(__FILE__) . '/includes/ajax_interface.php' );
		}

	}

	/**
	 * Add the Slider submenu to the WordPress admin sidebar.
	 *
	 * This function also will load in prerequisite CSS and JavaScript files if this admin page load
	 * is for the Total Slider admin pages.
	 *
	 * @return void
	 */
	public function add_admin_submenu() {
		if ( array_key_exists( 'page', $_GET ) && 'total-slider' == $_GET['page'] )
		{
		
			// this is a convenient point to upgrade our database if necessary
			$this->upgrade();
					
			// load .js if SCRIPT_DEBUG is true, or load .min.js otherwise
			$maybe_min = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : 'min.' ;
			
			wp_register_script(
			
				'total-slider-ejs', 										/* handle */
				plugin_dir_url( __FILE__ ).'js/ejs.' . $maybe_min . 'js',	/* src */
				array(
					'jquery', 'jquery-ui-draggable', 'jquery-ui-droppable',
					'jquery-ui-sortable'
				),															/* deps */
				date("YmdHis", @filemtime( plugin_dir_path( __FILE__ ) .
							'js/ejs.' . $maybe_min . 'js'	) ),			/* ver */
				true														/* in_footer */		
			);


			// get our JavaScript on
			wp_register_script(
			
				'total-slider-interface', 									/* handle */
				plugin_dir_url( __FILE__ ).'js/interface.' . $maybe_min . 'js',/* src */
				array(
					'jquery', 'jquery-ui-draggable', 'jquery-ui-droppable',
					'jquery-ui-sortable', 'total-slider-ejs'
				),															/* deps */
				date("YmdHis", @filemtime( plugin_dir_path( __FILE__ ) .
							'js/interface.' . $maybe_min . 'js'	) ),		/* ver */
				true														/* in_footer */		
			);
			
			
			wp_enqueue_script( 'jquery' );

			wp_enqueue_script( 'wp-ajax-response' );

			wp_enqueue_script( 'media' );
			wp_enqueue_script( 'media-upload' );

			if ( defined( 'TOTAL_SLIDER_SHOULD_LOAD_EXTENDED_MEDIA_JS' ) && TOTAL_SLIDER_SHOULD_LOAD_EXTENDED_MEDIA_JS ) {
				wp_enqueue_script( 'media-views' );
				wp_enqueue_script( 'media-editor' );
				wp_enqueue_script( 'media-grid' );
			}
			wp_enqueue_script( 'thickbox' );
			wp_enqueue_style( 'thickbox' );

			if ( function_exists( 'wp_enqueue_media' ) ) {
				wp_enqueue_media();
			}

			wp_enqueue_script( 'postbox' );

			wp_enqueue_script( 'jquery-ui-draggable' );
			wp_enqueue_script( 'jquery-ui-droppable' );
			wp_enqueue_script( 'jquery-ui-sortable' );

			wp_enqueue_style( 'wp-pointer' );
			wp_enqueue_script( 'wp-pointer' );
			
			wp_enqueue_script( 'total-slider-ejs' );
			
			wp_enqueue_script( 'total-slider-interface' );

			wp_localize_script( 'total-slider-interface', '_total_slider_L10n', $this->js_l10n() );	

			wp_register_style( 'total-slider-interface-styles', plugin_dir_url( __FILE__ ) . 'css/interface.css' );
			wp_enqueue_style( 'total-slider-interface-styles' );
			
			// enqueue the frontend so that the interface will be ready
			$this->enqueue_slider_frontend( 'backend' );	

			// load the WP_Pointer if we are on the Slides page
			if ( array_key_exists ('group', $_GET ) ) {
				add_action( 'admin_print_footer_scripts', array($this, 'print_help_pointer_js') );
			}

		}

		/* Top-level menu page */
		add_menu_page(

			__( 'Slider', 'total-slider' ),									/* title of options page */
			__( 'Slider', 'total-slider' ),									/* title of options menu item */
			TOTAL_SLIDER_REQUIRED_CAPABILITY,								/* permissions level */
			'total-slider',													/* menu slug */
			array( $this, 'print_slide_groups_page' ),				/* callback to print the page to output */
			plugin_dir_url( __FILE__ ) . 'img/total-slider-icon-16.png',	/* icon file */
			null 															/* menu position number */
		);

		/* First child, 'Slide Groups' */
		$submenu = add_submenu_page(

			'total-slider',										/* parent slug */
			__( 'Slide Groups', 'total-slider' ),				/* title of page */
			__( 'Slide Groups', 'total-slider' ),				/* title to use in menu */
			TOTAL_SLIDER_REQUIRED_CAPABILITY,					/* permissions level */
			'total-slider',										/* menu slug */
			array( $this, 'print_slide_groups_page' )	/* callback to print the page to output */

		);

		/* 'Settings' */
		add_submenu_page(

			'total-slider',										/* parent slug */
			__( 'Settings', 'total-slider' ),					/* title of page */
			__( 'Settings', 'total-slider' ),					/* title to use in menu */
			TOTAL_SLIDER_REQUIRED_CAPABILITY,					/* permissions level */
			'total-slider-settings',							/* menu slug */
			array($this, 'print_settings_page')		/* callback to print the page to output */

		);

		add_action( 'admin_head-'. $submenu, array($this, 'add_slides_help') );


	}

	/**
	 * Print the JavaScript used to display the WordPress Help Pointer.
	 *
	 * @return void
	 */
	public function print_help_pointer_js() {

		require( dirname( __FILE__ ) . '/admin/support/help-pointer-js.php' );

	}
	
	/**
	 * Add a reference to the ajax_interface.php access URL, so we can pass this to TinyMCE popup frames.
	 *
	 * This resolves an issue where TinyMCE popup frames need to access ajax_interface.php, but would not
	 * know where this file was located. To avoid hard-coding the plugins directory, this function spits out
	 * a JavaScript variable into the page with the correct URL to access ajax_interface.php, so this can
	 * be referenced by the TinyMCE popup. A bit of a hack.
	 *
	 * @return void
	 *
	 */
	public function print_js_admin_page_reference() {
		require( dirname( __FILE__ ) . '/admin/support/js-admin-page-reference.php' );	
	
	}

	/**
	 * When WP is enqueueing styles, inject our Slider CSS and JavaScript.
	 *
	 * We use the Template Manager to canonicalize the URIs and paths for the JS and CSS. If the $context is
	 * 'backend', we load the CSS only, but not the JavaScript.
	 *
	 * @param string $context Either 'frontend' (default) or 'backend'.
	 * @return void
	 */
	public function enqueue_slider_frontend( $context = 'frontend' )	{

	
		// load .min.js if available, if SCRIPT_DEBUG is not true in wp-config.php
		$is_min = ( defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ) ? false : true;

		$general_options = get_option( 'total_slider_general_options' );

		// do not run if enqueue is disabled
		if (
			is_array($general_options) &&
			array_key_exists( 'should_enqueue_template', $general_options ) &&
			'0' == $general_options['should_enqueue_template'] &&
			$context != 'backend'
		) {
			return false;
		}
		
		if ( ! $this->template || ! is_a( $this->template, 'Total_Slider_Template' ) )
		{	
			// determine the current template
			if ( ! $this->determine_template() )	{
				return false;		
			}
		}
		
		// load the CSS
		wp_register_style(		
			'total-slider-frontend',										/* handle */
			$this->template->css_uri(),									/* src */
			array(),														/* deps */
			date( 'YmdHis', @filemtime($this->template->css_path() ) ),	/* ver */
			'all'															/* media */
		);
		
		wp_enqueue_style( 'total-slider-frontend' );
		
		if ( 'backend' != $context ) {
			if ($is_min) {
				$js_uri = $this->template->js_min_uri();
				$js_path = $this->template->js_min_path();				
			}
			else {
				$js_uri = $this->template->js_uri();
				$js_path = $this->template->js_path();				
			}
			
			// enqueue the JS
			
			wp_register_script(	
				'total-slider-frontend',										/* handle */
				$js_uri,														/* src */
				array( 'jquery' ),												/* deps */
				date( 'YmdHis', @filemtime($jsPath) ),							/* ver */
				true															/* in_footer */					
			);
			
			wp_enqueue_script( 'total-slider-frontend' );
			
		}		

	}

	/**
	 * Add our help tab to the Slides page.
	 *
	 * @return void
	 */
	public function add_slides_help() {

		require( dirname( __FILE__ ) . '/admin/support/help.php' );

	}

	/**
	 * Return the object containing all i18n-capable strings in interface.js, ready for wp_localize_script().
	 * 
	 * @return array
	 */
	public function js_l10n()
	{
		return array (

			'switchEditWouldLoseChanges'	=> __( "You are still editing the current slide. Switching to a different slide will lose your changes.\n\nDo you want to lose your changes?", 'total-slider' ),
			'leavePageWouldLoseChanges'		=> __( 'You are still editing the current slide. Leaving this page will lose your changes.', 'total-slider' ),
				/* note:
					This message may or may not be shown. This is browser-dependent. All we can do in some cases is throw a generic
					“don’t leave, you haven’t saved yet” confirm box, which is better than nothing.
				*/
			'wouldLoseUnsavedChanges'		=> __( "You will lose any unsaved changes.\n\nAre you sure you want to lose these changes?", 'total-slider' ),
			'confirmDeleteOperation'		=> __( "Are you sure you want to delete this slide?\n\nThis action cannot be undone.", 'total-slider' ),
			'validationErrorIntroduction'		=> __( "Please correct the following errors with the form.\n\n", 'total-slider' ),
			'validationNoSlideTitle'		=> __( 'You must enter a slide title.', 'total-slider' ),
			'validationNoSlideDescription'		=> __( 'You must enter a slide description.', 'total-slider' ),
			'validationInvalidBackgroundURL'	=> __( 'The supplied background image URL is not a valid URL.', 'total-slider' ),
			'validationInvalidLinkURL'		=> __( 'The supplied external link is not a valid URL.', 'total-slider' ),
			'validationInvalidLinkID'		=> __( 'The supplied post ID for the slide link is not valid.', 'total-slider' ),
			'validationInvalidPostStatus'           => __( 'The supplied post status for this slide is not valid.', 'total-slider' ),
			'sortWillSaveSoon'			=> __( 'The new order will be saved when you save the new slide.', 'total-slider' ),
			'unableToResortSlides'			=> __( 'Sorry, unable to resort the slides.', 'total-slider' ),
			'newSlideTemplateUntitled'		=> __( 'untitled', 'total-slider' ),
			'newSlideTemplateNoText'		=> __( '(no text)', 'total-slider' ),
			'newSlideTemplateMove'			=> __( 'Move', 'total-slider' ),
			'newSlideTemplateDelete'		=> __( 'Delete', 'total-slider' ),
			'slideEditNoPostSelected'		=> __( 'No post selected.', 'total-slider' ),
			'publishButtonValue'			=> __( 'Publish', 'total-slider' ),
			'unableToGetSlide'			=> __( 'Sorry, unable to get that slide', 'total-slider' ),
			'slideDraftSaved'			=> __( 'Draft saved.', 'total-slider' ),
			'slidePublished'                        => __( 'Slide published.', 'total-slider' ),
			'uploadSlideBgImage'			=> __( 'Upload slide background image', 'total-slider' ),
			'unableToSaveSlide'			=> __( 'Sorry, unable to save the new slide.', 'total-slider' ),
			'unableToDeleteSlideNoID'		=> __( 'Unable to delete -- could not get the slide ID for the current slide.', 'total-slider' ),
			'unableToDeleteSlide'			=> __( 'Sorry, unable to delete the slide.', 'total-slider' ),
			'templateChangeWouldLoseData'		=> __( "Changing the template affects all slides in this group.\n\nAny custom positions for the title and description will be lost. You should review your slides after the change.\n\nDo you want to change the template?", 'total-slider' ),
			'mustFinishEditingFirst'		=> __( 'You must finish editing the slide before performing this action. Please either save your changes to the slide, or click Cancel.', 'total-slider' ),
			'uploadSlideBgButtonText'		=> __( 'Use as background image', 'total-slider' ),
			
		);

	}
	
	/**
	 * Bootstrap the setup of the Total Slider insert button on the rich text editing toolbar.
	 *
	 * @return void
	 *
	 */
	public function bootstrap_tinymce_plugin() {

		if ( !current_user_can('edit_posts') &&  ! current_user_can('edit_pages') )
		{
			return;
		}
		
		if ( 'true' == get_user_option( 'rich_editing' ) ) {
		
			// check option to see if we should add the button to the toolbar or not
			$general_options = get_option( 'total_slider_general_options' );
			
			if (
				is_array($general_options) &&
				array_key_exists('should_show_tinymce_button', $general_options) &&
				'1' == $general_options['should_show_tinymce_button']
			) {
				add_filter( 'mce_external_plugins', array( $this, 'register_tinymce_plugin' ) );
				add_filter( 'mce_buttons', array( $this, 'register_tinymce_button' ) );			
			}
		}

		add_action( 'admin_head', array( $this, 'print_js_admin_page_reference' ) );
		// we should always load the JS admin page reference -- see #58 at https://github.com/vanpattenmedia/total-slider/issues/58
	
	}
	
	/**
	 * Register our TinyMCE plugin JavaScript, so it can be run by TinyMCE.
	 *
	 * @param array $plugin_array The TinyMCE plugin array, which we modify and return
	 * @return array
	 */
	public function register_tinymce_plugin( $plugin_array ) {
		$plugin_array['total_slider_insert'] = plugin_dir_url( __FILE__ ) . 'tinymce-custom/mce/total_slider_insert/editor_plugin.js';
		return $plugin_array;
		
	}
	
	/**
	 * Add our new Total Slider button to the TinyMCE buttons toolbar.
	 *
	 * @param array $buttons The existing TinyMCE array of buttons, which we modify and retunr
	 * @return array
	 */
	public function register_tinymce_button ( $buttons ) {
		array_push( $buttons, 'separator', 'total_slider_insert' );
		return $buttons;
				
	}

	/***********	// !Print functions for each plugin admin page	***********/

	/**
	 * Print the actual page HTML for adding, deleting Slide Groups and offering the 'edit' buttons for a particular Group.
	 *
	 * @return void
	 *
	 */
	public function print_slide_groups_page() {

		global $TS_Total_Slider;

		require( dirname( __FILE__ ) . '/admin/slide-groups.php' );

	}

	/**
	 * Print the actual page HTML for adding, editing and removing the slides of a particular Slide Group.
	 *
	 * @return void
	 *
	 */
	public function print_slides_page() {
		global $TS_Total_Slider;

		require( dirname( __FILE__ ) . '/admin/slides.php' );

	}



	/**
	 * Print the actual page HTML for our settings page. Also handles Settings forms when submitted.
	 *
	 * @return void
	 */
	public function print_settings_page() {
		global $TS_Total_Slider;
		require( dirname( __FILE__ ) . '/admin/settings.php' );

	}

	/**
	 * Print the JavaScript to inject into the Media Uploader.
	 *
	 * @return void
	 *
	 */
	public function print_uploader_javascript() {
		require( dirname( __FILE__ ) . '/admin/support/uploader-javascript.php' );

	}

	/***********	// !Metabox printer callbacks	***********/

	/**
	 * Print the HTML for the credit/notes metabox
	 *
	 * @return void
	 *
	 */
	public function print_credits_metabox()
	{
		global $TS_Total_Slider;
		require( dirname( __FILE__) . '/admin/metaboxes/credits.php' );
	}

	/**
	 * Print the HTML for the slide sorter/slide listing metabox.
	 *
	 * @return void
	 */
	public function print_slide_sorter_metabox() {
		global $TS_Total_Slider;

		require( dirname( __FILE__ ) . '/admin/metaboxes/slide-sorter.php' );
	}
	
	/**
	 * Print the HTML for the slide template metabox.
	 *
	 * @return void
	 *
	 */
	public function print_slide_template_metabox() {

		global $TS_Total_Slider;
	
		if ( ! $this->slug ) {
			if ( ! array_key_exists('group', $_GET ) )
			{
				return false;
			}
			
			$this->slug = $this->sanitize_slide_group_slug( $_GET['group'] );
		}
		
		$slide_group = new Total_Slide_Group( $this->slug );
		if ( ! $slide_group->load() ) {
			return false;
		}
	
		?><div id="template-switch-controls">
			<p>
			<?php $t = new Total_Slider_Template_Iterator(); ?>
				<select name="template-slug" id="template-slug-selector">
					
					<?php $builtin = $t->discover_templates( 'builtin' ); ?>
					<?php if ( is_array( $builtin ) && count( $builtin ) > 0 ): ?>
					<optgroup label="<?php _e( 'Built-in', 'total-slider' ); ?>">
						<?php foreach( $builtin as $tpl ): ?>

							<option
								value="<?php echo esc_attr( $tpl['slug'] ); ?>"
								<?php if ( 'builtin' == $slide_group->templateLocation && $slide_group->template == $tpl['slug'] ): ?>
								selected="selected"
								<?php endif; ?>
								
							><?php echo esc_html( $tpl['name'] );?></option>
						<?php endforeach; ?>
					</optgroup>
					<?php endif; ?>
					
					<?php $theme = $t->discover_templates( 'theme' ); ?>
					<?php if ( is_array( $theme ) && count( $theme ) > 0 ): ?>
					<optgroup label="<?php _e( 'Theme', 'total-slider' ); ?>">
						<?php foreach( $theme as $tpl ): ?>
							<option
								value="<?php echo esc_attr( $tpl['slug'] ); ?>"
								<?php if ('theme' == $slide_group->templateLocation && $slide_group->template == $tpl['slug']): ?>
								selected="selected"
								<?php endif; ?>
								
							><?php echo esc_html( $tpl['name'] );?></option>
						<?php endforeach; ?>
					</optgroup>
					<?php endif; ?>
					
					<?php $legacy = $t->discover_templates( 'legacy', false ); ?>
					<?php if ( is_array( $legacy ) && count( $legacy ) > 0 ): ?>
					<optgroup label="<?php _e( 'v1.0 Templates', 'total-slider' ); ?>">
						<?php foreach( $legacy as $tpl ): ?>
							<option
							value="<?php echo esc_attr( $tpl['slug'] ); ?>"
							<?php if ('legacy' == $slide_group->templateLocation): ?>
								selected="selected"
								<?php endif; ?>
							><?php echo esc_html( $tpl['name'] );?></option>
						<?php endforeach; ?>
					</optgroup>								
					<?php endif; ?>										
			
					<?php //$downloaded = $t->discover_templates('downloaded'); ?>
					<?php $downloaded = false; ?>
					<?php if ( is_array( $downloaded ) && count( $downloaded ) > 0 ): ?>
					<!--<optgroup label="<?php _e( 'Downloaded', 'total-slider' );?>">
						<?php foreach( $downloaded as $tpl ): ?>
							<option
								value="<?php echo esc_attr( $tpl['slug'] ); ?>"
								<?php if ( 'downloaded' == $slide_group->templateLocation && $slideGroup->template == $tpl['slug'] ): ?>
								selected="selected"
								<?php endif; ?>
								
							><?php echo esc_html( $tpl['name'] );?></option>
						<?php endforeach; ?>																
					</optgroup>	-->
					<?php endif; ?>
													
				</select>
			<input id="template-switch-button" type="submit" class="button-secondary action" style="margin-top:8px; max-width:180px;" value="<?php _e( 'Change Template', 'total-slider' );?>" />
			</p>
		</div><?php	
		
	}

	/**
	 * Print the HTML for the slide preview metabox.
	 *
	 * @return void
	 *
	 */
	public function print_slide_preview_metabox() {
	
		global $TS_Total_Slider;
	
		require( dirname( __FILE__ ) . '/admin/metaboxes/slide-preview.php' );

	}

	/**
	 * Print the HTML for the slide editor metabox.
	 *
	 * @return void 
	 */
	public function print_slide_editor_metabox()
	{
		global $TS_Total_Slider;

		require( dirname( __FILE__ ) . '/admin/metaboxes/slide-editor.php' );

	}

	/**
	 * Print the CSS that shows our WordPress admin menu icon in the sidebar.
	 *
	 * @return void
	 *
	 */
	public function print_admin_css() {
		require( dirname( __FILE__ ) . '/admin/support/admin-css.php' );
	}


};

require_once( dirname( __FILE__ ) . '/includes/class.total-slider-widget.php' );


$TS_Total_Slider = new Total_Slider();

/**
 * Stub function that calls Total_Slider::shortcode_handler
 *
 * @param array $atts An array containing the shortcode attributes. See WordPress Codex documentation. We desire a string, 'group', containing the slide group slug.
 * @param string $content Not used by our shortcode handler. 
 * @param string $tag Not used by our shortcode handler.
 * @return string
 */
function total_slider_shortcode( $atts, $content, $tag ) {
	global $TS_Total_Slider;
	return $TS_Total_Slider->shortcode_handler( $atts, $content, $tag );
}

