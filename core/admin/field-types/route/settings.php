<?php
	namespace BigTree;
	
	/**
	 * @global array $settings
	 */
	
	$settings["source"] = isset($settings["source"]) ? $settings["source"] : "";
	$settings["not_unique"] = isset($settings["not_unique"]) ? $settings["not_unique"] : "";
	$settings["keep_original"] = isset($settings["keep_original"]) ? $settings["keep_original"] : "";
	
	$text_source_add = Text::translate("Add Another Source", true);
	$text_source_remove = Text::translate("Remove Source", true);
?>
<fieldset id="js-source-fieldset">
	<label><?=Text::translate("Source Fields <small>(the table columns to use for route generation)</small>")?></label>
	<?php
		if (is_array($settings["source"]) && count($settings["source"])) {
			foreach ($settings["source"] as $source) {
	?>
	<div class="contain route_source_field">
		<a href="#" class="icon_small icon_small_add" title="<?=$text_source_add?>"></a>
		<a href="#" class="icon_small icon_small_delete" title="<?=$text_source_remove?>"></a>
		<select name="source[]">
			<?php SQL::drawColumnSelectOptions($_POST["table"], $source); ?>
		</select>
	</div>
	<?php
			}
		} else {
	?>
	<div class="contain route_source_field">
		<a href="#" class="icon_small icon_small_add" title="<?=$text_source_add?>"></a>
		<a href="#" class="icon_small icon_small_delete" title="<?=$text_source_remove?>"></a>
		<select name="source[]">
			<?php SQL::drawColumnSelectOptions($_POST["table"], $settings["source"])?>
		</select>
	</div>
	<?php
		}
	?>
</fieldset>

<fieldset>
	<input id="settings_field_not_unique" type="checkbox" name="not_unique" <?php if ($settings["not_unique"]) { ?>checked="checked" <?php } ?>/>
	<label for="settings_field_not_unique" class="for_checkbox"><?=Text::translate("Disregard Uniqueness<small>(if this box is checked duplicate routes can exist)</small>")?></label>
</fieldset>

<fieldset>
	<input id="settings_field_keep_original" type="checkbox" name="keep_original" <?php if ($settings["keep_original"]) { ?>checked="checked" <?php } ?>/>
	<label for="settings_field_keep_original" class="for_checkbox"><?=Text::translate("Keep Original Route<small>(check to keep the first generated route)</small>")?></label>
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