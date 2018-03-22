<?php
	$bigtree["many-to-many"][$field["key"]] = array(
		"table" => $field["settings"]["mtm-connecting-table"],
		"my-id" => $field["settings"]["mtm-my-id"],
		"other-id" => $field["settings"]["mtm-other-id"],
		"data" => $field["input"]
	);	

	// This field doesn't have it's own key to process.
	$field["ignore"] = true;
