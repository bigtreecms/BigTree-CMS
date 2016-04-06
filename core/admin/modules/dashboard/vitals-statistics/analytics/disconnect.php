<?php
	namespace BigTree;

	$analytics->disconnect();
	$admin->growl("Analytics","Disconnected");

	Router::redirect(MODULE_ROOT);
	