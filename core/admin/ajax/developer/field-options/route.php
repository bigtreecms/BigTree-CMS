<?
	// Stop notices
	$data["source"] = isset($data["source"]) ? $data["source"] : "";
	$data["not_unique"] = isset($data["not_unique"]) ? $data["not_unique"] : "";
	$data["keep_original"] = isset($data["keep_original"]) ? $data["keep_original"] : "";
?>
<fieldset id="js-source-fieldset">
	<label>Source Fields <small>(the table columns to use for route generation)</small></label>
	<div class="contain route_source_field">
		<a href="#" class="icon_small icon_small_add" title="Add Another Source"></a>
		<a href="#" class="icon_small icon_small_delete" title="Remove Source"></a>
		<select name="source[]">
			<?=BigTree::getFieldSelectOptions($_POST["table"],$data["source"])?>
		</select>
	</div>
</fieldset>

<fieldset>
	<input type="checkbox" name="not_unique" <? if ($data["not_unique"]) { ?>checked="checked" <? } ?>/>
	<label class="for_checkbox">Disregard Uniqueness<small>(if this box is checked duplicate routes can exist)</small></label>
</fieldset>

<fieldset>
	<input type="checkbox" name="keep_original" <? if ($data["keep_original"]) { ?>checked="checked" <? } ?>/>
	<label class="for_checkbox">Keep Original Route<small>(check to keep the first generated route)</small></label>
</fieldset>

<script>
	$("#js-source-fieldset").on("click", ".icon_small_add", function(ev) {
		ev.preventDefault();
		
		var new_field = $(".route_source_field").last().clone().attr("id", "");
		new_field.find("select").removeClass("custom_control").prop("selectedIndex", 0);
		new_field.find(".select").remove();
		$("#js-source-fieldset").append(new_field);

		BigTreeCustomControls("#js-source-fieldset");
	}).on("click", ".icon_small_delete", function(ev) {
		ev.preventDefault();
		
		$(this).parents(".contain").remove();
	});
</script>