<?
	$_POST["id"] = $_GET["draft"];
	include BigTree::path("admin/ajax/dashboard/approve-change.php");

	$admin->growl("Pages","Published Draft");
	BigTree::redirect(ADMIN_ROOT."pages/revisions/".end($bigtree["commands"])."/");
?>