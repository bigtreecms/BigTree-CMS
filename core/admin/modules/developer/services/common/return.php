<?php
	namespace BigTree;
	
	$token = $api->oAuthSetToken($_GET["code"]);

	if ($api->OAuthError) {
		$admin->growl("$name API",$api->OAuthError,"error");
	} else {
		$bigtree["api_return_function"]($api);
		$admin->growl("$name API","Connected");
	}

	Router::redirect(DEVELOPER_ROOT."services/$route/");
	