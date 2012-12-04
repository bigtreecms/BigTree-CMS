<?
	$tags = array();
	$action = "create";
	// Reset the $page variable to take out the information from the parent page.
	$page = array("id" => $page["id"]);
	include BigTree::path("admin/modules/pages/_form.php");
?>