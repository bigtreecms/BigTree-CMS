<?php
	$forms = $admin->getModuleForms("title",$module["id"]);
?>
<section>
	<div class="left last">
		<fieldset>
			<label class="required">Item Title <small>(for example, "Questions" to make the title "Viewing Questions")</small></label>
			<input type="text" class="required" name="title" value="<?=$title?>" />
		</fieldset>

		<fieldset>
			<label>Preview URL <small>(optional, the item's id will be entered as a route)</small></label>
			<input type="text" name="preview_url" value="<?=$preview_url?>" />
		</fieldset>
	</div>
		
	<fieldset class="view_description right last">
		<label>Description <small>(instructions for the user)</small></label>
		<textarea name="description" ><?=$description?></textarea>
	</fieldset>			
	<div class="triplets last">
		<fieldset>
			<label class="required">Data Table</label>
			<select name="table" id="view_table" class="required" >
				<option></option>
				<?php BigTree::getTableSelectOptions($table); ?>
			</select>
		</fieldset>
		<fieldset>
			<label>Related Form</label>
			<select name="related_form">
				<option value="">&mdash;</option>
				<?php foreach ($forms as $form) { ?>
				<option value="<?=$form["id"]?>"<?php if ($form["id"] == $related_form) { ?> selected="selected"<?php } ?>><?=$form["title"]?> (<?=$form["table"]?>)</option>
				<?php } ?>
			</select>
		</fieldset>
		<fieldset class="view_type">
			<label>View Type</label>
			<select name="type" id="view_type" class="left" >
				<?php foreach (BigTreeAdmin::$ViewTypes as $key => $t) { ?>
				<option value="<?=$key?>"<?php if ($key == $type) { ?> selected="selected"<?php } ?>><?=$t?></option>
				<?php } ?>
			</select>
			&nbsp; <a href="#" class="js-view-settings icon_settings centered"></a>
			<input type="hidden" name="settings" id="view_settings" value="<?=htmlspecialchars(json_encode($settings))?>" />
		</fieldset>
	</div>
</section>
<section class="sub" id="field_area">
	<?php
		if (!$table) {
	?>
	<p>Please choose a table to populate this area.</p>
	<?php
		} else {
			include BigTree::path("admin/ajax/developer/load-view-fields.php");
		}
	?>
</section>