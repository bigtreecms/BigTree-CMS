<?php
	if (!empty($field["settings"]["simple"]) || (isset($field["settings"]["simple_by_permission"]) && $field["settings"]["simple_by_permission"] > $admin->Level)) {
		$bigtree["simple_html_fields"][] = $field["id"];
	} else {
		$bigtree["html_fields"][] = $field["id"];
	}
?>
<textarea class="<?=$field["settings"]["validation"]?>" name="<?=$field["key"]?>" tabindex="<?=$field["tabindex"]?>" id="<?=$field["id"]?>"><?=htmlspecialchars($field["value"])?></textarea>