<div id="feeds_table"></div>
<script>
	BigTreeTable({
		container: "#feeds_table",
		title: "Field Types",
		data: <?=BigTree::jsonExtract($admin->getFeeds(),array("id","name","route","type"))?>,
		actions: {
			edit: function(id,state) {
				document.location.href = "<?=DEVELOPER_ROOT?>feeds/edit/" + id + "/";
			},
			delete: function(id,state) {
				BigTreeDialog({
					title: "Delete Feed",
					content: '<p class="confirm">Are you sure you want to delete this feed?</p>',
					icon: "delete",
					alternateSaveText: "OK",
					callback: function() {
						document.location.href = "<?=DEVELOPER_ROOT?>feeds/delete/" + id + "/";
					}
				});
			}
		},
		columns: {
			name: { title: "Feed Name", largeFont: true, actionHook: "edit", size: 0.3 },
			url: { title: "URL", size: 0.7, source: "<?=WWW_ROOT?>feeds/{route}/" },
			type: { title: "Type", size: 140 }
		}
	});
</script>