<?php
	namespace BigTree;
	
	// If we always genereate a new route or don't have an existing published route
	if (empty($this->Settings["keep_original"]) || empty($this->ExistingValue)) {
		if (is_array($this->Settings["source"])) {
			$source_data = "";
			
			foreach ($this->Settings["source"] as $source_field) {
				$source_data .= " ".strip_tags($this->POSTData[$source_field]);
			}
		} else {
			$source_data = strip_tags($this->POSTData[$this->Settings["source"]]);
		}
		
		$source_data = trim($source_data);
		
		if ($this->Settings["not_unique"]) {
			$this->Output = Link::urlify($source_data);
		} else {
			$clean_route = Link::urlify($source_data);
			$this->Output = SQL::unique($this->EntryTable, $this->Key, $clean_route, $this->EntryID);
		}
	} else {
		$this->Ignore = true;
	}
