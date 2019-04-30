<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 * @global array $interface_list
	 */
	
	$id = end($bigtree["path"]);	
	$module = new Module($id);
	$actions = ModuleAction::allByModule($id, "position DESC, id ASC");
	$groups = ModuleGroup::all("name ASC");
	$action_data = [];
	$interface_data = [];
	
	// Set the drag disabled flag for non-visible actions
	foreach ($actions as $action) {
		$action_data[] = [
			"id" => $action->ID,
			"name" => $action->Name,
			"!disable_drag" => !$action->InNav
		];
	}
	
	// Get a list of interfaces, this is separated out because actions form uses the same logic
	include Router::getIncludePath("admin/modules/developer/modules/_interface-sort.php");
	
	// Put together our interface list
	foreach ($interface_list as $key => $type) {
		foreach ($type["items"] as $item) {
			$interface_data[] = [
				"id" => $item["id"],
				"type" => ucwords($item["type"]),
				"title" => $item["title"],
				"style_link" => $item["show_style"] ? '<a href="'.DEVELOPER_ROOT.'modules/views/style/'.$item["id"].'/" class="icon_preview"></a>' : "",
				"edit_link" => '<a href="'.DEVELOPER_ROOT.'modules/'.$item["edit_url"].'" class="icon_edit"></a>'
			];
		}
	}
?>
<section class="container">
	<header>
		<nav class="left">
			<a href="#details_tab" class="active"><?=Text::translate("Details")?></a>
			<a href="#actions_tab"><?=Text::translate("Actions")?></a>
			<a href="#interfaces_tab"><?=Text::translate("Interfaces")?></a>
		</nav>
	</header>
	<div id="details_tab" class="section">
		<form method="post" action="<?=DEVELOPER_ROOT?>modules/update/<?=$module->ID?>/" enctype="multipart/form-data" class="module left">
			<?php CSRF::drawPOSTToken(); ?>
			<section>
				<div class="left">
					<fieldset>
						<label for="module_field_name" class="required"><?=Text::translate("Name")?></label>
						<input id="module_field_name" name="name" type="text" value="<?=$module->Name?>" class="required" />
					</fieldset>
				</div>
				<br class="clear" /><br />
				<fieldset class="clear developer_module_group">
					<label for="module_field_group_new"><?=Text::translate("Group <small>(if a new group name is chosen, the select box is ignored)</small>")?></label>
					<input id="module_field_group_new" name="group_new" type="text" placeholder="<?=Text::translate("New Group", true)?>" />
					
					<span><?=Text::translate("OR")?></span>
					
					<label for="module_field_group_existing" class="visually_hidden">Existing Group</label>
					<select id="module_field_group_existing" name="group_existing">
						<option value=""></option>
						<?php foreach ($groups as $group) { ?>
						<option value="<?=$group->ID?>"<?php if ($group->ID == $module->Group) { ?> selected="selected"<?php } ?>><?=$group->Name?></option>
						<?php } ?>
					</select>
				</fieldset>
				<div class="left">
					<fieldset>
						<label for="module_field_class"><?=Text::translate("Class Name <small>(only change this if you renamed your class manually)</small>")?></label>
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
					<input type="checkbox" name="gbp[enabled]" id="gbp_on" <?php if (!empty($module->Group["enabled"]))  { ?>checked="checked" <?php } ?> <?php if ($module->DeveloperOnly) { ?>disabled="disabled"<?php } ?> />
					<label for="gbp_on" class="for_checkbox"><?=Text::translate("Enable Advanced Permissions")?></label>
				</fieldset>
				<fieldset class="right last">
					<input type="checkbox" name="developer_only" id="developer_only" <?php if ($module->DeveloperOnly) { ?>checked="checked" <?php } ?>/>
					<label for="developer_only" class="for_checkbox"><?=Text::translate("Limit Access to Developers")?></label>
				</fieldset>
				<br class="clear" />
			</section>
			<?php include Router::getIncludePath("admin/modules/developer/modules/_gbp.php") ?>
			<footer>
				<input type="submit" class="button blue" value="<?=Text::translate("Update", true)?>" />	
			</footer>
		</form>
	</div>

	<div id="actions_tab" style="display: none;" class="section">
		<div id="actions_table"></div>
	</div>

	<div id="interfaces_tab" style="display: none;" class="section">
		<div id="interfaces_table"></div>
	</div>
</section>

<?php include Router::getIncludePath("admin/modules/developer/modules/_js.php") ?>
<script>
	BigTreeTable({
		container: "#actions_table",
		title: "<?=Text::translate("Actions", true)?>",
		icon: "actions",
		button: { title: "<span></span>Add", className: "add", link: "<?=DEVELOPER_ROOT?>modules/actions/add/?module=<?=$id?>" },
		columns: {
			name: { title: "<?=Text::translate("Name", true)?>" }
		},
		actions: {
			"edit": "<?=DEVELOPER_ROOT?>modules/actions/edit/{id}/",
			"delete": function(id) {
				BigTreeDialog({
					title: "<?=Text::translate("Delete Action", true)?>",
					content: '<p class="confirm"><?=Text::translate("Are you sure you want to delete this module action?")?></p>',
					icon: "delete",
					alternateSaveText: "<?=Text::translate("OK", true)?>",
					callback: function() {
						document.location.href = "<?=DEVELOPER_ROOT?>modules/actions/delete/?id=" + id + "<?php CSRF::drawGETToken(); ?>";
					}
				});
			}
		},
		data: <?=json_encode($action_data)?>,
		draggable: function(positioning) {
			$.secureAjax("<?=ADMIN_ROOT?>ajax/developer/order-module-actions/", { type: "POST", data: { module: "<?=$module->ID?>", positioning: positioning } });
		}
	});

	BigTreeTable({
		container: "#interfaces_table",
		title: "<?=Text::translate("Interfaces", true)?>",
		icon: "interfaces",
		button: { title: "<span></span><?=Text::translate("Add")?>", className: "add", link: "<?=DEVELOPER_ROOT?>modules/interfaces/add/?module=<?=$id?>" },
		columns: {
			type: { title: "Type", size: 175 },
			title: { title: "Title" },
			style_link: { title: "", size: 40, center: true, noPadding: true },
			edit_link: { title: "", size: 40, center: true, noPadding: true }
		},
		actions: {
			"delete": function(id) {
				BigTreeDialog({
					title: "<?=Text::translate("Delete Interface", true)?>",
					content: '<p class="confirm"><?=Text::translate("Are you sure you want to delete this module interface?")?></p>',
					icon: "delete",
					alternateSaveText: "<?=Text::translate("OK", true)?>",
					callback: function() {
						document.location.href = "<?=DEVELOPER_ROOT?>modules/interfaces/delete/?id=" + id + "<?php CSRF::drawGETToken(); ?>";
					}
				});
			}
		},
		data: <?=json_encode($interface_data)?>
	});

	BigTreeFormNavBar.init();
</script>