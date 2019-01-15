<?php
	$admin->verifyCSRFToken();
	
	parse_str($_POST["sort"],$data);
	$max = count($data["row_actions"]);
	
	foreach ($data["row_actions"] as $pos => $id) {
		$admin->setModuleActionPosition("actions-".$id, $max - $pos);
	}
