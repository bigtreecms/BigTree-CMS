<div id="callouts_table"></div>
<script>
	BigTreeTable({
		container: "#callouts_table",
		title: "Callouts",
		data: <?=json_encode($admin->getCallouts("name ASC"))?>,
		actions: {
			edit: function(id,state) {
				document.location.href = "<?=DEVELOPER_ROOT?>callouts/edit/" + id + "/";
			},
			delete: function(id,state) {
				BigTreeDialog({
					title: "Delete Callout",
					content: '<p class="confirm">Are you sure you want to delete this callout?<br /><br />Deleting a callout also removes its files in the /templates/callouts/ directory.</p>',
					icon: "delete",
					alternateSaveText: "OK",
					callback: function() {
						document.location.href = "<?=DEVELOPER_ROOT?>callouts/delete/" + id + "/";
					}
				});
			}
		},
		columns: {
			name: { title: "Callout Name", largeFont: true, actionHook: "edit" }
		}
	});
</script>