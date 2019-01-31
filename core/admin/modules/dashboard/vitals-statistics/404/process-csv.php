<?php
	if (!file_exists(SERVER_ROOT."cache/404-import.csv")) {
		BigTree::redirect(ADMIN_ROOT."dashboard/vitals-statistics/404/upload-csv/");
	}

	$site_key = !empty($_POST["site_key"]) ? $_POST["site_key"] : null;
	$temp_file = fopen(SERVER_ROOT."cache/404-import.csv", "r");
	$imported = [];
	$x = 0;

	while ($row = fgetcsv($temp_file, 0, ",", '"')) {
		$parsed = BigTreeAdmin::parse404SourceURL($row[0], $_POST["site_key"] ?: null);

		if (!in_array($parsed["url"]."?".$parsed["get_vars"], $imported)) {
			$x++;
			$admin->create301($row[0], $row[1], $site_key);
			$imported[] = $parsed["url"]."?".$parsed["get_vars"];
		}
	}

	fclose($temp_file);
	unlink(SERVER_ROOT."cache/404-import.csv");
?>
<div class="container">
	<section>
		<p>Imported <strong><?=$x?></strong> record<?php if ($x != 1) { echo "s"; } ?>.</p>
	</section>
</div>