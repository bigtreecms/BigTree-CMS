<?php
	namespace BigTree;
		
	/**
	 * @global array $bigtree
	 */
	
	// If we always genereate a new route, don't have a route, or we're updating a pending entry.
	if (!$this->Settings["keep_original"] || !$bigtree["existing_data"][$this->Key] || (isset($bigtree["edit_id"]) && !is_numeric($bigtree["edit_id"]))) {
		
		if (is_array($field["options"]["source"])) {
			$source_data = "";
			
			foreach ($field["options"]["source"] as $source_field) {
				$source_data .= " ".strip_tags($bigtree["post_data"][$source_field]);
			}
		} else {
			$source_data = strip_tags($bigtree["post_data"][$field["options"]["source"]]);
		}
		
		$route = Link::urlify(trim($source_data));

		if (!$this->Settings["not_unique"]) {
			$route = SQL::unique($bigtree["form"]["table"], $this->Key, $route, $bigtree["edit_id"]);
		}

		$this->Output = $route;
	} else {
		$this->Ignore = true;
	}
	