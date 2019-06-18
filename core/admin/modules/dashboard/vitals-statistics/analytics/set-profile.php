<?php
	namespace BigTree;
	
	/**
	 * @global GoogleAnalytics\API $analytics
	 */

	$analytics->Settings["profile"] = $_POST["profile"];

	Setting::updateValue($analytics->SettingID, $analytics->Settings, true);
	Admin::growl("Analytics","Profile Set");
	Router::redirect(MODULE_ROOT."cache/");
	