<?php
	header("Content-type: text/csv");
	header("Content-Disposition: attachment; filename=".$cms->urlify($bigtree["module"]["name"])."-".$admin->convertTimestampToUser("now", "Y-m-d").".csv");
	header("Pragma: no-cache");
	header("Expires: 0");
	
	// Draw the column titles
	$cols = array();
	foreach ($bigtree["report"]["fields"] as $id => $title) {
		$cols[] = '"'.str_replace('"','""',$title).'"';
	}
	echo implode(",",$cols)."\n";

	// Get the results and draw them
	$results = BigTreeAutoModule::getReportResults($bigtree["report"],$bigtree["view"],$bigtree["form"],$_POST,$_POST["*sort"]["field"],$_POST["*sort"]["order"]);
	
	foreach ($results as $r) {
		$row = array();
	
		foreach ($bigtree["report"]["fields"] as $id => $title) {
			if (is_string($r[$id])) {
				$row[] = '"'.str_replace('"', '""', htmlspecialchars_decode($r[$id])).'"';
			} else {
				$row[] = '"'.$r[$id].'"';
			}
		}
	
		echo implode(",",$row)."\n";
	}

	die();
