<?
	$admin->deleteModuleEmbedForm(end($bigtree["commands"]));

	$admin->growl("Developer","Deleted Embeddable Form");
	BigTree::redirect(DEVELOPER_ROOT."modules/edit/".$_GET["module"]."/");
?>