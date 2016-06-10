<?php
	namespace BigTree;
	
	// Get list of interfaces but dump embeddable forms since they're for the front end
	$id = $_GET["module"];
	include Router::getIncludePath("admin/modules/developer/modules/_interface-sort.php");
	unset($interface_list["embeddable-form"]);
?>
<section>
	<fieldset>
		<label class="required"><?=Text::translate("Name")?></label>
		<input type="text" name="name" class="required" value="<?=$item["name"]?>" />
	</fieldset>
	<fieldset>
		<label><?=Text::translate("Route")?></label>
		<input type="text" name="route" value="<?=$item["route"]?>" />
	</fieldset>
	<div class="contain">
		<fieldset class="float">
			<label><?=Text::translate("Access Level")?></label>
			<select name="level">
				<option value="0"><?=Text::translate("Normal User")?></option>
				<option value="1"<?php if ($item["level"] == 1) { ?> selected="selected"<?php } ?>><?=Text::translate("Administrator")?></option>
				<option value="2"<?php if ($item["level"] == 2) { ?> selected="selected"<?php } ?>><?=Text::translate("Developer")?></option>
			</select>
		</fieldset>
		<fieldset class="float">
			<label><?=Text::translate("Interface")?></label>
			<select name="interface">
				<option></option>
				<?php
					foreach ($interface_list as $type => $info) {
						if (count($info["items"])) {
				?>
				<optgroup label="<?=Text::htmlEncode($info["name"])?>">
					<?php foreach ($info["items"] as $interface) { ?>
					<option value="<?=$interface["id"]?>"<?php if ($interface["id"] == $item["interface"]) { ?> selected="selected"<?php } ?>><?=$interface["title"]?> (<?=$interface["table"]?>)</option>
					<?php } ?>
				</optgroup>
				<?php
						}
					}
				?>
			</select>
		</fieldset>
	</div>
	<fieldset>
		<label class="required"><?=Text::translate("Icon")?></label>
		<input type="hidden" name="class" id="selected_icon" value="<?=$item["class"]?>" />
		<ul class="developer_icon_list">
			<?php foreach (Module::$IconClasses as $class) { ?>
			<li>
				<a href="#<?=$class?>"<?php if ($class == $item["class"]) { ?> class="active"<?php } ?>><span class="icon_small icon_small_<?=$class?>"></span></a>
			</li>
			<?php } ?>
		</ul>
	</fieldset>
	<fieldset>
		<label><?=Text::translate("In Navigation")?></label>
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