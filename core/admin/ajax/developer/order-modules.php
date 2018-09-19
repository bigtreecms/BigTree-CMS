<?php
	$admin->verifyCSRFToken();
	
	parse_str($_POST["sort"],$data);
	$max = count($data["row_modules"]);
	
	foreach ($data["row_modules"] as $pos => $id) {
		$admin->setModulePosition("modules-".$id, $max - $pos);
	}
