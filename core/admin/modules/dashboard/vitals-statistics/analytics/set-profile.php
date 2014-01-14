<?
	$admin->requireLevel(1);
	$analytics->Settings["profile"] = $_POST["profile"];

	$admin->growl("Analytics","Profile Set");
	BigTree::redirect(MODULE_ROOT."cache/");	
?>