<?
	$st = $options["sub_type"];
	
	if (is_array($data[$key])) {
		foreach ($data[$key] as &$v) {
			$v = htmlspecialchars($v);
		}	
	} else {
		$data[$key] = htmlspecialchars($data[$key]);
	}
	
	if ($st == "phone") {
		if (is_array($data[$key])) {
			$value = $data[$key]["phone_1"]."-".$data[$key]["phone_2"]."-".$data[$key]["phone_3"];
		} else {
			$value = $data[$key];
		}
	} elseif ($st == "address" || $st == "name") {
		$value = json_encode($data[$key]);
	} else {
		$value = $data[$key];
	}
?>