<?php
	namespace BigTree;
	
	$feeds = Feed::all("name ASC", true);
?>
<div id="feeds_table"></div>
<script>
	BigTreeTable({
		container: "#feeds_table",
		title: "<?=Text::translate("Field Types")?>",
		data: <?=JSON::encodeColumns($feeds, ["id", "name", "route", "type"])?>,
		actions: {
			"edit": "<?=DEVELOPER_ROOT?>feeds/edit/{id}/",
			"delete": function(id,state) {
				BigTreeDialog({
					title: "<?=Text::translate("Delete Feed", true)?>",
					content: '<p class="confirm"><?=Text::translate("Are you sure you want to delete this feed?", true)?></p>',
					icon: "delete",
					alternateSaveText: "<?=Text::translate("OK", true)?>",
					callback: function() {
						document.location.href = "<?=DEVELOPER_ROOT?>feeds/delete/?id=" + id + "<?php CSRF::drawGETToken(); ?>";
					}
				});
			}
		},
		columns: {
			name: { title: "<?=Text::translate("Feed Name", true)?>", largeFont: true, actionHook: "edit", size: 0.3 },
			url: { title: "<?=Text::translate("URL", true)?>", size: 0.7, source: "<?=WWW_ROOT?>feeds/{route}/" },
			type: { title: "<?=Text::translate("Type", true)?>", size: 140 }
		},
		searchable: true,
		sortable: true
	});
</script>