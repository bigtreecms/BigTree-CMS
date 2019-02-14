<?php
	namespace BigTree;
	
	if ($this->Input) {
		if (!is_numeric($this->Input)) {
			$resource = Resource::getByFile(str_replace("resource://", "", $this->Input));
			
			if ($resource) {
				Link::$IRLsCreated[] = $resource->ID;
				$this->Output = $resource->ID;
			} else {
				$bigtree["errors"][] = [
					"field" => $this->Title,
					"error" => Text::translate("Could not find selected image.")
				];
				$this->Output = null;
			}
		} else {
			$this->Output = $this->Input;
		}
	}
