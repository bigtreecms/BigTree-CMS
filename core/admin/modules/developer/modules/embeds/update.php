<?
	BigTree::globalizePOSTVars();

	$fields = array();
	foreach ($_POST["type"] as $key => $val) {
		$field = json_decode(str_replace(array("\r","\n"),array('\r','\n'),$_POST["options"][$key]),true);
		$field["type"] = $val;
		$field["title"] = htmlspecialchars($_POST["titles"][$key]);
		$field["subtitle"] = htmlspecialchars($_POST["subtitles"][$key]);
		$fields[$key] = $field;
	}

	$admin->updateModuleEmbedForm(end($bigtree["path"]),$title,$table,$fields,$preprocess,$callback,$default_position,$default_pending,$css,$redirect_url,$thank_you_message);
	$admin->growl("Developer","Updated Embeddable Form");

	$form = BigTreeAutoModule::getEmbedForm(end($bigtree["path"]));
	BigTree::redirect(DEVELOPER_ROOT."modules/edit/".$form["module"]."/");
?>