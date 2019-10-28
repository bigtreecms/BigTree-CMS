<?php
	namespace BigTree;

	ini_set("auto_detect_line_endings", true);
	
	if (!file_exists(SERVER_ROOT."cache/404-import.csv")) {
		Router::redirect(ADMIN_ROOT."dashboard/vitals-statistics/404/upload-csv/");
	}
	
	$site_key = !empty($_POST["site_key"]) ? $_POST["site_key"] : null;
	$temp_file_handle = fopen(SERVER_ROOT."cache/404-import.csv", "r");
	$imported = [];
	$total = 0;
	
	while ($row = fgetcsv($temp_file_handle, 0, ",", '"')) {
		$parsed = Redirect::parseSourceURL($row[0], $site_key);
		
		if (!in_array($parsed["url"]."?".$parsed["get_vars"], $imported)) {
			$total++;
			$imported[] = $parsed["url"]."?".$parsed["get_vars"];

			Redirect::create($row[0], $row[1], $site_key);
		}
	}
	
	fclose($temp_file_handle);
	unlink(SERVER_ROOT."cache/404-import.csv");
?>
<div class="container">
	<section>
		<p><?=Text::translate("Imported <strong>:count:</strong> record(s).", false, [":count:" => $total])?></p>
	</section>
</div>