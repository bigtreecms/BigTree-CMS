<?
	// Time Zone
	date_default_timezone_set("America/New_York");

	// Website Environment
	$bigtree["config"]["debug"] = true; // Set to false to stop all PHP errors/warnings from showing.
	$bigtree["config"]["domain"] = "[domain]";	// "domain" should be http:///www.website.com
	$bigtree["config"]["www_root"] = "[wwwroot]"; // "www_root" should be http://www.website.com/location/of/the/site/
	$bigtree["config"]["static_root"] = "[staticroot]"; // "static_root" can either be the same as "www_root" or another domain that points to the same place -i t is used to server static files to increase page load time due to max connections per domain in most browsers.
	$bigtree["config"]["environment"] = ""; // "dev" or "live"; empty to hide
	$bigtree["config"]["environment_live_url"] = ""; // Live environment URL
	$bigtree["config"]["developer_mode"] = false; // Set to true to lock out all users except developers.
	$bigtree["config"]["maintenance_url"] = false; // Set to a URL to 307 redirect visitors to a maintenance page (driven by /templates/basic/_maintenance.php).
	$bigtree["config"]["routing"] = "[routing]";

	// Database Environment
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
		
	// Enable Simple Caching
	$bigtree["config"]["cache"] = false;
	$bigtree["config"]["xsendfile"] = false; // Use X-Sendfile headers to deliver cached files (more memory efficient, but your web server must support X-Sendfile headers) -- https://tn123.org/mod_xsendfile/
?>