<?
	if ($options["list_type"] == "db") {
		$other_table = $options["pop-table"];
		$other_id = $options["pop-id"];
		$other_title = $options["pop-description"];
		$other_sort = $options["pop-sort"];
	
		$q = sqlquery("SELECT `id`,`$other_title` FROM `$other_table` ORDER BY $other_sort");
		$list = array();
	
		if ($module && $module["gbp"]["enabled"] && $form["table"] == $module["gbp"]["table"] && $key == $module["gbp"]["group_field"]) {
			while ($f = sqlfetch($q)) {
				if ($admin->canAccessGroup($module,$f["id"])) {
					$list[] = array("value" => $f["id"],"description" => $f[$other_title]);
				}
			}
		} else {
			while ($f = sqlfetch($q)) {
				$list[] = array("value" => $f["id"],"description" => $f[$other_title]);
			}
		}
		
		$options["list"] = $list;
	} elseif ($options["list_type"] == "state") {
		$list = array();
		foreach ($state_list as $a => $s) {
			$list[] = array(
				"value" => $a,
				"description" => $s
			);
		}
		$options["list"] = $list;
	} elseif ($options["list_type"] == "country") {
		$list = array();
		foreach ($country_list as $c) {
			$list[] = array(
				"value" => $c,
				"description" => $c
			);
		}
		$options["list"] = $list;
	}
?>
<fieldset>
	<? if ($title) { ?><label<?=$label_validation_class?>><?=$title?><? if ($subtitle) { ?> <small><?=$subtitle?></small><? } ?></label><? } ?>
	<select<?=$input_validation_class?> name="<?=$key?>" tabindex="<?=$tabindex?>" id="field_<?=$key?>">
		<? if ($options["allow-empty"] == "Yes") { ?>
		<option></option>
		<? } ?>
		<? foreach ($options["list"] as $option) { ?>
		<option value="<?=$option["value"]?>"<? if ($value == $option["value"]) { ?> selected="selected"<? } ?>><?=BigTree::trimLength($option["description"], 100)?></option>
		<? } ?>
	</select>
</fieldset>