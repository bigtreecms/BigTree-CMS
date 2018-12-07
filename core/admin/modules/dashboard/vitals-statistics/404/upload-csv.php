<form class="container" action="<?=ADMIN_ROOT?>dashboard/vitals-statistics/404/import-csv/" method="post" enctype="multipart/form-data">
	<section>
		<p>To import a CSV of redirects you must upload a comma delimited and quote escaped CSV file (not XLS, XLSX, TSV, etc) with the following two columns:</p>
		<ol>
			<li><strong>Source URL</strong> (a full URL or the URL fragment following <?=WWW_ROOT?>)</li>
			<li><strong>Destination URL</strong> (a full URL including http://)</li>
		</ol>
		<hr>
		<?php
			if (!empty($_GET["error"])) {
		?>
		<p class="error_message"><?=BigTree::safeEncode($_GET["error"])?></p>
		<?php
			}

			if (is_array($bigtree["config"]["sites"]) && count($bigtree["config"]["sites"]) > 1) {
		?>
		<fieldset>
			<label>Site <small>(if you enter a full URL into Source URL column this will be automatically infered based on the URL)</small></label>
			<select name="site_key">
				<?php
					foreach ($bigtree["config"]["sites"] as $site_key => $site) {
						$domain = parse_url($site["domain"],  PHP_URL_HOST);
				?>
				<option value="<?=BigTree::safeEncode($site_key)?>"><?=$domain?></option>
				<?php
					}
				?>
			</select>
		</fieldset>
		<?php
			}
		?>
		<fieldset>
			<label for="field_csv">CSV File</label>
			<input type="file" accept=".csv" name="csv" id="field_csv" class="required">
		</fieldset>
		<fieldset>
			<input type="checkbox" name="first_row_titles" id="field_row_titles">
			<label class="for_checkbox" for="field_row_titles">First Row Contains Column Titles</label>
		</fieldset>
	</section>
	<footer>
		<input type="submit" class="button blue" value="Upload">
	</footer>
</form>

<script>
	new BigTreeFormValidator("form.container");
</script>