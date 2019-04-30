<?php
	namespace BigTree;

	$templates = Template::all("position DESC, id ASC", true);
	$basic_data = $routed_data = [];
	
	foreach ($templates as $template) {
		if ($template["routed"]) {
			$routed_data[] = $template;
		} else {
			$basic_data[] = $template;
		}
	}
?>
<div id="basic_templates"></div>
<div id="routed_templates"></div>
<script>
	var table_config = {
		actions: {
			"edit": "<?=DEVELOPER_ROOT?>templates/edit/{id}/",
			"delete": function(id) {
				BigTreeDialog({
					title: "<?=Text::translate("Delete Template", true)?>",
					content: '<p class="confirm"><?=Text::translate("Are you sure you want to delete this template?<br /><br />Deleting a template also removes its files in the /templates/ directory.")?></p>',
					icon: "delete",
					alternateSaveText: "<?=Text::translate("OK", true)?>",
					callback: function() {
						document.location.href = "<?=DEVELOPER_ROOT?>templates/delete/?id=" + id + "<?php CSRF::drawGETToken(); ?>";
					}
				});
			}
		},
		columns: {
			name: { title: "<?=Text::translate("Template Name", true)?>", largeFont: true, actionHook: "edit" }
		},
		draggable: function(positioning) {
			$.secureAjax("<?=ADMIN_ROOT?>ajax/developer/order-templates/", { type: "POST", data: positioning });
		},
		searchable: true
	};

	// Basic table
	BigTreeTable($.extend(table_config,{
		container: "#basic_templates",
		data: <?=JSON::encodeColumns($basic_data, ["id", "name"])?>,
		title: "<?=Text::translate("Basic Templates", true)?>"
	}));

	// Routed table
	BigTreeTable($.extend(table_config,{
		container: "#routed_templates",
		data: <?=JSON::encodeColumns($routed_data, ["id", "name"])?>,
		title: "<?=Text::translate("Routed Templates", true)?>"
	}));
</script>