<?
	$forms = $admin->getModuleForms("title",$module["id"]);
	$views = $admin->getModuleViews("title",$module["id"]);
	$reports = $admin->getModuleReports("title",$module["id"]);
?>
<section>
	<fieldset>
		<label class="required">Name</label>
		<input type="text" name="name" class="required" value="<?=$item["name"]?>" />
	</fieldset>
	<fieldset>
		<label>Route</label>
		<input type="text" name="route" value="<?=$item["route"]?>" />
	</fieldset>
	<div class="contain">
		<fieldset class="float">
			<label>Access Level</label>
			<select name="level">
				<option value="0">Normal User</option>
				<option value="1"<? if ($item["level"] == 1) { ?> selected="selected"<? } ?>>Administrator</option>
				<option value="2"<? if ($item["level"] == 2) { ?> selected="selected"<? } ?>>Developer</option>
			</select>
		</fieldset>
		<fieldset class="float">
			<label>Interface</label>
			<select name="function">
				<option></option>
				<optgroup label="Forms">
					<? foreach ($forms as $form) { ?>
					<option value="form-<?=$form["id"]?>"<? if ($form["id"] == $item["form"]) { ?> selected="selected"<? } ?>>Add/Edit <?=$form["title"]?> (<?=$form["table"]?>)</option>
					<? } ?>
				</optgroup>
				<optgroup label="Views">
					<? foreach ($views as $view) { ?>
					<option value="view-<?=$view["id"]?>"<? if ($view["id"] == $item["view"]) { ?> selected="selected"<? } ?>>View <?=$view["title"]?> (<?=$view["table"]?>)</option>
					<? } ?>
				</optgroup>
				<optgroup label="Reports">
					<? foreach ($reports as $report) { ?>
					<option value="report-<?=$report["id"]?>"<? if ($report["id"] == $item["report"]) { ?> selected="selected"<? } ?>><?=$report["title"]?> (<?=$report["table"]?>)</option>
					<? } ?>
				</optgroup>
			</select>
		</fieldset>
	</div>
	<fieldset>
		<label class="required">Icon</label>
		<input type="hidden" name="class" id="selected_icon" value="<?=$item["class"]?>" />
		<ul class="developer_icon_list">
			<? foreach (BigTreeAdmin::$ActionClasses as $class) { ?>
			<li>
				<a href="#<?=$class?>"<? if ($class == $item["class"]) { ?> class="active"<? } ?>><span class="icon_small icon_small_<?=$class?>"></span></a>
			</li>
			<? } ?>
		</ul>
	</fieldset>
	<fieldset>
		<label>In Navigation</label>
		<input type="checkbox" name="in_nav" <? if ($item["in_nav"]) { ?>checked="checked" <? } ?>/>
	</fieldset>
</section>
<script>
	BigTreeFormValidator("form.module");
	$("select[name=form]").change(function() {
		if ($(this).val()) {
			$("select[name=view]").get(0).customControl.disable();
			$("select[name=report]").get(0).customControl.disable();
		} else {
			$("select[name=view]").get(0).customControl.enable();
			$("select[name=report]").get(0).customControl.enable();
		}
	});
	$("select[name=view]").change(function() {
		if ($(this).val()) {
			$("select[name=form]").get(0).customControl.disable();
			$("select[name=report]").get(0).customControl.disable();
		} else {
			$("select[name=form]").get(0).customControl.enable();
			$("select[name=report]").get(0).customControl.enable();
		}
	});
	$("select[name=report]").change(function() {
		if ($(this).val()) {
			$("select[name=form]").get(0).customControl.disable();
			$("select[name=view]").get(0).customControl.disable();
		} else {
			$("select[name=form]").get(0).customControl.enable();
			$("select[name=view]").get(0).customControl.enable();
		}
	});
</script>