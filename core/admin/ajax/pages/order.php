<?
	$r = $admin->getPageAccessLevel($_GET["id"]);
	if ($r == "p") {
		parse_str($_GET["sort"]);
		
		$max = count($row);
		foreach ($row as $pos => $id) {
			$admin->setPagePosition($id,$max - $pos);
		}
	}
?>