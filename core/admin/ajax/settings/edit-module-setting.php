<?
	$setting = $admin->getSetting($_POST["id"]);
	if ($setting["locked"]) {
		$admin->requireLevel(2);
	} else {
		$admin->requireLevel(1);
	}
	
	$title = $setting["title"];
	$value = $setting["value"];
	$key = $setting["id"];
?>
<div style="width: 460px;">
	<input type="hidden" name="setting-id" value="<?=$_POST["id"]?>" />
	<? if ($setting["description"]) { ?>
	<p><?=$setting["description"]?></p>
	<? } ?>
	<? include BigTree::path("admin/form-field-types/draw/".$setting["type"].".php"); ?>
</div>