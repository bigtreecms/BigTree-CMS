<?php
	namespace BigTree;
		
	/**
	 * @global array $bigtree
	 */
	
	// If we always genereate a new route, don't have a route, or we're updating a pending entry.
	if (!$this->Settings["keep_original"] || !$bigtree["existing_data"][$this->Key] || (isset($bigtree["edit_id"]) && !is_numeric($bigtree["edit_id"]))) {
		if ($this->Settings["not_unique"]) {
			$this->Output = Link::urlify(strip_tags($bigtree["post_data"][$this->Settings["source"]]));
		} else {
			$route = Link::urlify(strip_tags($bigtree["post_data"][$this->Settings["source"]]));
			$this->Output = SQL::unique($bigtree["form"]["table"], $this->Key, $route, $bigtree["edit_id"]);
		}
	} else {
		$this->Ignore = true;
	}
	