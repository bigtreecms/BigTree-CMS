<?php
	namespace BigTree;
	
	/**
	 * @global array $settings
	 */
	
	
	if (is_string($settings["fields"])) {
		$settings["fields"] = explode(",", $settings["fields"]);
	} else {
		$settings["fields"] = is_array($settings["fields"]) ? $settings["fields"] : [""];
	}
	
	if (!is_array($settings["fields"]) || !count($settings["fields"])) {
		$settings["fields"] = [""];
	}
?>
<fieldset id="js-source-fieldset" class="last">
	<label for="geocoding_source_field_select"><?=Text::translate("Source Fields <small>(the table columns to use for address generation)</small>")?></label>
	<?php
		foreach ($settings["fields"] as $field) {
			$field = trim($field);
	?>
	<div class="contain route_source_field">
		<a href="#" class="icon_small icon_small_add" title="<?=Text::translate("Add Another Field", true)?>"></a>
		<a href="#" class="icon_small icon_small_delete" title="<?=Text::translate("Remove Field", true)?>"></a>
		<select id="geocoding_source_field_select" name="fields[]">
			<?php SQL::drawColumnSelectOptions($_POST["table"], $field); ?>
		</select>
	</div>
	<?php
		}
	?>
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