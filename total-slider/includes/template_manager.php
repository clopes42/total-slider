<?php
/*

	Template Manager
	
	Handles the determination of canonical template URIs and paths for inclusion and
	enqueue purposes, as well as rendering the templates for edit-time JavaScript purposes.

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

if (!defined('TOTAL_SLIDER_IN_FUNCTIONS'))
{
	header('HTTP/1.1 403 Forbidden');
	die('<h1>Forbidden</h1>');
}

// expected directories where we'll find templates, in the plugin (builtin) and elsewhere
define('TOTAL_SLIDER_TEMPLATES_BUILTIN_DIR', 'templates');
define('TOTAL_SLIDER_TEMPLATES_DIR', 'total-slider-templates');

/* Exceptions:

	1xx -- invalid input or arguments

		101 -- the template location is not one of the allowed template locations.
		102 -- Unable to determine the WP_CONTENT_DIR to load this template.
		
	2xx -- unable to load the template
		201 -- The template's %s file was not found, but we expected to find it at '%s'.
	
*/

class Total_Slider_Template {
	
	private $slug;
	private $location; // one of 'builtin','theme','downloaded'
	
	private $mdName;
	private $mdURI;
	private $mdDescription;
	private $mdVersion;
	private $mdAuthor;
	
	private $pathPrefix = null;
	private $uriPrefix = null;
	
	private $phpPath = null;
	private $jsPath = null;
	private $jsDevPath = null;
	private $cssPath = null;
	
	private $phpURI = null;
	private $jsURI = null;
	private $jsDevURI = null;
	private $cssURI = null;
	
	private static $allowedTemplateLocations = array(
		'builtin',
		'theme',
		'downloaded'
	);
	
	public function __construct($slug, $location)
	{
	/*
		Prepare this Template -- pass in the slug of its directory, as
		well as the location ('builtin','theme','downloaded').
		
		We will check for existence, prepare to be asked about this template's
		canonical URIs and paths, and, if required, be ready to load metadata
		from the PHP file, and render the template for JavaScript edit-side purposes.
	*/
	
		// get some key things ready
		$this->slug = $this->sanitizeSlug($slug);
		
		if (in_array($location, $this->allowedTemplateLocations))
		{
			$this->location = $location;
		}
		else
		{
			throw new UnexpectedValueException(__('The supplied template location is not one of the allowed template locations', 'total_slider'), 101);
			return;
		}
		
		// we will load canonicalised paths and urls, and check for existence, but be lazy about metadata
		$this->canonicalize();
		
	}
	
	private function sanitizeSlug($slug)
	{
	/*
		Sanitize a template slug before we assign it to our instance internally, or print
		it anywhere.
		
		A template slug must be fewer than 128 characters in length, unique, and consist only
		of the following characters:
		
			a-z, A-Z, 0-9, _, -
			
		The slug is used as the directory name for the template, as well as the basename for its
		standard PHP, CSS and JS files.
		
	*/
	
		return substr(preg_replace('/[^a-zA-Z0-9_\-]/', '', $slug), 0, 128 );
		
	}
	
