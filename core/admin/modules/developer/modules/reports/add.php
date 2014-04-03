<?
	$id = $_GET["module"];
	$module = $admin->getModule($id);
	$type = "csv";

	// Find out available views to use
	$available_views = $admin->getModuleViews("action_name",$module["id"]);
?>
<div class="container">
	<form method="post" action="<?=SECTION_ROOT?>create/<?=$module["id"]?>/" class="module">
		<section>
			<div class="left last">
				<fieldset>
					<label class="required">Title</label>
					<input type="text" class="required" name="title" />
				</fieldset>

				<fieldset>
					<label class="required">Data Table</label>
					<select name="table" id="report_table" class="required">
						<option></option>
						<? BigTree::getTableSelectOptions(); ?>
					</select>
				</fieldset>
			</div>

			<div class="right last">
				<fieldset>
					<label>Type</label>
					<select name="type" id="report_type">
						<option value="csv">CSV Export</option>
						<option value="view">Filtered View</option>
					</select>
				</fieldset>

				<fieldset id="data_parser_function">
					<label>Data Parser Function <small>(optional, just the function name)</small></label>
					<input type="text" name="parser" />
					<p class="note">Your function will receive an array of records to modify and return.</p>
				</fieldset>

				<fieldset id="filtered_view" style="display: none;">
					<label>Filtered View <small>(after the report is submitted, it will show data using this view)</small></label>
					<select name="return_view">
						<? foreach ($available_views as $view) { ?>
						<option value="<?=$view["id"]?>"<? if (isset($_GET["view"]) && $_GET["view"] == $view["id"]) { ?> selected="selected"<? } ?>><?=$view["action_name"]?></option>
						<? } ?>
					</select>
				</fieldset>
			</div>
		</section>
		<section class="sub" id="field_area">
			<?
				if ($table) {
					include BigTree::path("admin/ajax/developer/load-report.php");
				} else {
					echo "<p>Please choose a table to populate this area.</p>";
				}
			?>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Create" />
		</footer>
	</form>
</div>
<? include BigTree::path("admin/modules/developer/modules/reports/_js.php") ?>