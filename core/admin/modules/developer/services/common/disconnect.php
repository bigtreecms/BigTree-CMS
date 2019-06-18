<?php
	namespace BigTree;
	
	/**
	 * @global OAuth $api
	 * @global string $name
	 * @global string $route
	 */
	
	CSRF::verify();
	
	$api->disconnect();
	
	Admin::growl("$name API","Disconnected");
	Router::redirect(DEVELOPER_ROOT."services/$route/");
	