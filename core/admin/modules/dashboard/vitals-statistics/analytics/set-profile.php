<?php
	$analytics->Settings["profile"] = $_POST["profile"];
	$analytics->saveSettings();

	$admin->growl("Analytics","Profile Set");
	BigTree::redirect(MODULE_ROOT."cache/");	
