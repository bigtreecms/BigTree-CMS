<?php
	namespace BigTree;
	
	/**
	 * @global GoogleAnalytics\API $analytics
	 */

	$analytics->disconnect();
	
	Utils::growl("Analytics","Disconnected");
	Router::redirect(MODULE_ROOT);
	