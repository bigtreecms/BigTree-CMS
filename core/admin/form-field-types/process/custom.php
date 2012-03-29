<?
	if ($options["process_function"]) {
		$call = '$value = '.$options["process_function"].'($data[$key],$key);';
		eval($call);
	} else {
		$value = $data[$key];
	}
?>