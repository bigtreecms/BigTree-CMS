<?
	$cached_types = $admin->getCachedFieldTypes();
	$groups = $admin->getCalloutGroups();
	$types = $cached_types["callouts"];
?>
<section>
	<p class="error_message"<? if (!$show_error) { ?> style="display: none;"<? } ?>>Errors found! Please fix the highlighted fields before submitting.</p>
	
	<div class="left">
		<? if (!isset($callout)) { ?>
		<fieldset<? if ($show_error) { ?> class="form_error"<? } ?>>
			<label class="required">ID <small>(used for file name, alphanumeric, "-" and "_" only)</small><? if ($show_error) { ?> <span class="form_error_reason"><?=$show_error?></span><? } ?></label>
			<input type="text" class="required" name="id" value="<?=$id?>" />
		</fieldset>
		<? } ?>
		<fieldset>
			<label class="required">Name</label>
			<input type="text" class="required" name="name" value="<?=$name?>" />
		</fieldset>
		<fieldset>
			<label>Access Level</label>
			<select name="level">
				<option value="0">Normal User</option>
				<option value="1"<? if ($level == 1) { ?> selected="selected"<? } ?>>Administrator</option>
				<option value="2"<? if ($level == 2) { ?> selected="selected"<? } ?>>Developer</option>
			</select>
		</fieldset>
		<fieldset>
			<label class="required">Default Display Label <small>(displays if no resources are assigned as "Label")</small></label>
			<input type="text" name="display_default" value="<?=$display_default?>" />
		</fieldset>
	</div>
	<div class="right">
		<fieldset>
			<label>Description</label>
			<textarea name="description"><?=$description?></textarea>
		</fieldset>	
	</div>
	<fieldset class="clear developer_module_group">
		<label>Group <small>(if a new group name is chosen, the select box is ignored)</small></label> 
		<input name="group_new" type="text" placeholder="New Group" /><span>OR</span> 
		<select name="group_existing">
			<option value="0"></option>
			<? foreach ($groups as $g) { ?>
			<option value="<?=$g["id"]?>"<? if ($g["id"] == $group) { ?> selected="selected"<? } ?>><?=$g["name"]?></option>
			<? } ?>
		</select>
	</fieldset>
</section>
<section class="sub">
	<label>Resources <small>("type", "display_field", "display_title", and "display_default" are all reserved IDs &mdash; any resources with these IDs will be removed)</small></label>
	<div class="form_table">
		<header>
			<a href="#" class="add_resource add"><span></span>Add Resource</a>
		</header>
		<div class="labels">
			<span class="developer_resource_callout_id">ID</span>
			<span class="developer_resource_callout_title">Title</span>
			<span class="developer_resource_callout_subtitle">Subtitle</span>
			<span class="developer_resource_type">Type</span>
			<span class="developer_resource_display_title">Label</span>
			<span class="developer_resource_action right">Delete</span>
		</div>
		<ul id="resource_table">
			<?
				$x = 0;
				foreach ($resources as $resource) {
					$x++;
			?>
			<li>
				<section class="developer_resource_callout_id">
					<span class="icon_sort"></span>
					<input type="text" name="resources[<?=$x?>][id]" value="<?=$resource["id"]?>" />
				</section>
				<section class="developer_resource_callout_title">
					<input type="text" name="resources[<?=$x?>][title]" value="<?=$resource["title"]?>" />
				</section>
				<section class="developer_resource_callout_subtitle">
					<input type="text" name="resources[<?=$x?>][subtitle]" value="<?=$resource["subtitle"]?>" />
				</section>
				<section class="developer_resource_type">
					<select name="resources[<?=$x?>][type]" id="type_<?=$x?>">
						<? foreach ($types as $k => $v) { ?>
						<option value="<?=$k?>"<? if ($k == $resource["type"]) { ?> selected="selected"<? } ?>><?=$v["name"]?></option>
						<? } ?>
					</select>
					<a href="#" class="icon_settings" name="<?=$x?>"></a>
					<input type="hidden" name="resources[<?=$x?>][options]" value="<?=htmlspecialchars(json_encode($resource))?>" id="options_<?=$x?>" />
				</section>
				<section class="developer_resource_display_title">
					<input type="radio" name="display_field" value="<?=$resource["id"]?>" id="display_title_<?=$x?>"<? if ($display_field == $resource["id"]) echo ' checked="checked"'; ?> />
				</section>
				<section class="developer_resource_action right">
					<a href="#" class="icon_delete"></a>
				</section>
			</li>
			<?
				}
			?>
		</ul>
	</div>
</section>