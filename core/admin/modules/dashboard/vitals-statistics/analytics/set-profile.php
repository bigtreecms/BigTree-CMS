<?php
	namespace BigTree;
	
	/**
	 * @global GoogleAnalytics\API $analytics
	 */

	Auth::user()->requireLevel(1);

	$analytics->Settings["profile"] = $_POST["profile"];

	Setting::updateValue($analytics->SettingID, $analytics->Settings, true);
	Utils::growl("Analytics","Profile Set");
	Router::redirect(MODULE_ROOT."cache/");
	