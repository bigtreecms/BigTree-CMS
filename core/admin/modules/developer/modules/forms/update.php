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

	$admin->updateModuleForm(end($bigtree["path"]),$title,$table,$fields,$preprocess,$callback,$default_position,$suffix,$return_view,$return_url,$tagging);
	$action = $admin->getModuleActionForForm(end($bigtree["path"]));

	$admin->growl("Developer","Updated Module Form");
	BigTree::redirect($developer_root."modules/edit/".$action["module"]."/");
?>