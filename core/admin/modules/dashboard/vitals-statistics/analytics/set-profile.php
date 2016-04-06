<?php
	namespace BigTree;

	$admin->requireLevel(1);
	$analytics->Settings["profile"] = $_POST["profile"];

	$admin->growl("Analytics","Profile Set");

	Router::redirect(MODULE_ROOT."cache/");
	