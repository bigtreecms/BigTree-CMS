<?
	$module_title = "Users";
	
	if (end($path) != "password") {
		$admin->requireLevel(1);
	}
	
	$breadcrumb = array(
		array("link" => "users/","title" => "Users")
	);
?>