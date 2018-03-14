<?php
	$forms = $admin->getModuleForms("title",$module["id"]);
	$views = $admin->getModuleViews("title",$module["id"]);
	$reports = $admin->getModuleReports("title",$module["id"]);

	$admin->drawCSRFToken();
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
	<fieldset class="last">
		<label>Access Level</label>
		<select name="level">
			<option value="0">Normal User</option>
			<option value="1"<?php if ($item["level"] == 1) { ?> selected="selected"<?php } ?>>Administrator</option>
			<option value="2"<?php if ($item["level"] == 2) { ?> selected="selected"<?php } ?>>Developer</option>
		</select>
	</fieldset>
	<div class="triplets">
		<fieldset>
			<label>Form</label>
			<select name="form"<?php if ($item["view"] || $item["report"]) { ?> disabled="disabled"<?php } ?>>
				<option value="">&mdash;</option>
				<?php foreach ($forms as $form) { ?>
				<option value="<?=$form["id"]?>"<?php if ($form["id"] == $item["form"]) { ?> selected="selected"<?php } ?>><?=$form["title"]?> (<?=$form["table"]?>)</option>
				<?php } ?>
			</select>
		</fieldset>
		<fieldset>
			<label>View</label>
			<select name="view"<?php if ($item["form"] || $item["report"]) { ?> disabled="disabled"<?php } ?>>
				<option value="">&mdash;</option>
				<?php foreach ($views as $view) { ?>
				<option value="<?=$view["id"]?>"<?php if ($view["id"] == $item["view"]) { ?> selected="selected"<?php } ?>><?=$view["title"]?> (<?=$view["table"]?>)</option>
				<?php } ?>
			</select>
		</fieldset>
		<fieldset>
			<label>Report</label>
			<select name="report"<?php if ($item["view"] || $item["form"]) { ?> disabled="disabled"<?php } ?>>
				<option value="">&mdash;</option>
				<?php foreach ($reports as $report) { ?>
				<option value="<?=$report["id"]?>"<?php if ($report["id"] == $item["report"]) { ?> selected="selected"<?php } ?>><?=$report["title"]?> (<?=$report["table"]?>)</option>
				<?php } ?>
			</select>
		</fieldset>
	</div>
	<fieldset>
		<label class="required">Icon</label>
		<input type="hidden" name="class" id="selected_icon" value="<?=$item["class"]?>" />
		<ul class="developer_icon_list">
			<?php foreach (BigTreeAdmin::$ActionClasses as $class) { ?>
			<li>
				<a href="#<?=$class?>"<?php if ($class == $item["class"]) { ?> class="active"<?php } ?>><span class="icon_small icon_small_<?=$class?>"></span></a>
			</li>
			<?php } ?>
		</ul>
	</fieldset>
	<fieldset>
		<label>In Navigation</label>
		<input type="checkbox" name="in_nav" <?php if ($item["in_nav"]) { ?>checked="checked" <?php } ?>/>
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