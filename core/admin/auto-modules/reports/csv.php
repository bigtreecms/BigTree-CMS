<?php
	namespace BigTree;
	
	/**
	 * @global ModuleReport $report
	 * @global string $filter_field_id
	 */

	$date = Auth::user()->convertTimestampTo("now", "Y-m-d");
	$route = Link::urlify(Router::$Module->Name);
	
	header("Content-type: text/csv");
	header("Content-Disposition: attachment; filename=$route-$date.csv");
	header("Pragma: no-cache");
	header("Expires: 0");
	
	// Draw the column titles
	$cols = [];

	foreach ($report->Fields as $id => $title) {
		$cols[] = '"'.str_replace('"','""',$title).'"';
	}

	echo implode(",",$cols)."\n";

	// Get the results and draw them
	$results = $report->getResults($_POST, $_POST["*sort"]["field"], $_POST["*sort"]["order"]);

	foreach ($results as $result) {
		$row = [];

		foreach ($report->Fields as $id => $title) {
			$row[] = '"'.str_replace('"', '""', htmlspecialchars_decode($result[$id])).'"';
		}

		echo implode(",",$row)."\n";
	}

	die();
