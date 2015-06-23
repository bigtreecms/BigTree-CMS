<div id="callout_groups_table"></div>
<script>
	BigTreeTable({
		container: "#callout_groups_table",
		title: "Callout Groups",
		data: <?=BigTree::jsonExtract($admin->getCalloutGroups(),array("name","id"))?>,
		actions: {
			edit: "<?=DEVELOPER_ROOT?>callouts/groups/edit/{id}/",
			delete: function(id,state) {
				BigTreeDialog({
					title: "Delete Callout Group",
					content: '<p class="confirm">Are you sure you want to delete this callout group?</p>',
					icon: "delete",
					alternateSaveText: "OK",
					callback: function() {
						document.location.href = "<?=DEVELOPER_ROOT?>callouts/groups/delete/" + id + "/";
					}
				});
			}
		},
		columns: {
			name: { title: "Group Name", largeFont: true, actionHook: "edit" }
		},
		searchable: true
	});
</script>