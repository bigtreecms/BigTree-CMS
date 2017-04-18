<?php
	namespace BigTree;

	/**
	 * @global ModuleEmbedForm $form
	 */
	
	CSRF::drawPOSTToken();
?>
<section>
	<div class="left last">
		<fieldset>
			<label for="form_field_title" class="required"><?=Text::translate("Title <small>(for reference only, not shown in the embed)</small>")?></label>
			<input id="form_field_title" type="text" class="required" name="title" value="<?=$form->Title?>" />
		</fieldset>

		<fieldset>
			<label for="form_table" class="required"><?=Text::translate("Data Table")?></label>
			<select name="table" id="form_table" class="required">
				<option></option>
				<?php SQL::drawTableSelectOptions($form->Table); ?>
			</select>
		</fieldset>

		<fieldset>
			<label for="thank_you_message"><?=Text::translate("Thank You Message")?></label>
			<textarea name="thank_you_message" id="thank_you_message"><?=htmlspecialchars($form->ThankYouMessage)?></textarea>
		</fieldset>
	</div>
	<div class="right last">
		<fieldset>
			<label for="form_field_css"><?=Text::translate("Custom CSS File <small>(full URL)</small>")?></label>
			<input id="form_field_css" type="text" name="css" value="<?=$form->CSS?>" />
		</fieldset>

		<fieldset>
			<label for="form_field_redirect_url"><?=Text::translate('Redirect URL <small>(overrides "Thank You Message")</small>')?></label>
			<input id="form_field_redirect_url" type="text" name="redirect_url" value="<?=$form->RedirectURL?>" />
		</fieldset>

		<fieldset>
			<a href="#" id="manage_hooks"><span class="icon_small icon_small_lightning"></span> <?=Text::translate("Manage Hooks")?></a>
			<input name="hooks" type="hidden" id="form_hooks" value="<?=htmlspecialchars(json_encode($form["hooks"]))?>" />

			<input id="form_field_default_pending" type="checkbox" name="default_pending"<?php if ($form->DefaultPending) { ?> checked="checked"<?php } ?> />
			<label for="form_field_default_pending" class="for_checkbox"><?=Text::translate("Default Submissions to Pending")?></label>
		</fieldset>
	</div>
</section>
<?php
	$bigtree["simple_html_fields"] = array("thank_you_message");
	$bigtree["html_editor_width"] = 435;
	include Router::getIncludePath("admin/layouts/_html-field-loader.php");
?>