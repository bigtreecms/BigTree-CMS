<?php
	namespace BigTree;
	
	$token = $api->oAuthSetToken($_GET["code"]);

	if ($api->OAuthError) {
		Utils::growl("$name API",$api->OAuthError,"error");
	} else {
		$bigtree["api_return_function"]($api);
		Utils::growl("$name API","Connected");
	}

	$api->Setting->save();

	Router::redirect(DEVELOPER_ROOT."services/$route/");
	