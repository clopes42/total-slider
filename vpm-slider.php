<?php
/*
Plugin Name: VPM Slider
Plugin URI: http://www.vanpattenmedia.com/
Description: Allows the user to create, edit and remove ‘slides’ with text and images. MAKE ME BETTER.
Version: 1.0
Author: Peter Upfold
Author URI: http://vanpattenmedia.com/
License: GPL2
/* ----------------------------------------------*/

/*  Copyright (C) 2011-2012 Peter Upfold.

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

define('VPM_SLIDER_IN_FUNCTIONS', true);
define('VPM_SLIDER_REQUIRED_CAPABILITY', 'vpm_slider_manage_slides');
require_once(dirname(__FILE__).'/slides_backend.php');


class VPMSlider { // not actually a widget -- really a plugin admin panel
							//  the widget class comes later



	/* data strucutre
	
		a serialized array stored as a wp_option
		
		
		vpm_slider_slides
		
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
	
	public function createSlidesOptionField() {
	/*
		Upon plugin activation, creates the vpm_homepage_slides option
		in wp_options, if it does not already exist.
	*/
	
		if (!get_option('vpm_slider_slides')) {
		
			add_option('vpm_slider_slides', array()); // create with a blank array
		
		}
		
		// set the capability for administrator so they can visit the options page
		$admin = get_role('administrator');
		$admin->add_cap(VPM_SLIDER_REQUIRED_CAPABILITY);
	
	}
	
	private function getCurrentSlides() {
	/*
		Returns an array of the current slides in the database, in their 
		current precedence order.
	*/
		return get_option('vpm_slider_slides');	
	}
	
	private function idFilter($idToFilter)
	{
	/*
		Filter a uniqid string for output to the admin interface HTML.
	*/
	
		return preg_replace('[^0-9a-zA-Z_]', '', $idToFilter);
	
	}
	
	public function passControlToAjaxHandler()
	{
	/*
		If the user is trying to perform an Ajax action, immediately pass
		control over to ajax_interface.php.
		
		This should hook admin_init() (therefore be as light as possible).
	*/
	
		if (array_key_exists('page', $_GET) && $_GET['page'] == 'vpm-slider' &&
			array_key_exists('vpm-slider-ajax', $_GET) && $_GET['vpm-slider-ajax'] == 'true'
		)
		{
			require_once(dirname(__FILE__).'/ajax_interface.php');
		}		
	
	}

	public function addAdminSubMenu() {
		/*
			Add the submenu to the admin sidebar for the configuration screen.
		*/	
		
		if (array_key_exists('page', $_GET) && $_GET['page'] == 'vpm-slider')
		{
		
			// get our JavaScript on	
			wp_enqueue_script('jquery');
			wp_enqueue_script('jquery-ui');
			
			wp_enqueue_script('media-upload');
			wp_enqueue_script('thickbox');
			wp_enqueue_style('thickbox');
			
			wp_enqueue_script('jquery-ui-draggable');	
			wp_enqueue_script('jquery-ui-droppable');	
			wp_enqueue_script('jquery-ui-sortable');		

			wp_register_script('vpm-slider-interface', plugin_dir_url( __FILE__ ).'interface.js');
			wp_enqueue_script('vpm-slider-interface');	
			
			// load the rotator css
			wp_register_style('vpm-slider-rotator-styles', plugin_dir_url( __FILE__ ).'slider_edit.css');
			wp_enqueue_style('vpm-slider-rotator-styles');
			
			wp_register_style('vpm-slider-interface-styles', plugin_dir_url( __FILE__ ).'interface.css');
			wp_enqueue_style('vpm-slider-interface-styles');
		
		}
	
		/* Top-level menu page */
		add_menu_page(
			
			'Slider',										/* title of options page */
			'Slider',										/* title of options menu item */
			VPM_SLIDER_REQUIRED_CAPABILITY,					/* permissions level */
			'vpm-slider',									/* menu slug */
			array('VPMSlider', 'printSlidesPage'),			/* callback to print the page to output */
			null,											/* icon file */
			null 											/* menu position number */
		);
		
		/* First child, 'Slides' */
		add_submenu_page(
		
			'vpm-slider',									/* parent slug */
			'Slides',										/* title of page */
			'Slides',										/* title to use in menu */
			VPM_SLIDER_REQUIRED_CAPABILITY,					/* permissions level */
			'vpm-slider',									/* menu slug */
			array('VPMSlider', 'printSlidesPage')			/* callback to print the page to output */
		
		);
		
		/* 'Settings' */
		add_submenu_page(
		
			'vpm-slider',									/* parent slug */
			'Settings',										/* title of page */
			'Settings',										/* title to use in menu */
			VPM_SLIDER_REQUIRED_CAPABILITY,					/* permissions level */
			'vpm-slider-settings',							/* menu slug */
			array('VPMSlider', 'printSettingsPage')			/* callback to print the page to output */
		
		);		
		
		
	
	}
	
	public function printSlidesPage() {
	/*
		Print the actual slides page for adding, editing and removing the slides.
	*/
		// permissions check
		if (!current_user_can(VPM_SLIDER_REQUIRED_CAPABILITY))
		{
			?><h1>This page is not accessible to your user.</h1><?php
			return;
		}
		
		?>
		

		<script type="text/javascript">var VPM_WP_ROOT = '<?php echo admin_url(); ?>';var VPM_HPS_PLUGIN_URL = '<?php echo admin_url();?>admin.php?page=vpm-slider&vpm-slider-ajax=true&';</script>
		
		<div class="wrap">
		
		<div id="icon-plugins" class="icon32"><br /></div><h2>Slides <a href="#" id="new-slide-button" class="add-new-h2">Add New</a></h2>
		
		<noscript>
		<h3>Sorry, this interface absolutely does require JavaScript.</h3>
		<p>You will need to enable JavaScript for this page before any of the controls below will work.</p>
		</noscript>
		
		<form name="homepage-slides">
				
		<!--sortable slides-->
		<ul id="slidesort">
		<?php
		
		$currentSlides = VPMSlider::getCurrentSlides();
		
		if (is_array($currentSlides) && count($currentSlides) > 0)
		{
		
			foreach($currentSlides as $slide) {
			
				$myId = VPMSlider::idFilter($slide['id']);
				
				?>
				
				<li id="slidesort_<?php echo $myId;?>">
								
					<span id="slidesort_<?php echo $myId;?>_text"><?php echo stripslashes(esc_html($slide['title']));?></span>
					
					<span id="slidesort_<?php echo $myId;?>_delete" class="slide-delete">
						[<a id="slidesort_<?php echo $myId;?>_delete_button" class="slide-delete-button" href="#">delete</a>]
					</span>
				
				</li>
				
				<?php
			
			}
		
		}
		
		?>
		<div class="slidesort-add-hint"<?php if (is_array($currentSlides) && count($currentSlides) > 0) echo ' style="display:none"'; ?>>Click &lsquo;Add New&rsquo; to create a slide.</div>
		</ul>
		
		<div id="message-area"></div>
		
		<div id="loading-area"><img src="<?php echo plugin_dir_url( __FILE__ ).'loadingAnimation.gif';?>" /></div>
		
		<hr />
		
		<div id="edit-area">
		
			<!--<div id="preview-area">
			
				<div id="slide-preview">
				<h2 id="slide-preview-title">Slide preview</h2>
				<p id="slide-preview-description">Class Aptent Taciti Sociosqu Ad Litora Torquent Per Conubia Nostra, Per Inceptos.</p>
				</div>
			
			</div>-->
		
			<ul id="homepage_slider">
			
				<li id="preview-area">
				
					<div id="slide-preview" class="desc">
						<h2 id="slide-preview-title">Slide preview</h2>
						<div class="png_fix">
							<p id="slide-preview-description">Class Aptent Taciti Sociosqu Ad Litora Torquent Per Conubia Nostra, Per Inceptos.</p>
						</div>
					</div>
				
				</li>
			
			</ul>
		
			<div id="edit-controls">
				<form id="edit-form">
				<div class="edit-controls-inputs">
					<p><label for="edit-slide-title">Title:</label> <input type="text" name="slide-title" id="edit-slide-title" value="" maxlength="64" /></p>
					<p><label for="edit-slide-description">Description:</label> <input type="text" name="slide-description" id="edit-slide-description" value="" maxlength="255" /></p>
					<p><label for="edit-slide-image-upload">Background</label>: <span id="edit-slide-image-url"></span> <input id="edit-slide-image" type="hidden" name="slide-image" /><input id="edit-slide-image-upload" type="button" value="Upload image" /></p>
					<p><label for="edit-slide-link">Slide Link:</label> <input type="text" name="slide-link" id="edit-slide-link" value="" maxlength="255" /></p>
				</div>
				<div class="edit-controls-save-input">
					<p><input type="button" id="edit-controls-save" class="button-primary" value="Save" /></p>
					<p><input type="button" id="edit-controls-cancel" class="button-secondary" value="Cancel" /></p>
				</div>
				</form>
			
			</div>
		
		</div>
		
		<div style="clear:both;"></div>
		
		
		<!--<br/>
		<input type="button" value="Serialise slide order" id="serialise-me-test" /><br />
		<input type="button" value="Show X/Y offset values" id="show-xy-test" /><br />-->
		
		
		</form>
		</div><?php
	
	}
	
	public function setCapabilityForRoles($rolesToSet)
	{
	/*
		Set the VPM_SLIDER_REQUIRED_CAPABILITY capability against this role, so this WordPress
		user role is able to manage the slides.
		
		Will clear out the capability from all roles, then add it to both administrator and the
		specified roles. (Administrator always has access).
	*/
		global $wp_roles;
		
		if (!current_user_can('manage_options'))
		{
			return false;
		}
	
		$allRoles = get_editable_roles();
		$validRoles = array_keys($allRoles);
		
		if (!is_array($allRoles) || count($allRoles) < 1)
		{
			return false;
		}
		
		// clear the capability from all roles first
		foreach ($allRoles as $rName => $r)
		{
			$wp_roles->remove_cap($rName, VPM_SLIDER_REQUIRED_CAPABILITY);
		}
		
		// add the capability to 'administrator', which can always manage slides
		$wp_roles->add_cap('administrator', VPM_SLIDER_REQUIRED_CAPABILITY);
		
		// add the capability to the specified $roleToSet
		if (is_array($rolesToSet) && count($rolesToSet) > 0)
		{
			foreach($rolesToSet as $theRole)
			{
				if (in_array($theRole, $validRoles))
				{
					$wp_roles->add_cap($theRole, VPM_SLIDER_REQUIRED_CAPABILITY);
				}
			}
		}
		
		return true;
	
	}
	
	public function printSettingsPage()
	{
	/*
		Print the settings page to output, and also handle any of the Settings forms if they
		have come back to us.
	*/
	
		if (!current_user_can(VPM_SLIDER_REQUIRED_CAPABILITY))
		{
			echo '<h1>You do not have permission to manage slider settings.</h1>';
			die();
		}
	
		$success = null;
		$message = '';
	
		if (strtolower($_SERVER['REQUEST_METHOD']) == 'post' && array_key_exists('vpm-slider-settings-submitted', $_POST))
		{
			// handle the submitted form
			
			if (current_user_can('manage_options'))
			{
			
				$rolesToAdd = array();
			
				// find any checked roles to add our capability to
				foreach($_POST as $pk => $po)
				{
					if (preg_match('/^required_capability_/', $pk))
					{
						$roleNameChopped = substr($pk, strlen('required_capability_'));
						
						// do not allow administrator to be modified
						if ($roleNameChopped != 'administrator' && $po == '1')
						{
							$rolesToAdd[] = $roleNameChopped;		
						}								
					}
				}
				
				VPMSlider::setCapabilityForRoles($rolesToAdd);
				$success = true;
				$message .= 'Required role level saved.';
			
			}
			
		
		}
	
		?><div class="wrap">
		<div id="icon-plugins" class="icon32"><br /></div><h2>Settings</h2>
		
		
		<?php if ($success): ?>
			<div class="updated settings-error">
				<p><strong><?php echo esc_html($message); ?></strong></p>
			</div>
		<?php endif; ?>
		
		
		<form method="post" action="admin.php?page=vpm-slider-settings">
			<input type="hidden" name="vpm-slider-settings-submitted" value="true" />
		
			<!-- Only display 'Required Role Level' to manage_options capable users -->
			<?php if (current_user_can('manage_options')):?>
		
			<h3>Required Role Level</h3>
			<p>Any user with a checked role will be allowed to create, edit and delete slides. Only users that can manage
			widgets are able to activate, deactivate or move the VPM Slider widget, which makes the slides show up on your site.</p>
			
			<table class="form-table">
			<tbody>
				<tr class="form-field">
					<td>
					<!--<pre>
					<?php
					$allRoles = get_editable_roles();
					print_r($allRoles);
					?>
					</pre>-->
					
					<?php
							if (is_array($allRoles) && count($allRoles) > 0):
								foreach($allRoles as $rName => $r): ?>
					<tr>
						<td>
							<label for="required_capability_<?php echo esc_attr($rName);?>">
								<input type="checkbox" name="required_capability_<?php echo esc_attr($rName);?>"
								id="required_capability_<?php echo esc_attr($rName);?>" value="1" style="width:20px;"
									<?php 
									// if this role has the vpm_slider_manage_slides capability, mark it as selected
									
									if (array_key_exists(VPM_SLIDER_REQUIRED_CAPABILITY, $r['capabilities'])): ?>
									checked="checked"
									<?php endif;?>
									
									<?php // lock administrator checkbox on
									if ($rName == 'administrator'):
									 ?>
									disabled="disabled"
									 <?php endif; ?>

								 /><?php echo esc_html($r['name']);?><br/>
							</label>
						</td>
					</tr>
					
					<?php endforeach; endif; ?>
				
			</table>
			
			<?php endif; ?>		
			
		<p class="submit">
			<input class="button-primary" type="submit" value="Save Changes" id="submitbutton" />		
		</p>
		
		</form>
		</div><?php
	
	}
	
	public function enqueueFrontendCSS()
	{
	/*
		When WordPress is enqueueing the styles, inject our slider CSS in.
	*/
	
		$file = plugin_dir_path( __FILE__ ) . 'slider_edit.css';
		$url = plugin_dir_url( __FILE__ ) . 'slider_edit.css';
		
		if (@file_exists($file))
		{
			wp_register_style('vpm-slider-frontend', $url, array(), '20120214', 'all');
			wp_enqueue_style('vpm-slider-frontend');
		}
	
	}
	
	public function registerAsWidget() {
	/*
		Register the output to the theme as a widget	
	*/
	
	register_widget('VPMSliderWidget');

}


};


