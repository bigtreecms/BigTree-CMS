<?
	$bigtree["config"]["css"]["prefix"] = false; // Flag for BigTree CSS3 parsing - automatic vendor prefixing for standard CSS3
	$bigtree["config"]["css"]["minify"] = false; // Flag for CSS minification
	
	// Array containing all CSS files to minify; key = name of compiled file
	// example: $bigtree["config"]["css"]["site"] compiles all CSS files into "site.css"
	$bigtree["config"]["css"]["files"]["site"] = array(
		"lib/sprout.css",
		"lib/gridlock-base.css",
		"lib/gridlock-12.css",
		"lib/jquery.bp.wallpaper.css",
		"lib/jquery.fs.boxer.css",
		"lib/jquery.fs.shifter.css",
		"master.css"
	);

	$bigtree["config"]["css"]["files"]["site-ie8"] = array(
		"lib/gridlock-ie.css",
		"ie.css",
		"ie8.css"
	);

	$bigtree["config"]["css"]["files"]["site-ie9"] = array(
		"ie.css"
	);
	
	// Array containing variables to be replaced in compiled CSS files
	// example: "variable_name" => "Variable Value" will replace all instances of $variable_name with 'Variable Value'
	$bigtree["config"]["css"]["vars"] = array(
		"iconIndent"  => "overflow: hidden; text-indent: 125%; white-space: nowrap",
		"iconSprite"  => "url(https://bigtree-demo-site.s3.amazonaws.com/images/icons.svg) no-repeat",

		"ptSans"      => 'font-family: "PTSans", sans-serif',
		"ptSerif"     => 'font-family: "PTSerif", serif',

		"white0"     => "rgba(255, 255, 255, 0)",
		"white25"    => "rgba(255, 255, 255, 0.25)",
		"white50"    => "rgba(255, 255, 255, 0.5)",
		"white75"    => "rgba(255, 255, 255, 0.75)",
		"white"		  => "#fff",

		"black0"     => "rgba(0, 0, 0, 0)",
		"black25"    => "rgba(0, 0, 0, 0.25)",
		"black50"    => "rgba(0, 0, 0, 0.5)",
		"black75"    => "rgba(0, 0, 0, 0.75)",
		"black"       => "#333",

		"brown0"     => "rgba(42, 32, 27, 0)",
		"brown25"    => "rgba(42, 32, 27, 0.25)",
		"brown50"    => "rgba(42, 32, 27, 0.5)",
		"brown75"    => "rgba(42, 32, 27, 0.75)",
		"brown"       => "#2A201C",

		"tan50"      => "rgba(164, 134, 95, 0.5)",
		"tan"         => "#A4865F",

		"darkGray"    => "#777",
		"gray"        => "#999",
		"lightGray"   => "#eee",
		"lighterGray" => "#ddd"
	);

	// Flag for JS minification 
	$bigtree["config"]["js"]["minify"] = false; 

	// Array containing all JS files to minify; key = name of compiled file
	// example: $bigtree["config"]["js"]["site"] compiles all JS files into "site.js"
	$bigtree["config"]["js"]["files"]["site"] = array(
		"lib/jquery-1.10.2.min.js",
		"lib/jquery.bp.rubberband.min.js",
		"lib/jquery.bp.zoetrope.min.js",
		"lib/jquery.bp.wallpaper.min.js",
		"lib/jquery.fs.boxer.min.js",
		"lib/jquery.fs.shifter.min.js",
		"main.js",
		"lib/google.webfontloader.1.5.0.min.js"
	);

	$bigtree["config"]["js"]["files"]["site-ie8"] = array(
		"ie/matchMedia.ie8.js",
		"ie/html5.js"
	);

	$bigtree["config"]["js"]["files"]["site-ie9"] = array(
		"ie/matchMedia.ie9.js",
		"ie/html5.js"
	);
	
	// Array containing variables to be replaced in compiled JS files
	// example: "variable_name" => "Variable Value" will replace all instances of $variable_name with 'Variable Value'
	$bigtree["config"]["js"]["vars"] = array();
		
	// Admin Settings
	$bigtree["config"]["html_editor"] = array("name" => "TinyMCE 4","src" => "tinymce4/tinymce.js"); // WYSIWYG editor to use
	$bigtree["config"]["password_depth"] = 8; // The amount of work for the password hashing.  Higher is more secure but more costly on your CPU.
	$bigtree["config"]["admin_css"] = array(); // Additional CSS Files For the Admin to load, relative to /custom/admin/css/
	$bigtree["config"]["admin_js"] = array(); // Additional JavaScript Files For the Admin to load, relative to /custom/admin/js/
	$bigtree["config"]["ignore_admin_updates"] = false; // Set to true to disable pinging bigtreecms.org for version updates

	// Default Image Quality Presets
	$bigtree["config"]["image_quality"] = 90; // 0-100, size increases dramatically after 90
	$bigtree["config"]["retina_image_quality"] = 25; // 0-100, size increases dramatically after 90
	$bigtree["config"]["image_force_jpeg"] = false; // Set to true to make images uploaded as PNG save as JPG
	// Placeholder image defaults - add your own key to the "placeholder" array to create more placeholder image templates.	
	$bigtree["config"]["placeholder"]["default"] = array( 
		"background_color" => "CCCCCC",
		"text_color" => "666666",
		"image" => false,
		"text" => false
	);
	
	// Custom Output Filter Function
	$bigtree["config"]["output_filter"] = false;
	
	// Encryption key for encrypted settings
	$bigtree["config"]["settings_key"] = "[settings_key]";

	// Base classes for BigTree.  If you want to extend / overwrite core features of the CMS, change these to your new class names
	// Set BIGTREE_CUSTOM_BASE_CLASS_PATH to the directory path (relative to /site/) of the file that will extend BigTreeCMS
	// Set BIGTREE_CUSTOM_ADMIN_CLASS_PATH to the directory path (relative to /site/) of the file that will extend BigTreeAdmin
	define("BIGTREE_CUSTOM_BASE_CLASS",false);
	define("BIGTREE_CUSTOM_ADMIN_CLASS",false);
	define("BIGTREE_CUSTOM_BASE_CLASS_PATH",false);
	define("BIGTREE_CUSTOM_ADMIN_CLASS_PATH",false);
?>