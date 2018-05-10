<?php
	$list = array();
	
	// See if this is a DB populated list in the related form
	$field = $bigtree["form"]["fields"][$id];

	if (empty($field["settings"])) {
		$field["settings"] = $field["options"];
	}

	if ($field && $field["type"] == "list" && $field["settings"]["list_type"] == "db") {
		$q = sqlquery("SELECT id,`".$field["settings"]["pop-description"]."` FROM `".$field["settings"]["pop-table"]."` ORDER BY ".$field["settings"]["pop-sort"]);
		while ($f = sqlfetch($q)) {
			$list[] = array("value" => $f["id"],"description" => $f[$field["settings"]["pop-description"]]);
		}
	} else {
		$q = sqlquery("SELECT DISTINCT(`$id`) FROM `".$bigtree["report"]["table"]."` ORDER BY `$id`");
		while ($f = sqlfetch($q)) {
			$list[] = array("value" => $f[$id],"description" => $f[$id]);
		}
	}
?>
<select name="<?=$id?>">
	<option></option>
	<?php foreach ($list as $item) { ?>
	<option value="<?=BigTree::safeEncode($item["value"])?>"><?=BigTree::safeEncode($item["description"])?></option>
	<?php } ?>
</select>