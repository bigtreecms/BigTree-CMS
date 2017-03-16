<?
	$settings = $cms->getSetting("bigtree-internal-media-settings");
?>
<div class="table" id="image_presets_table">
	<summary>
		<h2>Image Option Presets</h2>
		<a class="add" href="#"><span></span>Add</a>
	</summary>
	<ul>
		<? foreach (array_filter((array)$settings["presets"]) as $preset) { ?>
		<li>
			<input type="hidden" value="<?=htmlspecialchars(json_encode($preset))?>" />
			<section class="developer_templates_name"><?=$preset["name"]?></section>
			<section class="view_action"><a href="#" class="icon_edit"></a></section>
			<section class="view_action"><a href="#" class="icon_delete"></a></section>
		</li>
		<? } ?>
	</ul>
</div>
<script>
	(function() {
		var Container = $("#image_presets_table");
		var Current;
		var List = Container.find("ul");

		function addPreset(ev) {
			ev.preventDefault();

			// Prevent double clicks
			if (BigTree.Busy) {
				return;
			}

			$.ajax("<?=ADMIN_ROOT?>ajax/developer/media/preset/", { complete: function(e) {
				BigTreeDialog({
					title: "Add Image Preset",
					icon: "add",
					content: e.responseText,
					callback: function(data) {
						// We update the DB first because we need the random ID that's created
						$.secureAjax("<?=ADMIN_ROOT?>ajax/developer/media/save-preset/", { type: "POST", data: data, complete: function(response) {
							data.id = response.responseText;
							var li = new $("<li>");
							li.html('<input type="hidden" />' +
									'<section class="developer_templates_name">' + htmlspecialchars(data.name) + '</section>' +
									'<section class="view_action"><a href="#" class="icon_edit"></a></section>' +
									'<section class="view_action"><a href="#" class="icon_delete"></a></section>');
							li.find("input").val(JSON.stringify(data));
							List.append(li);
						}});
					}
				});
			}});
		}

		function deletePreset(ev) {
			ev.preventDefault();

			Current = $(this).parents("li");
			BigTreeDialog({
				title: "Delete Image Preset",
				content: '<p class="confirm">Are you sure you want to delete this image preset?</p>',
				icon: "delete",
				alternateSaveText: "OK",
				callback: function() {
					var data = JSON.parse(Current.find("input").val());
					// Remove from DOM
					Current.remove();
					// Remove from DB
					$.secureAjax("<?=ADMIN_ROOT?>ajax/developer/media/delete-preset/", { type: "POST", id: data.id });
				}
			});
		}

		function editPreset(ev) {
			ev.preventDefault();

			// Prevent double clicks
			if (BigTree.Busy) {
				return;
			}

			Current = $(this).parents("li");
			$.ajax("<?=ADMIN_ROOT?>ajax/developer/media/preset/", { method: "POST", data: JSON.parse(Current.find("input").val()), complete: function(e) {
				BigTreeDialog({
					title: "Edit Image Preset",
					icon: "edit",
					content: e.responseText,
					callback: function(data) {
						// Update DOM
						Current.find(".developer_templates_name").html(htmlspecialchars(data.name));
						Current.find("input").val(JSON.stringify(data));
						// Update DB
						$.secureAjax("<?=ADMIN_ROOT?>ajax/developer/media/save-preset/", { type: "POST", data: data });
					}
				});
			}});
		}

		Container.find(".add").click(addPreset);
		Container.on("click","a.icon_delete",deletePreset)
				 .on("click",".icon_edit",editPreset);
	})();
</script>