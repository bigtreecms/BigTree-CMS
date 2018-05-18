<?php $admin->drawCSRFToken() ?>
<section>
	<p class="error_message"<?php if (!$e) { ?> style="display: none;"<?php } ?>>Errors found! Please fix the highlighted fields before submitting.</p>
	<div class="contain">
		<div class="left">
			<fieldset<?php if ($e) { ?> class="form_error"<?php } ?>>
				<label class="required">ID <small>(unique &mdash; this will be used to query for this setting)</small><?php if ($e) { ?><span class="form_error_reason">ID Already In Use</span><?php } ?></label>
				<input type="text" name="id" value="<?=$id?>" class="required" />
			</fieldset>
			<fieldset>
				<label class="required">Name</label>
				<input type="text" name="name" value="<?=$name?>" class="required" />
			</fieldset>
		</div>
		<div class="right">
			<fieldset>
				<label class="required">Type</label>
				<select name="type" id="settings_type">
					<optgroup label="Default">
						<?php foreach ($types["default"] as $k => $v) { ?>
						<option value="<?=$k?>"<?php if ($k == $type) { ?> selected="selected"<?php } ?>><?=$v["name"]?></option>
						<?php } ?>
					</optgroup>
					<?php if (count($types["custom"])) { ?>
					<optgroup label="Custom">
						<?php foreach ($types["custom"] as $k => $v) { ?>
						<option value="<?=$k?>"<?php if ($k == $type) { ?> selected="selected"<?php } ?>><?=$v["name"]?></option>
						<?php } ?>
					</optgroup>
					<?php } ?>
				</select> &nbsp; <a class="icon_settings" href="#"></a>
				<input type="hidden" name="settings" value="<?=$settings?>" id="field_settings" />
			</fieldset>
			<fieldset>
				 <input type="checkbox" name="locked"<?php if ($locked) { ?> checked="checked"<?php } ?> />
				<label class="for_checkbox">Locked to Developers</label>
			</fieldset>
			<fieldset>
				<input type="checkbox" name="encrypted"<?php if ($encrypted) { ?> checked="checked"<?php } ?> />
				<label class="for_checkbox">Encrypted</label>
			</fieldset>
		</div>
	</div>
	<fieldset>
		<label>Description</label>
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

		$.ajax("<?=ADMIN_ROOT?>ajax/developer/load-field-settings/", { type: "POST", data: { setting: "true", type: $("#settings_type").val(), data: $("#field_settings").val() }, complete: function(response) {
			BigTreeDialog({
				title: "Field Settings",
				content: response.responseText,
				icon: "edit",
				callback: function(data) { $("#field_settings").val(JSON.stringify(data)); }
			});
		}});
	});
</script>