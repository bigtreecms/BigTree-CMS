<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	// If we always genereate a new route, don't have a route, or we're updating a pending entry.
	if (!$this->Settings["keep_original"] || !$bigtree["existing_data"][$this->Key] || (isset($bigtree["edit_id"]) && !is_numeric($bigtree["edit_id"]))) {
		if (is_array($this->Settings["source"])) {
			$source_data = "";
			
			foreach ($this->Settings["source"] as $source_field) {
				$source_data .= " ".strip_tags($bigtree["post_data"][$source_field]);
			}
		} else {
			$source_data = strip_tags($bigtree["post_data"][$this->Settings["source"]]);
		}
		
		$source_data = trim($source_data);
		
		if ($this->Settings["not_unique"]) {
			$this->Output = Link::urlify($source_data);
		} else {
			$original_route = Link::urlify($source_data);
			$this->Output = $original_route;
			$x = 2;
			$exists = false;
			
			// We're going to try 1000 times at most so we don't time out
			while ($x < 1000 &&
				   SQL::rows("SELECT id FROM `".$bigtree["form"]["table"]."`
				   			  WHERE `".$this->Key."` = ?
				   			    AND id != ?", $this->Output, $bigtree["edit_id"])
			) {
				$this->Output = $original_route."-".$x;
				$x++;
			}
			
			if ($x == 1000) {
				$this->Output = "";
			}
		}
	} else {
		$this->Ignore = true;
	}
