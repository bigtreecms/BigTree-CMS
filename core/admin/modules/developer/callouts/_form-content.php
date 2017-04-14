<?php
	namespace BigTree;
	
	/**
	 * @global Callout $callout
	 * @global string $show_error
	 */
	
	$field_types = FieldType::reference(true, "callouts");
	
	CSRF::drawPOSTToken();
?>
<section>
	<p class="error_message"<?php if (!$show_error) { ?> style="display: none;"<?php } ?>><?=Text::translate("Errors found! Please fix the highlighted fields before submitting.")?></p>
	
	<div class="left">
		<?php if (!isset($callout)) { ?>
		<fieldset<?php if ($show_error) { ?> class="form_error"<?php } ?>>
			<label for="callout_field_id" class="required"><?=Text::translate('ID <small>("used for file name, alphanumeric, "-" and "_" only")</small>')?><?php if ($show_error) { ?> <span class="form_error_reason"><?=Text::translate($show_error)?></span><?php } ?></label>
			<input id="callout_field_id" type="text" class="required" name="id" value="<?=Text::htmlEncode($callout->ID)?>" />
		</fieldset>
		<?php } ?>
		<fieldset>
			<label for="callout_field_name" class="required"><?=Text::translate("Name")?></label>
			<input id="callout_field_name" type="text" class="required" name="name" value="<?=Text::htmlEncode($callout->Name)?>" />
		</fieldset>
		<fieldset>
			<label for="callout_field_level"><?=Text::translate("Access Level")?></label>
			<select id="callout_field_level" name="level">
				<option value="0"><?=Text::translate("Normal User")?></option>
				<option value="1"<?php if ($callout->Level == 1) { ?> selected="selected"<?php } ?>><?=Text::translate("Administrator")?></option>
				<option value="2"<?php if ($callout->Level == 2) { ?> selected="selected"<?php } ?>><?=Text::translate("Developer")?></option>
			</select>
		</fieldset>
		<fieldset>
			<label for="callout_field_display_default" class="required"><?=Text::translate('Default Display Label <small>(displays if no fields are assigned as "Label")</small>')?></label>
			<input id="callout_field_display_default" type="text" name="display_default" value="<?=Text::htmlEncode($callout->DisplayDefault)?>" />
		</fieldset>
	</div>
	<div class="right">
		<fieldset>
			<label for="callout_field_description"><?=Text::translate("Description")?></label>
			<textarea id="callout_field_description" name="description"><?=Text::htmlEncode($callout->Description)?></textarea>
		</fieldset>	
	</div>
</section>
<section class="sub">
	<label><?=Text::translate('Fields <small>("type", "display_field", "display_title", and "display_default" are all reserved IDs &mdash; any fields with these IDs will be removed)</small>')?></label>
	<div class="form_table">
		<header>
			<a href="#" class="add_field add"><span></span><?=Text::translate("Add Field")?></a>
			<a href="#" class="button clear_label"><span></span><?=Text::translate("Clear Label")?></a>
		</header>
		<div class="labels">
			<span class="developer_resource_callout_id"><?=Text::translate("ID")?></span>
			<span class="developer_resource_callout_title"><?=Text::translate("Title")?></span>
			<span class="developer_resource_callout_subtitle"><?=Text::translate("Subtitle")?></span>
			<span class="developer_resource_type"><?=Text::translate("Type")?></span>
			<span class="developer_resource_display_title"><?=Text::translate("Label")?></span>
			<span class="developer_resource_action right"><?=Text::translate("Delete")?></span>
		</div>
		<ul id="field_table">
			<?php
				$field_count = 0;
				
				foreach ($callout->Fields as $field) {
					$field_count++;
			?>
			<li>
				<section class="developer_resource_callout_id">
					<span class="icon_sort"></span>
					<input type="text" name="fields[<?=$field_count?>][id]" value="<?=$field["id"]?>" />
				</section>
				<section class="developer_resource_callout_title">
					<input type="text" name="fields[<?=$field_count?>][title]" value="<?=$field["title"]?>" />
				</section>
				<section class="developer_resource_callout_subtitle">
					<input type="text" name="fields[<?=$field_count?>][subtitle]" value="<?=$field["subtitle"]?>" />
				</section>
				<section class="developer_resource_type">
					<select name="fields[<?=$field_count?>][type]" id="type_<?=$field_count?>">
						<optgroup label="<?=Text::translate("Default", true)?>">
							<?php foreach ($field_types["default"] as $k => $v) { ?>
							<option value="<?=$k?>"<?php if ($k == $field["type"]) { ?> selected="selected"<?php } ?>><?=$v["name"]?></option>
							<?php } ?>
						</optgroup>
						<?php if (count($field_types["custom"])) { ?>
						<optgroup label="<?=Text::translate("Custom", true)?>">
							<?php foreach ($field_types["custom"] as $k => $v) { ?>
							<option value="<?=$k?>"<?php if ($k == $field["type"]) { ?> selected="selected"<?php } ?>><?=$v["name"]?></option>
							<?php } ?>
						</optgroup>
						<?php } ?>
					</select>
					<a href="#" class="icon_settings" name="<?=$field_count?>"></a>
					<input type="hidden" name="fields[<?=$field_count?>][options]" value="<?=htmlspecialchars(json_encode($field["options"]))?>" id="options_<?=$field_count?>" />
				</section>
				<section class="developer_resource_display_title">
					<input type="radio" name="display_field" value="<?=$field["id"]?>" id="display_title_<?=$field_count?>"<?php if ($callout->DisplayField == $field["id"]) echo ' checked="checked"'; ?> />
				</section>
				<section class="developer_resource_action right">
					<a href="#" class="icon_delete"></a>
				</section>
			</li>
			<?php
				}
			?>
		</ul>
	</div>
</section>