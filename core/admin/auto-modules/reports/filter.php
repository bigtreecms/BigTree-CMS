<?php
	namespace BigTree;

	/**
	 * @global ModuleReport $report
	 */
?>
<div class="container">
	<form method="post" action="<?=ADMIN_ROOT.Router::$Module->Route."/".Router::$ModuleAction->Route."/".$report->Type?>/">
		<?php
			if (Auth::user()->Level > 1) {
		?>
		<div class="developer_buttons">
			<a href="<?=ADMIN_ROOT?>developer/modules/reports/edit/<?=$report->ID?>/?return=front" title="<?=Text::translate("Edit Report in Developer", true)?>">
				<?=Text::translate("Edit Report in Developer")?>
				<span class="icon_small icon_small_edit_yellow"></span>
			</a>
		</div>
		<?php
			}
		?>
		<section>
			<?php
				$x = 1;
				
				foreach ($report->Filters as $id => $filter) {
					$filter_field_id = "report_filter_field_$x";
					$x++;
			?>
			<fieldset>
				<label for="<?=$filter_field_id?>"><?=$filter["title"]?></label>
				<?php include Router::getIncludePath("admin/auto-modules/reports/filters/".$filter["type"].".php") ?>	
			</fieldset>
			<?php
				}
			?>
			<div class="sub_section last">
				<fieldset class="float_margin">
					<label for="report_field_sort_by"><?=Text::translate("Sort By")?></label>
					<select id="report_field_sort_by" name="*sort[field]">
						<?php
							if ($report->Type == "csv") {
								foreach ($report->Fields as $key => $title) {
						?>
						<option value="<?=htmlspecialchars($key)?>"><?=htmlspecialchars($title)?></option>
						<?php
								}
							} else {
								$view = $report->RelatedModuleView;
								
								foreach ($view->Fields as $key => $field) {
						?>
						<option value="<?=htmlspecialchars($key)?>"><?=$field["title"]?></option>
						<?php
								}
							}
						?>
					</select>
				</fieldset>
				<fieldset>
					<label for="report_field_sort_order"><?=Text::translate("Sort Order")?></label>
					<select id="report_field_sort_order" name="*sort[order]">
						<option value="ASC"><?=Text::translate("Ascending")?></option>
						<option value="DESC"><?=Text::translate("Descending")?></option>
					</select>
				</fieldset>
			</div>
		</section>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Submit", true)?>" />
		</footer>
	</form>
</div>