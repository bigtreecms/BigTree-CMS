<?php
	namespace BigTree;
	
	/**
	 * @global array $interface_list
	 * @global ModuleAction $action
	 */
	
	// Get list of interfaces but dump embeddable forms since they're for the front end
	$id = $_GET["module"];
	include Router::getIncludePath("admin/modules/developer/modules/_interface-sort.php");
	unset($interface_list["embeddable-form"]);
?>
<section>
	<fieldset>
		<label for="action_field_name" class="required"><?=Text::translate("Name")?></label>
		<input id="action_field_name" type="text" name="name" class="required" value="<?=Text::htmlEncode($action->Name)?>" />
	</fieldset>
	<fieldset>
		<label for="action_field_route"><?=Text::translate("Route")?></label>
		<input id="action_field_route" type="text" name="route" value="<?=Text::htmlEncode($action->Route)?>" />
	</fieldset>
	<div class="contain">
		<fieldset class="float">
			<label for="action_field_level"><?=Text::translate("Access Level")?></label>
			<select id="action_field_level" name="level">
				<option value="0"><?=Text::translate("Normal User")?></option>
				<option value="1"<?php if ($action->Level == 1) { ?> selected="selected"<?php } ?>><?=Text::translate("Administrator")?></option>
				<option value="2"<?php if ($action->Level == 2) { ?> selected="selected"<?php } ?>><?=Text::translate("Developer")?></option>
			</select>
		</fieldset>
		<fieldset class="float">
			<label for="action_field_interface"><?=Text::translate("Interface")?></label>
			<select id="action_field_interface" name="interface">
				<option></option>
				<?php
					foreach ($interface_list as $type => $info) {
						if (count($info["items"])) {
				?>
				<optgroup label="<?=Text::htmlEncode($info["name"])?>">
					<?php foreach ($info["items"] as $interface) { ?>
					<option value="<?=$interface["id"]?>"<?php if ($interface["id"] == $action->Interface) { ?> selected="selected"<?php } ?>><?=$interface["title"]?> (<?=$interface["table"]?>)</option>
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
		<input type="hidden" name="class" id="selected_icon" value="<?=Text::htmlEncode($action->Icon)?>" />
		<ul class="developer_icon_list">
			<?php foreach (Module::$IconClasses as $class) { ?>
			<li>
				<a href="#<?=$class?>"<?php if ($class == $action->Icon) { ?> class="active"<?php } ?>><span class="icon_small icon_small_<?=$class?>"></span></a>
			</li>
			<?php } ?>
		</ul>
	</fieldset>
	<fieldset>
		<label for="action_field_in_nav"><?=Text::translate("In Navigation")?></label>
		<input id="action_field_in_nav" type="checkbox" name="in_nav" <?php if ($action->InNav) { ?>checked="checked" <?php } ?>/>
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