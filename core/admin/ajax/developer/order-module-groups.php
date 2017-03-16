<?
	$admin->verifyCSRFToken();

	parse_str($_POST["sort"],$data);
	$max = count($data["row"]);
	
	foreach ($data["row"] as $pos => $id) {
		$admin->setModuleGroupPosition($id,$max - $pos);
	}
?>