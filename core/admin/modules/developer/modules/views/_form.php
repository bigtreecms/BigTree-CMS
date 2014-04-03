<?
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
				<? BigTree::getTableSelectOptions($table); ?>
			</select>
		</fieldset>
		<fieldset>
			<label>Related Form</label>
			<select name="related_form">
				<option value="">&mdash;</option>
				<? foreach ($forms as $form) { ?>
				<option value="<?=$form["id"]?>"<? if ($form["id"] == $related_form) { ?> selected="selected"<? } ?>><?=$form["title"]?> (<?=$form["table"]?>)</option>
				<? } ?>
			</select>
		</fieldset>
		<fieldset class="view_type">
			<label>View Type</label>
			<select name="type" id="view_type" class="left" >
				<? foreach ($admin->ViewTypes as $key => $t) { ?>
				<option value="<?=$key?>"<? if ($key == $type) { ?> selected="selected"<? } ?>><?=$t?></option>
				<? } ?>
			</select>
			&nbsp; <a href="#" class="options icon_settings centered"></a>
			<input type="hidden" name="options" id="view_options" value="<?=htmlspecialchars(json_encode($options))?>" />
		</fieldset>
	</div>
</section>
<section class="sub" id="field_area">
	<?
		if (!$table) {
	?>
	<p>Please choose a table to populate this area.</p>
	<?
		} else {
			include BigTree::path("admin/ajax/developer/load-view-fields.php");
		}
	?>
</section>