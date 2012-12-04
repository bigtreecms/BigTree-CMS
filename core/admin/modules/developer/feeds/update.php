<?
	$admin->updateFeed(end($bigtree["path"]),$_POST["name"],$_POST["description"],$_POST["table"],$_POST["type"],$_POST["options"],$_POST["fields"]);
	
	$admin->growl("Developer","Updated Feed");
	BigTree::redirect($developer_root."feeds/");
?>