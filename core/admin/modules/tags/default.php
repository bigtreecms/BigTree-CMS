<?php
	namespace BigTree;
	
	$tags = Tag::all("tag ASC", true);
?>
<div id="tags_table"></div>
<script>
	BigTreeTable({
		container: "#tags_table",
		columns: {
			tag: { title: "<?=Text::translate("Name", true)?>", size: 0.8, sort: "asc" },
			usage_count: { title: "<?=Text::translate("Number of Relationships", true)?>", size: 0.2 },
		},
		actions: {
			"merge": "<?=ADMIN_ROOT?>tags/merge/{id}/",
			"delete": function(id) {
				BigTreeDialog({
					title: "<?=Text::translate("Delete Tag", true)?>",
					content: '<p class="confirm"><?=Text::translate("Are you sure you want to delete this tag?", true)?></p>',
					icon: "delete",
					alternateSaveText: "<?=Text::translate("OK", true)?>",
					callback: function() {
						document.location.href = "<?=ADMIN_ROOT?>tags/delete/?id=" + id + "<?php CSRF::drawGETToken(); ?>";
					}
				});
			}
		},
		data: <?=JSON::encodeColumns($tags, ["id", "tag", "usage_count"])?>,
		searchable: true,
		sortable: true,
		perPage: 10
	});
</script>