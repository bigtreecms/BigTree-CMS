<?php
	if (empty($_FILES["csv"]["tmp_name"])) {
		$error = "The CSV file failed to upload. Please try again.";
	} else {
		$x = $overwrite = $dupes = 0;
		$f = fopen($_FILES["csv"]["tmp_name"], "r");
		$temp_file = fopen(SERVER_ROOT."cache/404-import.csv", "w");
		$to_import = [];

		while ($row = fgetcsv($f, 0, ",", '"')) {
			$x++;

			if (!empty($_POST["first_row_titles"]) && $x == 1) {
				continue;
			}

			$row = array_filter($row);
			$count = count($row);

			if ($count != 2) {
				$error = "The provided CSV does not appear to match the proper format and has one or more rows with $count column".($count != 1 ? "s" : "").".";
				break;
			} else {
				$parsed = BigTreeAdmin::parse404SourceURL($row[0], $_POST["site_key"] ?: null);

				if (in_array($parsed["url"]."?".$parsed["get_vars"], $to_import)) {
					$dupes++;
				} else {
					fputcsv($temp_file, $row, ",", '"');

					$existing = BigTreeAdmin::getExisting404($parsed["url"], $parsed["get_vars"], $_POST["site_key"] ?: null);
					$to_import[] = $parsed["url"]."?".$parsed["get_vars"];

					if ($existing && $existing["redirect_url"]) {
						$overwrite++;
					}
				}
			}
		}
	}

	fclose($f);
	fclose($temp_file);

	if ($error) {
		BigTree::redirect(ADMIN_ROOT."dashboard/vitals-statistics/404/upload-csv/?error=".urlencode($error));
		die();
	}
?>
<form class="container" method="post" action="<?=ADMIN_ROOT?>dashboard/vitals-statistics/404/process-csv/">
	<input type="hidden" name="site_key" value="<?=BigTree::safeEncode($_POST["site_key"])?>">
	<section>
		<p>
			Ready to import <strong><?=($x - $dupes)?></strong> redirects.
			<?php
				if ($overwrite) {
					echo "<br><strong>".$overwrite."</strong> existing redirect".($overwrite != 1 ? "s" : "")." will be replaced.";
				}

				if ($dupes) {
					echo "<br><strong>".$dupes."</strong> duplicate source URL".($dupes != 1 ? "s were" : " was")." found in the CSV. Only the first record will import.";
				}
			?>
		</p>
	</section>
	<footer>
		<input type="submit" class="button blue" value="Import">
	</footer>
</div>