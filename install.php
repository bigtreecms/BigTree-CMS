<?php
	// Set version
	include "core/version.php";
	include "core/inc/bigtree/utils.php";

	// Setup SQL functions for MySQL extension if we have it.
	if (function_exists("mysql_connect")) {
		function sqlconnect($server,$user,$password,$port,$socket) {
			$port = $port ?: 3306;
			$server = $socket ? ":".ltrim($socket,":") : $server.":".$port;
			return mysql_connect($server,$user,$password);
		}

		function sqlselectdb($db) {
			return mysql_select_db($db);
		}

		function sqlquery($query) {
			return mysql_query($query);
		}

		function sqlescape($string) {
			return mysql_real_escape_string($string);
		}
	// Otherwise Use MySQLi
	} else {
		function sqlconnect($server,$user,$password,$port,$socket) {
			return mysqli_connect($server,$user,$password,"",$port ?: 3306,$socket);
		}

		function sqlselectdb($db) {
			global $sql_connection;
			return $sql_connection->select_db($db);
		}

		function sqlquery($query) {
			global $sql_connection;
			return $sql_connection->query($query);
		}

		function sqlescape($string) {
			global $sql_connection;
			return $sql_connection->real_escape_string($string);
		}
	}

	// Turn off errors
	
	// Allow for passing in $_POST via command line for automatic installs.
	if (isset($argv) && count($argv) > 1) {
		// Cut off the first argument.
		$vars = array_slice($argv, 1);
		// Loop through the variables passed in.
		foreach ($vars as $v) {
			list($key,$val) = explode("=",$v);
			$_POST[$key] = $val;
		}
	}
	
	// Issues that are game enders first.
	$fails = [];
	if (version_compare(PHP_VERSION,"5.3.0","<")) {
		$fails[] = "PHP 5.3 or higher is required.";
	}

	if (!extension_loaded('json')) {
		$fails[] = "PHP does not have the JSON extension installed.";
	}

	if (!extension_loaded("mysql") && !extension_loaded("mysqli")) {
		$fails[] = "PHP does not have the MySQL extension installed.";
	}

	if (!extension_loaded('gd')) {
		$fails[] = "PHP does not have the GD extension installed.";
	}

	if (!extension_loaded('curl')) {
		$fails[] = "PHP does not have the cURL extension installed.";
	}

	if (!extension_loaded('ctype')) {
		$fails[] = "PHP does not have the ctype extension installed.";
	}

	if (!extension_loaded("openssl")) {
		$fails[] = "PHP does not have the OpenSSL extension installed.";
	}

	if (!ini_get('file_uploads')) {
		$fails[] = "PHP does not have file uploads enabled.";
	}

	if (!is_writable(".")) {
		$fails[] = "Please make the current directory writable.";
	}

	// Issues that could cause problems next.
	$warnings = [];
	
	if (function_exists("get_magic_quotes_gpc") && get_magic_quotes_gpc()) {
		$fails[] = "magic_quotes_gpc is on. This is a deprecated setting that will break BigTree. Please disable it in php.ini.";
	}

	if (intval(ini_get('upload_max_filesize')) < 4) {
		$warnings[] = "Max upload filesize (upload_max_filesize in php.ini) is currently less than 4MB. 8MB or higher is recommended.";
	}

	if (intval(ini_get('post_max_size')) < 4) {
		$warnings[] = "Max POST size (post_max_size in php.ini) is currently less than 4MB. 8MB or higher is recommended.";
	}

	if (intval(ini_get("memory_limit")) < 32) {
		$warnings[] = "PHP's memory limit is currently under 32MB. BigTree recommends at least 32MB of memory be available to PHP.";
	}

	// Determine if we're on Apache or IIS
	if (strpos($_SERVER["SERVER_SOFTWARE"],"IIS") !== false) {
		$iis = $iis_rewrite = true;
		$warnings[] = "You are running Microsoft IIS. BigTree is only tested on Apache; proceed with caution in production environments.";
		// See if we have the equivalent of rewrite installed.
		if (!isset($_SERVER["IIS_UrlRewriteModule"])) {
			$warnings[] = "You do not seem to have the IIS rewrite module installed; only basic routing is available.";
			$iis_rewrite = false;
		}
	} else {
		$iis = false;
	}

	// mod_rewrite check
	$rewrite_enabled = true;
	if (function_exists("apache_get_modules")) {		
		$apache_modules = apache_get_modules();
		if (in_array('mod_rewrite', $apache_modules) === false) {
			$warnings[] = "Apache's mod_rewrite is not installed. Only basic routing is available without mod_rewrite.";
			$rewrite_enabled = false;
		}
	}

	// Clean all post variables up, prevent SESSION hijacking.
	foreach ($_POST as $key => $val) {
		if (substr($key,0,1) != "_") {
			$$key = $val;
		}
	}
	
	$error = null;
	$success = false;
	$installed = false;

	if (count($_POST) && !($db && $host && $user && $cms_user && $cms_pass)) {
		$error = "Errors found! Please fix the highlighted fields and submit the form again.";
	} elseif (count($_POST)) {
		if ($write_host && $write_user && $write_password) {
			$sql_connection = @sqlconnect($write_host,$write_user,$write_password,$write_port,$write_socket);
		} else {
			$sql_connection = @sqlconnect($host,$user,$password,$port,$socket);
		}

		if (!$sql_connection) {
			$error = "Could not connect to MySQL server.";
		} else {
			// Try to create the database
			sqlquery("CREATE DATABASE IF NOT EXISTS `$db`");
			// Try to select it
			$select = sqlselectdb($db);
			if (!$select) {
				$error = "Error accessing/creating database &ldquo;$db&rdquo;.";
			}
		}

		if (!filter_var($cms_user, FILTER_VALIDATE_EMAIL)) {
			$error = "An invalid email address was entered for the CMS user.";
		}
	}
	
	if (empty($error) && count($_POST)) {

		// Let domain/www_root/static_root be set by post for command line installs
		if (!isset($domain)) {
			$scheme = BigTree::getIsSSL() ? "https" : "http";
			$domain = $scheme."://".$_SERVER["HTTP_HOST"];
			
			if ($routing == "basic") {
				$static_root = $domain.str_replace("install.php","",$_SERVER["REQUEST_URI"])."site/";
				$www_root = $static_root."index.php/";
			} elseif ($routing == "iis") {
				$www_root = $static_root = $domain.str_replace("install.php","",$_SERVER["REQUEST_URI"])."site/";
			} else {
				$www_root = $static_root = $domain.str_replace("install.php","",$_SERVER["REQUEST_URI"]);
			}
		}
		
		$find = [
			"[host]",
			"[db]",
			"[user]",
			"[password]",
			"[port]",
			"[socket]",
			"[write_host]",
			"[write_db]",
			"[write_user]",
			"[write_password]",
			"[write_port]",
			"[write_socket]",
			"[domain]",
			"[wwwroot]",
			"[staticroot]",
			"[email]",
			"[settings_key]",
			"[force_secure_login]",
			"[routing]",
			"[slash_behavior]",
			"[session_handler]",
		];
		
		$replace = [
			$host,
			$db,
			$user,
			$password,
			$port,
			$socket,
			(isset($loadbalanced)) ? $write_host : "",
			(isset($loadbalanced)) ? $write_db : "",
			(isset($loadbalanced)) ? $write_user : "",
			(isset($loadbalanced)) ? $write_password : "",
			(isset($loadbalanced)) ? $write_port : "",
			(isset($loadbalanced)) ? $write_socket : "",
			$domain,
			$www_root,
			$static_root,
			$cms_user,
			uniqid("",true),
			(isset($force_secure_login)) ? "true" : "false",
			($routing == "basic") ? "basic" : "htaccess",
			$slash_behavior,
			$session_handler,
		];
		
		// Make sure we're not running in a special mode that forces values for textareas that aren't allowing null.
		sqlquery("SET SESSION sql_mode = ''");
		$sql_queries = explode("\n",file_get_contents("core/setup/base.sql"));
		
		foreach ($sql_queries as $query) {
			$query = trim($query);
		
			if ($query != "") {
				$q = sqlquery($query);
			}
		}

		// Allow for a theme SQL
		if (file_exists("bigtree-theme.sql")) {
			$sql_queries = explode("\n",file_get_contents("bigtree-theme.sql"));
		
			foreach ($sql_queries as $query) {
				$query = trim($query);
				
				if ($query != "") {
					$q = sqlquery($query);
				}
			}
		}
		
		$enc_pass = sqlescape(password_hash(trim($cms_pass), PASSWORD_DEFAULT));
		sqlquery("INSERT INTO bigtree_users (`email`,`password`,`new_hash`,`name`,`level`) VALUES ('$cms_user','$enc_pass','on','Developer','2')");
		
		// Determine whether Apache is running as the owner of the BigTree files -- only works if we have posix_getuid
		// We do this to determine whether we need to make the files the script writes 777
		if (function_exists("posix_getuid")) {
			if (posix_getuid() == getmyuid()) {
				define("BT_SU_EXEC",true);
			} else {
				define("BT_SU_EXEC",false);
			}
		} else {
			define("BT_SU_EXEC",false);
		}

		function bt_mkdir_writable($dir) {
			global $root;

			mkdir($root.$dir);

			if (!BT_SU_EXEC) {
				chmod($root.$dir,0777);
			}
		}
		
		function bt_touch_writable($file,$contents = "") {
			if (!file_exists($file)) {
				file_put_contents($file,$contents);
			}

			if (!BT_SU_EXEC) {
				chmod($file,0777);
			}
		}
		
		function bt_copy_dir($from,$to) {
			global $root;

			$d = opendir($root.$from);

			if (!file_exists($root.$to)) {
				@mkdir($root.$to);
				if (!BT_SU_EXEC) {
					@chmod($root.$to,0777);
				}
			}

			while ($f = readdir($d)) {
				if ($f != "." && $f != "..") {
					if (is_dir($root.$from.$f)) {
						bt_copy_dir($from.$f."/",$to.$f."/");
					} else {
						if (!file_exists($to.$f)) {
							@copy($from.$f,$to.$f);
						}

						if (!BT_SU_EXEC) {
							@chmod($to.$f,0777);
						}
					}
				}
			}
		}
		
		$root = "";
		
		bt_mkdir_writable("cache/");
		bt_mkdir_writable("custom/");
		bt_mkdir_writable("custom/admin/");
		bt_mkdir_writable("custom/admin/ajax/");
		bt_mkdir_writable("custom/admin/css/");
		bt_mkdir_writable("custom/admin/images/");
		bt_mkdir_writable("custom/admin/modules/");
		bt_mkdir_writable("custom/admin/pages/");
		bt_mkdir_writable("custom/admin/field-types/");
		bt_mkdir_writable("custom/inc/");
		bt_mkdir_writable("custom/inc/modules/");
		bt_mkdir_writable("custom/inc/required/");
		bt_mkdir_writable("extensions/");
		bt_mkdir_writable("site");
		bt_mkdir_writable("site/css/");
		bt_mkdir_writable("site/extensions/");
		bt_mkdir_writable("site/files/");
		bt_mkdir_writable("site/files/pages/");
		bt_mkdir_writable("site/files/resources/");
		bt_mkdir_writable("site/images/");
		bt_mkdir_writable("site/js/");
		bt_mkdir_writable("templates");
		bt_mkdir_writable("templates/ajax/");
		bt_mkdir_writable("templates/layouts/");
		bt_mkdir_writable("templates/routed/");
		bt_mkdir_writable("templates/basic/");
		bt_mkdir_writable("templates/callouts/");

		bt_touch_writable("custom/environment.php",str_replace($find,$replace,file_get_contents("core/setup/environment.php")));
		bt_touch_writable("cache/composer-check.flag", "true");
		
		// Install the example site if they asked for it.
		if ($install_example_site) {
			bt_copy_dir("core/example-site/","");
			$sql_queries = explode("\n",file_get_contents("core/setup/example-site.sql"));
			foreach ($sql_queries as $query) {
				$query = trim($query);
				if ($query != "") {
					$q = sqlquery($query);
				}
			}
			bt_touch_writable("custom/settings.php",str_replace($find,$replace,file_get_contents("core/example-site/custom/settings.php")));
		} else {
			bt_touch_writable("custom/settings.php",str_replace($find,$replace,file_get_contents("core/setup/settings.php")));
			bt_mkdir_writable("custom/json-db/");
			bt_copy_dir("core/setup/json-db/","custom/json-db/");
		}

		// Now copy over the default templates
		bt_touch_writable("templates/layouts/_header.php");
		bt_touch_writable("templates/layouts/default.php",'<?php
	include "_header.php";
	echo $bigtree["content"];
	include "_footer.php";
?>');
		bt_touch_writable("templates/layouts/_footer.php");
		bt_touch_writable("templates/basic/_404.php","<h1>404 - Page Not Found</h1>");
		bt_touch_writable("templates/basic/_maintenance.php","<h1>Under Construction</h1><p>Maintenance mode has been enabled.</p>");
		bt_touch_writable("templates/basic/_sitemap.php","<h1>Sitemap</h1>");
		bt_touch_writable("templates/basic/home.php");
		bt_touch_writable("templates/basic/content.php",'<h1><?=$page_header?></h1>
<?=$page_content?>');

		// Create a cron runner script for symlinked cores
		bt_touch_writable("cron-run.php", '<?php
	$server_root = str_replace("cron-run.php", "", strtr(__FILE__, "\\\\", "/"));	
	include $server_root."core/cron.php";
');

		// Create site/index.php, site/.htaccess, and .htaccess (masks the 'site' directory)
		bt_touch_writable("site/index.php",'<?php
	$server_root = str_replace("site/index.php","",strtr(__FILE__, "\\\\", "/"));	
	include "../core/launch.php";
?>');
		
		if ($routing == "advanced") {
			bt_touch_writable("site/.htaccess",'<IfModule mod_deflate.c>
	<IfModule mod_setenvif.c>
		<IfModule mod_headers.c>
			SetEnvIfNoCase ^(Accept-EncodXng|X-cept-Encoding|X{15}|~{15}|-{15})$ ^((gzip|deflate)\s*,?\s*)+|[X~-]{4,13}$ HAVE_Accept-Encoding
			RequestHeader append Accept-Encoding "gzip,deflate" env=HAVE_Accept-Encoding
		</IfModule>
	</IfModule>
	<IfModule mod_mime.c>
		AddEncoding gzip svgz
	</IfModule>
	<IfModule mod_filter.c>
		AddOutputFilterByType DEFLATE "application/atom+xml" \
			"application/javascript" \
			"application/json" \
			"application/ld+json" \
			"application/manifest+json" \
			"application/rss+xml" \
			"application/vnd.geo+json" \
			"application/vnd.ms-fontobject" \
			"application/x-font-ttf" \
			"application/x-web-app-manifest+json" \
			"application/xhtml+xml" \
			"application/xml" \
			"font/opentype" \
			"image/svg+xml" \
			"image/x-icon" \
			"text/cache-manifest" \
			"text/css" \
			"text/html" \
			"text/plain" \
			"text/vtt" \
			"text/x-component" \
			"text/xml" \
			"text/javascript"
	</IfModule>
</IfModule>

<IfModule mod_expires.c>

    ExpiresActive on
    ExpiresDefault                                      "access plus 1 month"

  # CSS
    ExpiresByType text/css                              "access plus 1 year"

  # Data interchange
    ExpiresByType application/atom+xml                  "access plus 1 hour"
    ExpiresByType application/rdf+xml                   "access plus 1 hour"
    ExpiresByType application/rss+xml                   "access plus 1 hour"

    ExpiresByType application/json                      "access plus 0 seconds"
    ExpiresByType application/ld+json                   "access plus 0 seconds"
    ExpiresByType application/schema+json               "access plus 0 seconds"
    ExpiresByType application/vnd.geo+json              "access plus 0 seconds"
    ExpiresByType application/xml                       "access plus 0 seconds"
    ExpiresByType text/xml                              "access plus 0 seconds"

  # Favicon (cannot be renamed!) and cursor images
    ExpiresByType image/vnd.microsoft.icon              "access plus 1 week"
    ExpiresByType image/x-icon                          "access plus 1 week"

  # HTML
    ExpiresByType text/html                             "access plus 0 seconds"

  # JavaScript
    ExpiresByType application/javascript                "access plus 1 year"
    ExpiresByType application/x-javascript              "access plus 1 year"
    ExpiresByType text/javascript                       "access plus 1 year"

  # Manifest files
    ExpiresByType application/manifest+json             "access plus 1 year"

    ExpiresByType application/x-web-app-manifest+json   "access plus 0 seconds"
    ExpiresByType text/cache-manifest                   "access plus 0 seconds"

  # Media files
    ExpiresByType audio/ogg                             "access plus 1 month"
    ExpiresByType image/bmp                             "access plus 1 month"
    ExpiresByType image/gif                             "access plus 1 month"
    ExpiresByType image/jpeg                            "access plus 1 month"
    ExpiresByType image/png                             "access plus 1 month"
    ExpiresByType image/svg+xml                         "access plus 1 month"
    ExpiresByType image/webp                            "access plus 1 month"
    ExpiresByType video/mp4                             "access plus 1 month"
    ExpiresByType video/ogg                             "access plus 1 month"
    ExpiresByType video/webm                            "access plus 1 month"

  # Web fonts

    # Embedded OpenType (EOT)
    ExpiresByType application/vnd.ms-fontobject         "access plus 1 month"
    ExpiresByType font/eot                              "access plus 1 month"

    # OpenType
    ExpiresByType font/opentype                         "access plus 1 month"

    # TrueType
    ExpiresByType application/x-font-ttf                "access plus 1 month"

    # Web Open Font Format (WOFF) 1.0
    ExpiresByType application/font-woff                 "access plus 1 month"
    ExpiresByType application/x-font-woff               "access plus 1 month"
    ExpiresByType font/woff                             "access plus 1 month"

    # Web Open Font Format (WOFF) 2.0
    ExpiresByType application/font-woff2                "access plus 1 month"

  # Other
    ExpiresByType text/x-cross-domain-policy            "access plus 1 week"

</IfModule>

<IfModule mod_headers.c>
	<FilesMatch "\.(ttf|otf|eot|woff)$">
		Header set Access-Control-Allow-Origin "*"
	</FilesMatch>

	Header set X-UA-Compatible "IE=edge"
	<FilesMatch "\.(appcache|atom|crx|css|cur|eot|f4[abpv]|flv|geojson|gif|htc|ico|jpe?g|js|json(ld)?|m4[av]|manifest|map|mp4|oex|og[agv]|opus|otf|pdf|png|rdf|rss|safariextz|svgz?|swf|topojson|tt[cf]|txt|vcf|vtt|webapp|web[mp]|woff2?|xml|xpi)$">
		Header unset X-UA-Compatible
	</FilesMatch>
	
	Header set X-Content-Type-Options "nosniff"
	Header set X-XSS-Protection "1; mode=block"
	Header set X-Permitted-Cross-Domain-Policies "master-only"
</IfModule>

AddType image/svg+xml svg
AddType video/ogg .ogv
AddType video/mp4 .mp4
AddType video/webm .webm

IndexIgnore */*

Options -MultiViews

RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^(.*)$ index.php?bigtree_htaccess_url=$1 [QSA,L]

RewriteRule .* - [E=HTTP_IF_MODIFIED_SINCE:%{HTTP:If-Modified-Since}]
RewriteRule .* - [E=HTTP_BIGTREE_PARTIAL:%{HTTP:BigTree-Partial}]');
			
		} elseif ($routing == "simple") {
			bt_touch_writable("site/.htaccess",'IndexIgnore */*

Options -MultiViews

RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^(.*)$ index.php?bigtree_htaccess_url=$1 [QSA,L]

RewriteRule .* - [E=HTTP_IF_MODIFIED_SINCE:%{HTTP:If-Modified-Since}]
RewriteRule .* - [E=HTTP_BIGTREE_PARTIAL:%{HTTP:BigTree-Partial}]');
		} else {
			bt_touch_writable("index.php",'<?php header("Location: site/index.php/"); ?>');
		}
		
		if ($routing != "basic" && $routing != "iis") {
			bt_touch_writable(".htaccess",'RewriteEngine On
RewriteRule ^$ site/ [L]
RewriteRule (.*) site/$1 [L]');
		}

		$installed = true;
	}

	if ($installed) {
		@unlink("install.php");
		@unlink("bigtree-theme.sql");
		@unlink("README.md");
	}
	
	// Set localhost as the default MySQL host
	$host = !empty($host) ? $host : "localhost";
?>
<!doctype html> 
<!--[if lt IE 7 ]> <html lang="en" class="no-js ie6"> <![endif]-->
<!--[if IE 7 ]>	<html lang="en" class="no-js ie7"> <![endif]-->
<!--[if IE 8 ]>	<html lang="en" class="no-js ie8"> <![endif]-->
<!--[if IE 9 ]>	<html lang="en" class="no-js ie9"> <![endif]-->
<!--[if (gt IE 9)|!(IE)]><!--> <html lang="en" class="no-js"> <!--<![endif]-->
	<head>
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
		<title>Install BigTree <?=BIGTREE_VERSION?></title>
		<?php if ($installed && $routing != "iis") { ?>
		<link rel="stylesheet" href="<?=$www_root?>admin/css/install.css" type="text/css" media="all" />
		<?php } else { ?>
		<script src="core/admin/js/lib.js"></script>
		<script src="core/admin/js/main.js"></script>
		<?php } ?>

		<style type="text/css">
			<?php
				$protocol = (@$_SERVER["HTTPS"] == "on") ? "https://" : "http://";
				
				if ($_SERVER["SERVER_PORT"] != "80" && $port) {
					$base = $protocol.$_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].str_replace("install.php", "", $_SERVER["REQUEST_URI"]);
				} else {
					$base = $protocol.$_SERVER["SERVER_NAME"].str_replace("install.php", "", $_SERVER["REQUEST_URI"]);
				}

				require_once "vendor/wikimedia/less.php/lib/Less/Autoloader.php";
				Less_Autoloader::register();

				// Load LESS compiler
				$parser = new \Less_Parser(["compress" => true]);

				if ($installed) {
					$parser->parseFile("core/admin/css/install.less", $base."admin/filler/");
				} else {
					$parser->parseFile("core/admin/css/install.less", $base."core/admin/filler/");
				}

				echo $parser->getCss();
			?>
		</style>
	</head>
	<body class="install">
		<div class="install_wrapper">
			<?php if ($installed) { ?>
			<h1>BigTree <?=BIGTREE_VERSION?> Installed</h1>
			<form method="post" action="" class="module">
				<h2 class="getting_started"><span></span>Installation Complete</h2>
				<fieldset class="clear">
					<p>Your new BigTree site is ready to go! Login to the CMS using the email/password you entered on the previous page.</p>
					<?php if ($routing == "basic" && file_exists("install.php")) { ?>
					<p class="delete_message">Remember to delete install.php from your root folder as it is publicly accessible in Basic Routing mode.</p>
					<?php } elseif ($routing == "iis") { ?>
					<p class="error_message iis_message">To setup proper rewrite routing for IIS you must import the following .htaccess rules to the /site/ directory:</p>
					<code>
						RewriteCond %{REQUEST_FILENAME} !-d<br />
						RewriteCond %{REQUEST_FILENAME} !-f<br />
						RewriteRule ^(.*)$ index.php?bigtree_htaccess_url=$1 [QSA,L]
					</code>
					<p class="delete_message">To remove the /site/ path from your BigTree install you will need to setup a separate IIS Site for your BigTree install and set its document root to the /site/ folder (as well as moving the rewrite rules to apply the the main Site instead of the /site/ directory). After doing so, edit your /custom/environment.php file to adjust your domain, www_root, static_root, and admin_root variables.</p>
					<?php } ?>
				</fieldset>
				
				<hr />
				
				<h2>Public Site</h2>
				<fieldset class="clear">
					<p><small>URL</small><a href="<?=$www_root?>"><?=$www_root?></a></p>
				</fieldset>
				<br /><br />
				<h2>Administration Area</h2>
				<fieldset class="clear">
					<p>
						<small>URL</small><a href="<?=$www_root?>admin/"><?=$www_root?>admin/</a><br />
						<small>EMAIL</small><?=$cms_user?><br />
						<small>PASSWORD</small><?php for ($i = 0, $count = strlen($cms_pass); $i < $count; $i++) { echo "*"; } ?><br />
					</p>
				</fieldset>
				
				<br class="clear" /><br />
			</form>
			<?php } else { ?>
			<h1>Install BigTree <?=BIGTREE_VERSION?></h1>
			<form method="post" action="" class="module">
				<h2 class="getting_started"><span></span>Getting Started</h2>
				<fieldset class="clear">
					<p>Welcome to the BigTree installer. If you need help with installation, <a href="https://www.bigtreecms.org/docs/dev-guide/installation/" target="_blank">check out the developer docs</a>.</p>
					<br />
				</fieldset>
				<?php
					if (count($warnings)) {
						echo '<br />';
						foreach ($warnings as $warning) {
				?>
				<p class="warning_message clear"><?=$warning?></p>
				<?php
						}
					}
					if (count($fails)) {
						echo '<br />';
						foreach ($fails as $fail) {
				?>
				<p class="error_message clear"><?=$fail?></p>
				<?php
						}
						echo '<br /><fieldset class="clear"><p><strong>Please resolve all the errors marked in red above to install BigTree.</strong></p></fieldset><br /><br />';
					} else {
						if ($error) {
							echo '<br />';
				?>
				<p class="error_message clear"><?=$error?></p>
				<?php
						}
				?>
				<hr />
				
				<h2 class="database"><span></span>Session &amp; Database Properties</h2>
				<fieldset class="clear">
					<p>Enter your MySQL database information below.</p>
				</fieldset>
				<hr />
				<fieldset class="left<?php if (count($_POST) && !$host) { ?> form_error<?php } ?>">
					<label>Hostname</label>
					<input class="text" type="text" id="db_host" required name="host" value="<?=htmlspecialchars($host ?? "")?>" tabindex="1" />
				</fieldset>
				<fieldset class="right<?php if (count($_POST) && !$db) { ?> form_error<?php } ?>">
					<label>Database</label>
					<input class="text" type="text" id="db_name" required name="db" value="<?=htmlspecialchars($db ?? "")?>" tabindex="2" />
				</fieldset>
				<br class="clear" /><br />
				<fieldset class="left<?php if (count($_POST) && !$user) { ?> form_error<?php } ?>">
					<label>Username</label>
					<input class="text" type="text" id="db_user" required name="user" value="<?=htmlspecialchars($user ?? "")?>" tabindex="3" autocomplete="off" />
				</fieldset>
				<fieldset class="right">
					<label>Password</label>
					<input class="text" type="password" id="db_pass" required name="password" value="<?=htmlspecialchars($password ?? "")?>" tabindex="4" autocomplete="off" />
				</fieldset>
				<div class="db_port_or_socket_settings"<?php if (empty($db_port_or_socket)) { ?> style="display: none;"<?php } ?>>
					<br class="clear" /><br />
					<fieldset class="left">
						<label>Port <small>(defaults to 3306)</small></label>
						<input class="text" type="text" name="port" value="<?=htmlspecialchars($port ?? "")?>" tabindex="7" />
					</fieldset>
					<fieldset class="right">
						<label>Socket</label>
						<input class="text" type="text" name="socket" value="<?=htmlspecialchars($socket ?? "")?>" tabindex="8" />
					</fieldset>
				</div>
				<fieldset>
					<br /><br />
					<input type="checkbox" class="checkbox" name="db_port_or_socket" id="db_port_or_socket"<?php if (!empty($db_port_or_socket)) { ?> checked="checked"<?php } ?> tabindex="5" />
					<label class="for_checkbox">Connect via Socket or Alternate Port</label>
					<input type="checkbox" class="checkbox" name="loadbalanced" id="loadbalanced"<?php if (!empty($loadbalanced)) { ?> checked="checked"<?php } ?> tabindex="6" />
					<label class="for_checkbox">Load Balanced MySQL</label>
				</fieldset>

				<fieldset>
					<br /><br />
					<label>Session Storage <small>(BigTree Database Handler required for enhanced security features)</small></label>
					<select name="session_handler" tabindex="7">
						<option value="db">BigTree Database Handler</option>
						<option value="default">Default PHP Handler</option>
					</select>
				</fieldset>
				
				<div id="loadbalanced_settings"<?php if (empty($loadbalanced)) { ?> style="display: none;"<?php } ?>>
					<hr />
					
					<h2 class="database"><span></span>Write Database Properties</h2>
					<fieldset class="clear">
						<p>If you are hosting a load balanced setup with multiple MySQL servers, enter the master write server information below.</p>
					</fieldset>
					<hr />
					<fieldset class="left<?php if (count($_POST) && !empty($loadbalanced) && empty($write_host)) { ?> form_error<?php } ?>">
						<label>Hostname</label>
						<input class="text" type="text" id="db_write_host" name="write_host" value="<?=htmlspecialchars($write_host ?? "")?>" tabindex="8" />
					</fieldset>
					<fieldset class="right<?php if (count($_POST) && !empty($loadbalanced) && empty($write_db)) { ?> form_error<?php } ?>">
						<label>Database</label>
						<input class="text" type="text" id="db_write_name" name="write_db" value="<?=htmlspecialchars($write_db ?? "")?>" tabindex="9" />
					</fieldset>
					<br class="clear" /><br />
					<fieldset class="left<?php if (count($_POST) && !empty($loadbalanced) && empty($write_user)) { ?> form_error<?php } ?>">
						<label>Username</label>
						<input class="text" type="text" id="db_write_user" name="write_user" value="<?=htmlspecialchars($write_user ?? "")?>" tabindex="10" autocomplete="off" />
					</fieldset>
					<fieldset class="right<?php if (count($_POST) && !empty($loadbalanced) && empty($write_password)) { ?> form_error<?php } ?>">
						<label>Password</label>
						<input class="text" type="password" id="db_write_pass" name="write_password" value="<?=htmlspecialchars($write_password ?? "")?>" tabindex="11" autocomplete="off" />
					</fieldset>
					<div class="db_port_or_socket_settings"<?php if (empty($db_port_or_socket)) { ?> style="display: none;"<?php } ?>>
						<br class="clear" /><br />
						<fieldset class="left">
							<label>Port <small>(defaults to 3306)</small></label>
							<input class="text" type="text" name="write_port" value="<?=htmlspecialchars($write_port ?? "")?>" tabindex="12" />
						</fieldset>
						<fieldset class="right">
							<label>Socket</label>
							<input class="text" type="text" name="write_socket" value="<?=htmlspecialchars($write_socket ?? "")?>" tabindex="13" />
						</fieldset>
					</div>
					<br class="clear" /><br />
				</div>
				
				<hr />
				
				<h2 class="account"><span></span>Administrator Account</h2>
				<fieldset class="clear">
					<p>Please enter the desired email address and password for your site's developer account.</p>
				</fieldset>
				<hr />
				<fieldset class="left<?php if (count($_POST) && empty($cms_user)) { ?> form_error<?php } ?>">
					<label>Email Address</label>
					<input class="text" type="email" required id="cms_user" name="cms_user" value="<?=htmlspecialchars($cms_user ?? "")?>" tabindex="14" autocomplete="off" />
				</fieldset>
				<fieldset class="right<?php if (count($_POST) && empty($cms_pass)) { ?> form_error<?php } ?>">
					<label>Password</label>
					<input class="text" type="password" required id="cms_pass" name="cms_pass" value="<?=htmlspecialchars($cms_pass ?? "")?>" tabindex="15" autocomplete="off" />
				</fieldset>
				<fieldset class="clear">
					<br /><br />
					<input type="checkbox" class="checkbox" name="force_secure_login" id="force_secure_login"<?php if (!empty($force_secure_login)) { ?> checked="checked"<?php } ?> tabindex="16" />
					<label class="for_checkbox">Force HTTPS Logins</label>
				</fieldset>
				
				<hr />
				
				<?php if (!$iis || $iis_rewrite) { ?>
				<h2 class="routing"><span></span>Site Routing</h2>
				<fieldset class="clear">
					<?php if ($iis) { ?>
					<p>BigTree makes your URLs pretty but URL Rewrite support can make them even more pretty. By choosing "Rewrite Routing" you can remove /index.php/ from your URLs.</p>
					<?php } else { ?>
					<p>BigTree makes your URLs pretty but mod_rewrite support can make them even more pretty. If your server supports .htaccess overrides and mod_rewrite support you can remove /index.php/ from your URLs.</p>
					<?php } ?>
					<ul>
						<?php if ($iis) { ?>
						<li>Choose <strong>"Basic Routing"</strong> if you are not familiar with importing .htaccess rules into IIS.</li>
						<li>Choose <strong>"Rewrite Routing"</strong> if you want cleaner looking URLs and can import .htaccess rules.</li>
						<?php } else { ?>
						<li>Choose <strong>"Basic Routing"</strong> if you are unsure your server supports .htaccess overrides and mod_rewrite.</li>
						<li>Choose <strong>"Simple Rewrite Routing"</strong> if your server supports .htaccess and mod_rewrite but does not allow for php_flags and content compression.</li>
						<li>Choose <strong>"Advanced Routing"</strong> to install an .htaccess that enables caching, compression, and routing.</li>
						<?php } ?>
					</ul>
				</fieldset>
				<hr />
				<div class="contain">
					<fieldset class="left">
						<label>Routing</label>
						<select name="routing" tabindex="17">
							<?php
								if ($iis) {
							?>
							<option value="basic"<?php if (empty($routing) || $routing == "basic") { ?> selected="selected"<?php } ?>>Basic Routing</option>
							<option value="iis"<?php if ($routing == "iis") { ?> selected="selected"<?php } ?>>Rewrite Routing</option>
							<?php
								} else {
							?>
							<option value="basic"<?php if (empty($routing) || $routing == "basic") { ?> selected="selected"<?php } ?>>Basic Routing</option>
							<?php
									if ($rewrite_enabled) {
							?>
							<option value="simple"<?php if (!empty($routing) && $routing == "simple") { ?> selected="selected"<?php } ?>>Simple Rewrite Routing</option>
							<option value="advanced"<?php if (!empty($routing) && $routing == "advanced") { ?> selected="selected"<?php } ?>>Advanced Routing</option>
							<?php
									}
								}
							?>
						</select>
					</fieldset>
					<fieldset class="left">
						<label>URL Behavior</label>
						<select name="slash_behavior" tabindex="18">
							<option value="append">URLs End With /</option>
							<option value="remove"<?php if (empty($slash_behavior) || $slash_behavior == "remove") { ?> selected="selected"<?php } ?>>URLs End With Page Slug</option>
							<option value="none"<?php if (!empty($slash_behavior) && $slash_behavior == "none") { ?> selected="selected"<?php } ?>>Allow Either</option>
						</select>
					</fieldset>
				</div>
				
				<hr />
				<?php } else { ?>
				<input type="hidden" name="routing" value="basic" />
				<?php } ?>
				
				<h2 class="example"><span></span>Example Site</h2>
				<fieldset class="clear">
					<p>If you would also like to install the BigTree example site, check the box below. These optional demo files include example templates and modules to help get you started learning BigTree.</p>
				</fieldset>
				<br />
				<fieldset class="clear">
					<input type="checkbox" class="checkbox" name="install_example_site" id="install_example_site"<?php if (!empty($install_example_site)) { ?> checked="checked"<?php } ?> tabindex="19" />
					<label class="for_checkbox">Install Example Site</label>
				</fieldset>
								
				<fieldset class="lower">
					<input type="submit" class="button blue" value="Install Now" tabindex="20" />
				</fieldset>
				<?php
					}
				?>
			</form>
		    <script>
		        $(document).ready(function() {
		        	$("#loadbalanced").on("change", function() {
		        		if ($(this).prop("checked")) {
		        			$("#loadbalanced_settings").css({ display: "block" });
		        		} else {
		        			$("#loadbalanced_settings").css({ display: "none" });
		        		}
		        	});
		        	$("#db_port_or_socket").on("change", function() {
		        		if ($(this).prop("checked")) {
		        			$(".db_port_or_socket_settings").css({ display: "block" });
		        		} else {
		        			$(".db_port_or_socket_settings").css({ display: "none" });
		        		}
		        	});
		        });
		    </script>
			<?php } ?>
			<a href="https://www.bigtreecms.org" class="install_logo" target="_blank">BigTree</a>
			<a href="https://www.fastspot.com" class="install_copyright" target="_blank">&copy; <?=date("Y")?> Fastspot</a>
		</div>
	</body>
</html>