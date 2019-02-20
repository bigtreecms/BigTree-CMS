<?php
	namespace BigTree;

	/**
	 * @global string $id
	 * @global ModuleReport $report
	 */
	
	$list = [];
	$form = $report->RelatedModuleForm;
	$field = $form->Fields[$id];

	// See if this is a DB populated list in the related form
	if ($field && $field["type"] == "list" && $field["settings"]["list_type"] == "db") {
		$query = SQL::query("SELECT id, `".$field["settings"]["pop-description"]."` AS `description` 
							 FROM `".$field["settings"]["pop-table"]."` 
							 ORDER BY ".$field["settings"]["pop-sort"]);
		
		while ($entry = $query->fetch()) {
			$list[] = ["value" => $entry["id"], "description" => $entry["description"]];
		}
	} else {
		$ids = SQL::fetchAllSingle("SELECT DISTINCT(`$id`) FROM `".$report->Table."` ORDER BY `$id`");

		foreach ($ids as $id) {
			$list[] = ["value" => $id, "description" => $id];
		}
	}
?>
<select name="<?=$id?>">
	<option></option>
	<?php foreach ($list as $item) { ?>
	<option value="<?=Text::htmlEncode($item["value"])?>"><?=Text::htmlEncode($item["description"])?></option>
	<?php } ?>
</select>