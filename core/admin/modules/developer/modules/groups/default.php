<?php
	namespace BigTree;
?>
<div id="module_groups_table"></div>
<script>
	BigTreeTable({
		container: "#module_groups_table",
		title: "Module Groups",
		actions: {
			edit: "<?=DEVELOPER_ROOT?>modules/groups/edit/{id}/",
			delete: function(id) {
				BigTreeDialog({
					title: "Delete Module Group",
					content: '<p class="confirm">Are you sure you want to delete this module group?<br /><br />Modules in this group will become uncategorized.</p>',
					icon: "delete",
					alternateSaveText: "OK",
					callback: function() {
						document.location.href = "<?=DEVELOPER_ROOT?>modules/groups/delete/" + id + "/";
					}
				});
			}
		},
		columns: {
			name: { title: "Group Name", largeFont: true, actionHook: "edit" }
		},
		draggable: function(positioning) {
			$.ajax("<?=ADMIN_ROOT?>ajax/developer/order-module-groups/", { type: "POST", data: positioning });
		},
		data: <?=JSON::encodeColumns($admin->getModuleGroups(),array("id","name"))?>,
		searchable: true
	});
</script>