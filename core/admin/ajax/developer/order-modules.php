<?
	parse_str($_POST["sort"],$data);
	$max = count($data["row"]);
	
	foreach ($data["row"] as $pos => $id) {
		$admin->setModulePosition($id,$max - $pos);
	}
?>