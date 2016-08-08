<?php
	namespace BigTree;
	
	$groups = ModuleGroup::all("position DESC, id ASC", true);
?>
<div id="module_groups_table"></div>
<script>
	BigTreeTable({
		container: "#module_groups_table",
		title: "<?=Text::translate("Module Groups", true)?>",
		actions: {
			"edit": "<?=DEVELOPER_ROOT?>modules/groups/edit/{id}/",
			"delete": function(id) {
				BigTreeDialog({
					title: "<?=Text::translate("Delete Module Group", true)?>",
					content: '<p class="confirm"><?=Text::translate("Are you sure you want to delete this module group?<br /><br />Modules in this group will become uncategorized.")?></p>',
					icon: "delete",
					alternateSaveText: "<?=Text::translate("OK", true)?>",
					callback: function() {
						document.location.href = "<?=DEVELOPER_ROOT?>modules/groups/delete/" + id + "/";
					}
				});
			}
		},
		columns: {
			name: { title: "<?=Text::translate("Group Name", true)?>", largeFont: true, actionHook: "edit" }
		},
		draggable: function(positioning) {
			$.ajax("<?=ADMIN_ROOT?>ajax/developer/order-module-groups/", { type: "POST", data: positioning });
		},
		data: <?=JSON::encodeColumns($groups, array("id", "name"))?>,
		searchable: true
	});
</script>