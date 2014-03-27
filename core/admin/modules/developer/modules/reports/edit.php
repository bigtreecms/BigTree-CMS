<?
	$report = BigTreeAutoModule::getReport(end($bigtree["commands"]));
	$action = $admin->getModuleActionForReport($report);
	BigTree::globalizeArray($report);

	// Find out available views to use
	$available_views = $admin->getModuleViews("action_name",$action["module"]);
?>
<div class="container">
	<form method="post" action="<?=SECTION_ROOT?>update/<?=$report["id"]?>/" class="module">
		<? if ($_GET["return"] == "front") { ?>
		<input type="hidden" name="return_page" value="<?=htmlspecialchars($_SERVER["HTTP_REFERER"])?>" />
		<? } ?>
		<section>
			<div class="left last">
				<fieldset>
					<label class="required">Title</label>
					<input type="text" class="required" name="title" value="<?=$title?>" />
				</fieldset>

				<fieldset>
					<label class="required">Data Table</label>
					<select name="table" id="report_table" class="required">
						<option></option>
						<? BigTree::getTableSelectOptions($table); ?>
					</select>
				</fieldset>
			</div>

			<div class="right last">
				<fieldset>
					<label>Type</label>
					<select name="type" id="report_type">
						<option value="csv">CSV Export</option>
						<option value="view"<? if ($type == "view") { ?> selected="selected"<? } ?>>Filtered View</option>
					</select>
				</fieldset>

				<fieldset id="data_parser_function">
					<label>Data Parser Function <small>(optional, just the function name)</small></label>
					<input type="text" name="parser" value="<?=htmlspecialchars($parser)?>" />
					<p class="note">Your function will receive an array of records to modify and return.</p>
				</fieldset>

				<fieldset id="filtered_view" style="display: none;">
					<label>Filtered View <small>(after the report is submitted, it will show data using this view)</small></label>
					<select name="return_view">
						<? foreach ($available_views as $v) { ?>
						<option value="<?=$v["id"]?>"<? if ($view == $v["id"]) { ?> selected="selected"<? } ?>><?=$v["action_name"]?></option>
						<? } ?>
					</select>
				</fieldset>
			</div>
		</section>
		<section class="sub" id="field_area">
			<? include BigTree::path("admin/ajax/developer/load-report.php") ?>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Update" />
		</footer>
	</form>
</div>
<? include BigTree::path("admin/modules/developer/modules/reports/_js.php") ?>