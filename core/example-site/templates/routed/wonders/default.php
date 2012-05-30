<?
	$bigtree["layout"] = "wonders";
	
	if ($bigtree["commands"][0] != "") {
		$currentWonder = $wondersMod->getByRoute($bigtree["commands"][0]);
		include "_detail.php";
	} else {
		$wonders = $wondersMod->getAll("date DESC");
		include "_list.php";
	}
?>