<?
	$admin->create301($_POST["from"],$_POST["to"]);
	$admin->growl("301 Redirects","Created Redirect");
	BigTree::redirect(ADMIN_ROOT."dashboard/vitals-statistics/404/301/");
?>