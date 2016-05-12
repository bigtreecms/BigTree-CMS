<?php
	namespace BigTree;

	if (isset($_GET["return"])) { ?>
<input type="hidden" name="return_to_front" value="true" />
<?php
	}
?>
<section>
	<p class="error_message"<?php if (!$e) { ?> style="display: none;"<?php } ?>><?=Text::translate("Errors found! Please fix the highlighted fields before submitting.")?></p>
	<div class="contain">
		<div class="left">
			<fieldset<?php if ($e) { ?> class="form_error"<?php } ?>>
				<label class="required"><?=Text::translate('ID <small>(unique &mdash; this will be used to query for this setting)</small>')?><?php if ($e) { ?><span class="form_error_reason"><?=Text::translate("ID Already In Use")?></span><?php } ?></label>
				<input type="text" name="id" value="<?=$id?>" class="required" />
			</fieldset>
			<fieldset>
				<label class="required"><?=Text::translate("Name")?></label>
				<input type="text" name="name" value="<?=$name?>" class="required" />
			</fieldset>
		</div>
		<div class="right">
			<fieldset>
				<label class="required"><?=Text::translate("Type")?></label>
				<select name="type" id="settings_type">
					<optgroup label="<?=Text::translate("Default", true)?>">
						<?php foreach ($types["default"] as $k => $v) { ?>
						<option value="<?=$k?>"<?php if ($k == $type) { ?> selected="selected"<?php } ?>><?=$v["name"]?></option>
						<?php } ?>
					</optgroup>
					<?php if (count($types["custom"])) { ?>
					<optgroup label="<?=Text::translate("Custom", true)?>">
						<?php foreach ($types["custom"] as $k => $v) { ?>
						<option value="<?=$k?>"<?php if ($k == $type) { ?> selected="selected"<?php } ?>><?=$v["name"]?></option>
						<?php } ?>
					</optgroup>
					<?php } ?>
				</select> &nbsp; <a class="icon_settings" href="#"></a>
				<input type="hidden" name="options" value="<?=$options?>" id="options_settings" />
			</fieldset>
			<fieldset>
				 <input type="checkbox" name="locked"<?php if ($locked) { ?> checked="checked"<?php } ?> />
				<label class="for_checkbox"><?=Text::translate("Locked to Developers")?></label>
			</fieldset>
			<fieldset>
				<input type="checkbox" name="encrypted"<?php if ($encrypted) { ?> checked="checked"<?php } ?> />
				<label class="for_checkbox"><?=Text::translate("Encrypted")?></label>
			</fieldset>
		</div>
	</div>
	<fieldset>
		<label><?=Text::translate("Description")?></label>
		<textarea name="description" id="setting_description"><?=$description?></textarea>
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
			title: "<?=Text::translate("Setting Options", true)?>",
			url: "<?=ADMIN_ROOT?>ajax/developer/load-field-options/",
			post: { setting: "true", type: $("#settings_type").val(), data: $("#options_settings").val() },
			icon: "edit",
			callback: function(data) { $("#options_settings").val(JSON.stringify(data)); }
		});
	});
</script>