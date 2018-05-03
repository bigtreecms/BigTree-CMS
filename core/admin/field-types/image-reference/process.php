<?php
	if ($field["input"]) {
		if (!is_numeric($field["input"])) {
			$resource = $admin->getResourceByFile(str_replace("resource://", "", $field["input"]));
	
			if ($resource) {
				BigTreeAdmin::$IRLsCreated[] = $resource["id"];
				$field["output"] = $resource["id"];
			} else {
				$bigtree["errors"][] = array("field" => $field["title"], "error" => "Could not find selected image.");
				$field["output"] = null;
			}
		} else {
			$field["output"] = $field["input"];
		}
	}
