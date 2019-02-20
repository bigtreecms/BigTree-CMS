<?php
	// If we're using a preset, the prefix may be there
	if (!empty($field["settings"]["preset"])) {
		if (!isset($bigtree["media_settings"])) {
			$bigtree["media_settings"] = BigTreeJSONDB::get("config", "media-settings");
		}

		$preset = $bigtree["media_settings"]["presets"][$field["settings"]["preset"]];
		
		if (!empty($preset["preview_prefix"])) {
			$field["settings"]["preview_prefix"] = $preset["preview_prefix"];
		}
		
		if (!empty($preset["min_width"])) {
			$field["settings"]["min_width"] = $preset["min_width"];
		}
		
		if (!empty($preset["min_height"])) {
			$field["settings"]["min_height"] = $preset["min_height"];
		}
	}

	// Get min width/height designations
	$min_width = $field["settings"]["min_width"] ? intval($field["settings"]["min_width"]) : 0;
	$min_height = $field["settings"]["min_height"] ? intval($field["settings"]["min_height"]) : 0;
	$filtered_crops = [];

	// Get preview data and existing crops
	if ($field["value"]) {
		if ($field["settings"]["preview_prefix"]) {
			$preview_image = BigTree::prefixFile($field["value"],$field["settings"]["preview_prefix"]);
		} else {
			$preview_image = $field["value"];
		}

		$image = new BigTreeImage($field["value"], $field["settings"]);
		$image->filterGeneratableCrops();
		$filtered_crops = $image->Settings["crops"];

		foreach ($filtered_crops as $index => $crop) {
			if ($crop["width"] == $image->Width && $crop["height"] == $image->Height) {
				unset($filtered_crops[$index]);
			}
		}
	} else {
		$preview_image = false;
	}

	// Generate the file manager restrictions
	$button_options = htmlspecialchars(json_encode(array(
		"minWidth" => $min_width,
		"minHeight" => $min_height,
		"currentlyKey" => $field["key"],
		"type" => "image"
	)));
?>
<div class="image_field" id="<?=$field["id"]?>">
	<div class="contain">
		<input<?php if ($field["required"]) { ?> class="required"<?php } ?> type="file" tabindex="<?=$field["tabindex"]?>" name="<?=$field["key"]?>" data-min-width="<?=$min_width?>" data-min-height="<?=$min_height?>" accept="image/*" />
		<?php
			if (!defined("BIGTREE_FRONT_END_EDITOR") && empty($bigtree["form"]["embedded"])) {
		?>
		<span class="or">OR</span>
		<a href="#<?=$field["id"]?>_currently" data-options="<?=$button_options?>" class="button resource_browser_button"><span class="icon_images"></span>Browse</a>
		<?php
			}
		?>
	</div>
	<div class="contain">
		<div class="currently<?php if (!empty($field["settings"]["preview_files_square"])) { ?> currently_files_square<?php } ?>" <?php if (!$field["value"]) { ?> style="display: none;"<?php } ?> id="<?=$field["id"]?>_currently">
			<?php
				if (empty($field["settings"]["disable_remove"])) {
			?>
			<a href="#" class="remove_resource"></a>
			<?php
				}
			?>
			<div class="currently_wrapper">
				<?php if ($preview_image) { ?>
				<a href="<?=$field["value"]?>" target="_blank"><img src="<?=$preview_image?>" alt="" /></a>
				<?php } ?>
			</div>
			<label>CURRENT</label>
			<input type="hidden" name="<?=$field["key"]?>" value="<?=$field["value"]?>" />
			<?php
				if (!empty($field["forced_recrop"])) {
					if (strpos($field["key"], "[") === false) {
						$recrop_key = "__".$field["key"]."_recrop__";
					} else {
						$parts = explode("[", $field["key"]);
						$last_part = $parts[count($parts) - 1];
						$parts[count($parts) - 1] = "__".substr($last_part, 0, -1)."_recrop__]";
						$recrop_key = implode("[", $parts);
					}
			?>
			<input type="hidden" name="<?=$recrop_key?>" value="true">
			<?php
				}
			?>
		</div>
		<?php
			if (!empty($field["value"]) && empty($field["forced_recrop"]) && count($filtered_crops)) {
		?>
		<div class="recrop_button_container">
			<button class="button green recrop_button">Choose New Crops</button>
			<?php
				if (!defined("BIGTREE_CALLOUT_RESOURCES") && !empty($image->Settings["crops"]) && count($image->Settings["crops"])) {
			?>
			&nbsp;&nbsp;&nbsp;
			<button class="button show_crops_button">Show Existing Crops</button>
			<?php
				}
			?>
			<p class="recrop_status_text"><strong>Currently:</strong> Using existing crops</p>
		</div>
		<?php
			}
		?>
	</div>
	<?php
		if (!defined("BIGTREE_CALLOUT_RESOURCES")) {
	?>
	<div class="existing_crops_container" style="display: none;"></div>
	<?php
		}
	?>
</div>

<script>
	(function() {
		var Container = $("#<?=$field["id"]?>");
		var ExistingCropsContainer = Container.find(".existing_crops_container");

		Container.find(".recrop_button").on("click", function(ev) {
			ev.preventDefault();

			if ($(this).hasClass("green")) {
				$(this).addClass("red").removeClass("green");
				$(this).html("Use Existing Crops");
				$(this).siblings(".recrop_status_text").html("<strong>Currently:</strong> Generating new crops after saving");

				if ("<?=$field["key"]?>".indexOf("[") > -1) {
					var parts = "<?=$field["key"]?>".split("[");
					var last_part = parts[parts.length - 1];

					parts[parts.length - 1] = "__" + last_part.substr(0, last_part.length - 1) + "_recrop__]";

					$(this).after('<input type="hidden" name="' + parts.join("[") + '" value="true">');
				} else {
					$(this).after('<input type="hidden" name="__<?=$field["key"]?>_recrop__" value="true">');
				}
			} else {
				$(this).removeClass("red").addClass("green");
				$(this).siblings("input").remove();
				$(this).html("Choose New Crops");
				$(this).siblings(".recrop_status_text").html("<strong>Currently:</strong> Using existing crops");
			}
		});

		Container.find(".show_crops_button").on("click", function(ev) {
			ev.preventDefault();

			if ($(this).html() == "Hide Existing Crops") {
				ExistingCropsContainer.hide();
				ExistingCropsContainer.parents("fieldset").removeClass("existing_crops_open");
				$(this).html("Show Existing Crops");
			} else {
				if (ExistingCropsContainer.hasClass("loaded")) {
					cropsContainerLoaded();
				} else {
					$(this).addClass("disabled").after('<span class="button_loader"></span>');
		
					ExistingCropsContainer.load("<?=ADMIN_ROOT?>ajax/files/load-crops-preview/", {
						file: "<?=$field["value"]?>",
						crops: <?=json_encode($filtered_crops)?>
					}, cropsContainerLoaded);
				}
			}
		});

		function cropsContainerLoaded() {
			ExistingCropsContainer.addClass("loaded").show();
			ExistingCropsContainer.parents("fieldset").addClass("existing_crops_open");
			Container.find(".show_crops_button").removeClass("disabled").html("Hide Existing Crops").siblings(".button_loader").remove();
		}
	})();
</script>