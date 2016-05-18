<?php
	namespace BigTree;
	
	$report = \BigTreeAutoModule::getReport(end($bigtree["commands"]));
	$action = $admin->getModuleActionForInterface($report);
	\BigTree::globalizeArray($report);

	// Find out available views to use
	$available_views = $admin->getModuleViews("title",$action["module"]);
?>
<div class="container">
	<form method="post" action="<?=SECTION_ROOT?>update/<?=$report["id"]?>/" class="module">
		<?php if ($_GET["return"] == "front") { ?>
		<input type="hidden" name="return_page" value="<?=htmlspecialchars($_SERVER["HTTP_REFERER"])?>" />
		<?php } ?>
		<section>
			<div class="left last">
				<fieldset>
					<label class="required"><?=Text::translate("Title")?></label>
					<input type="text" class="required" name="title" value="<?=$title?>" />
				</fieldset>

				<fieldset>
					<label class="required"><?=Text::translate("Data Table")?></label>
					<select name="table" id="report_table" class="required">
						<option></option>
						<?php \BigTree::getTableSelectOptions($table); ?>
					</select>
				</fieldset>

				<fieldset id="filtered_view"<?php if ($type == "csv") { ?> style="display: none;"<?php } ?>>
					<label><?=Text::translate("Filtered View <small>(after the report is submitted, it will show data using this view)</small>")?></label>
					<select name="view">
						<?php foreach ($available_views as $v) { ?>
						<option value="<?=$v["id"]?>"<?php if ($view == $v["id"]) { ?> selected="selected"<?php } ?>><?=$v["title"]?></option>
						<?php } ?>
					</select>
				</fieldset>
			</div>

			<div class="right last">
				<fieldset>
					<label><?=Text::translate("Type")?></label>
					<select name="type" id="report_type">
						<option value="csv"><?=Text::translate("CSV Export")?></option>
						<option value="view"<?php if ($type == "view") { ?> selected="selected"<?php } ?>><?=Text::translate("Filtered View")?></option>
					</select>
				</fieldset>

				<fieldset id="data_parser_function">
					<label><?=Text::translate("Data Parser Function <small>(optional, just the function name)</small>")?></label>
					<input type="text" name="parser" value="<?=htmlspecialchars($parser)?>" />
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