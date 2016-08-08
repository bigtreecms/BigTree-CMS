<?php
	namespace BigTree;
	
	$settings = Setting::all("name ASC", true);
?>
<div id="settings_table"></div>
<script>
	BigTreeTable({
		container: "#settings_table",
		title: "Settings",
		data: <?=JSON::encodeColumns($settings, array("id", "name", "type"))?>,
		actions: {
			"edit": "<?=DEVELOPER_ROOT?>settings/edit/{id}/",
			"delete": function(id) {
				BigTreeDialog({
					title: "Delete Setting",
					content: '<p class="confirm">Are you sure you want to delete this setting?</p>',
					icon: "delete",
					alternateSaveText: "OK",
					callback: function() {
						document.location.href = "<?=DEVELOPER_ROOT?>settings/delete/" + id + "/";
					}
				});
			}
		},
		columns: {
			name: { title: "Name", largeFont: true, actionHook: "edit", size: 0.5 },
			id: { title: "ID", size: 0.3 },
			type: { title: "Type" }
		},
		searchable: true,
		sortable: true
	});
</script>