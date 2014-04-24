<?
	$groups = $admin->getCalloutGroups();
	// Stop notices
	$data["group"] = isset($data["group"]) ? $data["group"] : "";
	$data["verb"] = isset($data["verb"]) ? $data["verb"] : "";
?>
<fieldset>
	<label>Group <small>(leaving empty will include all callouts)</small></label>
	<select name="group">
		<option></option>
		<? foreach ($groups as $g) { ?>
		<option value="<?=$g["id"]?>"<? if ($data["group"] == $g["id"]) { ?> selected="selected"<? } ?>><?=$g["name"]?></option>
		<? } ?>
	</select>
</fieldset>
<fieldset>
	<label>Noun <small>(defaults to "Callout")</small></label>
	<input type="text" name="noun" value="<?=htmlspecialchars($data["noun"])?>" />
</fieldset>