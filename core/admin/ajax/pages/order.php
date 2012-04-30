<?
	$r = $admin->getPageAccessLevel($_POST["id"]);
	if ($r == "p") {
		parse_str($_POST["sort"]);
		
		$max = count($row);
		foreach ($row as $pos => $id) {
			$admin->setPagePosition($id,$max - $pos);
		}
	}
?>