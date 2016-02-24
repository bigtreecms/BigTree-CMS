<section class="sub" id="gbp"<?php if (!isset($gbp["enabled"]) || !$gbp["enabled"]) { ?> style="display: none;"<?php } ?>>
	<div class="left">
		<fieldset>
			<label>Grouping Name <small>(i.e. "Category")</small></label>
			<input type="text" name="gbp[name]" value="<?=htmlspecialchars($gbp["name"])?>" />
		</fieldset>
		<fieldset>
			<label>Main Table</label>
			<select name="gbp[table]" class="table_select" data-pop-name="gbp[group_field]" data-pop-target="#gbp_group_field">
				<option></option>
				<?php BigTree::getTableSelectOptions($gbp["table"]) ?>
			</select>
		</fieldset>
		<fieldset name="gbp[group_field]">
			<label>Main Field</label>
			<div id="gbp_group_field">
				<?php if ($gbp["table"]) { ?>
				<select name="gbp[group_field]">
					<?php BigTree::getFieldSelectOptions($gbp["table"],$gbp["group_field"]) ?>
				</select>
				<?php } else { ?>
				<input type="text" disabled="disabled" value="Please select &quot;Main Table&quot;" />
				<?php } ?>
			</div>
		</fieldset>
	</div>
	<div class="right">
		<fieldset>
			<label>Title Parser Function <small>(modifies the group title shown in the user editor)</small></label>
			<input type="text" name="gbp[item_parser]" value="<?=BigTree::safeEncode($gbp["item_parser"])?>" />
		</fieldset>
		<fieldset>
			<label>Other Table</label>
			<select name="gbp[other_table]" class="table_select" data-pop-name="gbp[title_field]" data-pop-target="#gbp_title_field">
				<option></option>
				<?php BigTree::getTableSelectOptions($gbp["other_table"]) ?>
			</select>
		</fieldset>
		<fieldset name="gbp[title_field]">
			<label>Title Field</label>
			<div id="gbp_title_field">
				<?php if ($gbp["other_table"]) { ?>
				<select name="gbp[title_field]">
					<?php BigTree::getFieldSelectOptions($gbp["other_table"],$gbp["title_field"]) ?>
				</select>
				<?php } else { ?>
				<input type="text" disabled="disabled" value="Please select &quot;Other Table&quot;" />
				<?php } ?>
			</div>
		</fieldset>
	</div>
</section>