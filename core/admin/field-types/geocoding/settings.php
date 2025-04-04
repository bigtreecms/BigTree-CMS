<fieldset id="js-source-fieldset" class="last">
	<label>Source Fields <small>(the table columns to use for address generation)</small></label>
	<?php
		if (!empty($settings["fields"]) && is_string($settings["fields"])) {
			$settings["fields"] = explode(",", $settings["fields"]);
		} else if (!empty($settings["fields"])) {
			$settings["fields"] = is_array($settings["fields"]) ? $settings["fields"] : [""];
		}

		if (empty($settings["fields"]) || !is_array($settings["fields"])) {
			$settings["fields"] = array("");
		}

		foreach ($settings["fields"] as $field) {
			$field = trim($field);
	?>
	<div class="contain route_source_field">
		<a href="#" class="icon_small icon_small_add" title="Add Another Field"></a>
		<a href="#" class="icon_small icon_small_delete" title="Remove Field"></a>
		<select name="fields[]">
			<?=BigTree::getFieldSelectOptions($_POST["table"], $field)?>
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
		new_field.find("select").prop("selectedIndex", 0);
		$("#js-source-fieldset").append(new_field);
	}).on("click", ".icon_small_delete", function(ev) {
		ev.preventDefault();

		$(this).parents(".contain").remove();
	});
</script>