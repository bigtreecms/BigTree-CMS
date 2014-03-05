<?
	BigTree::globalizePOSTVars();

	$module = end($bigtree["path"]);

	$suffix = isset($suffix) ? "-".$suffix : "";
	$default_position = isset($default_position) ? $default_position : "";

	$fields = array();
	if (is_array($_POST["type"])) {
		foreach ($_POST["type"] as $key => $val) {
			$field = json_decode(str_replace(array("\r","\n"),array('\r','\n'),$_POST["options"][$key]),true);
			$field["type"] = $val;
			$field["title"] = htmlspecialchars($_POST["titles"][$key]);
			$field["subtitle"] = htmlspecialchars($_POST["subtitles"][$key]);
			$fields[$key] = $field;
		}
	}

	$form_id = $admin->createModuleForm($module,$title,$table,$fields,$preprocess,$callback,$default_position,$return_view,$return_url,$tagging);
	$admin->createModuleAction($module,"Add $title","add".$suffix,"on","add",$form_id);
	$admin->createModuleAction($module,"Edit $title","edit".$suffix,"","edit",$form_id);

	$module_info = $admin->getModule($module);

	$admin->growl("Developer","Created Module Form");
	BigTree::redirect(DEVELOPER_ROOT."modules/edit/$module/");
?>