<?
	$tags = array();
	$bigtree["form_action"] = "create";
	$bigtree["current_page"] = array();
	// Reset the $page variable to take out the information from the parent page.
	$page = array("id" => $page["id"]);
	include BigTree::path("admin/modules/pages/_form.php");
?>