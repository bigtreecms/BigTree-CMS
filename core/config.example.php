<?
	// Time Zone
	date_default_timezone_set("America/New_York");
	
	// Set to false to stop all PHP errors/warnings from showing.
	$bigtree["config"]["debug"] = true;
	
	// Routing setup
	$bigtree["config"]["routing"] = "[routing]";
	
	// Database info.
	$bigtree["config"]["db"]["host"] = "[host]";
	$bigtree["config"]["db"]["name"] = "[db]";
	$bigtree["config"]["db"]["user"] = "[user]";
	$bigtree["config"]["db"]["password"] = "[password]";
	$bigtree["config"]["sql_interface"] = "mysqli"; // Change to "mysql" to use legacy MySQL interface in PHP.
	
	// Separate write database info (for load balanced setups)
	$bigtree["config"]["db_write"]["host"] = "[write_host]";
	$bigtree["config"]["db_write"]["name"] = "[write_db]";
	$bigtree["config"]["db_write"]["user"] = "[write_user]";
	$bigtree["config"]["db_write"]["password"] = "[write_password]";
	
	// Setup the www_root and resource_root
	// Resource root must be on a different domain than www_root.  Usually we just remove the www. from the domain.
	$bigtree["config"]["domain"] = "[domain]";
	$bigtree["config"]["www_root"] = "[wwwroot]";
	$bigtree["config"]["static_root"] = "[staticroot]";
	$bigtree["config"]["admin_root"] = "[wwwroot]admin/";
	
	// Current Environment
	$bigtree["config"]["environment"] = ""; // "dev" or "live"; empty to hide
	$bigtree["config"]["environment_live_url"] = ""; // Site URL
	
	// Default Image Quality Presets
	$bigtree["config"]["image_quality"] = 90;
	$bigtree["config"]["retina_image_quality"] = 25;
	
	// The amount of work for the password hashing.  Higher is more secure but more costly on your CPU.
	$bigtree["config"]["password_depth"] = 8;
	// If you have HTTPS enabled, set to true to force admin logins through HTTPS
	$bigtree["config"]["force_secure_login"] = [force_secure_login];
	// Encryption key for encrypted settings
	$bigtree["config"]["settings_key"] = "[settings_key]";
	
	// Custom Output Filter Function
	$bigtree["config"]["output_filter"] = false;
	
	// Enable Simple Caching
	$bigtree["config"]["cache"] = false;
	// Use X-Sendfile headers to deliver cached files (more memory efficient, but your web server must support X-Sendfile headers) -- https://tn123.org/mod_xsendfile/
	$bigtree["config"]["xsendfile"] = false;
	
	// ReCAPTCHA Keys
	$bigtree["config"]["recaptcha"]["private"] = "6LcjTrwSAAAAADnHAf1dApaNCX1ODNuEBP1YdMdJ";
	$bigtree["config"]["recaptcha"]["public"] = "6LcjTrwSAAAAAKvNG6n0YtCROEWGllOu-dS5M5oj";
	
	// Google Maps Key for Geocoding
	$bigtree["config"]["google_maps_key"] = "";
	
	// Base classes for BigTree.  If you want to extend / overwrite core features of the CMS, change these to your new class names
	// Set BIGTREE_CUSTOM_BASE_CLASS_PATH to the directory path (relative to /core/) of the file that will extend BigTreeCMS
	// Set BIGTREE_CUSTOM_ADMIN_CLASS_PATH to the directory path (relative to /core/) of the file that will extend BigTreeAdmin
	define("BIGTREE_CUSTOM_BASE_CLASS",false);
	define("BIGTREE_CUSTOM_ADMIN_CLASS",false);
	define("BIGTREE_CUSTOM_BASE_CLASS_PATH",false);
	define("BIGTREE_CUSTOM_ADMIN_CLASS_PATH",false);
	
	// ------------------------------
	// BigTree Resource Configuration
	// ------------------------------
	
	// Array containing all JS files to minify; key = name of compiled file
	// example: $bigtree["config"]["js"]["site"] compiles all JS files into "site.js"
	$bigtree["config"]["js"]["files"]["site"] = array(
		// "javascript_file.js"
	);
	
	// Array containing variables to be replaced in compiled JS files
	// example: "variable_name" => "Variable Value" will replace all instances of $variable_name with 'Variable Value'
	$bigtree["config"]["js"]["vars"] = array(
		// "variable_name" => "Variable Value"
	);
	
	// Flag for JS minification 
	$bigtree["config"]["js"]["minify"] = false; 
	
	
	// Array containing all CSS files to minify; key = name of compiled file
	// example: $bigtree["config"]["css"]["site"] compiles all CSS files into "site.css"
	$bigtree["config"]["css"]["files"]["site"] = array( 
		// "style_sheet.css"
	);
	
	// Array containing variables to be replaced in compiled CSS files
	// example: "variable_name" => "Variable Value" will replace all instances of $variable_name with 'Variable Value'
	$bigtree["config"]["css"]["vars"] = array(
		// "variable_name" => "Variable Value"
	);
	
	// Flag for BigTree CSS3 parsing - automatic vendor prefixing for standard CSS3 
	$bigtree["config"]["css"]["prefix"] = false;
	
	// Flag for CSS minification 
	$bigtree["config"]["css"]["minify"] = false;

	// Additional CSS Files For the Admin to Load
	$bigtree["config"]["admin_css"] = array(
		// "whatever.css"
	);

	// Additional JavaScript Files For the Admin to Load
	$bigtree["config"]["admin_js"] = array(
		// "whatever.js"
	);
	
	// --------------------------
	// Placeholder Image Defaults
	// --------------------------

	// Add your own key to the "placeholder" array to create more placeholder image templates.	
	$bigtree["config"]["placeholder"]["default"] = array( 
		"background_color" => "CCCCCC",
		"text_color" => "666666",
		"image" => false,
		"text" => false
	);
?>