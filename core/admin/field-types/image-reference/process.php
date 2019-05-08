<?php
	namespace BigTree;
	
	if ($this->Input) {
		if (!is_numeric($this->Input)) {
			$resource = Resource::getByFile(str_replace("resource://", "", $this->Input));
			
			if ($resource) {
				Link::$IRLsCreated[] = $resource->ID;
				$this->Output = $resource->ID;
			} else {
				Router::logUserError("Could not find selected image.", $this->Title);
				$this->Output = null;
			}
		} else {
			$this->Output = $this->Input;
		}
	}
