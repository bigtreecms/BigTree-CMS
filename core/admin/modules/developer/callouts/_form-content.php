<?php
	namespace BigTree;
	
	$cached_types = $admin->getCachedFieldTypes(true);
	$types = $cached_types["callouts"];
?>
<section>
	<p class="error_message"<?php if (!$show_error) { ?> style="display: none;"<?php } ?>><?=Text::translate("Errors found! Please fix the highlighted fields before submitting.")?></p>
	
	<div class="left">
		<?php if (!isset($callout)) { ?>
		<fieldset<?php if ($show_error) { ?> class="form_error"<?php } ?>>
			<label class="required"><?=Text::translate("ID")?> <small>(<?=Text::translate("used for file name, alphanumeric, \"-\" and \"_\" only")?></small><?php if ($show_error) { ?> <span class="form_error_reason"><?=Text::translate($show_error)?></span><?php } ?></label>
			<input type="text" class="required" name="id" value="<?=$id?>" />
		</fieldset>
		<?php } ?>
		<fieldset>
			<label class="required"><?=Text::translate("Name")?></label>
			<input type="text" class="required" name="name" value="<?=$name?>" />
		</fieldset>
		<fieldset>
			<label><?=Text::translate("Access Level")?></label>
			<select name="level">
				<option value="0"><?=Text::translate("Normal User")?></option>
				<option value="1"<?php if ($level == 1) { ?> selected="selected"<?php } ?>><?=Text::translate("Administrator")?></option>
				<option value="2"<?php if ($level == 2) { ?> selected="selected"<?php } ?>><?=Text::translate("Developer")?></option>
			</select>
		</fieldset>
		<fieldset>
			<label class="required"><?=Text::translate("Default Display Label")?> <small>(<?=Text::translate("displays if no fields are assigned as \"Label\"")?></small></label>
			<input type="text" name="display_default" value="<?=$display_default?>" />
		</fieldset>
	</div>
	<div class="right">
		<fieldset>
			<label><?=Text::translate("Description")?></label>
			<textarea name="description"><?=$description?></textarea>
		</fieldset>	
	</div>
</section>
<section class="sub">
	<label><?=Text::translate("Fields")?> <small>(<?=Text::translate('"type", "display_field", "display_title", and "display_default" are all reserved IDs &mdash; any fields with these IDs will be removed')?>)</small></label>
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
				$x = 0;
				foreach ($fields as $field) {
					$x++;
			?>
			<li>
				<section class="developer_resource_callout_id">
					<span class="icon_sort"></span>
					<input type="text" name="fields[<?=$x?>][id]" value="<?=$field["id"]?>" />
				</section>
				<section class="developer_resource_callout_title">
					<input type="text" name="fields[<?=$x?>][title]" value="<?=$field["title"]?>" />
				</section>
				<section class="developer_resource_callout_subtitle">
					<input type="text" name="fields[<?=$x?>][subtitle]" value="<?=$field["subtitle"]?>" />
				</section>
				<section class="developer_resource_type">
					<select name="fields[<?=$x?>][type]" id="type_<?=$x?>">
						<optgroup label="<?=Text::translate("Default", true)?>">
							<?php foreach ($types["default"] as $k => $v) { ?>
							<option value="<?=$k?>"<?php if ($k == $field["type"]) { ?> selected="selected"<?php } ?>><?=$v["name"]?></option>
							<?php } ?>
						</optgroup>
						<?php if (count($types["custom"])) { ?>
						<optgroup label="<?=Text::translate("Custom", true)?>">
							<?php foreach ($types["custom"] as $k => $v) { ?>
							<option value="<?=$k?>"<?php if ($k == $field["type"]) { ?> selected="selected"<?php } ?>><?=$v["name"]?></option>
							<?php } ?>
						</optgroup>
						<?php } ?>
					</select>
					<a href="#" class="icon_settings" name="<?=$x?>"></a>
					<input type="hidden" name="fields[<?=$x?>][options]" value="<?=htmlspecialchars(json_encode($field["options"]))?>" id="options_<?=$x?>" />
				</section>
				<section class="developer_resource_display_title">
					<input type="radio" name="display_field" value="<?=$field["id"]?>" id="display_title_<?=$x?>"<?php if ($display_field == $field["id"]) echo ' checked="checked"'; ?> />
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