	private function canonicalize()
	{
	/*
		Construct canonical paths and URLs for this template, by using the template slug
		and the location to work out where the template files are.
		
		We must check that these canonical paths correspond to files that exist, so we are ready
		for enqueuing and such.
	*/
	
		switch ($this->location)
		{
			
			case 'builtin':
				$pathPrefix = plugin_dir_path( __FILE__ ) . '/' . TOTAL_SLIDER_TEMPLATES_BUILTIN_DIR . '/';
				$uriPrefix = plugin_dir_url( __FILE__ ) . '/'. TOTAL_SLIDER_TEMPLATES_BUILTIN_DIR . '/';
				
				$phpExists = @file_exists($pathPrefix . $this->slug . '/' . $this->slug . '.php' );
				$cssExists = @file_exists($pathPrefix . $this->slug . '/' . 'style.css';
				$jsExists = @file_exists($pathPrefix . $this->slug . '/' . $this->slug . '.js' );
				$jsDevExists = @file_exists($pathPrefix . $this->slug . '/' . $this->slug . '.dev.js' ); 
				
				$missingFile = '';
				
				if (!$phpExists)
				{
					$missingFile = 'PHP';
					$expectedLocation = $pathPrefix . $this->slug . '/' . $this->slug . '.php';			
				}
				else if (!$jsExists)
				{
					$missingFile = 'JS';
					$expectedLocation = $pathPrefix . $this->slug . '/' . $this->slug . '.js';								
				}
				else if (!$cssExists)
				{
					$missingFile = 'CSS';
					$expectedLocation = $pathPrefix . $this->slug . '/style.css';										
				}
				
				else
				{
					$this->phpPath = $pathPrefix . $this->slug . '/' . $this->slug . '.php';
					$this->phpURI = $uriPrefix . $this->slug . '/' . $this->slug . '.php';
					
					$this->cssPath = $pathPrefix . $this->slug . '/style.css';
					$this->cssURI = $uriPrefix . $this->slug . '/style.css';

					$this->jsPath = $pathPrefix . $this->slug . '/' . $this->slug . '.js';
					$this->jsURI = $uriPrefix . $this->slug . '/' . $this->slug . '.js';
					
					if ($jsDevExists)
					{
						$this->jsDevPath = $pathPrefix . $this->slug . '/' . $this->slug . '.dev.js';
						$this->jsDevURI = $uriPrefix . $this->slug . '/' . $this->slug . '.dev.js';
					}
					
					$this->pathPrefix = $pathPrefix;
					$this->uriPrefix = $uriPrefix;
					
					return true;
					
				}
				
				// if a file was missing, then bubble up a relevant exception
				if (!empty($missingFile))
				{
					throw new RuntimeException(
						sprintf(__("The template's %s file was not found, but we expected to find it at '%s'.", 'total_slider'), $missingFile, $expectedLocation)
					, 201);
					return false;
				}
				
				
			break;
			
			case 'theme':
				// check in child theme 'get_stylesheet_*', if not, look in parent theme 'get_template_directory()'
				$prefix['child']['path'] = get_stylesheet_diretory() . '/' . TOTAL_SLIDER_TEMPLATES_DIR . '/';
				$prefix['child']['uri'] = get_stylesheet_directory_uri() . '/' . TOTAL_SLIDER_TEMPLATES_DIR . '/';
				$prefix['parent']['path'] = get_template_directory() . '/' . TOTAL_SLIDER_TEMPLATES_DIR .'/';
				$prefix['parent']['uri'] = get_template_directory_uri() . '/' . TOTAL_SLIDER_TEMPLATES_DIR . '/';
				
				/* 
				
				Loop through each the child prefix and the parent prefix.
				
				If everything is in order in the child theme directory, we will use that as
				canonical.
				
				If not, and everything is in order in the parent theme directory, we will use that
				as canonical.
				
				Failing that, we can't load the template.
				
				*/
				foreach ($prefix as $p)
				{
				
					// load in either the child or parent JS
					if (!$this->jsPath || !$this->jsURI)
					{
						//TODO decide on whether it's [slug].js, or script.js, or something else
						// #19 -- decide on js expected filename
						if ( @file_exists($p['path'] . $this->slug . '/' . $this->slug . '.js' )
						{
							$this->jsPath = $p['path'] . $this->slug . '/' . $this->slug . '.js' );
							$this->jsURI = $p['uri'] . $this->slug . '/' . $this->slug . '.js' ); 
						}
						else {
							$this->jsPath = null;
							$this->jsURI = null;
						}
						
						if ( @file_exists($p['path'] . $this->slug . '/' . $this->slug . '.dev.js'))
						{
							$this->jsDevPath = $p['path'] . $this->slug . '/' . $this->slug . '.dev.js';
							$this->jsDevURI = $p['uri'] . $this->slug . '/' . $this->slug . '.dev.js';						
						}
						else {
							$this->jsDevPath = null;
							$this->jsDevURI = null;
						}
					}			
				
					if (!$this->cssPath || !$this->cssURI || !$this->phpPath || !$this->phpURI) {
					
						// check for the PHP file and the CSS file
						if ( @file_exists($p['path'] . $this->slug . '/' . $this->slug . '.php' ) &&
							 @file_exists($p['path'] . $this->slug . '/' . 'style.css' )
						)
						{
							$this->phpPath = $p['path'] . $this->slug . '/' . $this->slug . '.php';
							$this->cssPath = $p['path'] . $this->slug . '/style.css';
							
							$this->phpURI = $p['uri'] . $this->slug . '/'. $this->slug . '.php';
							$this->cssURI = $p['uri'] . $this->slug . '/style.css';
							
							$this->pathPrefix = $p['path'];
							$this->uriPrefix = $p['uri'];
												
						}
						else {
							$this->phpPath = null;
							$this->cssPath = null;
							
							$this->phpURI = null;
							$this->cssURI = null;
						}
					}
										
				}
				
				$missingFile = '';
				
				// if any paths are null, we can't load the template
				if (!$this->phpPath || !$this->phpURI)
				{
					$missingFile = 'PHP';
					$expectedLocation = $prefix['child']['path'] . $this->slug . '/' . $this->slug . '.php\' or \''; // allow the error message to include 'or' parent hint
					$expectedLocation .= $prefix['parent']['path'] . $this->slug . '/' . $this->slug . '.php';
				}
				else if (!$this->jsPath || !$this->jsURI)
				{
					$missingFile = 'JS';
					$expectedLocation = $prefix['child']['path'] . $this->slug . '/' . $this->slug . '.js\' or \'';
					$expectedLocation .= $prefix['parent']['path'] . $this->slug . '/' . $this->slug . '.js';
				}
				else if (!$this->cssPath || !$this->cssURI)
				{
					$missingFile = 'CSS';
					$expectedLocation = $prefix['child']['path'] . $this->slug . '/style.css\' or \'';
					$expectedLocation .= $prefix['parent']['path'] . $this->slug . '/style.css';					
				}
				
				// if a file was missing, then bubble up a relevant exception
				if (!empty($missingFile))
				{
					throw new RuntimeException(
						sprintf(__("The template's %s file was not found, but we expected to find it at '%s'.", 'total_slider'), $missingFile, $expectedLocation)
					, 201);
					return false;
				}
				else {
					return true;
				}
								
			break;
			
			case 'downloaded':
				//NOTE: in the conspicious absence of a `content_path()` function, we must use the WP_CONTENT_DIR constant
				
				if (!defined('WP_CONTENT_DIR'))
				{
					throw new UnexpectedValueException(__('Unable to determine the WP_CONTENT_DIR, so cannot load this template.', 'total_slider'), 102);
					return false;					
				}
				
				$pathPrefix = WP_CONTENT_DIR . '/' . TOTAL_SLIDER_TEMPLATES_DIR . '/';
				$uriPrefix = content_url() . '/'. TOTAL_SLIDER_TEMPLATES_DIR . '/';
				
				$phpExists = @file_exists($pathPrefix . $this->slug . '/' . $this->slug . '.php' );
				$cssExists = @file_exists($pathPrefix . $this->slug . '/style.css';
				$jsExists = @file_exists($pathPrefix . $this->slug . '/' . $this->slug . '.js' );
				$jsDevExists = @file_exists($pathPrefix . $this->slug . '/' . $this->slug . '.dev.js' );
								
				$missingFile = '';
				
				if (!$phpExists)
				{
					$missingFile = 'PHP';
					$expectedLocation = $pathPrefix . $this->slug . '/' . $this->slug . '.php';			
				}
				else if (!$jsExists)
				{
					$missingFile = 'JS';
					$expectedLocation = $pathPrefix . $this->slug . '/' . $this->slug . '.js';								
				}
				else if (!$cssExists)
				{
					$missingFile = 'CSS';
					$expectedLocation = $pathPrefix . $this->slug . '/style.css';										
				}
				
				else
				{
					$this->phpPath = $pathPrefix . $this->slug . '/' . $this->slug . '.php';
					$this->phpURI = $uriPrefix . $this->slug . '/' . $this->slug . '.php';
					
					$this->cssPath = $pathPrefix . $this->slug . '/style.css';
					$this->cssURI = $uriPrefix . $this->slug . '/style.css';

					$this->jsPath = $pathPrefix . $this->slug . '/' . $this->slug . '.js';
					$this->jsURI = $uriPrefix . $this->slug . '/' . $this->slug . '.js';
					
					if ($jsDevExists)
					{
						$this->jsDevPath = $pathPrefix . $this->slug . '/' . $this->slug . '.dev.js';
						$this->jsDevURI = $uriPrefix . $this->slug . '/' . $this->slug . '.dev.js';
					}					
					
					$this->pathPrefix = $pathPrefix;
					$this->uriPrefix = $uriPrefix;					
					
					return true;
					
				}
				
				// if a file was missing, then bubble up a relevant exception
				if (!empty($missingFile))
				{
					throw new RuntimeException(
						sprintf(__("The template's %s file was not found, but we expected to find it at '%s'.", 'total_slider'), $missingFile, $expectedLocation)
					, 201);
					return false;
				}			
			break;
			
			default:
				throw new UnexpectedValueException(__('The supplied template location is not one of the allowed template locations', 'total_slider'), 101);
				return false;				
			break;
			
			
		}
		
	}
	
	/***********	Canonical path and URI accessor methods		***********/
	
	public function pathPrefix()
	{
	/*
		Return the canonical path for this template.
	*/	
	
		if (!$this->pathPrefix)
		{
			$this->canonicalize();
		}
		
		return $this->pathPrefix;
		
	}
	
	public function uriPrefix()
	{
	/*
		Return the canonical URI for this template.
	*/
	
		if (!$this->uriPrefix)
		{
			$this->canonicalize();
		}
		
		return $this->uriPrefix;
		
	}
	
	public function phpPath()
	{
	/*
		Return the canonical path to this template's PHP file.
	*/
		
		if (!$this->phpPath)
		{
			$this->canonicalize();
		}
		
		return $this->phpPath;
		
	}
	
	public function jsPath()
	{
	/*
		Return the canonical path to this template's JavaScript file.
	*/
	
		if (!$this->jsPath)
		{
			$this->canonicalize();
		}
		
		return $this->jsPath;
	
	}
	
	public function jsDevPath()
	{
	/*
		Return the canonical path to this template's development (non-minified) JavaScript file.
	*/	
	
		if (!$this->jsDevPath)
		{
			return $this->jsPath;
		}
		
		return $this->jsDevPath;
				
	}
	
	public function cssPath()
	{
	/*
		Return the canonical path to this template's CSS file.
	*/	
		if (!$this->cssPath)
		{
			$this->canonicalize();
		}
		
		return $this->cssPath;		
	}
	
	public function phpURI()
	{
	/*
		Return the canonical URI for this template's PHP file.
	*/
		if (!$this->phpURI)
		{
			$this->canonicalize();
		}
		
		return $this->phpURI;	
		
	}
	
	public function jsURI()
	{
	/*
		Return the canonical URI for this template's JavaScript file.
	*/
		if (!$this->jsURI)
		{
			$this->canonicalize();
		}
		
		return $this->jsURI;
		
	}
	
	public function jsDevURI()
	{
	/*
		Return the canonical URI for this template's development (non-minified) JavaScript file.
	*/
		if (!$this->jsDevURI)
		{
			return $this->jsURI;
		}
		
		return $this->jsDevURI;
		
	}
	
	public function cssURI()
	{
	/*
		Return the canonical URI for this template's PHP file.
	*/
		if (!$this->cssURI)
		{
			$this->canonicalize();
		}
		
		return $this->cssURI;	
		
	}
	
};


?>