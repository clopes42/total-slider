<?php
/********************************************************************************

	VPM Slider Default Template
	
	The default template for showing the slides. Used if there is no
	`vpm-slider-templates/vpm-slider-template.php` file found in the active
	theme's directory.
	
	Do not edit this file: copy it into a folder named `vpm-slider-templates`
	in your theme and make your changes there. The plugin will automatically
	detect its presence.

*********************************************************************************/

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

/*
Template Options

Crop-Suggested-Width: 600
Crop-Suggested-Height: 300
Disable-XY-Positioning-In-Admin: No
*/
?>
<?php if ($s->slides_count() > 0): ?>
	<ul class="vpm-slider">
	
	<?php while ($s->has_slides()): ?>
		<li id="vpm-slider-slide-<?php $s->the_identifier();?>"
			style="background:url(<?php $s->the_background_url();?>) bottom repeat-x; padding: 0;"
		>
			<a href="<?php $s->the_link();?>">
				<div class="desc" style="left: <?php $s->the_x();?>px; top: <?php $s->the_y();?>px">
					<h2><?php $s->the_title();?></h2>
					<div class="png_fix">
						<p><?php $s->the_description();?></p>
					</div>
				</div>
			</a>
		</li>
		<?php endwhile ;?>
		
	</ul>
<?php endif; ?>