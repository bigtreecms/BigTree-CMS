<?php
	namespace BigTree;

	Auth::user()->requireLevel(1);
	$analytics->Settings["profile"] = $_POST["profile"];

	Utils::growl("Analytics","Profile Set");

	Router::redirect(MODULE_ROOT."cache/");
	