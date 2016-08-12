<?php
	namespace BigTree;

	/**
	 * @global array $bigtree
	 */

	$report = new ModuleReport(end($bigtree["commands"]));
	$action = ModuleAction::getByInterface($report->ID);
	$available_views = ModuleView::allByModule($action["module"], "title");
?>
<div class="container">
	<form method="post" action="<?=SECTION_ROOT?>update/<?=$report["id"]?>/" class="module">
		<?php if ($_GET["return"] == "front") { ?>
		<input type="hidden" name="return_page" value="<?=htmlspecialchars($_SERVER["HTTP_REFERER"])?>" />
		<?php } ?>
		<section>
			<div class="left last">
				<fieldset>
					<label for="report_field_title" class="required"><?=Text::translate("Title")?></label>
					<input id="report_field_title" type="text" class="required" name="title" value="<?=$report->Title?>" />
				</fieldset>

				<fieldset>
					<label for="report_table" class="required"><?=Text::translate("Data Table")?></label>
					<select name="table" id="report_table" class="required">
						<option></option>
						<?php SQL::drawTableSelectOptions($report->Table); ?>
					</select>
				</fieldset>

				<fieldset id="filtered_view"<?php if ($report->Type == "csv") { ?> style="display: none;"<?php } ?>>
					<label for="report_field_view"><?=Text::translate("Filtered View <small>(after the report is submitted, it will show data using this view)</small>")?></label>
					<select id="report_field_view" name="view">
						<?php foreach ($available_views as $v) { ?>
						<option value="<?=$v["id"]?>"<?php if ($report->View == $v["id"]) { ?> selected="selected"<?php } ?>><?=$v["title"]?></option>
						<?php } ?>
					</select>
				</fieldset>
			</div>

			<div class="right last">
				<fieldset>
					<label for="report_type"><?=Text::translate("Type")?></label>
					<select name="type" id="report_type">
						<option value="csv"><?=Text::translate("CSV Export")?></option>
						<option value="view"<?php if ($report->Type == "view") { ?> selected="selected"<?php } ?>><?=Text::translate("Filtered View")?></option>
					</select>
				</fieldset>

				<fieldset id="data_parser_function">
					<label for="report_field_parser"><?=Text::translate("Data Parser Function <small>(optional, just the function name)</small>")?></label>
					<input id="report_field_parser" type="text" name="parser" value="<?=htmlspecialchars($report->Parser)?>" />
					<p class="note"><?=Text::translate("Your function will receive an array of records to modify and return.")?></p>
				</fieldset>
			</div>
		</section>
		<section class="sub" id="field_area">
			<?php include Router::getIncludePath("admin/ajax/developer/load-report.php") ?>
		</section>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Update", true)?>" />
		</footer>
	</form>
</div>
<?php include Router::getIncludePath("admin/modules/developer/modules/reports/_js.php") ?>