<?php
/*
Total Slider Slide Group Class
	
This class provides a set of methods for manipulating the slide group and its slides at the database level.
It is used by ajax_interface.php, primarily.
/* ----------------------------------------------*/

/*  Copyright (C) 2011-2012 Peter Upfold.

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

if ( ! defined('TOTAL_SLIDER_IN_FUNCTIONS' ) ) {
	header( 'HTTP/1.1 403 Forbidden' );
	die( '<h1>Forbidden</h1>' );
}

static $allowed_template_locations = array(
	'builtin',
	'theme',
	'downloaded',
	'legacy'
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
			
			
			background and link may also be numeric, encoded as a string
			
			if numeric and integers, they will be interpreted as WP post IDs to look up
				
	
	*/
	
class Total_Slide_Group { 
/*
	Defines a slide group object for the purposes of storing a list of available
	groups in the wp_option	'total_slider_slide_groups'.
	
	This object specifies the slug and friendly group name. We then use the slug
	to work out which wp_option to query later -- total_slider_slides_[slug].
	
	This class provides a set of methods for manipulating the slide group and its slides
	at the database level. It is used by ajax_interface.php, primarily.
*/

	public $slug;
	public $originalSlug;
	public $name;
	public $templateLocation;
	public $template;
	
	public function __construct( $slug, $name = null ) {
	/*
		Set the slug and name for this group.
	*/
	
		$this->slug = $this->sanitize_slug($slug);
		$this->originalSlug = $this->slug;
		
		if ($name)
		{
			$this->name = $name;
		}
	}
	
	public function sanitize_slug( $slug ) {
	/*
		Sanitize a slide group slug, for accessing the wp_option row with that slug name.		
	*/
		return substr( preg_replace( '/[^a-zA-Z0-9_\-]/', '', $slug ), 0, ( 63 - strlen( 'total_slider_slides_' ) ) );
	}	
	
	public function load() {
	/*
		Load this slide group's name and slug into the object, from the DB.
	*/
	
		global $allowed_template_locations;
	
		if ( ! get_option( 'total_slider_slide_groups' ) ) {
			return false;
		}
		
		// get the current slide groups
		$current_groups = get_option( 'total_slider_slide_groups' );
		
		$the_index = false;
		
		// loop through to find one with this original slug
		foreach( $current_groups as $key => $group ) {
			if ( $group->slug == $this->originalSlug ) {
				$the_index = $key;
				break;
			}
		}
		
		if ( false === $the_index ) {
			return false;
		}
		else {
			$this->name = $current_groups[$the_index]->name;
			
			$this->slug = Total_Slider::sanitize_slide_group_slug($currentGroups[$the_index]->slug);
			
			if (
				property_exists( $current_groups[$the_index], 'templateLocation' ) &&
				in_array( $current_groups[$the_index]->templateLocation, $allowed_template_locations )
			) {
				$this->templateLocation = $current_groups[$the_index]->templateLocation;
			}
			else {
				$this->templateLocation = 'builtin';
			}
			
			if ( 
				property_exists( $current_groups[$the_index], 'template' ) &&
				strlen( $current_groups[$the_index]->template) > 0 ) {
				$this->template = $current_groups[$the_index]->template;
			}
			else {
				$this->template = 'default';
			}
			
			return true;
		}
	}
	
	public function save() {
	/*
		Save this new slide group to the slide groups option.
	*/
	
		if ( ! get_option('total_slider_slide_groups' ) ) {
			// create option
			add_option( 'total_slider_slide_groups', array(), '', 'yes' );
		}
		
		// get the current slide groups
		$current_groups = get_option( 'total_slider_slide_groups' );
		
		$the_index = false;
		
		// loop through to find one with this original slug
		foreach( $current_groups as $key => $group ) {
			if ( $group->slug == $this->originalSlug ) {
				$the_index = $key;
				break;
			}
		}
		
		if ( false === $the_index ) {
			// add this as a new slide group at the end
			$current_groups[] = $this;
		}
		else {
			// replace the group at $theIndex with the new information
			$current_groups[$the_index] = $this;
		}
		
		// save the groups list
		update_option( 'total_slider_slide_groups', $current_groups );
	
	}
	
