<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 * @global OAuth $api
	 * @global string $name
	 * @global string $route
	 */
	
	$api->oAuthSetToken($_GET["code"]);

	if ($api->OAuthError) {
		Admin::growl("$name API",$api->OAuthError,"error");
	} else {
		$bigtree["api_return_function"]($api);
		Admin::growl("$name API","Connected");
	}

	Router::redirect(DEVELOPER_ROOT."services/$route/");
	