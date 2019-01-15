<?php
	$admin->verifyCSRFToken();

	parse_str($_POST["sort"],$data);
	$max = count($data["row_module-groups"]);
	
	foreach ($data["row_module-groups"] as $pos => $id) {
		$admin->setModuleGroupPosition("module-groups-".$id, $max - $pos);
	}
