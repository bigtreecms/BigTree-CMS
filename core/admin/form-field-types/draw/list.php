<?
	$is_group_based_perm = false;
	if ($options["list_type"] == "db") {
		$other_table = $options["pop-table"];
		$other_id = $options["pop-id"];
		$other_title = $options["pop-description"];
		$other_sort = $options["pop-sort"];
	
		$q = sqlquery("SELECT `id`,`$other_title` FROM `$other_table` ORDER BY $other_sort");
		$list = array();
	
		if ($module && $module["gbp"]["enabled"] && $form["table"] == $module["gbp"]["table"] && $key == $module["gbp"]["group_field"]) {
			$is_group_based_perm = true;
			while ($f = sqlfetch($q)) {
				$access_level = $admin->canAccessGroup($module,$f["id"]);
				if ($access_level) {
					$list[] = array("value" => $f["id"],"description" => $f[$other_title],"access_level" => $access_level);
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
	<select<?=$input_validation_class?> name="<?=$key?>" tabindex="<?=$tabindex?>" id="field_<?=$key?>"<? if ($is_group_based_perm) { ?> class="gbp_select"<? } ?>>
		<? if ($options["allow-empty"] != "No") { ?>
		<option></option>
		<? } ?>
		<? foreach ($options["list"] as $option) { ?>
		<option value="<?=$option["value"]?>"<? if ($value == $option["value"]) { ?> selected="selected"<? } ?><? if ($option["access_level"]) { ?> data-access-level="<?=$option["access_level"]?>"<? } ?>><?=BigTree::trimLength($option["description"], 100)?></option>
		<? } ?>
	</select>
</fieldset>