	public function delete() {
	/*
		Delete the slide group with this slug from the list.
	*/
	
		if ( ! get_option('total_slider_slide_groups' ) ) {
			return false;
		}
		
		// get the current slide groups
		$current_groups = get_option( 'total_slider_slide_groups' );
		
		$the_index = false;
		
		// loop through to find one with this original slug
		foreach( $current_groups as $key => $group ) {
			if ( $group->slug == $this->originalSlug ) {
				$the_index = $key;
				break;
			}
		}
		
		if ( false === $the_index )
		{
			return false;
		}
		else {
			// remove this group at $theIndex
			unset($current_groups[$the_index]);
		}
		
		// save the groups list
		update_option( 'total_slider_slide_groups', $current_groups );
	
	}
	
	public function new_slide( $title, $description, $background, $link, $title_pos_x, $title_pos_y ) {
	/*
			Given a pre-validated set of data (title, description, backgorund,
			link, title_pos_x and title_pos_y, create a new slide and add to the
			option. Return the new slide ID for resorting in another function.	
	*/
	
		$current_slides = get_option('total_slider_slides_' . $this->slug);
		
		if (false === $current_slides) {
			
			$this->save();
			
			$current_slides = get_option( 'total_slider_slides_' . $this->slug );
			if (false === $current_slides)
			{
				return false; //can't do it
			}
		}
		
		$new_id = str_replace('.', '', uniqid('', true));
		
		$new_slide = array(
		
			'id' => $new_id,
			'title' => $title,
			'description' => $description,
			'background' => $background,
			'link' => $link,
			'title_pos_x' => $title_pos_x,
			'title_pos_y' => $title_pos_y		
		
		);	
		
		$current_slides[ count( $current_slides ) ] = $new_slide;
		
		if ( $this->save_slides( $current_slides) ) {
			return $new_id;
		}
		else {
			return false;
		}
		
		
	}
	
	public function get_slide( $slide_id ) {
	/*
		Fetch the whole object for the given slide ID.
	*/
	
		$current_slides = get_option( 'total_slider_slides_' . $this->slug );
		
		if (
			false === $current_slides ||
			! is_array( $current_slides ) ||
			count( $current_slides ) < 0
		) {
			return false;
		}
		
		else {
		
			foreach( $current_slides as $slide ) {
			
				if ( $slide['id'] == $slide_id ) {
				
					if ( (int) $slide['link'] == $slide['link'] ) {
						// if slide link is a number, and therefore a post ID of some sort
						$slp = (int) $slide['link'];
						$link_post = get_post($slp);
						if ($link_post)
						{
							$slide['link_post_title'] = $link_post->post_title;
						}
					}
					
					if ( (int) $slide['background'] == $slide['background'] && $slide['background'] > 0 ) {
						// if slide background is a number, it must be an attachment ID
						// so get its URL
						$slide['background_url'] = wp_get_attachment_url((int)$slide['background']);
						
						if ( $slide['background_url'] == false )
						{
							/* 
								If it failed to look up, simply fail to provide the URL.
								We must not provide (string)'false' as the URL or things will break.
								
								'false' isn't a valid URL, but will be loaded into the frontend, and stays unless replaced by the user
								during the edit process. This will bite the user when they then try and save, as they will be told
								the background URL is not valid.
							*/
							unset( $slide['background_url'] );
						}
					}
				
					return $slide;
				
				}			
			
			}
			
			// if we didn't find it
			
			return false;
		}
	
	}
	
