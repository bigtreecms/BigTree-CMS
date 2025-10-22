<?php
	header("Content-type: text/csv");
	header("Content-Disposition: attachment; filename=".$cms->urlify($bigtree["module"]["name"])."-".$admin->convertTimestampToUser("now", "Y-m-d").".csv");
	header("Pragma: no-cache");
	header("Expires: 0");

	// Stop output buffering to prevent memory issues with large exports
	ob_end_flush();

	if (!empty($bigtree["report"]["streaming"])) {
		$buffer = fopen("php://output", "w");
		$header_columns = [];

		foreach ($bigtree["report"]["fields"] as $id => $title) {
			$header_columns[] = $title;
		}

		fputcsv($buffer, $header_columns);

		$stream_function = function($row) use ($bigtree, $buffer) {
			$csv_row = [];

			foreach ($bigtree["report"]["fields"] as $id => $title) {
				$csv_row[] = is_string($row[$id]) ? htmlspecialchars_decode($row[$id]) : $row[$id];
			}

			fputcsv($buffer, $csv_row);
		};

		BigTreeAutoModule::getReportResults(
			$bigtree["report"],
			$bigtree["view"],
			$bigtree["form"],
			$_POST,
			$_POST["*sort"]["field"],
			$_POST["*sort"]["order"],
			$stream_function
		);

		fclose($buffer);
	} else {
		// Draw the column titles
		$cols = array();
		foreach ($bigtree["report"]["fields"] as $id => $title) {
			$cols[] = '"' . str_replace('"', '""', $title) . '"';
		}
		echo implode(",", $cols) . "\n";

		// Get the results and draw them
		$results = BigTreeAutoModule::getReportResults(
			$bigtree["report"],
			$bigtree["view"],
			$bigtree["form"],
			$_POST,
			$_POST["*sort"]["field"],
			$_POST["*sort"]["order"]
		);

		foreach ($results as $r) {
			$row = array();

			foreach ($bigtree["report"]["fields"] as $id => $title) {
				if (is_string($r[$id])) {
					$row[] = '"' . str_replace('"', '""', htmlspecialchars_decode($r[$id])) . '"';
				} else {
					$row[] = '"' . $r[$id] . '"';
				}
			}

			echo implode(",", $row) . "\n";
		}
	}

	die();
