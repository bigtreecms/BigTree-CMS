<?php
	$db_error = false;
	$is_group_based_perm = false;
	$list = array();

	// Database populated list.
	if ($field["settings"]["list_type"] == "db") {
		$list_table = $field["settings"]["pop-table"];
		$list_id = $field["settings"]["pop-id"];
		$list_title = $field["settings"]["pop-description"];
		$list_sort = $field["settings"]["pop-sort"];
		
		// If debug is on we're going to check if the tables exists...
		if ($bigtree["config"]["debug"] && !BigTree::tableExists($list_table)) {
			$db_error = true;
		} else {
			$q = sqlquery("SELECT `id`,`$list_title` FROM `$list_table` ORDER BY $list_sort");
			
			// Check if we're doing module based permissions on this table.
			if ($bigtree["module"] && $bigtree["module"]["gbp"]["enabled"] && $bigtree["form"]["table"] == $bigtree["module"]["gbp"]["table"] && $field["key"] == $bigtree["module"]["gbp"]["group_field"]) {
				$is_group_based_perm = true;

				if ($field["settings"]["allow-empty"] != "No") {
					$module_access_level = $admin->getAccessLevel($bigtree["module"]);
				}

				while ($f = sqlfetch($q)) {
					// Find out whether the logged in user can access a given group, and if so, specify the access level.
					$access_level = $admin->canAccessGroup($bigtree["module"],$f["id"]);
					
					if ($access_level) {
						$list[] = array("value" => $f["id"],"description" => $f[$list_title],"access_level" => $access_level);
					}
				}
			// We're not doing module group based permissions, get a regular list.
			} else {
				while ($f = sqlfetch($q)) {
					$list[] = array("value" => $f["id"],"description" => $f[$list_title]);
				}
			}
		}
	// State List
	} elseif ($field["settings"]["list_type"] == "state") {
		foreach (BigTree::$StateList as $a => $s) {
			$list[] = array(
				"value" => $a,
				"description" => $s
			);
		}
	// Country List
	} elseif ($field["settings"]["list_type"] == "country") {
		foreach (BigTree::$CountryList as $c) {
			$list[] = array(
				"value" => $c,
				"description" => $c
			);
		}
	// Static List
	} else {
		$list = $field["settings"]["list"];
	}

	// If we have a parser, send a list of the available items through it.
	if (isset($field["settings"]["parser"]) && $field["settings"]["parser"]) {
		$list = call_user_func($field["settings"]["parser"],$list);
	}

	// If the table was deleted for a database populated list, throw an error.
	if ($db_error) {
?>
<p class="error_message">The table for this field no longer exists (<?=htmlspecialchars($list_table)?>).</p>
<?php
	// Draw the list.
	} else {
		$class = array();
		if ($is_group_based_perm) {
			$class[] = "gbp_select";
		}
		if ($field["required"]) {
			$class[] = "required";
		}
?>
<select<?php if (count($class)) { ?> class="<?=implode(" ",$class)?>"<?php } ?> name="<?=$field["key"]?>" tabindex="<?=$field["tabindex"]?>" id="<?=$field["id"]?>">
	<?php if ($field["settings"]["allow-empty"] != "No") { ?>
	<option<?php if ($is_group_based_perm) { ?> data-access-level="<?=$module_access_level?>"<?php } ?>></option>
	<?php } ?>
	<?php foreach ($list as $option) { ?>
	<option value="<?=BigTree::safeEncode($option["value"])?>"<?php if ($field["value"] == $option["value"]) { ?> selected="selected"<?php } ?><?php if ($option["access_level"]) { ?> data-access-level="<?=$option["access_level"]?>"<?php } ?>><?=BigTree::safeEncode(BigTree::trimLength(strip_tags($option["description"]), 100))?></option>
	<?php } ?>
</select>
<?php
	}
?>