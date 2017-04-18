<?php
	namespace BigTree;
	
	/**
	 * @global string $form_action
	 * @global Template $template
	 */

	$cached_types = FieldType::reference(true);
	$types = $cached_types["templates"];
	$show_error = !empty($_SESSION["bigtree_admin"]["error"]);
	
	CSRF::drawPOSTToken();

	if (isset($_GET["return"])) {
?>
<input type="hidden" name="return_to_front" value="<?=htmlspecialchars($_GET["return"])?>" />
<?php
	}
?>
<section>
	<p class="error_message"<?php if (!$show_error) { ?> style="display: none;"<?php } ?>><?=Text::translate("Errors found! Please fix the highlighted fields before submitting.")?></p>
	
	<div class="contain">
		<?php if (!isset($template)) { ?>
		<div class="left">
			<fieldset<?php if ($show_error) { ?> class="form_error"<?php } ?>>
				<label for="template_field_id" class="required"><?=Text::translate('ID <small>(used for file/directory name, alphanumeric, "-" and "_" only)</small>')?><?php if ($show_error) { ?> <span class="form_error_reason"><?=Text::translate($_SESSION["bigtree_admin"]["error"])?></span><?php } ?></label>
				<input id="template_field_id" type="text" class="required" name="id" value="<?=$template->ID?>" />
			</fieldset>
		</div>
		<?php } ?>
		<div class="<?php if (isset($template)) { ?>left<?php } else { ?>right<?php } ?>">
			<fieldset>
				<label for="template_field_name" class="required"><?=Text::translate("Name")?></label>
				<input id="template_field_name" type="text" class="required" name="name" value="<?=$template->Name?>" />
			</fieldset>
		</div>
	</div>
	<?php
		if ($form_action == "add") {
	?>
	<fieldset class="float_margin">
		<label for="template_field_type"><?=Text::translate("Type")?></label>
		<select id="template_field_type" name="routed">
			<option value=""><?=Text::translate("Basic")?></option>
			<option value="on"><?=Text::translate("Routed")?></option>
		</select>
	</fieldset>
	<?php
		}
		
		if ($form_action == "add" || $template->Routed) {
	?>
	<fieldset class="float_margin">
		<label for="template_field_module"><?=Text::translate("Related Module")?></label>
		<select id="template_field_module" name="module">
			<option></option>
			<?php
				$groups = ModuleGroup::all("name ASC");
				$groups[] = new ModuleGroup(array("id" => 0, "name" => "- Ungrouped -"));
				
				foreach ($groups as $group) {
					$modules = Module::allByGroup($group->ID, "name ASC");
					
					if (count($modules)) {
			?>
			<optgroup label="<?=$group->Name?>">
				<?php foreach ($modules as $module) { ?>
				<option value="<?=$module->ID?>"<?php if ($module->ID == $template->Module) { ?> selected="selected"<?php } ?>><?=$module->Name?></option>
				<?php } ?>
			</optgroup>
			<?php
					}
				}
			?>
		</select>	
	</fieldset>
	<?php
		}
	?>
	<fieldset class="float_margin">
		<label for="template_level"><?=Text::translate("Access Level")?></label>
		<select id="template_level" name="level">
			<option value="0"><?=Text::translate("Normal User")?></option>
			<option value="1"<?php if ($template->Level == 1) { ?> selected="selected"<?php } ?>><?=Text::translate("Administrator")?></option>
			<option value="2"<?php if ($template->Level == 2) { ?> selected="selected"<?php } ?>><?=Text::translate("Developer")?></option>
		</select>
	</fieldset>
</section>
<section class="sub">
	<label><?=Text::translate("Fields")?></label>
	<div class="form_table">
		<header>
			<a href="#" class="add_resource add"><span></span><?=Text::translate("Add Field")?></a>
		</header>
		<div class="labels">
			<span class="developer_resource_id"><?=Text::translate("ID")?></span>
			<span class="developer_resource_title"><?=Text::translate("Title")?></span>
			<span class="developer_resource_subtitle"><?=Text::translate("Subtitle")?></span>
			<span class="developer_resource_type"><?=Text::translate("Type")?></span>
			<span class="developer_resource_action right"><?=Text::translate("Delete")?></span>
		</div>
		<ul id="resource_table">
			<?php
				$x = 0;
				
				foreach ($template->Fields as $resource) {
					$x++;
			?>
			<li>
				<section class="developer_resource_id">
					<span class="icon_sort"></span>
					<input type="text" name="resources[<?=$x?>][id]" value="<?=$resource["id"]?>" />
				</section>
				<section class="developer_resource_title">
					<input type="text" name="resources[<?=$x?>][title]" value="<?=$resource["title"]?>" />
				</section>
				<section class="developer_resource_subtitle">
					<input type="text" name="resources[<?=$x?>][subtitle]" value="<?=$resource["subtitle"]?>" />
				</section>
				<section class="developer_resource_type">
					<select name="resources[<?=$x?>][type]" id="type_<?=$x?>">
						<optgroup label="Default">
							<?php foreach ($types["default"] as $k => $v) { ?>
							<option value="<?=$k?>"<?php if ($k == $resource["type"]) { ?> selected="selected"<?php } ?>><?=$v["name"]?></option>
							<?php } ?>
						</optgroup>
						<?php if (count($types["custom"])) { ?>
						<optgroup label="Custom">
							<?php foreach ($types["custom"] as $k => $v) { ?>
							<option value="<?=$k?>"<?php if ($k == $resource["type"]) { ?> selected="selected"<?php } ?>><?=$v["name"]?></option>
							<?php } ?>
						</optgroup>
						<?php } ?>
					</select>
					<a href="#" class="icon_settings" name="<?=$x?>"></a>
					<input type="hidden" name="resources[<?=$x?>][options]" value="<?=htmlspecialchars(json_encode($resource["options"]))?>" id="options_<?=$x?>" />
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