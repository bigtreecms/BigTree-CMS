<?php
	namespace BigTree;
	
	$api->disconnect();
	Utils::growl("$name API","Disconnected");

	Router::redirect(DEVELOPER_ROOT."services/$route/");
	