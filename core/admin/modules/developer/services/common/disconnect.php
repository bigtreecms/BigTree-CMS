<?php
	namespace BigTree;
	
	$api->disconnect();

	$admin->growl("$name API","Disconnected");
	Router::redirect(DEVELOPER_ROOT."services/$route/");
	