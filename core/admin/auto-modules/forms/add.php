<?
	$bigtree["tags"] = array();
	$bigtree["access_level"] = $admin->getAccessLevel($bigtree["current_module"]);
	include BigTree::path("admin/auto-modules/forms/_form.php");
?>