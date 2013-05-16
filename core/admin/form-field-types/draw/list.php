<?
	$db_error = false;
	$is_group_based_perm = false;
	$list = array();

	if ($field["options"]["list_type"] == "db") {
		$list_table = $field["options"]["pop-table"];
		$list_id = $field["options"]["pop-id"];
		$list_title = $field["options"]["pop-description"];
		$list_sort = $field["options"]["pop-sort"];
		
		// If debug is on we're going to check if the tables exists...
		if ($bigtree["config"]["debug"] && !BigTree::tableExists($list_table)) {
			$db_error = true;
		} else {
			$q = sqlquery("SELECT `id`,`$list_title` FROM `$list_table` ORDER BY $list_sort");
		
			if ($bigtree["module"] && $bigtree["module"]["gbp"]["enabled"] && $form["table"] == $bigtree["module"]["gbp"]["table"] && $key == $bigtree["module"]["gbp"]["group_field"]) {
				$is_group_based_perm = true;
				while ($f = sqlfetch($q)) {
					$access_level = $admin->canAccessGroup($bigtree["module"],$f["id"]);
					if ($access_level) {
						$list[] = array("value" => $f["id"],"description" => $f[$list_title],"access_level" => $access_level);
					}
				}
			} else {
				while ($f = sqlfetch($q)) {
					$list[] = array("value" => $f["id"],"description" => $f[$list_title]);
				}
			}
		}
	} elseif ($field["options"]["list_type"] == "state") {
		foreach (BigTree::$StateList as $a => $s) {
			$list[] = array(
				"value" => $a,
				"description" => $s
			);
		}
	} elseif ($field["options"]["list_type"] == "country") {
		foreach (BigTree::$CountryList as $c) {
			$list[] = array(
				"value" => $c,
				"description" => $c
			);
		}
	} else {
		$list = $field["options"]["list"];
	}

	if ($db_error) {
?>
<p class="error_message">The table for this field no longer exists (<?=htmlspecialchars($list_table)?>).</p>
<?
	} else {
		$class = array();
		if ($is_group_based_perm) {
			$class[] = "gbp_select";
		}
		if ($field["required"]) {
			$class[] = "required";
		}
?>
<select<? if (count($class)) { ?> class="<?=implode(" ",$class)?>"<? } ?> name="<?=$field["key"]?>" tabindex="<?=$field["tabindex"]?>" id="<?=$field["id"]?>">
	<? if ($field["options"]["allow-empty"] != "No") { ?>
	<option></option>
	<? } ?>
	<? foreach ($list as $option) { ?>
	<option value="<?=$option["value"]?>"<? if ($field["value"] == $option["value"]) { ?> selected="selected"<? } ?><? if ($option["access_level"]) { ?> data-access-level="<?=$option["access_level"]?>"<? } ?>><?=htmlspecialchars(htmlspecialchars_decode(BigTree::trimLength(strip_tags($option["description"]), 100)))?></option>
	<? } ?>
</select>
<?
	}
?>