<?php
	// If we always genereate a new route, don't have a route, or we're updating a pending entry.
	if (!$field["options"]["keep_original"] || !$bigtree["existing_data"][$field["key"]] || (isset($bigtree["edit_id"]) && !is_numeric($bigtree["edit_id"]))) {
		if ($field["options"]["not_unique"]) {
			$field["output"] = $cms->urlify(strip_tags($bigtree["post_data"][$field["options"]["source"]]));
		} else {
			$route = $cms->urlify(strip_tags($bigtree["post_data"][$field["options"]["source"]]));
			$route = SQL::unique($bigtree["form"]["table"],$field["key"],$route,$bigtree["edit_id"]);
		}
	} else {
		$field["ignore"] = true;
	}