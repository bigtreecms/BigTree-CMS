<?
	$admin->verifyCSRFToken();
	$r = $admin->getPageAccessLevel($_POST["id"]);
	
	if ($r == "p") {
		parse_str($_POST["sort"],$data);
		
		$max = count($data["row"]);
		foreach ($data["row"] as $pos => $id) {
			$admin->setPagePosition($id,$max - $pos);
		}
	}
?>