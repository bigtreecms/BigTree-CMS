<?php
	namespace BigTree;
	
	/**
	 * @global array $settings
	 */
	
	$cached_types = FieldType::reference(true);
	$types = $cached_types["callouts"];
	$columns = is_array($settings["columns"]) ? $settings["columns"] : [["id" => "","title" => "","subtitle" => "","type" => "text"]];
	$settings["max"] = $settings["max"] ? intval($settings["max"]) : "";

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
	
	// Translate text ahead of time
	$text_id = Text::translate("ID", true);
	$text_title = Text::translate("Title", true);
	$text_subtitle = Text::translate("Subtitle", true);
?>
<h3><?=Text::translate("Gallery Options")?></h3>
<fieldset>
	<label for="field_settings_max"><?=Text::translate("Maximum Entries <small>(defaults to unlimited)</small>")?></label>
	<input id="field_settings_max" type="text" name="max" value="<?=$settings["max"]?>" />
</fieldset>
<fieldset>
	<input id="field_settings_disable_photos" type="checkbox" name="disable_photos" <?php if ($settings["disable_photos"]) { ?>checked="checked" <?php } ?>/>
	<label for="field_settings_disable_photos" class="for_checkbox"><?=Text::translate("Disable Photos")?></label>
</fieldset>
<fieldset>
	<input id="field_settings_disable_youtube" type="checkbox" name="disable_youtube" <?php if ($settings["disable_youtube"]) { ?>checked="checked" <?php } ?>/>
	<label for="field_settings_disable_youtube" class="for_checkbox"><?=Text::translate("Disable YouTube Videos")?></label>
</fieldset>
<fieldset>
	<input id="field_settings_disable_vimeo" type="checkbox" name="disable_vimeo" <?php if ($settings["disable_vimeo"]) { ?>checked="checked" <?php } ?>/>
	<label for="field_settings_disable_vimeo" class="for_checkbox"><?=Text::translate("Disable Vimeo Videos")?></label>
</fieldset>
<fieldset>
	<input id="field_settings_enable_manual" type="checkbox" name="enable_manual" <?php if ($settings["enable_manual"]) { ?>checked="checked" <?php } ?>/>
	<label for="field_settings_enable_manual" class="for_checkbox"><?=Text::translate("<strong>Enable</strong> Manually Uploaded Videos")?></label>
</fieldset>
<hr />
<h3><?=Text::translate("Image Options")?></h3>
<fieldset>
	<label for="field_settings_directory"><?=Text::translate("Upload Directory <small>(relative to SITE_ROOT)</small>")?></label>
	<input id="field_settings_directory" type="text" name="directory" value="<?=Text::htmlEncode($settings["directory"])?>" />
</fieldset>
<?php
	include Router::getIncludePath("admin/field-types/_image-options.php");
?>
<hr />
<div class="matrix_wrapper">
	<span class="icon_small icon_small_add matrix_add_column"></span>
	<h3><?=Text::translate("Additional Fields")?></h3>
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
				<input type="text" name="columns[][id]" value="<?=Text::htmlEncode($column["id"])?>" placeholder="<?=$text_id?>" />
				<input type="text" name="columns[][title]" value="<?=Text::htmlEncode($column["title"])?>" placeholder="<?=$text_title?>" />
				<input type="text" name="columns[][subtitle]" value="<?=Text::htmlEncode($column["subtitle"])?>" placeholder="<?=$text_subtitle?>" />
			</div>
			<footer>
				<span class="icon_drag"></span>
				<a href="#" class="icon_delete"></a>
				<a href="#" class="icon_edit" name="<?=$x?>"></a>
				<input type="hidden" name="columns[][settings]" value="<?=Text::htmlEncode($column["settings"])?>" />
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

		// Handle editing the options on fields
		MatrixTable.on("click",".icon_edit",function(e) {
			e.preventDefault();

			// Prevent double clicks
			if (BigTree.Busy) {
				return;
			}

			CurrentColumn = $(this).parents("article");
			var type = CurrentColumn.find("select").val();
			var options = CurrentColumn.find("input[type=hidden]").val();

			$.ajax("<?=ADMIN_ROOT?>ajax/developer/load-field-settings/", { type: "POST", data: { template: "true", type: type, data: options }, complete: function(response) {
				BigTreeDialog({
					title: "Column Options",
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
										   '<input type="text" name="columns[' + ColumnCount + '][id]" value="" placeholder="<?=$text_id?>" />' +
										   '<input type="text" name="columns[' + ColumnCount + '][title]" value="" placeholder="<?=$text_title?>" />' +
										   '<input type="text" name="columns[' + ColumnCount + '][subtitle]" value="" placeholder="<?=$text_subtitle?>" /></div>' +
										   '<footer>' + 
												'<span class="icon_drag"></span>' + 
										   		'<a href="#" tabindex="-1" class="icon_delete"></a>' +
										   		'<a href="#" tabindex="-1" class="icon_edit" name="' + ColumnCount + '"></a>' +
										   		'<input type="hidden" name="columns[' + ColumnCount + '][options]" value="" />' +
										   	'</footer>');
	
			MatrixTable.sortable({ axis: "y", containment: "parent", handle: ".icon_drag", items: "article", placeholder: "ui-sortable-placeholder", tolerance: "pointer" })
					   .append(item);
			
			BigTreeCustomControls(item);
			return false;
		});
	})();
</script>