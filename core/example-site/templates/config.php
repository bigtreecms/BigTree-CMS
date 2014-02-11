<?
	// Set to false to stop all PHP errors/warnings from showing.
	$bigtree["config"]["debug"] = true;

	// Time Zone
	date_default_timezone_set("America/New_York");

	// ------------------------------
	// BigTree Resource Configuration
	// ------------------------------

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
	$bigtree["config"]["js"]["vars"] = array(
		// "variable_name" => "Variable Value"
	);

	// Flag for JS minification
	$bigtree["config"]["js"]["minify"] = false;


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
		"iconSprite"  => "url(../images/icons.svg) no-repeat",

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

	// Flag for BigTree CSS3 parsing - automatic vendor prefixing for standard CSS3
	$bigtree["config"]["css"]["prefix"] = false;

	// Flag for CSS minification
	$bigtree["config"]["css"]["minify"] = false;

	// Database info.
	$bigtree["config"]["db"]["host"] = "localhost";
	$bigtree["config"]["db"]["name"] = "bigtree-demo";
	$bigtree["config"]["db"]["user"] = "root";
	$bigtree["config"]["db"]["password"] = "fastspot";
	$bigtree["config"]["sql_interface"] = "mysqli"; // Change to "mysql" to use legacy MySQL interface in PHP.
	// Separate write database info (for load balanced setups)
	$bigtree["config"]["db_write"]["host"] = "";
	$bigtree["config"]["db_write"]["name"] = "";
	$bigtree["config"]["db_write"]["user"] = "";
	$bigtree["config"]["db_write"]["password"] = "";

	// "domain" should be http:///www.website.com
	$bigtree["config"]["domain"] = "http://dev.fastspot.com";
	// "www_root" should be http://www.website.com/location/of/the/site/
	$bigtree["config"]["www_root"] = "http://dev.fastspot.com/clients/bigtree-demo/";
	// "static_root" can either be the same as "www_root" or another domain that points to the same place -i t is used to server static files to increase page load time due to max connections per domain in most browsers.
	$bigtree["config"]["static_root"] = "http://dev.fastspot.com/clients/bigtree-demo/";

	// Current Environment
	$bigtree["config"]["environment"] = ""; // "dev" or "live"; empty to hide
	$bigtree["config"]["environment_live_url"] = ""; // Live environment URL
	$bigtree["config"]["developer_mode"] = false; // Set to true to lock out all users except developers.
	$bigtree["config"]["maintenance_url"] = false; // Set to a URL to 307 redirect visitors to a maintenance page (driven by /templates/basic/_maintenance.php).

	// Admin Settings
	$bigtree["config"]["admin_root"] = "http://dev.fastspot.com/clients/bigtree-demo/admin/"; // "admin_root" should be the location you want to access BigTree's admin from, i.e. http://www.website.com/admin/
	$bigtree["config"]["force_secure_login"] = false; // If you have HTTPS enabled, set to true to force admin logins through HTTPS
	$bigtree["config"]["html_editor"] = array("name" => "TinyMCE 4","src" => "tinymce4/tinymce.min.js"); // WYSIWYG editor to use
	$bigtree["config"]["password_depth"] = 8; // The amount of work for the password hashing.  Higher is more secure but more costly on your CPU.
	$bigtree["config"]["admin_css"] = array(); // Additional CSS Files For the Admin to load, relative to /custom/admin/css/
	$bigtree["config"]["admin_js"] = array( "demo.js" ); // Additional JavaScript Files For the Admin to load, relative to /custom/admin/js/

	// Routing setup
	$bigtree["config"]["routing"] = "htaccess";

	// Default Image Quality Presets
	$bigtree["config"]["image_quality"] = 90; // 1-100, size increases dramatically after 90
	$bigtree["config"]["retina_image_quality"] = 25; // 1-100, size increases dramatically after 90
	$bigtree["config"]["image_force_jpeg"] = false; // Set to true to make images uploaded as PNG save as JPG

	// Encryption key for encrypted settings
	$bigtree["config"]["settings_key"] = "dshshwegsdhsdhsdhsd";

	// Custom Output Filter Function
	$bigtree["config"]["output_filter"] = false;

	// Enable Simple Caching
	$bigtree["config"]["cache"] = false;
	// Use X-Sendfile headers to deliver cached files (more memory efficient, but your web server must support X-Sendfile headers) -- https://tn123.org/mod_xsendfile/
	$bigtree["config"]["xsendfile"] = false;

	// ReCAPTCHA Keys
	$bigtree["config"]["recaptcha"]["private"] = "6LcjTrwSAAAAADnHAf1dApaNCX1ODNuEBP1YdMdJ";
	$bigtree["config"]["recaptcha"]["public"] = "6LcjTrwSAAAAAKvNG6n0YtCROEWGllOu-dS5M5oj";

	// Base classes for BigTree.  If you want to extend / overwrite core features of the CMS, change these to your new class names
	// Set BIGTREE_CUSTOM_BASE_CLASS_PATH to the directory path (relative to /site/) of the file that will extend BigTreeCMS
	// Set BIGTREE_CUSTOM_ADMIN_CLASS_PATH to the directory path (relative to /site/) of the file that will extend BigTreeAdmin
	define("BIGTREE_CUSTOM_BASE_CLASS",false);
	define("BIGTREE_CUSTOM_ADMIN_CLASS",false);
	define("BIGTREE_CUSTOM_BASE_CLASS_PATH",false);
	define("BIGTREE_CUSTOM_ADMIN_CLASS_PATH",false);

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