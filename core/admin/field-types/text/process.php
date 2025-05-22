<?php
	if (is_array($field["input"])) {
		// Was erroneously re-decoded from JSON to an array
		if (empty($field["settings"]["sub_type"])) {
			$field["output"] = BigTree::safeEncode(json_encode($field["input"], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
		} else {
			foreach ($field["input"] as &$v) {
				$v = BigTree::safeEncode($v);
			}

			if ($field["settings"]["sub_type"] == "phone") {
				$field["output"] = $field["input"]["phone_1"]."-".$field["input"]["phone_2"]."-".$field["input"]["phone_3"];
			} elseif ($field["settings"]["sub_type"] == "address" || $field["settings"]["sub_type"] == "name") {
				$field["output"] = $field["input"];
			}
		}
	} else {
		$field["output"] = BigTree::safeEncode($field["input"]);
	}
