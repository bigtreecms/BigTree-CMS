<?php
	namespace BigTree;
?>
<section>
	<div class="left last">
		<fieldset>
			<label class="required"><?=Text::translate("Title <small>(for reference only, not shown in the embed)</small>")?></label>
			<input type="text" class="required" name="title" value="<?=$title?>" />
		</fieldset>

		<fieldset>
			<label class="required"><?=Text::translate("Data Table")?></label>
			<select name="table" id="form_table" class="required">
				<option></option>
				<?php SQL::drawTableSelectOptions($table); ?>
			</select>
		</fieldset>

		<fieldset>
			<label><?=Text::translate("Thank You Message")?></label>
			<textarea name="thank_you_message" id="thank_you_message"><?=htmlspecialchars($thank_you_message)?></textarea>
		</fieldset>
	</div>
	<div class="right last">
		<fieldset>
			<label><?=Text::translate("Custom CSS File <small>(full URL)</small>")?></label>
			<input type="text" name="css" value="<?=$css?>" />
		</fieldset>

		<fieldset>
			<label><?=Text::translate('Redirect URL <small>(overrides "Thank You Message")</small>')?></label>
			<input type="text" name="redirect_url" value="<?=$redirect_url?>" />
		</fieldset>

		<fieldset>
			<a href="#" id="manage_hooks"><span class="icon_small icon_small_lightning"></span> <?=Text::translate("Manage Hooks")?></a>
			<input name="hooks" type="hidden" id="form_hooks" value="<?=htmlspecialchars(json_encode($form["hooks"]))?>" />

			<input type="checkbox" name="default_pending"<?php if ($default_pending) { ?> checked="checked"<?php } ?> />
			<label class="for_checkbox"><?=Text::translate("Default Submissions to Pending")?></label>
		</fieldset>
	</div>
</section>
<?php
	$bigtree["simple_html_fields"] = array("thank_you_message");
	$bigtree["html_editor_width"] = 435;
	include Router::getIncludePath("admin/layouts/_html-field-loader.php");
?>