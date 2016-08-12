<?php
	namespace BigTree;
	
	$groups = ModuleGroup::all("name ASC");
	
	if (isset($_SESSION["bigtree_admin"]["saved"])) {
		$module = new Module($_SESSION["bigtree_admin"]["saved"]);
		$group_new = $_SESSION["bigtree_admin"]["saved"]["group_new"];
		$group_existing = $_SESSION["bigtree_admin"]["saved"]["group_existing"];
		
		unset($_SESSION["bigtree_admin"]["saved"]);
	} else {
		$module = new Module;
		$module->Icon = "gear";
		$group_new = "";
		$group_existing = "";
	}
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>modules/create/" class="module">
		<section>
			<p class="error_message" style="display: none;"><?=Text::translate("Errors found! Please fix the highlighted fields before submitting.")?></p>
			<div class="contain">
				<div class="left">
					<fieldset>
						<label for="module_field_name" class="required"><?=Text::translate("Name")?></label>
						<input id="module_field_name" name="name" class="required" type="text" value="<?=Text::htmlEncode($module->Name)?>" />
					</fieldset>
				</div>
				<div class="right">
					<fieldset<?php if (isset($_GET["error"])) { ?> class="form_error"<?php } ?>>
						<label for="module_field_route"><?=Text::translate('Route <small>(must be unique, auto generated if left blank, valid chars: alphanumeric and "-")</small>')?></label>
						<input id="module_field_route" name="route" type="text" value="<?=Text::htmlEncode($module->Route)?>" />
					</fieldset>
				</div>
			</div>
			<fieldset class="developer_module_group">
				<label for="module_field_group_new"><?=Text::translate("Group <small>(if a new group name is chosen, the select box is ignored)</small>")?></label>
				<input id="module_field_group_new" name="group_new" type="text" placeholder="<?=Text::translate("New Group", true)?>" value="<?=Text::htmlEncode($group_new)?>" />
				
				<span><?=Text::translate("OR")?></span>
				
				<label for="module_field_group_existing" class="visually_hidden">Existing Group</label>
				<select id="module_field_group_existing" name="group_existing">
					<option value=""></option>
					<?php foreach ($groups as $group) { ?>
					<option value="<?=$group->ID?>"<?php if ($group_existing == $group->ID) { ?> selected="selected"<?php } ?>><?=$group->Name?></option>
					<?php } ?>
				</select>
			</fieldset>
			<div class="left">
				<fieldset>
					<label for="rel_table"><?=Text::translate("Related Table")?></label>
					<select name="table" id="rel_table">
						<option></option>
						<?php SQL::drawTableSelectOptions($module->Table) ?>
					</select>
				</fieldset>
				<fieldset>
					<label for="module_field_class" class="required"><?=Text::translate("Class Name <small>(will create a class file in custom/inc/modules/)</small>")?></label>
					<input id="module_field_class" name="class" type="text" value="<?=$module->Class?>" />
				</fieldset>
			</div>
			
			<br class="clear" />
			<fieldset>
		        <label class="required"><?=Text::translate("Icon")?></label>
		        <input type="hidden" name="icon" id="selected_icon" value="<?=$module->Icon?>" />
		        <ul class="developer_icon_list">
		        	<?php foreach (Module::$IconClasses as $class) { ?>
		        	<li>
		        		<a href="#<?=$class?>"<?php if ($class == $module->Icon) { ?> class="active"<?php } ?>><span class="icon_small icon_small_<?=$class?>"></span></a>
		        	</li>
		        	<?php } ?>
		        </ul>
		    </fieldset>

			<fieldset class="left last">
				<input type="checkbox" name="gbp[enabled]" id="gbp_on" <?php if (!empty($module->GroupBasedPermissions["enabled"])) { ?>checked="checked" <?php } ?><?php if ($module->DeveloperOnly) { ?>disabled="disabled"<?php } ?> />
				<label for="gbp_on" class="for_checkbox"><?=Text::translate("Enable Advanced Permissions")?></label>
			</fieldset>
			<fieldset class="right last">
				<input type="checkbox" name="developer_only" id="developer_only" <?php if ($module->DeveloperOnly) { ?>checked="checked" <?php } ?>/>
				<label for="developer_only" class="for_checkbox"><?=Text::translate("Limit Access to Developers")?></label>
			</fieldset>
		</section>
		<?php include Router::getIncludePath("admin/modules/developer/modules/_gbp.php") ?>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Create", true)?>" />
		</footer>
	</form>
</div>
<?php include Router::getIncludePath("admin/modules/developer/modules/_js.php") ?>