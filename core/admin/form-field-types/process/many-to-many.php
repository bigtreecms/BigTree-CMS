<?
	$many_to_many[$key] = array(
		"table" => $options["mtm-connecting-table"],
		"my-id" => $options["mtm-my-id"],
		"other-id" => $options["mtm-other-id"],
		"data" => $data[$key]
	);	

	$no_process = true;
?>