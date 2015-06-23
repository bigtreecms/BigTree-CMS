<?php
	$templates = $admin->getTemplates();
	$basic_data = $routed_data = array();
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
			edit: "<?=DEVELOPER_ROOT?>templates/edit/{id}/",
			delete: function(id,state) {
				BigTreeDialog({
					title: "Delete Template",
					content: '<p class="confirm">Are you sure you want to delete this template?<br /><br />Deleting a template also removes its files in the /templates/ directory.</p>',
					icon: "delete",
					alternateSaveText: "OK",
					callback: function() {
						document.location.href = "<?=DEVELOPER_ROOT?>templates/delete/" + id + "/";
					}
				});
			}
		},
		columns: {
			name: { title: "Template Name", largeFont: true, actionHook: "edit" }
		},
		draggable: function(positioning) {
			$.ajax("<?=ADMIN_ROOT?>ajax/developer/order-templates/", { type: "POST", data: positioning });
		},
		searchable: true
	};

	// Basic table
	BigTreeTable($.extend(table_config,{
		container: "#basic_templates",
		data: <?=BigTree::jsonExtract($basic_data,array("id","name"))?>,
		title: "Basic Templates"
	}));

	// Routed table
	BigTreeTable($.extend(table_config,{
		container: "#routed_templates",
		data: <?=BigTree::jsonExtract($routed_data,array("id","name"))?>,
		title: "Routed Templates"
	}));
</script>