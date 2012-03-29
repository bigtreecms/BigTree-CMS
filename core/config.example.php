<?
	// Time Zone
	date_default_timezone_set("America/New_York");
	
	// Set to false to stop all PHP errors/warnings from showing.
	$debug = true;
	
	// Database info.
	$config["db"]["host"] = "[host]";
	$config["db"]["name"] = "[db]";
	$config["db"]["user"] = "[user]";
	$config["db"]["password"] = "[password]";
	
	// Separate write database info (for load balanced setups)
	$config["db_write"]["host"] = "[write_host]";
	$config["db_write"]["name"] = "[write_db]";
	$config["db_write"]["user"] = "[write_user]";
	$config["db_write"]["password"] = "[write_password]";
	
	// Setup the www_root and resource_root
	// Resource root must be on a different domain than www_root.  Usually we just remove the www. from the domain.
	$config["domain"] = "[domain]";
	$config["www_root"] = "[wwwroot]";
	$config["admin_root"] = "[wwwroot]admin/";
	//$GLOBALS["secure_root"] = str_replace("http://","https://",$config["www_root"]);
	$GLOBALS["secure_root"] = $config["www_root"];	
	
	// Email used for default form mailers	
	$config["contact_email"] = "[email]";
	
	// The amount of work for the password hashing.  Higher is more secure but more costly on your CPU.
	$config["password_depth"] = 8;
	// If you have HTTPS enabled, set to true to force admin logins through HTTPS
	$config["force_secure_login"] = [force_secure_login];
	// Encryption key for encrypted settings
	$config["settings_key"] = "[settings_key]";
	
	// Custom Output Filter Function
	$config["output_filter"] = false;
	
	// Enable Simple Caching (incomplete)
	$config["cache"] = false;
	$config["xsendfile"] = false;
	
	// ReCAPTCHA Keys
	$config["recaptcha"]["private"] = "6LcjTrwSAAAAADnHAf1dApaNCX1ODNuEBP1YdMdJ";
	$config["recaptcha"]["public"] = "6LcjTrwSAAAAAKvNG6n0YtCROEWGllOu-dS5M5oj";
	
	// Base classes for BigTree.  If you want to extend / overwrite core features of the CMS, change these to your new class names
	// Set BIGTREE_CUSTOM_BASE_CLASS_PATH to the directory path (relative to /core/) of the file that will extend BigTreeCMS
	// Set BIGTREE_CUSTOM_ADMIN_CLASS_PATH to the directory path (relative to /core/) of the file that will extend BigTreeAdmin
	define("BIGTREE_CUSTOM_BASE_CLASS",false);
	define("BIGTREE_CUSTOM_ADMIN_CLASS",false);
	define("BIGTREE_CUSTOM_BASE_CLASS_PATH",false);
	define("BIGTREE_CUSTOM_ADMIN_CLASS_PATH",false);
	
	
	// BigTree Resource Configuration
	
	// Array containing all JS files to minify; key = name of compiled file
	// example: $config["js"]["site"] compiles all JS files into "site.js"
	$config["js"]["files"]["site"] = array(
		// "javascript_file.js"
	);
	
	// Array containing variables to be replaced in compiled JS files
	// example: "variable_name" => "Variable Value" will replace all instances of $variable_name with 'Variable Value'
	$config["js"]["vars"] = array(
		// "variable_name" => "Variable Value"
	);
	
	// Flag for JS minification 
	$config["js"]["minify"] = false; 
	
	
	// Array containing all CSS files to minify; key = name of compiled file
	// example: $config["css"]["site"] compiles all CSS files into "site.css"
	$config["css"]["files"]["site"] = array( 
		// "style_sheet.css"
	);
	
	// Array containing variables to be replaced in compiled CSS files
	// example: "variable_name" => "Variable Value" will replace all instances of $variable_name with 'Variable Value'
	$config["css"]["vars"] = array(
		// "variable_name" => "Variable Value"
	);
	
	// Flag for BigTree CSS3 parsing - automatic vendor prefixing for standard CSS3 
	$config["css"]["prefix"] = false;
	
	// Flag for CSS minification 
	$config["css"]["minify"] = false;
?>