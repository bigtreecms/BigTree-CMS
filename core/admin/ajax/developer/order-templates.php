<?
	$admin->verifyCSRFToken();
	
	parse_str($_POST["sort"],$data);
	$max = count($data["row"]);
	
	foreach ($data["row"] as $pos => $id) {
		$id = $_POST["rel"][$id];
		$admin->setTemplatePosition($id,$max - $pos);
	}
?>