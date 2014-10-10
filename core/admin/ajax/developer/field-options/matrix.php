<?
	$cached_types = $admin->getCachedFieldTypes(true);
	$types = $cached_types["callouts"];
	$columns = is_array($data["columns"]) ? $data["columns"] : array(array("id" => "","title" => "","subtitle" => "","type" => "text"));
	$data["max"] = $data["max"] ? intval($data["max"]) : "";
?>
<fieldset>
	<label>Maximum Entries <small>(defaults to unlimited)</small></label>
	<input type="text" name="max" value="<?=$data["max"]?>" />
</fieldset>
<fieldset>
	<label>Style</label>
	<select name="style">
		<option value="list">List (like Many to Many)</option>
		<option value="callout"<? if ($data["style"] == "callout") { ?> selected="selected"<? } ?>>Blocks (like Callouts)</option>
	</select>
</fieldset>
<div class="matrix_wrapper">
	<span class="icon_small icon_small_add matrix_add_column"></span>
	<label>Columns</label>
	<section class="matrix_table">
		<?
			$x = 0;
			foreach ($columns as $column) {
				$x++;
		?>
		<article>
			<div>
				<select name="columns[][type]">
					<optgroup label="Default">
						<? foreach ($types["default"] as $k => $v) { ?>
						<option value="<?=$k?>"<? if ($k == $column["type"]) { ?> selected="selected"<? } ?>><?=$v["name"]?></option>
						<? } ?>
					</optgroup>
					<? if (count($types["custom"])) { ?>
					<optgroup label="Custom">
						<? foreach ($types["custom"] as $k => $v) { ?>
						<option value="<?=$k?>"<? if ($k == $column["type"]) { ?> selected="selected"<? } ?>><?=$v["name"]?></option>
						<? } ?>
					</optgroup>
					<? } ?>
				</select>		
				<input type="text" name="columns[][id]" value="<?=htmlspecialchars($column["id"])?>" placeholder="ID" />
				<input type="text" name="columns[][title]" value="<?=htmlspecialchars($column["title"])?>" placeholder="Title" />
				<input type="text" name="columns[][subtitle]" value="<?=htmlspecialchars($column["subtitle"])?>" placeholder="Subtitle" />
			</div>
			<footer>
				<div class="matrix_display_title">
					<input type="checkbox" name="columns[][display_title]"<? if ($column["display_title"]) { ?> checked="checked"<? } ?> />
					<label class="for_checkbox">Use as Title</label>
				</div>
				<span class="icon_drag"></span>
				<a href="#" class="icon_delete"></a>
				<a href="#" class="icon_edit" name="<?=$x?>"></a>
				<input type="hidden" name="columns[][options]" value="<?=htmlspecialchars($column["options"])?>" />
			</footer>
		</article>
		<?
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

			$.ajax("<?=ADMIN_ROOT?>ajax/developer/load-field-options/", { type: "POST", data: { template: "true", type: type, data: options }, complete: function(response) {
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
			
			var item = $('<article>').html('<div><select name="columns[' + ColumnCount + '][type]"><optgroup label="Default"><? foreach ($types["default"] as $k => $v) { ?><option value="<?=$k?>"><?=$v["name"]?></option><? } ?></optgroup><? if (count($types["custom"])) { ?><optgroup label="Custom"><? foreach ($types["custom"] as $k => $v) { ?><option value="<?=$k?>"><?=$v["name"]?></option><? } ?></optgroup><? } ?></select>' +
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
										   		'<input type="hidden" name="columns[' + ColumnCount + '][options]" value="" />' +
										   	'</footer>');
	
			MatrixTable.sortable({ axis: "y", containment: "parent", handle: ".icon_drag", items: "article", placeholder: "ui-sortable-placeholder", tolerance: "pointer" })
					   .append(item);
			
			BigTreeCustomControls(item);
			return false;
		});
	})();
</script>