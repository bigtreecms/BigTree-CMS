<?php
	$ungrouped_modules = $admin->getModulesByGroup(0);
	$groups_with_modules = array();
	
	$groups = $admin->getModuleGroups();
	foreach ($groups as $group) {
		$modules = $admin->getModulesByGroup($group["id"]);
		if (count($modules)) {
			$group["modules"] = $modules;
			$groups_with_modules[] = $group;
		}
	}

	foreach ($groups_with_modules as $group) {
?>
<div id="module_group_<?=$group["id"]?>"></div>
<?php
	}
?>
<div id="ungrouped_modules"></div>
<script>
	var table_config = {
		actions: {
			edit: function(id) {
				document.location.href = "<?=DEVELOPER_ROOT?>modules/edit/" + id + "/";
			},
			delete: function(id) {
				BigTreeDialog({
					title: "Delete Module",
					content: '<p class="confirm">Are you sure you want to delete this module?<br /><br />Deleting a module will also delete its class file and related directory in /custom/admin/modules/.</p>',
					icon: "delete",
					alternateSaveText: "OK",
					callback: function() {
						document.location.href = "<?=DEVELOPER_ROOT?>modules/delete/" + id + "/";
					}
				});
			}
		},
		columns: {
			name: { title: "Module Name", largeFont: true, actionHook: "edit" }
		},
		draggable: function(positioning) {
			$.ajax("<?=ADMIN_ROOT?>ajax/developer/order-modules/", { type: "POST", data: positioning });
		}
	};

	<?php
		if (count($ungrouped_modules)) {
	?>
	BigTreeTable($.extend(table_config,{
		title: "Ungrouped Modules",
		container: "#ungrouped_modules",
		data: <?=BigTree::jsonExtract($ungrouped_modules,array("id","name"))?>
	}));
	<?php
		}
		foreach ($groups_with_modules as $group) {
	?>
	BigTreeTable($.extend(table_config,{
		title: "<?=$group["name"]?>",
		container: "#module_group_<?=$group["id"]?>",
		data: <?=BigTree::jsonExtract($group["modules"],array("name","id"))?>
	}));
	<?php
		}
	?>
</script>