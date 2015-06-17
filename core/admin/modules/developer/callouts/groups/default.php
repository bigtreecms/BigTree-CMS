<div id="callout_groups_table"></div>
<script>
	BigTreeTable({
		container: "#callout_groups_table",
		title: "Callout Groups",
		data: <?=json_encode(array_values($admin->getCalloutGroups()))?>,
		actions: {
			edit: function(id,state) {
				document.location.href = "<?=DEVELOPER_ROOT?>callouts/groups/edit/" + id + "/";
			},
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
		}
	});
</script>