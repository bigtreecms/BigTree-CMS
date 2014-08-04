<?
	parse_str($_POST["sort"]);
	
	if (!$_POST["group"]) {
		$max = count($row);
		
		foreach ($row as $pos => $id) {
			$id = $_POST["rel"][$id];
			$admin->setCalloutPosition($id,$max - $pos);
		}
	} else {
		$callouts = array();
		foreach ($row as $id) {
			$callouts[] = $_POST["rel"][$id];
		}
		$admin->updateCalloutGroupPositions($_POST["group"],$callouts);
	}
?>