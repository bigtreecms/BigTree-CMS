<?
	$module_title = "Users";
	
	if (end($bigtree["path"]) != "password") {
		$admin->requireLevel(1);
	}
	
	$breadcrumb = array(
		array("link" => "users/","title" => "Users")
	);
?>