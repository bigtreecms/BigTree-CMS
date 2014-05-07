<?
	if (is_array($field["input"])) {
		foreach ($field["input"] as &$v) {
			$v = BigTree::safeEncode($v);
		}	
		if ($field["options"]["sub_type"] == "phone") {
			$value = $field["input"]["phone_1"]."-".$field["input"]["phone_2"]."-".$field["input"]["phone_3"];
		} elseif ($field["options"]["sub_type"] == "address" || $field["options"]["sub_type"] == "name") {
			$field["output"] = $field["input"];
		}
	} else {
		$field["output"] = BigTree::safeEncode($field["input"]);
	}
?>