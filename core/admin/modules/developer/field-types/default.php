<?php
	namespace BigTree;
?>
<div id="field_types_table"></div>
<script>
	BigTreeTable({
		container: "#field_types_table",
		title: "Field Types",
		data: <?=JSON::encodeColumns($admin->getFieldTypes(),array("id","name"))?>,
		actions: {
			edit: "<?=DEVELOPER_ROOT?>field-types/edit/{id}/",
			delete: function(id,state) {
				BigTreeDialog({
					title: "Delete Field Type",
					content: '<p class="confirm">Are you sure you want to delete this field type?<br /><br />Deleting a field type also deletes its draw, process, and options files.<br /><br />Fields using this type will revert to text fields.</p>',
					icon: "delete",
					alternateSaveText: "OK",
					callback: function() {
						document.location.href = "<?=DEVELOPER_ROOT?>field-types/delete/" + id + "/";
					}
				});
			}
		},
		columns: {
			name: { title: "Field Type Name", largeFont: true, actionHook: "edit" }
		},
		searchable: true,
		sortable: true
	});
</script>