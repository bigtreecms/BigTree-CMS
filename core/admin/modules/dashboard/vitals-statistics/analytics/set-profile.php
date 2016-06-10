<?php
	namespace BigTree;

	$admin->Auth->requireLevel(1);
	$analytics->Settings["profile"] = $_POST["profile"];

	Utils::growl("Analytics","Profile Set");

	Router::redirect(MODULE_ROOT."cache/");
	