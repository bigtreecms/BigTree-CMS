<section>
	<p class="error_message"<? if (!$e) { ?> style="display: none;"<? } ?>>Errors found! Please fix the highlighted fields before submitting.</p>
	<div class="contain">
		<div class="left">
			<fieldset<? if ($e) { ?> class="form_error"<? } ?>>
				<label class="required">ID <small>(unique &mdash; this will be used to query for this setting)</small><? if ($e) { ?><span class="form_error_reason">ID Already In Use</span><? } ?></label>
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
					<? foreach ($types as $k => $v) { ?>
					<option value="<?=$k?>"<? if ($k == $type) { ?> selected="selected"<? } ?>><?=$v["name"]?></option>
					<? } ?>
				</select> &nbsp; <a class="icon_settings" href="#"></a>
				<input type="hidden" name="options" value="<?=$options?>" id="options_settings" />
			</fieldset>
			<fieldset>
				 <input type="checkbox" name="locked"<? if ($locked) { ?> checked="checked"<? } ?> />
				<label class="for_checkbox">Locked to Developers</label>
			</fieldset>
			<fieldset>
				<input type="checkbox" name="encrypted"<? if ($encrypted) { ?> checked="checked"<? } ?> />
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
	$(".icon_settings").click(function() {
		$.ajax("<?=ADMIN_ROOT?>ajax/developer/load-field-options/", { type: "POST", data: { setting: "true", type: $("#settings_type").val(), data: $("#options_settings").val() }, complete: function(response) {
			new BigTreeDialog("Settings Options",response.responseText,function(data) {
				$("#options_settings").val(JSON.stringify(data));
			});
		}});
		
		return false;
	});
</script>
