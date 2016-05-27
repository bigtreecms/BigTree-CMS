<?php
	namespace BigTree;

	/**
	 * @global string $id
	 * @global ModuleReport $report
	 */
	
	$list = array();
	$form = $report->RelatedModuleForm;
	$field = $form->Fields[$id];

	// See if this is a DB populated list in the related form
	if ($field && $field["type"] == "list" && $field["options"]["list_type"] == "db") {
		$query = SQL::query("SELECT id, `".$field["options"]["pop-description"]."` AS `description` 
							 FROM `".$field["options"]["pop-table"]."` 
							 ORDER BY ".$field["options"]["pop-sort"]);
		while ($entry = $query->fetch()) {
			$list[] = array("value" => $entry["id"],"description" => $entry["description"]);
		}
	} else {
		$ids = SQL::fetchAllSingle("SELECT DISTINCT(`$id`) FROM `".$report->Table."` ORDER BY `$id`");

		foreach ($ids as $id) {
			$list[] = array("value" => $id,"description" => $id);
		}
	}
?>
<select name="<?=$id?>">
	<option></option>
	<?php foreach ($list as $item) { ?>
	<option value="<?=Text::htmlEncode($item["value"])?>"><?=Text::htmlEncode($item["description"])?></option>
	<?php } ?>
</select>