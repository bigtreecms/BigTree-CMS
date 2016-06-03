<?php
	namespace BigTree;

	$analytics->disconnect();
	Utils::growl("Analytics","Disconnected");

	Router::redirect(MODULE_ROOT);
	