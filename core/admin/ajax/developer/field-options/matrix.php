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
<span class="icon_small icon_small_add" id="matrix_add_column"></span>
<label>Columns</label>
<section id="matrix_table">
	<?
		$x = 0;
		foreach ($columns as $column) {
			$x++;
	?>
	<article>
		<div>
			<select name="columns[][type]" id="matrix_type_<?=$x?>">
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
			<a href="#" class="icon_edit" name="<?=$x?>"></a>
			<input type="hidden" name="columns[][options]" value="<?=htmlspecialchars($column["options"])?>" id="matrix_options_<?=$x?>" />
			<a href="#" class="icon_delete"></a>
		</footer>
	</article>
	<?
		}
	?>
</section>
<br class="clear" />
<script>
	(function() {
		var CurrentColumn = false;
		var ColumnCount = <?=$x?>;
		var MatrixTable = $("#matrix_table");

		// Handle editing the options on fields
		MatrixTable.on("click",".icon_edit",function(e) {
			e.preventDefault();

			CurrentColumn = $(this).attr("name");			
			$.ajax("<?=ADMIN_ROOT?>ajax/developer/load-field-options/", { type: "POST", data: { template: "true", type: $("#matrix_type_" + CurrentColumn).val(), data: $("#matrix_options_" + CurrentColumn).val() }, complete: function(response) {
				BigTreeDialog("Column Options",response.responseText,function(data) {
					$("#matrix_options_" + CurrentColumn).val(JSON.stringify(data));
				});
			}});
		// Deleting fields
		}).on("click",".icon_delete",function(e) {
			e.preventDefault();
			$(this).parents("li").remove();
		// Sorting fields
		}).sortable({ axis: "y", containment: "parent", handle: ".icon_drag", items: "article", placeholder: "ui-sortable-placeholder", tolerance: "pointer" });

		// Adding fields
		$("#matrix_add_column").click(function() {
			ColumnCount++;
			
			var item = $('<article>').html('<div><select name="columns[' + ColumnCount + '][type]" id="matrix_type_' + ColumnCount + '"><optgroup label="Default"><? foreach ($types["default"] as $k => $v) { ?><option value="<?=$k?>"><?=$v["name"]?></option><? } ?></optgroup><? if (count($types["custom"])) { ?><optgroup label="Custom"><? foreach ($types["custom"] as $k => $v) { ?><option value="<?=$k?>"><?=$v["name"]?></option><? } ?></optgroup><? } ?></select>' +
										   '<input type="text" name="columns[' + ColumnCount + '][id]" value="" placeholder="ID" />' +
										   '<input type="text" name="columns[' + ColumnCount + '][title]" value="" placeholder="Title" />' +
										   '<input type="text" name="columns[' + ColumnCount + '][subtitle]" value="" placeholder="Subtitle" /></div>' +
										   '<footer>' + 
										   		'<div class="matrix_display_title">' +
													'<input type="checkbox" name="columns[][display_title]" />' + 
													'<label class="for_checkbox">Use as Title</label>' + 
												'</div>' +
												'<span class="icon_drag"></span>' + 
										   		'<a href="#" tabindex="-1" class="icon_edit" name="' + ColumnCount + '"></a>' +
										   		'<input type="hidden" name="columns[' + ColumnCount + '][options]" value="" id="matrix_options_' + ColumnCount + '" />' +
										   		'<a href="#" tabindex="-1" class="icon_delete"></a>' +
										   	'</footer>');
	
			MatrixTable.sortable({ axis: "y", containment: "parent", handle: ".icon_drag", items: "article", placeholder: "ui-sortable-placeholder", tolerance: "pointer" })
					   .append(item);
			
			BigTreeCustomControls(item);
			return false;
		});
	})();
</script>