<?php
	namespace BigTree;
	
	$field_types = FieldType::all("name ASC", true);
?>
<div id="field_types_table"></div>
<script>
	BigTreeTable({
		container: "#field_types_table",
		title: "<?=Text::translate("Field Types", true)?>",
		data: <?=JSON::encodeColumns($field_types, ["id", "name"])?>,
		actions: {
			"edit": "<?=DEVELOPER_ROOT?>field-types/edit/{id}/",
			"delete": function(id,state) {
				BigTreeDialog({
					title: "<?=Text::translate("Delete Field Type", true)?>",
					content: '<p class="confirm"><?=Text::translate('Are you sure you want to delete this field type?<br /><br />Deleting a field type also deletes its draw, process, and settings files.<br /><br />Fields using this type will revert to text fields.')?></p>',
					icon: "delete",
					alternateSaveText: "<?=Text::translate("OK", true)?>",
					callback: function() {
						document.location.href = "<?=DEVELOPER_ROOT?>field-types/delete/?id=" + id + "<?php CSRF::drawGETToken(); ?>";
					}
				});
			}
		},
		columns: {
			name: { title: "<?=Text::translate("Field Type Name", true)?>", largeFont: true, actionHook: "edit" }
		},
		searchable: true,
		sortable: true
	});
</script>