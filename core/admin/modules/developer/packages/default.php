<div id="packages_table"></div>
<script>
	BigTreeTable({
		container: "#packages_table",
		title: "Packages",
		data: <?=BigTree::jsonExtract($admin->getPackages(),array("id","name"))?>,
		actions: {
			edit: function(id,state) {
				document.location.href = "<?=DEVELOPER_ROOT?>packages/edit/" + id + "/";
			},
			delete: function(id,state) {
				BigTreeDialog({
					title: "Uninstall Package",
					content: '<p class="confirm">Are you sure you want to uninstall this package?<br /><br />Related components, including those that were added to this package will also <strong>completely deleted</strong> (including related files).</p>',
					icon: "delete",
					alternateSaveText: "OK",
					callback: function() {
						document.location.href = "<?=DEVELOPER_ROOT?>packages/delete/" + id + "/";
					}
				});
			}
		},
		columns: {
			name: { title: "Package Name", largeFont: true, actionHook: "edit" }
		}
	});
</script>