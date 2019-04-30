<?php
	namespace BigTree;
?>
<div id="callout_groups_table"></div>
<script>
	BigTreeTable({
		container: "#callout_groups_table",
		title: "<?=Text::translate("Callout Groups", true)?>",
		data: <?=JSON::encodeColumns(CalloutGroup::all("name", true), ["name", "id"])?>,
		actions: {
			"edit": "<?=DEVELOPER_ROOT?>callouts/groups/edit/{id}/",
			"delete": function(id,state) {
				BigTreeDialog({
					title: "<?=Text::translate("Delete Callout Group", true)?>",
					content: '<p class="confirm"><?=Text::translate("Are you sure you want to delete this callout group?", true)?></p>',
					icon: "delete",
					alternateSaveText: "<?=Text::translate("OK", true)?>",
					callback: function() {
						document.location.href = "<?=DEVELOPER_ROOT?>callouts/groups/delete/?id=" + id + "<?php CSRF::drawGETToken(); ?>";
					}
				});
			}
		},
		columns: {
			name: { title: "<?=Text::translate("Group Name", true)?>", largeFont: true, actionHook: "edit" }
		},
		searchable: true
	});
</script>