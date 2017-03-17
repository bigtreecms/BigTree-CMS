<?
	$admin->verifyCSRFToken();
	
	BigTree::globalizePOSTVars();

	$admin->updateModuleEmbedForm(end($bigtree["path"]),$title,$table,$fields,$hooks,$default_position,$default_pending,$css,$redirect_url,$thank_you_message);
	$admin->growl("Developer","Updated Embeddable Form");

	$form = BigTreeAutoModule::getEmbedForm(end($bigtree["path"]));
	BigTree::redirect(DEVELOPER_ROOT."modules/edit/".$form["module"]."/");
?>