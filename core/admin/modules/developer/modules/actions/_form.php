<?php
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
				<option value="1"<?php if ($item["level"] == 1) { ?> selected="selected"<?php } ?>>Administrator</option>
				<option value="2"<?php if ($item["level"] == 2) { ?> selected="selected"<?php } ?>>Developer</option>
			</select>
		</fieldset>
		<fieldset class="float">
			<label>Interface</label>
			<select name="interface">
				<option></option>
				<?php
					if (count($forms)) {
				?>
				<optgroup label="Forms">
					<?php foreach ($forms as $form) { ?>
					<option value="<?=$form["id"]?>"<?php if ($form["id"] == $item["interface"]) { ?> selected="selected"<?php } ?>>Add/Edit <?=$form["title"]?> (<?=$form["table"]?>)</option>
					<?php } ?>
				</optgroup>
				<?php
					}
					if (count($views)) {
				?>
				<optgroup label="Views">
					<?php foreach ($views as $view) { ?>
					<option value="<?=$view["id"]?>"<?php if ($view["id"] == $item["interface"]) { ?> selected="selected"<?php } ?>>View <?=$view["title"]?> (<?=$view["table"]?>)</option>
					<?php } ?>
				</optgroup>
				<?php
					}
					if (count($reports)) {
				?>
				<optgroup label="Reports">
					<?php foreach ($reports as $report) { ?>
					<option value="<?=$report["id"]?>"<?php if ($report["id"] == $item["interface"]) { ?> selected="selected"<?php } ?>><?=$report["title"]?> (<?=$report["table"]?>)</option>
					<?php } ?>
				</optgroup>
				<?php
					}
				?>
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