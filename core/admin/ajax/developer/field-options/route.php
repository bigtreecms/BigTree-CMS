<?php
	namespace BigTree;
	
	/**
	 * @global array $options
	 */

	// Stop notices
	$options["source"] = isset($options["source"]) ? $options["source"] : "";
	$options["not_unique"] = isset($options["not_unique"]) ? $options["not_unique"] : "";
	$options["keep_original"] = isset($options["keep_original"]) ? $options["keep_original"] : "";
?>
<fieldset id="js-source-fieldset">
	<label>Source Fields <small>(the table columns to use for route generation)</small></label>
	<div class="contain route_source_field">
		<a href="#" class="icon_small icon_small_add js-source-add-hook" title="Add Another Source"></a>
		<a href="#" class="icon_small icon_small_delete js-source-delete-hook" title="Remove Source"></a>
		<select name="source[]">
			<?php SQL::drawColumnSelectOptions($_POST["table"], $options["source"]) ?>
		</select>
	</div>
</fieldset>

<fieldset>
	<input id="options_field_unique" type="checkbox" name="not_unique" <?php if ($options["not_unique"]) { ?>checked="checked" <?php } ?>/>
	<label for="options_field_unique" class="for_checkbox"><?=Text::translate("Disregard Uniqueness<small>(if this box is checked duplicate routes can exist)</small>")?></label>
</fieldset>

<fieldset>
	<input id="options_field_original" type="checkbox" name="keep_original" <?php if ($options["keep_original"]) { ?>checked="checked" <?php } ?>/>
	<label for="options_field_original" class="for_checkbox"><?=Text::translate("Keep Original Route<small>(check to keep the first generated route)</small>")?></label>
</fieldset>

<script>
	$("#js-source-fieldset").on("click", ".js-source-add-hook", function(ev) {
		ev.preventDefault();
		
		var new_field = $(".route_source_field").last().clone().attr("id", "");
		new_field.find("select").removeClass("custom_control").prop("selectedIndex", 0);
		new_field.find(".select").remove();
		$("#js-source-fieldset").append(new_field);

		BigTreeCustomControls("#js-source-fieldset");
	}).on("click", ".js-source-delete-hook", function(ev) {
		ev.preventDefault();
		
		$(this).parents(".contain").remove();
	});
</script>