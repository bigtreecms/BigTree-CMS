<?php
	namespace BigTree;

	$module = new Module($_GET["id"]);
	$available_views = ModuleView::allByModule($module->ID, "title");
?>
<div class="container">
	<form method="post" action="<?=SECTION_ROOT?>create/<?=$module["id"]?>/" class="module">
		<section>
			<div class="left last">
				<fieldset>
					<label for="report_field_title" class="required"><?=Text::translate("Title")?></label>
					<input id="report_field_title" type="text" class="required" name="title" />
				</fieldset>

				<fieldset>
					<label for="report_table" class="required"><?=Text::translate("Data Table")?></label>
					<select name="table" id="report_table" class="required">
						<option></option>
						<?php SQL::drawTableSelectOptions(); ?>
					</select>
				</fieldset>

				<fieldset id="filtered_view" style="display: none;">
					<label for="report_field_view"><?=Text::translate("Filtered View <small>(after the report is submitted, it will show data using this view)</small>")?></label>
					<select id="report_field_view" name="view">
						<?php foreach ($available_views as $view) { ?>
						<option value="<?=$view->ID?>"<?php if (isset($_GET["view"]) && $_GET["view"] == $view->ID) { ?> selected="selected"<?php } ?>><?=$view->Title?></option>
						<?php } ?>
					</select>
				</fieldset>
			</div>

			<div class="right last">
				<fieldset>
					<label for="report_type"><?=Text::translate("Type")?></label>
					<select name="type" id="report_type">
						<option value="csv"><?=Text::translate("CSV Export")?></option>
						<option value="view"><?=Text::translate("Filtered View")?></option>
					</select>
				</fieldset>

				<fieldset id="data_parser_function">
					<label for="report_field_parser"><?=Text::translate("Data Parser Function <small>(optional, just the function name)</small>")?></label>
					<input id="report_field_parser" type="text" name="parser" />
					<p class="note"><?=Text::translate("Your function will receive an array of records to modify and return.")?></p>
				</fieldset>
			</div>
		</section>
		<section class="sub" id="field_area">
			<p><?=Text::translate("Please choose a table to populate this area.")?></p>
		</section>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Create", true)?>" />
		</footer>
	</form>
</div>
<?php include Router::getIncludePath("admin/modules/developer/modules/reports/_js.php") ?>