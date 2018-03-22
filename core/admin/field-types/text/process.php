<?php
	if (is_array($field["input"])) {
		foreach ($field["input"] as &$v) {
			$v = BigTree::safeEncode($v);
		}	
		if ($field["settings"]["sub_type"] == "phone") {
			$field["output"] = $field["input"]["phone_1"]."-".$field["input"]["phone_2"]."-".$field["input"]["phone_3"];
		} elseif ($field["settings"]["sub_type"] == "address" || $field["settings"]["sub_type"] == "name") {
			$field["output"] = $field["input"];
		}
	} else {
		$field["output"] = BigTree::safeEncode($field["input"]);
	}
