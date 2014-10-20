<?
	$bigtree["many-to-many"][$field["key"]] = array(
		"table" => $field["options"]["mtm-connecting-table"],
		"my-id" => $field["options"]["mtm-my-id"],
		"other-id" => $field["options"]["mtm-other-id"],
		"data" => $field["input"]
	);	

	// This field doesn't have it's own key to process.
	$field["ignore"] = true;
?>