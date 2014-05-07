<?
	$list = array();
	// See if this is a DB populated list in the related form
	$field = $bigtree["form"]["fields"][$id];
	if ($field && $field["type"] == "list" && $field["list_type"] == "db") {
		$q = sqlquery("SELECT id,`".$field["pop-description"]."` FROM `".$field["pop-table"]."` ORDER BY ".$field["pop-sort"]);
		while ($f = sqlfetch($q)) {
			$list[] = array("value" => $f["id"],"description" => $f[$field["pop-description"]]);
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
	<? foreach ($list as $item) { ?>
	<option value="<?=BigTree::safeEncode($item["value"])?>"><?=BigTree::safeEncode($item["description"])?></option>
	<? } ?>
</select>