<?php
	$cached_types = $admin->getCachedFieldTypes(true);
	$types = $cached_types["callouts"];
	$columns = is_array($settings["columns"]) ? $settings["columns"] : array(array("id" => "","title" => "","subtitle" => "","type" => "text"));
	$settings["max"] = $settings["max"] ? intval($settings["max"]) : "";
?>
<fieldset>
	<label for="settings_field_max">Maximum Entries <small>(defaults to unlimited)</small></label>
	<input id="settings_field_max" type="text" name="max" value="<?=$settings["max"]?>" />
</fieldset>
<fieldset>
	<label for="settings_field_style">Style</label>
	<select id="settings_field_style" name="style">
		<option value="list">List (like Many to Many)</option>
		<option value="callout"<?php if ($settings["style"] == "callout") { ?> selected="selected"<?php } ?>>Blocks (like Callouts)</option>
	</select>
</fieldset>
<div class="matrix_wrapper">
	<span class="icon_small icon_small_add matrix_add_column"></span>
	<label>Columns</label>
	<section class="matrix_table">
		<?php
			$x = 0;
			foreach ($columns as $column) {
				$x++;
		?>
		<article>
			<div>
				<select name="columns[][type]">
					<optgroup label="Default">
						<?php foreach ($types["default"] as $k => $v) { ?>
						<option value="<?=$k?>"<?php if ($k == $column["type"]) { ?> selected="selected"<?php } ?>><?=$v["name"]?></option>
						<?php } ?>
					</optgroup>
					<?php if (count($types["custom"])) { ?>
					<optgroup label="Custom">
						<?php foreach ($types["custom"] as $k => $v) { ?>
						<option value="<?=$k?>"<?php if ($k == $column["type"]) { ?> selected="selected"<?php } ?>><?=$v["name"]?></option>
						<?php } ?>
					</optgroup>
					<?php } ?>
				</select>		
				<input type="text" name="columns[][id]" value="<?=htmlspecialchars($column["id"])?>" placeholder="ID" />
				<input type="text" name="columns[][title]" value="<?=htmlspecialchars($column["title"])?>" placeholder="Title" />
				<input type="text" name="columns[][subtitle]" value="<?=htmlspecialchars($column["subtitle"])?>" placeholder="Subtitle" />
			</div>
			<footer>
				<div class="matrix_display_title">
					<input type="checkbox" name="columns[][display_title]"<?php if ($column["display_title"]) { ?> checked="checked"<?php } ?> />
					<label class="for_checkbox">Use as Title</label>
				</div>
				<span class="icon_drag"></span>
				<a href="#" class="icon_delete"></a>
				<a href="#" class="icon_edit" name="<?=$x?>"></a>
				<input type="hidden" name="columns[][settings]" value="<?=htmlspecialchars($column["settings"] ?: $column["options"])?>" />
			</footer>
		</article>
		<?php
			}
		?>
	</section>
</div>
<br />
<script>
	(function() {
		var CurrentColumn = false;
		var ColumnCount = <?=$x?>;
		var MatrixTable = $(".bigtree_dialog_window").last().find(".matrix_table");

		// Handle editing the settings on fields
		MatrixTable.on("click",".icon_edit",function(e) {
			e.preventDefault();

			// Prevent double clicks
			if (BigTree.Busy) {
				return;
			}

			CurrentColumn = $(this).parents("article");
			var type = CurrentColumn.find("select").val();
			var settings = CurrentColumn.find("input[type=hidden]").val();

			$.ajax("<?=ADMIN_ROOT?>ajax/developer/load-field-settings/", { type: "POST", data: { template: "true", type: type, data: settings }, complete: function(response) {
				BigTreeDialog({
					title: "Column Settings",
					content: response.responseText,
					icon: "edit",
					callback: function(data) {
						CurrentColumn.find("input[type=hidden]").val(JSON.stringify(data));
					}
				});
			}});
		// Deleting fields
		}).on("click",".icon_delete",function(e) {
			e.preventDefault();
			$(this).parents("article").remove();
		// Sorting fields
		}).sortable({ axis: "y", containment: "parent", handle: ".icon_drag", items: "article", placeholder: "ui-sortable-placeholder", tolerance: "pointer" });

		// Adding fields
		$(".bigtree_dialog_window").last().find(".matrix_add_column").click(function() {
			ColumnCount++;
			
			var item = $('<article>').html('<div><select name="columns[' + ColumnCount + '][type]"><optgroup label="Default"><?php foreach ($types["default"] as $k => $v) { ?><option value="<?=$k?>"><?=$v["name"]?></option><?php } ?></optgroup><?php if (count($types["custom"])) { ?><optgroup label="Custom"><?php foreach ($types["custom"] as $k => $v) { ?><option value="<?=$k?>"><?=$v["name"]?></option><?php } ?></optgroup><?php } ?></select>' +
										   '<input type="text" name="columns[' + ColumnCount + '][id]" value="" placeholder="ID" />' +
										   '<input type="text" name="columns[' + ColumnCount + '][title]" value="" placeholder="Title" />' +
										   '<input type="text" name="columns[' + ColumnCount + '][subtitle]" value="" placeholder="Subtitle" /></div>' +
										   '<footer>' + 
										   		'<div class="matrix_display_title">' +
													'<input type="checkbox" name="columns[][display_title]" />' + 
													'<label class="for_checkbox">Use as Title</label>' + 
												'</div>' +
												'<span class="icon_drag"></span>' + 
										   		'<a href="#" tabindex="-1" class="icon_delete"></a>' +
										   		'<a href="#" tabindex="-1" class="icon_edit" name="' + ColumnCount + '"></a>' +
										   		'<input type="hidden" name="columns[' + ColumnCount + '][settings]" value="" />' +
										   	'</footer>');
	
			MatrixTable.sortable({ axis: "y", containment: "parent", handle: ".icon_drag", items: "article", placeholder: "ui-sortable-placeholder", tolerance: "pointer" })
					   .append(item);
			
			BigTreeCustomControls(item);
			return false;
		});
	})();
</script>