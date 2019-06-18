<?php
	namespace BigTree;
	
	/**
	 * @global GoogleAnalytics\API $analytics
	 */

	$analytics->disconnect();
	
	Admin::growl("Analytics","Disconnected");
	Router::redirect(MODULE_ROOT);
	