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
	
	$admin->updateModuleForm(end($bigtree["path"]),$title,$table,$fields,$preprocess,$callback,$default_position,$suffix);
	$action = $admin->getModuleActionForForm(end($bigtree["path"]));
	
	$admin->growl("Developer","Updated Module Form");
	header("Location: ".$developer_root."modules/edit/".$action["module"]."/");
	die();
?>