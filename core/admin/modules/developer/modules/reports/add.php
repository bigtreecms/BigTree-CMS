<?php
	namespace BigTree;
	
	$id = $_GET["module"];
	$module = $admin->getModule($id);
	$type = "csv";

	// Find out available views to use
	$available_views = $admin->getModuleViews("title",$module["id"]);
?>
<div class="container">
	<form method="post" action="<?=SECTION_ROOT?>create/<?=$module["id"]?>/" class="module">
		<section>
			<div class="left last">
				<fieldset>
					<label class="required"><?=Text::translate("Title")?></label>
					<input type="text" class="required" name="title" />
				</fieldset>

				<fieldset>
					<label class="required"><?=Text::translate("Data Table")?></label>
					<select name="table" id="report_table" class="required">
						<option></option>
						<?php \BigTree::getTableSelectOptions(); ?>
					</select>
				</fieldset>

				<fieldset id="filtered_view" style="display: none;">
					<label><?=Text::translate("Filtered View <small>(after the report is submitted, it will show data using this view)</small>")?></label>
					<select name="view">
						<?php foreach ($available_views as $view) { ?>
						<option value="<?=$view["id"]?>"<?php if (isset($_GET["view"]) && $_GET["view"] == $view["id"]) { ?> selected="selected"<?php } ?>><?=$view["title"]?></option>
						<?php } ?>
					</select>
				</fieldset>
			</div>

			<div class="right last">
				<fieldset>
					<label><?=Text::translate("Type")?></label>
					<select name="type" id="report_type">
						<option value="csv"><?=Text::translate("CSV Export")?></option>
						<option value="view"><?=Text::translate("Filtered View")?></option>
					</select>
				</fieldset>

				<fieldset id="data_parser_function">
					<label><?=Text::translate("Data Parser Function <small>(optional, just the function name)</small>")?></label>
					<input type="text" name="parser" />
					<p class="note"><?=Text::translate("Your function will receive an array of records to modify and return.")?></p>
				</fieldset>
			</div>
		</section>
		<section class="sub" id="field_area">
			<?php
				if ($table) {
					include Router::getIncludePath("admin/ajax/developer/load-report.php");
				} else {
					echo "<p>".Text::translate("Please choose a table to populate this area.")."</p>";
				}
			?>
		</section>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Create", true)?>" />
		</footer>
	</form>
</div>
<?php include Router::getIncludePath("admin/modules/developer/modules/reports/_js.php") ?>