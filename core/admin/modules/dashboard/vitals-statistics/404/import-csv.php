<?php
	namespace BigTree;
	
	$error = null;
	$total = $overwrite = $dupes = 0;
	
	if (empty($_FILES["csv"]["tmp_name"])) {
		$error = Text::translate("The CSV file failed to upload. Please try again.");
	} else {
		$upload_file_handle = fopen($_FILES["csv"]["tmp_name"], "r");
		$temp_file_handle = fopen(SERVER_ROOT."cache/404-import.csv", "w");
		$to_import = [];
		
		while ($row = fgetcsv($upload_file_handle, 0, ",", '"')) {
			$total++;
			
			if (!empty($_POST["first_row_titles"]) && $total == 1) {
				continue;
			}
			
			$row = array_filter($row);
			$count = count($row);
			
			if ($count != 2) {
				$error = Text::translate("The provided CSV does not appear to match the proper format and has one or more rows with :count: column(s).", false, [":count:" => $count]);
				break;
			} else {
				$parsed = Redirect::parseSourceURL($row[0], $_POST["site_key"] ?: null);
				
				if (in_array($parsed["url"]."?".$parsed["get_vars"], $to_import)) {
					$dupes++;
				} else {
					fputcsv($temp_file_handle, $row, ",", '"');
					
					$existing = Redirect::getExisting($parsed["url"], $parsed["get_vars"], $parsed["site_key"]);
					$to_import[] = $parsed["url"]."?".$parsed["get_vars"];
					
					if ($existing && $existing["redirect_url"]) {
						$overwrite++;
					}
				}
			}
		}
		
		fclose($upload_file_handle);
		fclose($temp_file_handle);
	}
	
	if ($error) {
		Router::redirect(ADMIN_ROOT."dashboard/vitals-statistics/404/upload-csv/?error=".urlencode($error));
		die();
	}
?>
<form class="container" method="post" action="<?=ADMIN_ROOT?>dashboard/vitals-statistics/404/process-csv/">
	<input type="hidden" name="site_key" value="<?=BigTree::safeEncode($_POST["site_key"])?>">
	<section>
		<p>
			<?php
				echo Text::translate("Ready to import <strong>:count:</strong> redirect(s).", false, [":count:" => $total - $dupes]);
				
				if ($overwrite) {
					echo "<br>";
					echo Text::translate("<strong>:count:</strong> existing redirect(s) will be replaced.", false, [":count:" => $overwrite]);
				}
				
				if ($dupes) {
					echo "<br>";
					echo Text::translate("<strong>:count:</strong> duplicate source URL(s) were found in the CSV. Only the first record will import.", false, [":count:" => $dupes]);
				}
			?>
		</p>
	</section>
	<footer>
		<input type="submit" class="button blue" value="<?=Text::translate("Import", true)?>">
	</footer>
</form>