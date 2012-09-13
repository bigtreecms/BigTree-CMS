<?
	$admin->deleteModuleView(end($bigtree["commands"]));
		
	$admin->growl("Developer","Deleted View");
	BigTree::redirect($developer_root."modules/edit/".$_GET["module"]."/");
?>