class VPMSliderWidget extends WP_Widget {
	
	/*
		Actual widget class, used only for the display
		of saved data in the theme file.
	*/
	
	
	public function __construct(){
		parent::__construct(false, 'VPM Slider');
	}
	
	public function widget($args, $instance) {
	
		$slides = get_option('vpm_slider_slides');
		
		if (is_array($slides) && count($slides) > 0)
		{
		?><ul id="homepage_slider">
		
		<?php
		foreach($slides as $slide)
		{			
		?>
<li style="background-image: url(<?php echo esc_url($slide['background']); ?>);">
		<a href="<?php echo esc_url($slide['link']); ?>">
			<div class="desc" style="top: <?php echo (int) $slide['title_pos_y'];?>px; left: <?php echo (int) $slide['title_pos_x']; ?>px;">
				<h2><?php echo esc_html($slide['title']); ?></h2>
				<div class="png_fix">
					<p><?php echo esc_html($slide['description']);?></p>
				</div>
			</div>
		</a>
	</li>	
		<?php	
	
		} // end foreach
	?>


</ul>	
	<?php
		} // end if
		else {
			// to display if there are none
		}
	} //end function
	
	public function form($instance)
	{
	
	?><p><input type="button" class="button-secondary" onclick="window.location.href = '<?php echo admin_url();?>admin.php?page=vpm-slider';" value="Configure Slides" /></p><?php
	
	}


};

register_activation_hook(__FILE__, array('VPMSlider', 'createSlidesOptionField'));
add_action('admin_menu', array('VPMSlider', 'addAdminSubMenu'));
add_action('widgets_init', array('VPMSlider', 'registerAsWidget'));
add_action('admin_init', array('VPMSlider', 'passControlToAjaxHandler'));

add_action('wp_enqueue_scripts', array('VPMSlider', 'enqueueFrontendCSS'));

?>