<?php
	namespace BigTree;
	
	/**
	 * @global Module $module
	 * @global ModuleReport $report
	 */
	
	header("Content-type: text/csv");
	header("Content-Disposition: attachment; filename=".Link::urlify($module->Name)."-".date("Y-m-d").".csv");
	header("Pragma: no-cache");
	header("Expires: 0");
	
	// Draw the column titles
	$cols = array();

	foreach ($report->Fields as $id => $title) {
		$cols[] = '"'.str_replace('"','""',$title).'"';
	}

	echo implode(",",$cols)."\n";

	// Get the results and draw them
	$results = $report->getResults($_POST, $_POST["*sort"]["field"], $_POST["*sort"]["order"]);

	foreach ($results as $result) {
		$row = array();

		foreach ($report->Fields as $id => $title) {
			$row[] = '"'.str_replace('"','""',$result[$id]).'"';
		}

		echo implode(",",$row)."\n";
	}

	die();