	public function update_slide( $slide_id, $title, $description, $background, $link, $title_pos_x, $title_pos_y ) {
	/*
		Given the slide ID, update that slide with the pre-filtered data specified.
	*/
	
		$current_slides = get_option( 'total_slider_slides_' . $this->slug );
		$original_slides = $current_slides;
		
		if (
			false === $current_slides ||
			!is_array( $current_slides ) ||
			count( $current_slides ) < 0
		) {
			return false;
		}
		
		else {
		
			$found = false;
		
			foreach( $current_slides as $i => $slide ) {
			
				if ( $slide['id'] == $slide_id ) {
				
					// we found the record we were looking for. update it
					$currentSlides[$i]['title'] = $title;
					$currentSlides[$i]['description'] = $description;
					$currentSlides[$i]['background'] = $background;
					$currentSlides[$i]['link'] = $link;
					$currentSlides[$i]['title_pos_x'] = $title_pos_x;
					$currentSlides[$i]['title_pos_y'] = $title_pos_y;
				
					$found = true;
				
				}	
			
			}
			
			if ( ! $found ) {
				return false;
			}
		}
		
		if ( $current_slides === $original_slides )
		{
			return true; // no change, don't bother update_option as it returns false and errors us out
		}
		
		// $currentSlides now holds the slides we want to save
		return $this->save_slides( $current_slides );
	
	}
	
