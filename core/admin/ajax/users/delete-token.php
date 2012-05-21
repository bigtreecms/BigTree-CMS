<?
	header("Content-type: text/javascript");
	$admin->requireLevel(1);
	
	$id = mysql_real_escape_string($_POST["id"]);
	sqlquery("delete from bigtree_api_tokens where id = '$id'");
?>
BigTree.growl("Users","Deleted API Token");