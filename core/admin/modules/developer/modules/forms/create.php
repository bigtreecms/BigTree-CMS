<?
	BigTree::globalizePOSTVars();

	$module = end($bigtree["path"]);

	$suffix = isset($suffix) ? "-".$suffix : "";
	$default_position = isset($default_position) ? $default_position : "";

	$fields = array();
	foreach ($_POST["type"] as $key => $val) {
		$field = json_decode(str_replace(array("\r","\n"),array('\r','\n'),$_POST["options"][$key]),true);
		$field["type"] = $val;
		$field["title"] = htmlspecialchars($_POST["titles"][$key]);
		$field["subtitle"] = htmlspecialchars($_POST["subtitles"][$key]);
		$fields[$key] = $field;
	}

	$form_id = $admin->createModuleForm($title,$table,$fields,$preprocess,$callback,$default_position,$return_view,$return_url,$tagging);
	$admin->createModuleAction($module,"Add $title","add".$suffix,"on","add",$form_id);
	$admin->createModuleAction($module,"Edit $title","edit".$suffix,"","edit",$form_id);

	$module_info = $admin->getModule($module);
?>
<div class="container">
	<section>
		<h3 class="action_title">Add/Edit <?=$title?></h3>
		<p>Your form has been created. If you were creating a module from scratch, the process is now complete.</p>
	</section>
	<footer>
		<a href="<?=ADMIN_ROOT?><?=$module_info["route"]?>/" class="button white">View Module</a>
		<a href="<?=ADMIN_ROOT?><?=$module_info["route"]?>/add<?=$suffix?>/" class="button blue">View Form</a>
	</footer>
</div>