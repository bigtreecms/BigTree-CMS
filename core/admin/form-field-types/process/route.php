<?
	// If we always genereate a new route, don't have a route, or we're updating a pending entry.
	if (!$field["options"]["keep_original"] || !$bigtree["existing_data"][$field["key"]] || (isset($bigtree["edit_id"]) && !is_numeric($bigtree["edit_id"]))) {
		if (is_array($field["options"]["source"])) {
			$source_data = "";
			
			foreach ($field["options"]["source"] as $source_field) {
				$source_data .= " ".strip_tags($bigtree["post_data"][$source_field]);
			}
		} else {
			$source_data = strip_tags($bigtree["post_data"][$field["options"]["source"]]);
		}
		
		$source_data = trim($source_data);
		
		if ($field["options"]["not_unique"]) {
			$field["output"] = $cms->urlify($source_data);
		} else {
			$original_route = $cms->urlify($source_data);
			$field["output"] = $original_route;
			$x = 2;
			
			// We're going to try 1000 times at most so we don't time out
			while ($x < 1000 && sqlrows(sqlquery("SELECT * FROM `".$bigtree["form"]["table"]."` WHERE `".$field["key"]."` = '".sqlescape($field["output"])."' AND id != '".sqlescape($bigtree["edit_id"])."'"))) {
				$field["output"] = $original_route."-".$x;
				$x++;
			}
			
			if ($x == 1000) {
				$field["output"] = "";
			}
		}
	} else {
		$field["ignore"] = true;
	}
?>