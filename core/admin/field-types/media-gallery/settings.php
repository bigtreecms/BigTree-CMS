<?php
	$cached_types = $admin->getCachedFieldTypes(true);
	$types = $cached_types["callouts"];
	$settings["max"] = !empty($settings["max"]) ? intval($settings["max"]) : "";
	
	if (empty($settings["columns"]) || !is_array($settings["columns"])) {
		$settings["columns"] = [
			["id" => "", "title" => "", "subtitle" => "", "type" => "text", "settings" => ""]
		];
	}
	
	if (empty($settings["directory"])) {
		if (isset($_POST["template"])) {
			$settings["directory"] = "files/pages/";
		} elseif (isset($_POST["callout"])) {
			$settings["directory"] = "files/callouts/";
		} elseif (isset($_POST["setting"])) {
			$settings["directory"] = "files/settings/";
		} else {
			$settings["directory"] = "files/modules/";
		}
	}
?>
<h3>Gallery Options</h3>
<fieldset>
	<label for="field_settings_max">Maximum Entries <small>(defaults to unlimited)</small></label>
	<input id="field_settings_max" type="text" name="max" value="<?=$settings["max"]?>" />
</fieldset>
<fieldset>
	<input id="field_settings_disable_photos" type="checkbox" name="disable_photos" <?php if (!empty($settings["disable_photos"])) { ?>checked="checked" <?php } ?>/>
	<label for="field_settings_disable_photos" class="for_checkbox">Disable Photos</label>
</fieldset>
<fieldset>
	<input id="field_settings_disable_youtube" type="checkbox" name="disable_youtube" <?php if (!empty($settings["disable_youtube"])) { ?>checked="checked" <?php } ?>/>
	<label for="field_settings_disable_youtube" class="for_checkbox">Disable YouTube Videos</label>
</fieldset>
<fieldset>
	<input id="field_settings_disable_vimeo" type="checkbox" name="disable_vimeo" <?php if (!empty($settings["disable_vimeo"])) { ?>checked="checked" <?php } ?>/>
	<label for="field_settings_disable_vimeo" class="for_checkbox">Disable Vimeo Videos</label>
</fieldset>
<fieldset>
	<input id="field_settings_enable_manual" type="checkbox" name="enable_manual" <?php if (!empty($settings["enable_manual"])) { ?>checked="checked" <?php } ?>/>
	<label for="field_settings_enable_manual" class="for_checkbox"><strong>Enable</strong> Manually Uploaded Videos</label>
</fieldset>
<hr />
<h3>Image Options</h3>
<fieldset>
	<label for="field_settings_directory">Upload Directory <small>(relative to SITE_ROOT)</small></label>
	<input id="field_settings_directory" type="text" name="directory" value="<?=htmlspecialchars($settings["directory"])?>" />
</fieldset>
<?php
	$image_options_prefix = "gallery_".uniqid()."_";
	include BigTree::path("admin/field-types/_image-options.php");
?>
<hr />
<div class="matrix_wrapper">
	<span class="icon_small icon_small_add matrix_add_column"></span>
	<h3>Additional Fields</h3>
	<section class="matrix_table">
		<?php
			$x = 0;

			foreach ($settings["columns"] as $column) {
				$x++;

				if (empty($column["settings"]) && !empty($column["options"])) {
					$column["settings"] = $column["options"];
				}
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
				<input type="text" name="columns[][id]" value="<?=htmlspecialchars($column["id"] ?? "")?>" placeholder="ID" />
				<input type="text" name="columns[][title]" value="<?=htmlspecialchars($column["title"] ?? "")?>" placeholder="Title" />
				<input type="text" name="columns[][subtitle]" value="<?=htmlspecialchars($column["subtitle"] ?? "")?>" placeholder="Subtitle" />
			</div>
			<footer>
				<div class="matrix_display_title">
					<input type="checkbox" name="columns[][display_title]"<?php if (!empty($column["display_title"])) { ?> checked="checked"<?php } ?> />
					<label class="for_checkbox">Use as Title</label>
				</div>
				<span class="icon_drag"></span>
				<a href="#" class="icon_delete"></a>
				<a href="#" class="icon_edit" name="<?=$x?>"></a>
				<input type="hidden" name="columns[][settings]" value="<?=htmlspecialchars($column["settings"] ?? "")?>" />
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