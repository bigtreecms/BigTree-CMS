<?
	// If we always genereate a new route, don't have a route, or we're updating a pending entry.
	if (!$field["options"]["keep_original"] || !$bigtree["existing_data"][$field["key"]] || (isset($bigtree["edit_id"]) && !is_numeric($bigtree["edit_id"]))) {
		if ($field["options"]["not_unique"]) {
			$field["output"] = $cms->urlify(strip_tags($bigtree["post_data"][$field["options"]["source"]]));
		} else {
			$oroute = $cms->urlify(strip_tags($bigtree["post_data"][$field["options"]["source"]]));
			$field["output"] = $oroute;
			$x = 2;
			while (sqlrows(sqlquery("SELECT * FROM `".$form["table"]."` WHERE `$key` = '".sqlescape($value)."' AND id != '".sqlescape($_POST["id"])."'"))) {
				$field["output"] = $oroute."-".$x;
				$x++;
			}
		}
	} else {
		$field["ignore"] = true;
	}	
?>