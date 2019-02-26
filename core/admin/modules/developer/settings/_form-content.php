<?php
	namespace BigTree;
	
	/**
	 * @global string $error
	 * @global Setting $setting
	 */
	
	$field_types = FieldType::reference(true, "settings");
	
	CSRF::drawPOSTToken();

	if (isset($_GET["return"])) { ?>
<input type="hidden" name="return_to_front" value="true" />
<?php
	}
?>
<section>
	<p class="error_message"<?php if (!$error) { ?> style="display: none;"<?php } ?>><?=Text::translate("Errors found! Please fix the highlighted fields before submitting.")?></p>
	<div class="contain">
		<div class="left">
			<fieldset<?php if ($error) { ?> class="form_error"<?php } ?>>
				<label for="setting_field_id" class="required">
					<?=Text::translate('ID <small>(unique &mdash; this will be used to query for this setting)</small>')?>
					<?php if ($error) { ?>
					<span class="form_error_reason"><?=Text::translate("ID Already In Use")?></span>
					<?php } ?>
				</label>
				<input id="setting_field_id" type="text" name="id" value="<?=Text::htmlEncode($setting->ID)?>" class="required" />
			</fieldset>
			<fieldset>
				<label for="setting_field_name" class="required"><?=Text::translate("Name")?></label>
				<input id="setting_field_name" type="text" name="name" value="<?=Text::htmlEncode($setting->Name)?>" class="required" />
			</fieldset>
		</div>
		<div class="right">
			<fieldset>
				<label for="settings_type" class="required"><?=Text::translate("Type")?></label>
				<select name="type" id="settings_type">
					<optgroup label="<?=Text::translate("Default", true)?>">
						<?php foreach ($field_types["default"] as $id => $field_type) { ?>
						<option value="<?=$id?>"<?php if ($id == $setting->Type) { ?> selected="selected"<?php } ?>><?=$field_type["name"]?></option>
						<?php } ?>
					</optgroup>
					<?php if (count($field_types["custom"])) { ?>
					<optgroup label="<?=Text::translate("Custom", true)?>">
						<?php foreach ($field_types["custom"] as $id => $field_type) { ?>
						<option value="<?=$id?>"<?php if ($id == $setting->Type) { ?> selected="selected"<?php } ?>><?=$field_type["name"]?></option>
						<?php } ?>
					</optgroup>
					<?php } ?>
				</select> <a class="icon_settings" href="#"></a>
				<input type="hidden" name="settings" value="<?=Text::htmlEncode(json_encode($setting->Settings))?>" id="field_settings" />
			</fieldset>
			<fieldset>
				<input id="setting_field_locked" type="checkbox" name="locked"<?php if ($setting->Locked) { ?> checked="checked"<?php } ?> />
				<label for="setting_field_locked" class="for_checkbox"><?=Text::translate("Locked to Developers")?></label>
			</fieldset>
			<fieldset>
				<input id="setting_field_encrypted" type="checkbox" name="encrypted"<?php if ($setting->Encrypted) { ?> checked="checked"<?php } ?> />
				<label for="setting_field_encrypted" class="for_checkbox"><?=Text::translate("Encrypted")?></label>
			</fieldset>
		</div>
	</div>
	<fieldset>
		<label for="setting_description"><?=Text::translate("Description")?></label>
		<textarea name="description" id="setting_description"><?=Text::htmlEncode($setting->Description)?></textarea>
	</fieldset>
</section>
<script>
	$(".icon_settings").click(function(ev) {
		ev.preventDefault();

		// Prevent double clicks
		if (BigTree.Busy) {
			return;
		}

		BigTreeDialog({
			title: "<?=Text::translate("Field Settings", true)?>",
			url: "<?=ADMIN_ROOT?>ajax/developer/load-field-settings/",
			post: { setting: "true", type: $("#settings_type").val(), data: $("#field_settings").val() },
			icon: "edit",
			callback: function(data) { $("#field_settings").val(JSON.stringify(data)); }
		});
	});
	
	BigTreeFormValidator("form.module");
</script>
<?php
	$bigtree["html_fields"] = array("setting_description");
	include Router::getIncludePath("admin/layouts/_html-field-loader.php");
?>