<?php
	namespace BigTree;

	/**
	 * @global ModuleForm $form
	 * @global Module $module
	 */

	// Find out if we have more than one view. If so, give them an option of which one to return to.
	$available_views = ModuleInterface::allByModuleAndType($module->ID, "view", "title ASC");
?>
<section>
	<div class="left last">
		<fieldset>
			<label for="form_field_title" class="required"><?=Text::translate('Item Title <small>(for example, "Question" as in "Adding Question")</small>')?></label>
			<input id="form_field_title" type="text" name="title" value="<?=$form->Title?>" class="required" />
		</fieldset>

		<fieldset>
			<label for="form_table" class="required"><?=Text::translate("Data Table")?></label>
			<select name="table" id="form_table" class="required">
				<option></option>
				<?php SQL::drawTableSelectOptions($form->Table); ?>
			</select>
		</fieldset>

		<fieldset>
			<a href="#" id="manage_hooks"><span class="icon_small icon_small_lightning"></span> <?=Text::translate("Manage Hooks")?></a>
			<input name="hooks" type="hidden" id="form_hooks" value="<?=htmlspecialchars(json_encode($form->Hooks))?>" />

			<input id="form_field_tagging" type="checkbox" name="tagging" <?php if ($form->Tagging) { ?>checked="checked" <?php } ?>/>
			<label for="form_field_tagging" class="for_checkbox"><?=Text::translate("Enable Tagging")?></label>
		</fieldset>
	</div>
	<div class="right last">
		<?php if (count($available_views) > 1) { ?>
		<fieldset>
			<label for="form_field_return_view"><?=Text::translate("Return View <small>(after the form is submitted, it will return to this view)")?></small></label>
			<select id="form_field_return_view" name="return_view">
				<?php foreach ($available_views as $view) { ?>
				<option value="<?=$view->ID?>"<?php if ($form->ReturnView == $view->ID) { ?> selected="selected"<?php } ?>><?=$view->Title?></option>
				<?php } ?>
			</select>
		</fieldset>
		<?php } ?>

		<fieldset>
			<label for="form_field_return_url"><?=Text::translate("Return URL <small>(an optional return URL to override the default return view)</small>")?></label>
			<input id="form_field_return_url" type="text" name="return_url" value="<?=htmlspecialchars($form->ReturnURL)?>" />
		</fieldset>
	</div>
</section>
<section class="sub" id="field_area">
	<?php
		if ($form->Table) {
			include Router::getIncludePath("admin/ajax/developer/load-form.php");
		} else {
			echo "<p>".Text::translate("Please choose a table to populate this area.")."</p>";
		}
	?>
</section>