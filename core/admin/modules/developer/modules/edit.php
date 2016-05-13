<?php
	namespace BigTree;
	
	$id = end($bigtree["path"]);	
	$module = $admin->getModule($id);
	$actions = $admin->getModuleActions($id);
	$groups = $admin->getModuleGroups("name ASC");
	$gbp = is_array($module["gbp"]) ? $module["gbp"] : array("enabled" => false, "name" => "", "table" => "", "group_field" => "", "other_table" => "", "title_field" => "");

	// Get a list of interfaces, this is separated out because actions form uses the same logic
	include Router::getIncludePath("admin/modules/developer/modules/_interface-sort.php");

	// Set the drag disabled flag for non-visible actions
	$action_data = array();
	foreach ($actions as $action) {
		$action_data[] = array(
			"id" => $action["id"],
			"name" => $action["name"],
			"!disable_drag" => $action["in_nav"] ? false : true
		);
	}

	// Put together our interface list
	$interface_data = array();
	foreach ($interface_list as $key => $type) {
		foreach ($type["items"] as $item) {
			$interface_data[] = array(
				"id" => $item["id"],
				"type" => ucwords($item["type"]),
				"title" => $item["title"],
				"style_link" => $item["show_style"] ? '<a href="'.DEVELOPER_ROOT.'modules/views/style/'.$item["id"].'/" class="icon_preview"></a>' : "",
				"edit_link" => '<a href="'.DEVELOPER_ROOT.'modules/'.$item["edit_url"].'" class="icon_edit"></a>'
			);
		}
	}
?>
<div class="container">
	<header>
		<nav class="left">
			<a href="#details_tab" class="active"><?=Text::translate("Details")?></a>
			<a href="#actions_tab"><?=Text::translate("Actions")?></a>
			<a href="#interfaces_tab"><?=Text::translate("Interfaces")?></a>
		</nav>
	</header>
	<div id="details_tab" class="section">
		<form method="post" action="<?=DEVELOPER_ROOT?>modules/update/<?=$module["id"]?>/" enctype="multipart/form-data" class="module left">
			<section>
				<div class="left">
					<fieldset>
						<label class="required"><?=Text::translate("Name")?></label>
						<input name="name" type="text" value="<?=$module["name"]?>" class="required" />
					</fieldset>
				</div>
				<br class="clear" /><br />
				<fieldset class="clear developer_module_group">
					<label><?=Text::translate("Group <small>(if a new group name is chosen, the select box is ignored)</small>")?></label>
					<input name="group_new" type="text" placeholder="<?=Text::translate("New Group", true)?>" />
					<span><?=Text::translate("OR")?></span> 
					<select name="group_existing">
						<option value=""></option>
						<?php foreach ($groups as $group) { ?>
						<option value="<?=$group["id"]?>"<?php if ($group["id"] == $module["group"]) { ?> selected="selected"<?php } ?>><?=$group["name"]?></option>
						<?php } ?>
					</select>
				</fieldset>
				<div class="left">
					<fieldset>
						<label><?=Text::translate("Class Name <small>(only change this if you renamed your class manually)</small>")?></label>
						<input name="class" type="text" value="<?=htmlspecialchars($module["class"])?>" />
					</fieldset>
				</div>
				
				<br class="clear" />
				<fieldset>
			        <label class="required"><?=Text::translate("Icon")?></label>
			        <input type="hidden" name="icon" id="selected_icon" value="<?=$module["icon"]?>" />
			        <ul class="developer_icon_list">
			        	<?php foreach (\BigTreeAdmin::$IconClasses as $class) { ?>
			        	<li>
			        		<a href="#<?=$class?>"<?php if ($class == $module["icon"]) { ?> class="active"<?php } ?>><span class="icon_small icon_small_<?=$class?>"></span></a>
			        	</li>
			        	<?php } ?>
			        </ul>
			    </fieldset>
				
				<fieldset class="left last">
					<input type="checkbox" name="gbp[enabled]" id="gbp_on" <?php if (isset($gbp["enabled"]) && $gbp["enabled"]) { ?>checked="checked" <?php } ?> <?php if ($module["developer_only"]) { ?>disabled="disabled"<?php } ?> />
					<label class="for_checkbox"><?=Text::translate("Enable Advanced Permissions")?></label>
				</fieldset>
				<fieldset class="right last">
					<input type="checkbox" name="developer_only" id="developer_only" <?php if ($module["developer_only"]) { ?>checked="checked" <?php } ?>/>
					<label class="for_checkbox"><?=Text::translate("Limit Access to Developers")?></label>
				</fieldset>
				<br class="clear" />
			</section>
			<?php include Router::getIncludePath("admin/modules/developer/modules/_gbp.php") ?>
			<footer>
				<input type="submit" class="button blue" value="<?=Text::translate("Update", true)?>" />	
			</footer>
		</form>
	</div>

	<section id="actions_tab" style="display: none;">
		<div id="actions_table"></div>
	</section>

	<section id="interfaces_tab" style="display: none;">
		<div id="interfaces_table"></div>
	</div>
</div>

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
			edit: "<?=DEVELOPER_ROOT?>modules/actions/edit/{id}/",
			delete: function(id,state) {
				BigTreeDialog({
					title: "<?=Text::translate("Delete Action", true)?>",
					content: '<p class="confirm"><?=Text::translate("Are you sure you want to delete this module action?")?></p>',
					icon: "delete",
					alternateSaveText: "<?=Text::translate("OK", true)?>",
					callback: function() {
						document.location.href = "<?=DEVELOPER_ROOT?>modules/actions/delete/" + id + "/?module=<?=$id?>";
					}
				});
			}
		},
		data: <?=json_encode($action_data)?>,
		draggable: function(positioning) {
			$.ajax("<?=ADMIN_ROOT?>ajax/developer/order-module-actions/", { type: "POST", data: { positioning: positioning } }); 	
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
			delete: function(id,state) {
				BigTreeDialog({
					title: "<?=Text::translate("Delete Interface", true)?>",
					content: '<p class="confirm"><?=Text::translate("Are you sure you want to delete this module interface?")?></p>',
					icon: "delete",
					alternateSaveText: "<?=Text::translate("OK", true)?>",
					callback: function() {
						document.location.href = "<?=DEVELOPER_ROOT?>modules/interfaces/delete/" + id + "/?module=<?=$id?>";
					}
				});
			}
		},
		data: <?=json_encode($interface_data)?>
	});

	BigTreeFormNavBar.init();
</script>