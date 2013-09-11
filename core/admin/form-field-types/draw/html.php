<?
	if ((isset($field["options"]["simple"]) && $field["options"]["simple"]) || (isset($field["options"]["simple_by_permission"]) && $field["options"]["simple_by_permission"] > $admin->Level)) {
		$bigtree["simple_html_fields"][] = $field["id"];
	} else {
		$bigtree["html_fields"][] = $field["id"];
	}
?>
<textarea class="<?=$field["options"]["validation"]?>" name="<?=$field["key"]?>" tabindex="<?=$field["tabindex"]?>" id="<?=$field["id"]?>"><?=htmlspecialchars($field["value"])?></textarea>