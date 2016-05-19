<?php
	namespace BigTree;

	$cached_types = $admin->getCachedFieldTypes(true);
	$types = $cached_types["callouts"];
	$columns = is_array($data["columns"]) ? $data["columns"] : array(array("id" => "","title" => "","subtitle" => "","type" => "text"));
	$data["max"] = $data["max"] ? intval($data["max"]) : "";

	// Pre-translate repeated strings
	$id_title = Text::translate("ID", true);
	$title_title = Text::translate("Title", true);
	$subtitle_title = Text::translate("Subtitle", true);
	$use_as_title = Text::translate("Use as Title");
?>
<fieldset>
	<label><?=Text::translate("Maximum Entries <small>(defaults to unlimited)</small>")?></label>
	<input type="text" name="max" value="<?=$data["max"]?>" />
</fieldset>
<fieldset>
	<label><?=Text::translate("Style")?></label>
	<select name="style">
		<option value="list"><?=Text::translate("List (like Many to Many)")?></option>
		<option value="callout"<?php if ($data["style"] == "callout") { ?> selected="selected"<?php } ?>><?=Text::translate("Blocks (like Callouts)")?></option>
	</select>
</fieldset>
<div class="matrix_wrapper">
	<span class="icon_small icon_small_add matrix_add_column"></span>
	<label><?=Text::translate("Columns")?></label>
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
				<input type="text" name="columns[][id]" value="<?=htmlspecialchars($column["id"])?>" placeholder="<?=$id_title?>" />
				<input type="text" name="columns[][title]" value="<?=htmlspecialchars($column["title"])?>" placeholder="<?=$title_title?>" />
				<input type="text" name="columns[][subtitle]" value="<?=htmlspecialchars($column["subtitle"])?>" placeholder="<?=$subtitle_title?>" />
			</div>
			<footer>
				<div class="matrix_display_title">
					<input type="checkbox" name="columns[][display_title]"<?php if ($column["display_title"]) { ?> checked="checked"<?php } ?> />
					<label class="for_checkbox"><?=$use_as_title?></label>
				</div>
				<span class="icon_drag"></span>
				<a href="#" class="icon_delete"></a>
				<a href="#" class="icon_edit" name="<?=$x?>"></a>
				<input type="hidden" name="columns[][options]" value="<?=htmlspecialchars($column["options"])?>" />
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

			BigTreeDialog({
				title: "<?=Text::translate("Column Options", true)?>",
				url: "<?=ADMIN_ROOT?>ajax/developer/load-field-options/",
				post: { template: "true", type: type, data: options },
				icon: "edit",
				callback: function(data) {
					CurrentColumn.find("input[type=hidden]").val(JSON.stringify(data));
				}
			});
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
										   '<input type="text" name="columns[' + ColumnCount + '][id]" value="" placeholder="<?=$id_title?>" />' +
										   '<input type="text" name="columns[' + ColumnCount + '][title]" value="" placeholder="<?=$title_title?>" />' +
										   '<input type="text" name="columns[' + ColumnCount + '][subtitle]" value="" placeholder="<?=$subtitle_title?>" /></div>' +
										   '<footer>' + 
										   		'<div class="matrix_display_title">' +
													'<input type="checkbox" name="columns[][display_title]" />' + 
													'<label class="for_checkbox"><?=$use_as_title?></label>' + 
												'</div>' +
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