	public function validate_url($url) {
	/*
		Assess whether or not a given string is a valid URL format, based on
		parse_url(). Returns true for valid format, false otherwise.
		
		Imported from Drupal 7 common.inc:valid_url.
		
		This function is Drupal code and is Copyright 2001 - 2010 by the original authors.
		This function, like the rest of this software, is GPL2-licensed.
		
	*/
	
		if ( $url === '#' )
		{
			// allow a '#' character only
			return true;
		}
		else {
	
			return (bool) preg_match( "
	      /^                                                      # Start at the beginning of the text
	      (?:ftp|https?|feed):\/\/                                # Look for ftp, http, https or feed schemes
	      (?:                                                     # Userinfo (optional) which is typically
	        (?:(?:[\w\.\-\+!$&'\(\)*\+,;=]|%[0-9a-f]{2})+:)*      # a username or a username and password
	        (?:[\w\.\-\+%!$&'\(\)*\+,;=]|%[0-9a-f]{2})+@          # combination
	      )?
	      (?:
	        (?:[a-z0-9\-\.]|%[0-9a-f]{2})+                        # A domain name or a IPv4 address
	        |(?:\[(?:[0-9a-f]{0,4}:)*(?:[0-9a-f]{0,4})\])         # or a well formed IPv6 address
	      )
	      (?::[0-9]+)?                                            # Server port number (optional)
	      (?:[\/|\?]
	        (?:[\w#!:\.\?\+=&@$'~*,;\/\(\)\[\]\-]|%[0-9a-f]{2})   # The path and query (optional)
	      *)?
	    $/xi", $url );
    
    	}
	
	}
	
	public function delete_slide( $slide_id ) {
	/*
		Remove the slide with slideID from the slides
		option.
	*/
	
		$current_slides = get_option( 'total_slider_slides_' . $this->slug );
		
		if ( false === $current_slides ) {
			$this->save();
			
			$current_slides = get_option( 'total_slider_slides_' . $this->slug );
			if ( false === $current_slides ) {
				return false; //can't do it
			}
		}	
		
		if ( is_array( $current_slides) && count($current_slides) > 0 ) {

			$found_it = false;		
			
			foreach( $current_slides as $index => $slide ) {
			
				if ($slide['id'] == $slide_id)
				{
					unset($current_slides[$index]);
					$found_it = true;
					break;
				}
			
			}
			
			if ( ! $found_it )
				return false;
			else
			{
				return $this->save_slides($current_slides);
			}
		
		}
		
		else {
			return false;
		}			
	
	}
	
	public function reshuffle($new_slide_order)
	{
	/*
		Given a new, serialised set of slide order IDs in an array,
		this function will shuffle the order of the slides with said
		IDs in the options array.
	*/
	
		$current_slides = get_option( 'total_slider_slides_' . $this->slug );
		
		if ( false === $current_slides ) {
			
			$this->save();
			
			$current_slides = get_option( 'total_slider_slides_' . $this->slug );
			if ( false === $current_slides ) {
				return false; //can't do it
			}
		}	
		
		
		if ( is_array( $current_slides ) && count( $currentSlides ) > 0 ) {
		
			$new_slides = array();	
			
			$new_slide_not_found_in_current = false;	
			
			foreach( $new_slide_order as $new_index => $new_slide_id ) {			
				$found_this_slide = false;
			
				foreach( $current_slides as $index => $slide ) {
					if ( $slide['id'] == $new_slide_id ) {
						$new_slides[ count( $new_slides ) ] = $slide;
						$found_this_slide = true;
						continue;
					}
				}
				
				if (!$found_this_slide)
				{
					$new_slide_not_found_in_current = true;
				}
				
			}
			
			if (
				count($current_slides ) != count( $newSlides ) ||
				$new_slide_not_found_in_current
			) {
				// there is a disparity -- so a slide or more will be lost
				return 'disparity';
			}
			
			if ( $new_slides === $current_slides ) {
				return true;
			}
			
			return $this->save_slides($new_slides);
		
		}
		else
		{
			return false;
		}
	
	}

	
	private function save_slides($slides_to_write) {
	/*
		Dumb function that just updates the option with the array it is given.
	*/
	
		return update_option( 'total_slider_slides_' . $this->slug, $slides_to_write );
	
	}
	
	public function remove_xy_data() {
	/*
		Remove all the X/Y positional information from this slide group's slides. This
		is used when changing the slide group template, to avoid the title/description
		box from being off-screen on the new template.
	*/
	
		$current_slides = get_option( 'total_slider_slides_' . $this->slug );
		
		if ( false === $current_slides ) {
			
			$this->save();
			
			$current_slides = get_option( 'total_slider_slides_' . $this->slug );
			
			if ( false === $current_slides ) {
				return false; //can't do it
			}
		}
		
		if ( is_array( $current_slides ) && count( $current_slides ) > 0 ) {
			foreach( $current_slides as $i => $slide ) {
				$currentSlides[$i]['title_pos_x'] = 0;
				$currentSlides[$i]['title_pos_y'] = 0;	
			}
			
			$this->save_slides($current_slides);
			return true;
			
		}
		else {
			return true;
		}				
		
	}
	
	public function mini_preview() {
	/*
		Render an HTML mini-preview of the slide images, for use in the widget selector. This allows
		an at-a-glance verification that the selected slide group is the desired slide group.
	*/	
		
		/*
			* Extract background images from slides.
			* (Get thumbnail versions?)
			* Get suggested crop width and height, scale down proportionally to calculate thumbnail size of template
			* Render thumbnail images against those dimensions
			* JS to spin through them with some kind of animation?
			
			How do we disclaim that this isn't truly WYSIWYG? Is that a problem?
		*/
		
		if ( empty($this->template) || empty($this->templateLocation) ) {
			if ( ! $this->load() )
				return false;
		}
		
		// load template information
		try {
			$t = new Total_Slider_Template( $this->template, $this->templateLocation );
		}
		catch ( Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				printf( __( 'Unable to render the slide mini-preview: %s (error code %d)', 'total_slider' ), $e->getMessage(), $e->getCode() );
			}
			return false;
		}		
		
		$template_options = $t->determine_options();
		
		?><p><strong><?php _e( 'Template:', 'total_slider' );?></strong> <?php echo esc_html( $t->name() ); ?></p><?php
		
		
		$current_slides = get_option( 'total_slider_slides_' . $this->slug );
		
		if (false === $current_slides || !is_array( $current_slides ) || count( $current_slides ) < 0)
		{
			?><p><?php _e( 'There are no slides to show.', 'total_slider' );?></p><?php
			return true;
		}
		
		?><div class="total-slider-mini-preview">
		<ul><?php
		
		foreach( $currentSlides as $idx => $slide ) {
		
			if ( is_numeric($slide['background'] ) && intval( $slide['background'] ) == $slide['background'] ) {
				// background references an attachment ID
				$image = wp_get_attachment_image_src( intval( $slide['background'] ), 'thumbnail' );
				$image = $image[0];
			}
			else {
				$image = $slide['background'];				
			}
			?><li><img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $slide['title'] ); ?>" title="<?php echo esc_attr( $slide['title'] ); ?>" width="100" height="32" /></li><?php
			
		}
		
		?>
		</ul>
		</div><?php
		
	}
	

};

?>