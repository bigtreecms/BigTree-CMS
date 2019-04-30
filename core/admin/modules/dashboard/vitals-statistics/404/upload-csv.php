<?php
	namespace BigTree;
	
	/** @global array $bigtree */
?>
<form class="container" action="<?=ADMIN_ROOT?>dashboard/vitals-statistics/404/import-csv/" method="post" enctype="multipart/form-data">
	<section>
		<p><?=Text::translate("To import a CSV of redirects you must upload a comma delimited and quote escaped CSV file (not XLS, XLSX, TSV, etc) with the following two columns:")?></p>
		<ol>
			<li><?=Text::translate("<strong>Source URL</strong> (a full URL or the URL fragment following :www_root:)", false, [":www_root:" => WWW_ROOT])?></li>
			<li><?=Text::translate("<strong>Destination URL</strong> (a full URL including http://)")?></li>
		</ol>
		<hr>
		<?php
			if (!empty($_GET["error"])) {
		?>
		<p class="error_message"><?=Text::translate($_GET["error"], true)?></p>
		<?php
			}
			
			if (is_array($bigtree["config"]["sites"]) && count($bigtree["config"]["sites"]) > 1) {
		?>
		<fieldset>
			<label for="field_site_key"><?=Text::translate("Site <small>(if you enter a full URL into Source URL column this will be automatically infered based on the URL)</small>")?></label>
			<select id="field_site_key" name="site_key">
				<?php
					foreach ($bigtree["config"]["sites"] as $site_key => $site) {
						$domain = parse_url($site["domain"],  PHP_URL_HOST);
				?>
				<option value="<?=Text::htmlEncode($site_key)?>"><?=$domain?></option>
				<?php
					}
				?>
			</select>
		</fieldset>
		<?php
			}
		?>
		<fieldset>
			<label for="field_csv"><?=Text::translate("CSV File")?></label>
			<input type="file" accept=".csv" name="csv" id="field_csv" class="required">
		</fieldset>
		<fieldset>
			<input type="checkbox" name="first_row_titles" id="field_row_titles">
			<label class="for_checkbox" for="field_row_titles"><?=Text::translate("First Row Contains Column Titles")?></label>
		</fieldset>
	</section>
	<footer>
		<input type="submit" class="button blue" value="<?=Text::translate("Upload", true)?>">
	</footer>
</form>

<script>
	new BigTreeFormValidator("form.container");
</script>