<?php
	namespace BigTree;
	
	/**
	 * @global GoogleAnalytics\API $analytics
	 */

	Auth::user()->requireLevel(1);
	$analytics->Settings["profile"] = $_POST["profile"];
	$analytics->Setting->save();

	Utils::growl("Analytics","Profile Set");
	Router::redirect(MODULE_ROOT."cache/");
	