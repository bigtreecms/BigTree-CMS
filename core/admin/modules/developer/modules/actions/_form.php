<?
	$forms = $admin->getModuleForms();
	$views = $admin->getModuleViews();
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
	<div class="triplets">
		<fieldset>
			<label>Access Level</label>
			<select name="level">
				<option value="0">Normal User</option>
				<option value="1"<? if ($item["level"] == 1) { ?> selected="selected"<? } ?>>Administrator</option>
				<option value="2"<? if ($item["level"] == 2) { ?> selected="selected"<? } ?>>Developer</option>
			</select>
		</fieldset>
		<fieldset>
			<label>Form</label>
			<select name="form"<? if ($item["view"]) { ?> disabled="disabled"<? } ?>>
				<option value="">&mdash;</option>
				<? foreach ($forms as $form) { ?>
				<option value="<?=$form["id"]?>"<? if ($form["id"] == $item["form"]) { ?> selected="selected"<? } ?>><?=$form["title"]?> (<?=$form["table"]?>)</option>
				<? } ?>
			</select>
		</fieldset>
		<fieldset>
			<label>View</label>
			<select name="view"<? if ($item["form"]) { ?> disabled="disabled"<? } ?>>
				<option value="">&mdash;</option>
				<? foreach ($views as $view) { ?>
				<option value="<?=$view["id"]?>"<? if ($view["id"] == $item["view"]) { ?> selected="selected"<? } ?>><?=$view["title"]?> (<?=$view["table"]?>)</option>
				<? } ?>
			</select>
		</fieldset>
	</div>
	<fieldset>
		<label class="required">Icon</label>
		<input type="hidden" name="class" id="selected_icon" value="<?=$item["class"]?>" />
		<ul class="developer_icon_list">
			<? foreach ($admin->ActionClasses as $class) { ?>
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
	new BigTreeFormValidator("form.module");
	$("select[name=form]").change(function() {
		if ($(this).val()) {
			$("select[name=view]").get(0).customControl.disable();
		} else {
			$("select[name=view]").get(0).customControl.enable();
		}
	});
	$("select[name=view]").change(function() {
		if ($(this).val()) {
			$("select[name=form]").get(0).customControl.disable();
		} else {
			$("select[name=form]").get(0).customControl.enable();
		}
	});
</script>