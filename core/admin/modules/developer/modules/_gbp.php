<?php
	namespace BigTree;
	
	/**
	 * @global Module $module
	 */
?>
<section class="sub" id="gbp"<?php if (empty($module->GroupBasedPermissions["enabled"])) { ?> style="display: none;"<?php } ?>>
	<div class="left">
		<fieldset>
			<label for="module_field_gbp_name"><?=Text::translate('Grouping Name <small>(i.e. "Category")</small>')?></label>
			<input id="module_field_gbp_name" type="text" name="gbp[name]" value="<?=Text::htmlEncode($module->GroupBasedPermissions["name"])?>" />
		</fieldset>
		<fieldset>
			<label for="module_field_gbp_table"><?=Text::translate("Main Table")?></label>
			<select id="module_field_gbp_table" name="gbp[table]" class="table_select" data-pop-name="gbp[group_field]" data-pop-target="#gbp_group_field">
				<option></option>
				<?php SQL::drawTableSelectOptions($module->GroupBasedPermissions["table"]) ?>
			</select>
		</fieldset>
		<fieldset name="gbp[group_field]">
			<label for="module_field_gbp_group_field"><?=Text::translate("Main Field")?></label>
			<div id="gbp_group_field">
				<?php if ($module->GroupBasedPermissions["table"]) { ?>
				<select id="module_field_gbp_group_field" name="gbp[group_field]">
					<?php SQL::drawColumnSelectOptions($module->GroupBasedPermissions["table"], $module->GroupBasedPermissions["group_field"]) ?>
				</select>
				<?php } else { ?>
				<input id="module_field_gbp_group_field" type="text" disabled="disabled" value="<?=Text::translate('Please select "Main Table"', true)?>" />
				<?php } ?>
			</div>
		</fieldset>
	</div>
	<div class="right">
		<fieldset>
			<label for="module_field_gbp_parser"><?=Text::translate("Title Parser Function <small>(modifies the group title shown in the user editor)</small>")?></label>
			<input id="module_field_gbp_parser" type="text" name="gbp[item_parser]" value="<?=Text::htmlEncode($module->GroupBasedPermissions["item_parser"])?>" />
		</fieldset>
		<fieldset>
			<label for="module_field_gbp_other_table"><?=Text::translate("Other Table")?></label>
			<select id="module_field_gbp_other_table" name="gbp[other_table]" class="table_select" data-pop-name="gbp[title_field]" data-pop-target="#gbp_title_field">
				<option></option>
				<?php SQL::drawTableSelectOptions($module->GroupBasedPermissions["other_table"]) ?>
			</select>
		</fieldset>
		<fieldset name="gbp[title_field]">
			<label for="module_field_gbp_title_field"><?=Text::translate("Title Field")?></label>
			<div id="gbp_title_field">
				<?php if ($module->GroupBasedPermissions["other_table"]) { ?>
				<select id="module_field_gbp_title_field" name="gbp[title_field]">
					<?php SQL::drawColumnSelectOptions($module->GroupBasedPermissions["other_table"], $module->GroupBasedPermissions["title_field"]) ?>
				</select>
				<?php } else { ?>
				<input id="module_field_gbp_title_field" type="text" disabled="disabled" value="<?=Text::translate('Please select "Other Table"', true)?>" />
				<?php } ?>
			</div>
		</fieldset>
	</div>
</section>