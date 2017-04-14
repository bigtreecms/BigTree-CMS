<?php
	namespace BigTree;
?>
<div id="callouts_table"></div>
<script>
	BigTreeTable({
		container: "#callouts_table",
		title: "<?=Text::translate("Callouts", true)?>",
		data: <?=JSON::encodeColumns(Callout::all("name ASC",true),array("name","id"))?>,
		actions: {
			"edit": "<?=DEVELOPER_ROOT?>callouts/edit/{id}/",
			"delete": function(id,state) {
				BigTreeDialog({
					title: "<?=Text::translate("Delete Callout", true)?>",
					content: '<p class="confirm"><?=Text::translate("Are you sure you want to delete this callout?", true)?><br /><br /><?=Text::translate("Deleting a callout also removes its files in the /templates/callouts/ directory.", true)?></p>',
					icon: "delete",
					alternateSaveText: "<?=Text::translate("OK")?>",
					callback: function() {
						document.location.href = "<?=DEVELOPER_ROOT?>callouts/delete/?id=" + id + "<?php CSRF::drawGETToken(); ?>";
					}
				});
			}
		},
		columns: {
			name: { title: "<?=Text::translate("Callout Name", true)?>", largeFont: true, actionHook: "edit" }
		},
		searchable: true
	});
</script>